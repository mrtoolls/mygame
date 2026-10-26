<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoinLedgerEntry;
use App\Models\Admin;
use App\Models\CoinRate;
use App\Models\CoinVendor;
use App\Models\CoinVendorApplication;
use App\Models\CoinVendorAudit;
use App\Models\Country;
use App\Models\Currency;
use App\Services\CoinLedger;
use App\Services\CoinVendorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class CoinVendorController extends Controller
{
    private function ready(): void
    {
        abort_unless(Schema::hasTable('coin_vendor_applications') && Schema::hasTable('coin_vendor_audits'), 503, 'Run the Database Update to create the Coin Vendor system.');
    }

    private function mayReviewIdentity(): void
    {
        $admin = auth('admin')->user();
        abort_unless($admin && (bool) ($admin->can_review_vendor_identity ?? false), 403, 'Your admin account does not have the Vendor Identity Review permission.');
    }

    public function applications(Request $request)
    {
        $this->ready(); $this->mayReviewIdentity();
        $status = $request->input('status', 'submitted');
        $query = CoinVendorApplication::with('user')->latest('submitted_at')->latest('id');
        if (in_array($status, ['submitted', 'in_review', 'approved', 'rejected', 'suspended', 'more_information'], true)) {
            $query->where('status', $status);
        } elseif ($status === 'all') {
            $query->whereNotIn('status', ['draft']);
        }
        if ($request->filled('search')) {
            $term = trim($request->input('search'));
            $query->whereHas('user', fn ($q) => $q->where('username', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
        }
        $applications = $query->paginate(getPaginate())->withQueryString();
        $counts = CoinVendorApplication::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $pageTitle = 'Vendor Applications';
        return view('admin.coin.vendors.applications', compact('pageTitle', 'applications', 'counts', 'status'));
    }

    public function show(int $id)
    {
        $this->ready(); $this->mayReviewIdentity();
        $application = CoinVendorApplication::with(['user.country', 'vendor'])->findOrFail($id);
        if ($application->status === 'submitted') {
            $application->status = 'in_review';
            $application->save();
            CoinVendorAudit::record('review_started', $application->id, $application->vendor?->id, auth('admin')->id());
        }
        $address = $application->identity_data ?? [];
        $addressCountry = !empty($address['address_country_id']) ? Country::find($address['address_country_id']) : null;
        $duplicateDeviceCount = $application->device_fingerprint_hash
            ? CoinVendorApplication::where('device_fingerprint_hash', $application->device_fingerprint_hash)->where('id', '!=', $application->id)->count()
            : 0;
        $pageTitle = 'Vendor Application #' . $application->id;
        return view('admin.coin.vendors.show', compact('pageTitle', 'application', 'address', 'addressCountry', 'duplicateDeviceCount'));
    }

    public function file(int $id, string $kind, CoinVendorService $vendors)
    {
        $this->ready(); $this->mayReviewIdentity();
        abort_unless(in_array($kind, ['id-front', 'id-back', 'selfie-video'], true), 404);
        $application = CoinVendorApplication::findOrFail($id);
        $column = ['id-front' => 'id_front_path', 'id-back' => 'id_back_path', 'selfie-video' => 'selfie_video_path'][$kind];
        $contents = $vendors->readPrivate((string) $application->$column);
        abort_if($contents === null, 404);
        CoinVendorAudit::record('identity_file_viewed', $application->id, null, auth('admin')->id(), $kind);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: 'application/octet-stream';
        if ($kind === 'selfie-video') {
            $mime = str_ends_with((string) $application->$column, '.mp4.enc') ? 'video/mp4' : 'video/webm';
            $range = request()->header('Range');
            if ($range && preg_match('/bytes=(\d*)-(\d*)/', $range, $match)) {
                $size = strlen($contents); $start = $match[1] === '' ? max(0, $size - (int) $match[2]) : (int) $match[1];
                $end = $match[2] === '' ? $size - 1 : min($size - 1, (int) $match[2]);
                if ($start <= $end && $start < $size) return response(substr($contents, $start, $end - $start + 1), 206, ['Content-Type' => $mime, 'Content-Length' => $end - $start + 1, 'Content-Range' => "bytes {$start}-{$end}/{$size}", 'Accept-Ranges' => 'bytes', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
            }
        }
        return response($contents, 200, ['Content-Type' => $mime, 'Content-Length' => strlen($contents), 'Accept-Ranges' => 'bytes', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function approve(Request $request, int $id, CoinVendorService $vendors, CoinLedger $ledger)
    {
        $this->ready(); $this->mayReviewIdentity();
        $data = $request->validate([
            'reviewer_notes' => 'nullable|string|max:3000', 'duplicate_face_note' => 'nullable|string|max:500',
            'checklist' => 'required|array', 'checklist.name_matches' => 'accepted', 'checklist.face_matches' => 'accepted',
            'checklist.code_spoken' => 'accepted', 'checklist.id_valid' => 'accepted', 'checklist.address_plausible' => 'accepted',
            'checklist.duplicate_checked' => 'accepted',
        ]);
        $application = CoinVendorApplication::with('user.currency')->findOrFail($id);
        if (!$application->selfie_video_path || !$application->challenge_code) return back()->withNotify([['error', 'This application is missing its live camera check.']]);
        if ($application->duplicate_flag && !$data['duplicate_face_note']) return back()->withErrors(['duplicate_face_note' => 'Explain the duplicate ID or phone flag before approving.'])->withInput();
        $approvalUser = $application->user;
        if (!$approvalUser || (int) $approvalUser->status !== 1 || (int) $approvalUser->ev !== 1 || (int) $approvalUser->sv !== 1 || (bool) $approvalUser->coin_vendor_flagged
            || !$approvalUser->country?->is_enabled || !$approvalUser->country?->is_allowed
            || !$approvalUser->country?->coin_marketplace_enabled || !$approvalUser->country?->vendors_allowed) {
            return back()->withNotify([['error', 'The applicant no longer meets the verified account, standing, or country requirements.']]);
        }
        $approvalRate = $approvalUser->currency_id ? CoinRate::where('currency_id', $approvalUser->currency_id)->first() : null;
        if (!$approvalRate?->buy_price || !$approvalRate?->sell_price) return back()->withNotify([['error', 'Coin pricing is not active for the applicant currency. Add a current price before approval.']]);

        try {
            DB::transaction(function () use ($application, $data, $vendors, $ledger) {
                $locked = CoinVendorApplication::whereKey($application->id)->lockForUpdate()->first();
                if (!in_array($locked->status, ['submitted', 'in_review', 'more_information'], true)) throw new InvalidArgumentException('This application is no longer awaiting a decision.');
                $user = $locked->user()->with('currency')->firstOrFail();
                $bond = (string) (gs('coin_vendor_security_bond') ?? '0');
                $currencyId = $user->currency_id;
                $rate = $currencyId ? CoinRate::where('currency_id', $currencyId)->first() : null;
                $vendor = CoinVendor::firstOrNew(['user_id' => $user->id]);
                $vendor->application_id = $locked->id;
                $vendor->status = 'active';
                $vendor->is_selling = false;
                $vendor->currency_id = $currencyId;
                $vendor->sell_rate = $rate?->buy_price;
                $vendor->per_trade_limit = gs('coin_vendor_new_per_trade_limit') ?? 1000;
                $vendor->daily_limit = gs('coin_vendor_new_daily_limit') ?? 5000;
                $vendor->bond_amount = $bond;
                $vendor->save();
                if (bccomp($bond, '0', 8) > 0) {
                    $ledger->holdVendorBond($user->id, $bond, 'coin_vendor_bond:' . $vendor->id . ':hold:initial', 'admin', auth('admin')->id());
                    CoinVendorAudit::record('vendor_bond_held', $locked->id, $vendor->id, auth('admin')->id(), null, $bond . ' coins held as vendor bond.');
                    $this->notifyAdmins('Vendor security bond held', $user->username . ': ' . $bond . ' coins held.', route('admin.coin.vendors.index'));
                    $this->sendVendorNotice($user, 'Vendor security bond held', $bond . ' coins are reserved as your vendor security bond.');
                }
                $locked->status = 'approved'; $locked->reviewed_by = auth('admin')->id(); $locked->reviewed_at = now();
                $locked->reviewer_notes = $data['reviewer_notes'] ?? null; $locked->duplicate_face_note = $data['duplicate_face_note'] ?? null;
                $locked->review_checklist = $data['checklist']; $locked->retention_delete_at = null; $locked->save();
                CoinVendorAudit::record('application_approved', $locked->id, $vendor->id, auth('admin')->id(), null, $locked->reviewer_notes);
            });
        } catch (InvalidArgumentException $exception) { return back()->withNotify([['error', $exception->getMessage()]]); }

        $this->sendVendorNotice($application->user, 'Coin Vendor application approved', 'Your application was approved. The Vendor Desk is now available in your account.');
        return to_route('admin.coin.vendors.applications', ['status' => 'approved'])->withNotify([['success', 'Vendor application approved.']]);
    }

    public function reject(Request $request, int $id)
    {
        $this->ready(); $this->mayReviewIdentity();
        $data = $request->validate(['reason' => 'required|string|max:1500', 'reviewer_notes' => 'nullable|string|max:3000']);
        $application = CoinVendorApplication::with('user')->findOrFail($id);
        abort_unless(in_array($application->status, ['submitted', 'in_review', 'more_information'], true), 409);
        $application->status = 'rejected'; $application->decision_reason = $data['reason']; $application->reviewer_notes = $data['reviewer_notes'] ?? null;
        $application->reviewed_by = auth('admin')->id(); $application->reviewed_at = now(); $application->rejected_at = now();
        $application->retention_delete_at = now()->addDays((int) (gs('coin_vendor_rejected_retention_days') ?? 60)); $application->save();
        CoinVendorAudit::record('application_rejected', $application->id, null, auth('admin')->id(), null, $data['reason']);
        $this->sendVendorNotice($application->user, 'Coin Vendor application declined', 'Your application was not approved. Reason: ' . $data['reason']);
        return to_route('admin.coin.vendors.applications', ['status' => 'rejected'])->withNotify([['success', 'Application rejected and applicant notified.']]);
    }

    public function requestInformation(Request $request, int $id)
    {
        $this->ready(); $this->mayReviewIdentity();
        $data = $request->validate(['requested_information' => 'required|string|max:2000']);
        $application = CoinVendorApplication::with('user')->findOrFail($id);
        abort_unless(in_array($application->status, ['submitted', 'in_review', 'more_information'], true), 409);
        $application->status = 'more_information'; $application->requested_information = $data['requested_information'];
        $application->reviewed_by = auth('admin')->id(); $application->reviewed_at = now(); $application->save();
        CoinVendorAudit::record('more_information_requested', $application->id, null, auth('admin')->id(), null, $data['requested_information']);
        $this->sendVendorNotice($application->user, 'More information is needed', $data['requested_information']);
        return back()->withNotify([['success', 'The applicant has been notified.']]);
    }

    public function vendors(Request $request)
    {
        $this->ready(); $this->mayReviewIdentity();
        $query = CoinVendor::with(['user.country', 'currency', 'application'])->latest();
        if ($request->filled('status')) $query->where('status', $request->input('status'));
        if ($request->filled('search')) { $term = trim($request->input('search')); $query->whereHas('user', fn ($q) => $q->where('username', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")); }
        $vendors = $query->paginate(getPaginate())->withQueryString();
        $vendorStats = CoinVendor::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $pageTitle = 'Coin Vendors';
        return view('admin.coin.vendors.vendors', compact('pageTitle', 'vendors', 'vendorStats'));
    }

    public function vendorAction(Request $request, int $id, string $action)
    {
        $this->ready(); $this->mayReviewIdentity();
        abort_unless(in_array($action, ['suspend', 'reinstate', 'revoke', 'limits', 'links', 'release-bond'], true), 404);
        $vendor = CoinVendor::with('user')->findOrFail($id);
        if ($action === 'release-bond') {
            $data = $request->validate(['reason' => 'required|string|max:1000']);
            abort_if($vendor->status === 'active', 409, 'Suspend, revoke, or accept resignation before releasing a vendor bond.');
            abort_if(!$vendor->bond_release_after_at || $vendor->bond_release_after_at->isFuture(), 409, 'The vendor bond cooldown has not ended.');
            $openStatuses = ['pending', 'paid_claimed', 'disputed'];
            $openTrades = \App\Models\CoinTrade::whereIn('status', $openStatuses)
                ->where(fn ($q) => $q->where(fn ($side) => $side->where('coin_seller_type', 'user')->where('coin_seller_id', $vendor->user_id))
                    ->orWhere(fn ($side) => $side->where('coin_buyer_type', 'user')->where('coin_buyer_id', $vendor->user_id)))->exists();
            abort_if($openTrades, 409, 'Resolve all open or disputed trades before releasing the bond.');
            $unresolvedPenalty = Schema::hasTable('coin_penalties') && Schema::hasTable('coin_trades')
                ? DB::table('coin_penalties')->join('coin_trades', 'coin_trades.id', '=', 'coin_penalties.trade_id')
                    ->where('coin_penalties.offender_type', 'user')->where('coin_penalties.offender_id', $vendor->user_id)
                    ->whereIn('coin_trades.status', $openStatuses)->exists()
                : false;
            abort_if($unresolvedPenalty, 409, 'Resolve penalties attached to open or disputed trades before releasing the bond.');
            $amount = (string) $vendor->bond_amount;
            if (bccomp($amount, '0', 8) > 0) {
                $ledger = app(CoinLedger::class);
                $ledger->releaseVendorBond((int) $vendor->user_id, $amount, 'coin_vendor_bond:' . $vendor->id . ':release:' . $vendor->bond_release_after_at->timestamp, $data['reason'], 'admin', auth('admin')->id());
                $vendor->bond_amount = '0';
                $vendor->save();
                CoinVendorAudit::record('vendor_bond_released', $vendor->application_id, $vendor->id, auth('admin')->id(), null, $amount . ' coins released. Reason: ' . $data['reason']);
                $this->notifyAdmins('Vendor security bond released', $vendor->user->username . ': ' . $amount . ' coins released. Reason: ' . $data['reason'], route('admin.coin.vendors.index'));
                $this->sendVendorNotice($vendor->user, 'Vendor security bond released', $amount . ' coins were released to your available wallet. Reason: ' . $data['reason']);
            }
            return back()->withNotify([['success', 'Vendor bond release completed.']]);
        }
        if ($action === 'links') {
            $data = $request->validate(['reason' => 'required|string|max:1000', 'linked_user_ids' => 'nullable|string|max:2000']);
            $ids = array_values(array_unique(array_filter(array_map('intval', preg_split('/[\s,]+/', trim($data['linked_user_ids'] ?? ''))))));
            $vendor->linked_user_ids = array_values(array_filter($ids, fn ($linkedId) => $linkedId > 0 && $linkedId !== (int) $vendor->user_id));
            $vendor->status_reason = $data['reason'];
            $vendor->save();
            CoinVendorAudit::record('vendor_linked_accounts_updated', $vendor->application_id, $vendor->id, auth('admin')->id(), null, $data['reason']);
            return back()->withNotify([['success', 'Linked account list updated. Matching users cannot trade with this vendor.']]);
        }
        if ($action === 'limits') {
            $data = $request->validate(['reason' => 'required|string|max:1000', 'per_trade_limit' => 'required|numeric|gt:0', 'daily_limit' => 'required|numeric|gt:0']);
            $vendor->per_trade_limit = $data['per_trade_limit']; $vendor->daily_limit = $data['daily_limit'];
            $vendor->status_reason = $data['reason']; $vendor->save();
        } else {
            $data = $request->validate(['reason' => 'required|string|max:1000']);
            if ($action === 'suspend') $vendor->status = 'suspended';
            if ($action === 'reinstate') {
                $open = \App\Models\CoinTrade::whereIn('status', ['pending','paid_claimed','disputed'])->where(fn ($q) => $q->where(fn ($side) => $side->where('coin_seller_type','user')->where('coin_seller_id',$vendor->user_id))->orWhere(fn ($side) => $side->where('coin_buyer_type','user')->where('coin_buyer_id',$vendor->user_id)))->exists();
                if ($open) return back()->withNotify([['error', 'Resolve all open trades before reinstating the vendor.']]);
                $requiredBond = (string) (gs('coin_vendor_security_bond') ?? '0');
                if (bccomp($requiredBond, (string) $vendor->bond_amount, 8) > 0) {
                    try { app(CoinLedger::class)->holdVendorBond((int) $vendor->user_id, bcsub($requiredBond, (string) $vendor->bond_amount, 8), 'coin_vendor_bond:' . $vendor->id . ':hold:reinstate:' . now()->timestamp, 'admin', auth('admin')->id()); }
                    catch (InvalidArgumentException $exception) { return back()->withNotify([['error', $exception->getMessage()]]); }
                    CoinVendorAudit::record('vendor_bond_held', $vendor->application_id, $vendor->id, auth('admin')->id(), null, 'Additional bond held during reinstatement.');
                    $this->notifyAdmins('Vendor security bond held', $vendor->user->username . ': additional bond held during reinstatement.', route('admin.coin.vendors.index'));
                    $this->sendVendorNotice($vendor->user, 'Vendor security bond held', 'Additional coins were reserved as your vendor security bond during reinstatement.');
                    $vendor->bond_amount = $requiredBond;
                }
                $vendor->bond_release_after_at = null;
                $vendor->status = 'active';
            }
            if ($action === 'revoke') {
                $vendor->status = 'revoked'; $vendor->is_selling = false;
                $vendor->bond_release_after_at = now()->addDays((int) (gs('coin_vendor_bond_cooldown_days') ?? 14));
                if ($vendor->application) {
                    $vendor->application->status = 'revoked';
                    $vendor->application->retention_delete_at = now()->addDays((int) (gs('coin_vendor_revoked_retention_days') ?? gs('coin_vendor_legal_hold_days') ?? 365));
                    $vendor->application->save();
                }
            }
            $vendor->status_reason = $data['reason']; $vendor->save();
            if ($vendor->application && $action === 'suspend') { $vendor->application->status = 'suspended'; $vendor->application->save(); }
            if ($vendor->application && $action === 'reinstate') { $vendor->application->status = 'approved'; $vendor->application->save(); }
        }
        CoinVendorAudit::record('vendor_' . $action, $vendor->application_id, $vendor->id, auth('admin')->id(), null, $vendor->status_reason);
        $this->sendVendorNotice($vendor->user, 'Coin Vendor account updated', 'Your vendor account is ' . str_replace('_', ' ', $vendor->status) . '. Reason: ' . $vendor->status_reason);
        return back()->withNotify([['success', 'Vendor profile updated and user notified.']]);
    }

    public function settings()
    {
        $this->ready(); $this->mayReviewIdentity();
        $pageTitle = 'Vendor Settings'; $countries = Country::with('currency')->orderBy('name')->get(); $currencies = Currency::where('enabled', true)->orderBy('code')->get();
        $reviewers = Admin::orderBy('username')->get(['id', 'username', 'email', 'can_review_vendor_identity']);
        return view('admin.coin.vendors.settings', compact('pageTitle', 'countries', 'currencies', 'reviewers'));
    }

    public function reviewerPermission(Request $request, int $admin)
    {
        $this->ready(); $this->mayReviewIdentity();
        $data = $request->validate(['can_review' => 'required|boolean', 'reason' => 'required|string|max:500']);
        $reviewer = Admin::findOrFail($admin);
        abort_if((int) $reviewer->id === (int) auth('admin')->id(), 422, 'Ask another identity reviewer to change your own permission.');
        $reviewer->can_review_vendor_identity = (bool) $data['can_review'];
        $reviewer->save();
        CoinVendorAudit::record('reviewer_permission_changed', null, null, auth('admin')->id(), 'admin:' . $reviewer->id, json_encode(['allowed' => (bool) $data['can_review'], 'reason' => $data['reason']]));
        return back()->withNotify([['success', 'Vendor identity review permission updated.']]);
    }

    public function saveSettings(Request $request)
    {
        $this->ready(); $this->mayReviewIdentity();
        $data = $request->validate([
            'coin_vendor_enabled' => 'nullable|boolean', 'min_account_age_days' => 'required|integer|min:0|max:3650',
            'rejection_cooldown_days' => 'required|integer|min:0|max:3650', 'rejected_retention_days' => 'required|integer|min:1|max:3650',
            'legal_hold_days' => 'required|integer|min:1|max:3650', 'bond_cooldown_days' => 'required|integer|min:1|max:3650', 'security_bond' => 'required|numeric|min:0',
            'new_per_trade_limit' => 'required|numeric|gt:0', 'new_daily_limit' => 'required|numeric|gt:0',
            'max_open_trades' => 'required|integer|min:1|max:100', 'rate_band_below_percent' => 'required|numeric|min:0|max:50',
            'rate_band_above_percent' => 'required|numeric|min:0|max:50', 'fee_percent' => 'nullable|numeric|min:0|max:100',
            'auto_suspend_penalties' => 'required|integer|min:1|max:100', 'id_types_text' => 'required|string|max:500',
            'vendors_allowed' => 'nullable|array', 'vendors_allowed.*' => 'nullable|boolean',
        ]);
        $general = gs();
        foreach ([
            'coin_vendor_enabled' => $request->boolean('coin_vendor_enabled'), 'coin_vendor_min_account_age_days' => $data['min_account_age_days'],
            'coin_vendor_rejection_cooldown_days' => $data['rejection_cooldown_days'], 'coin_vendor_rejected_retention_days' => $data['rejected_retention_days'],
            'coin_vendor_legal_hold_days' => $data['legal_hold_days'], 'coin_vendor_revoked_retention_days' => $data['legal_hold_days'], 'coin_vendor_security_bond' => $data['security_bond'],
            'coin_vendor_bond_cooldown_days' => $data['bond_cooldown_days'],
            'coin_vendor_new_per_trade_limit' => $data['new_per_trade_limit'], 'coin_vendor_new_daily_limit' => $data['new_daily_limit'],
            'coin_vendor_max_open_trades' => $data['max_open_trades'], 'coin_vendor_rate_band_below_percent' => $data['rate_band_below_percent'],
            'coin_vendor_rate_band_above_percent' => $data['rate_band_above_percent'], 'coin_vendor_fee_percent' => $data['fee_percent'] ?? null,
            'coin_vendor_auto_suspend_penalties' => $data['auto_suspend_penalties'], 'coin_vendor_id_types' => json_encode(array_values(array_unique(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $data['id_types_text'])))))),
        ] as $field => $value) $general->$field = $value;
        $general->save();
        foreach (Country::all() as $country) {
                $country->vendors_allowed = in_array((string) $country->id, array_map('strval', array_keys($request->input('vendors_allowed', []))), true);
            $country->save();
        }
        CoinVendorAudit::record('vendor_settings_updated', null, null, auth('admin')->id(), null, 'Vendor eligibility and limits updated.');
        return back()->withNotify([['success', 'Vendor settings saved.']]);
    }

    private function sendVendorNotice($user, string $subject, string $message): void
    {
        try { notify($user, 'DEFAULT', ['subject' => $subject, 'message' => $message]); } catch (\Throwable $exception) { report($exception); }
    }

    private function notifyAdmins(string $title, string $message, string $url): void
    {
        if (!Schema::hasTable('admin_notifications')) return;
        try {
            $notice = new \App\Models\AdminNotification();
            $notice->user_id = 0;
            $notice->title = $title . ' — ' . $message;
            $notice->click_url = $url;
            $notice->save();
        } catch (\Throwable $exception) { report($exception); }
    }
}
