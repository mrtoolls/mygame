<?php

namespace App\Http\Controllers\User;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\CoinLedgerEntry;
use App\Models\CoinListing;
use App\Models\CoinTrade;
use App\Models\CoinVendor;
use App\Models\CoinVendorApplication;
use App\Models\Country;
use App\Models\Currency;
use App\Models\User;
use App\Services\CoinLedger;
use App\Services\CoinMarketplace;
use App\Services\CoinVendorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use InvalidArgumentException;

class CoinVendorController extends Controller
{
    public function apply(CoinVendorService $vendors)
    {
        abort_unless((bool) gs('coin_mode') && (bool) gs('coin_vendor_enabled'), 404);
        abort_unless(Schema::hasTable('coin_vendor_applications'), 503, 'Run the Database Update before opening vendor applications.');
        $pageTitle = 'Become a Coin Vendor';
        $eligibility = $vendors->eligibility(auth()->user());
        $countries = Country::where('is_enabled', true)->orderBy('name')->get();
        $idTypes = $vendors->idTypes();
        return view('Template::user.coin.vendor.apply', compact('pageTitle', 'eligibility', 'countries', 'idTypes'));
    }

    public function saveDraft(Request $request, CoinVendorService $vendors)
    {
        $user = auth()->user();
        $eligibility = $vendors->eligibility($user);
        $canContinue = in_array($eligibility['application']?->status, ['draft', 'more_information'], true);
        $requirementsOkay = collect($eligibility['checks'])->reject(fn ($check) => $check['label'] === 'Application status')->every(fn ($check) => $check['ok']);
        abort_unless($eligibility['eligible'] || ($canContinue && $requirementsOkay), 403, 'You do not currently meet the vendor application requirements.');
        $data = $request->validate([
            'legal_name' => 'required|string|min:3|max:160', 'street' => 'required|string|max:180',
            'city' => 'required|string|max:100', 'region' => 'required|string|max:100',
            'address_country_id' => 'required|integer|exists:countries,id', 'postal_code' => 'required|string|max:24',
            'phone' => 'required|string|max:40', 'id_type' => 'required|string|max:40', 'id_number' => 'required|string|max:100',
            'id_front' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:8192', 'id_back' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:8192',
            'consent_identity' => 'required|accepted', 'consent_p2p_risk' => 'required|accepted',
        ]);
        if (!in_array($data['id_type'], $vendors->idTypes(), true)) return back()->withErrors(['id_type' => 'Choose an available identity document type.'])->withInput();
        $registeredPhone = (string) ($user->mobileNumber ?? '');
        if ((int) $user->sv !== Status::VERIFIED || preg_replace('/\D+/', '', $data['phone']) !== preg_replace('/\D+/', '', $registeredPhone)) {
            return back()->withErrors(['phone' => 'Use the verified phone number on your account.'])->withInput();
        }

        $application = $eligibility['application'] && in_array($eligibility['application']->status, ['draft', 'more_information'], true)
            ? $eligibility['application'] : new CoinVendorApplication(['user_id' => $user->id, 'status' => 'draft']);
        $normalizedId = mb_strtolower(preg_replace('/\s+/', '', trim($data['id_number'])));
        $idHash = hash_hmac('sha256', $normalizedId, (string) config('app.key'));
        $phoneHash = hash_hmac('sha256', preg_replace('/\D+/', '', $data['phone']), (string) config('app.key'));
        // A privacy-preserving, coarse device hint based on server-observed browser and network
        // data. It is for manual review only and is not represented as a hardware fingerprint.
        $deviceFingerprintHash = hash_hmac('sha256', mb_strtolower(trim((string) $request->userAgent())) . '|' . (string) $request->ip(), (string) config('app.key'));
        $duplicate = CoinVendorApplication::where('user_id', '!=', $user->id)->where(function ($query) use ($idHash, $phoneHash, $deviceFingerprintHash) {
            $query->where('id_number_hash', $idHash)->orWhere('phone', $phoneHash)->orWhere('device_fingerprint_hash', $deviceFingerprintHash);
        })->exists();

        $files = [];
        foreach (['id_front' => 'id_front_path', 'id_back' => 'id_back_path'] as $input => $field) {
            if (!$request->hasFile($input)) continue;
            $file = $request->file($input);
            if (!in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png', 'webp'], true)
                || preg_match('/\.(?:jpg|jpeg|png|webp)\.(?:php|phtml|html?|svg|js)$/i', $file->getClientOriginalName())) {
                return back()->withErrors([$input => 'Use a single-extension JPG, PNG, or WebP image file.'])->withInput();
            }
            $image = @imagecreatefromstring(file_get_contents($file->getRealPath()));
            if (!$image) return back()->withErrors([$input => 'That image could not be decoded. Please take another photo.'])->withInput();
            ob_start();
            imagejpeg($image, null, 88);
            $jpeg = ob_get_clean();
            imagedestroy($image);
            if (!is_string($jpeg) || strlen($jpeg) > 8 * 1024 * 1024) return back()->withErrors([$input => 'The re-encoded photo is too large. Please use a smaller image.'])->withInput();
            $files[$field] = $vendors->storePrivate($jpeg, 'jpg');
        }
        if (!isset($files['id_front_path']) && !$application->id_front_path) return back()->withErrors(['id_front' => 'Upload the front of your ID document.'])->withInput();
        if ($data['id_type'] !== 'International Passport' && !isset($files['id_back_path']) && !$application->id_back_path) return back()->withErrors(['id_back' => 'Upload the back of your ID card.'])->withInput();

        foreach ($files as $field => $newPath) {
            $vendors->deletePrivate($application->$field);
            $application->$field = $newPath;
        }
        $application->identity_data = [
            'legal_name' => trim($data['legal_name']), 'street' => trim($data['street']), 'city' => trim($data['city']),
            'region' => trim($data['region']), 'address_country_id' => (int) $data['address_country_id'],
            'postal_code' => trim($data['postal_code']), 'phone' => trim($data['phone']),
        ];
        $application->phone = $phoneHash;
        $application->device_fingerprint_hash = $deviceFingerprintHash;
        $application->id_type = $data['id_type'];
        $application->id_number_hash = $idHash;
        $application->id_last4 = substr(preg_replace('/\s+/', '', $data['id_number']), -4);
        $application->consent_identity = true;
        $application->consent_p2p_risk = true;
        $application->duplicate_flag = $duplicate;
        $application->save();

        return back()->withNotify([['success', 'Your application draft is saved securely. Continue with the live camera check when you are ready.']]);
    }

    public function challenge(Request $request, CoinVendorService $vendors)
    {
        $application = CoinVendorApplication::where('user_id', auth()->id())->latest('id')->firstOrFail();
        abort_unless($application->status === 'draft' && $application->id_front_path, 403);
        $token = Str::random(48);
        $tokenId = (string) Str::uuid();
        $code = (string) random_int(1000, 9999);
        $application->challenge_code = $code;
        $application->challenge_token_id = $tokenId;
        $application->challenge_token_hash = hash_hmac('sha256', $token, (string) config('app.key'));
        $application->challenge_expires_at = now()->addMinutes(5);
        $application->challenge_used_at = null;
        $application->challenge_session_hash = hash_hmac('sha256', (string) $request->session()->getId(), (string) config('app.key'));
        $application->challenge_user_agent = Str::limit((string) $request->userAgent(), 500, '');
        $application->challenge_ip = $request->ip();
        $application->save();
        return response()->json(['token' => $token, 'challenge_id' => $tokenId, 'code' => $code, 'expires_in' => 300]);
    }

    public function uploadSelfie(Request $request, CoinVendorService $vendors)
    {
        if (!$request->hasFile('video')) {
            $postLimit = ini_get('post_max_size') ?: 'unknown';
            $uploadLimit = ini_get('upload_max_filesize') ?: 'unknown';
            $file = $request->file('video');
            $uploadError = $file && method_exists($file, 'getError') ? $file->getError() : UPLOAD_ERR_NO_FILE;
            $reason = match ($uploadError) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "The server's per-file upload limit is {$uploadLimit}.",
                UPLOAD_ERR_PARTIAL => 'The connection interrupted the upload before it finished.',
                default => "The server did not receive the video. Its request-size limit is {$postLimit}; record a shorter clip and try again.",
            };
            return response()->json(['message' => $reason . ' Keep the recording small and use a stable connection.'], 422);
        }
        $data = $request->validate(['video' => 'required|file|max:6144', 'liveness_token' => 'required|string|min:32|max:100', 'challenge_id' => 'required|uuid', 'duration_ms' => 'required|integer|between:8000,15000']);
        $application = CoinVendorApplication::where('user_id', auth()->id())->latest('id')->firstOrFail();
        $expected = hash_hmac('sha256', $data['liveness_token'], (string) config('app.key'));
        abort_unless($application->status === 'draft'
            && $application->challenge_token_hash
            && hash_equals((string) $application->challenge_token_id, $data['challenge_id'])
            && hash_equals($application->challenge_token_hash, $expected)
            && !$application->challenge_used_at
            && $application->challenge_expires_at?->isFuture()
            && hash_equals((string) $application->challenge_session_hash, hash_hmac('sha256', (string) $request->session()->getId(), (string) config('app.key'))), 403, 'This camera challenge expired or has already been used. Request a new code.');

        $video = $request->file('video');
        $header = file_get_contents($video->getRealPath(), false, null, 0, 64);
        $isWebm = is_string($header) && str_starts_with($header, "\x1A\x45\xDF\xA3");
        $isMp4 = is_string($header) && substr($header, 4, 4) === 'ftyp';
        // Some iOS/Safari versions label MediaRecorder MP4 as QuickTime or
        // octet-stream. Trust the container signature, never the filename.
        $mime = $isMp4 ? 'video/mp4' : ($isWebm ? 'video/webm' : $video->getMimeType());
        if ((!$isWebm && !$isMp4) || !in_array($mime, ['video/webm', 'video/mp4'], true) || $video->getSize() < 100_000 || $video->getSize() > 6 * 1024 * 1024) {
            return response()->json(['message' => 'The camera recording format or size is not supported. Record 8–15 seconds in MP4 or WebM and keep it under 6 MB.'], 422);
        }
        $consumed = CoinVendorApplication::whereKey($application->id)
            ->where('challenge_token_id', $data['challenge_id'])
            ->where('challenge_token_hash', $expected)
            ->where('challenge_session_hash', hash_hmac('sha256', (string) $request->session()->getId(), (string) config('app.key')))
            ->whereNull('challenge_used_at')->where('challenge_expires_at', '>', now())
            ->update(['challenge_used_at' => now()]);
        abort_unless($consumed === 1, 403, 'This camera challenge expired or was already used. Request a new code.');
        $contents = file_get_contents($video->getRealPath());
        $durationMs = (int) $data['duration_ms'];
        $ffprobe = (new ExecutableFinder())->find('ffprobe');
        if ($ffprobe) {
            $probe = new Process([$ffprobe, '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1', $video->getRealPath()]);
            $probe->setTimeout(8);
            $probe->run();
            $probedSeconds = $probe->isSuccessful() ? (float) trim($probe->getOutput()) : 0;
            if ($probedSeconds < 8 || $probedSeconds > 15) return response()->json(['message' => 'The recorded video duration could not be verified as 8–15 seconds. Please record again.'], 422);
            $durationMs = (int) round($probedSeconds * 1000);
        }
        $extension = str_contains($mime, 'mp4') ? 'mp4' : 'webm';
        $path = $vendors->storePrivate($contents, $extension);
        $vendors->deletePrivate($application->selfie_video_path);
        $application->selfie_video_path = $path;
        $application->video_duration_ms = $durationMs;
        $application->video_recorded_at = now();
        $application->challenge_used_at = now();
        $application->save();

        return response()->json(['ok' => true, 'message' => 'Camera check recorded. The reviewer will check your face and spoken code.']);
    }

    public function submit(Request $request, CoinVendorService $vendors)
    {
        $user = auth()->user();
        $eligibility = $vendors->eligibility($user);
        $application = CoinVendorApplication::where('user_id', $user->id)->latest('id')->firstOrFail();
        $requirementsOkay = collect($eligibility['checks'])->reject(fn ($check) => $check['label'] === 'Application status')->every(fn ($check) => $check['ok']);
        abort_unless($eligibility['eligible'] || (in_array($application->status, ['draft', 'more_information'], true) && $requirementsOkay), 403);
        if (!$application->id_front_path || !$application->selfie_video_path || !$application->challenge_used_at || !$application->consent_identity || !$application->consent_p2p_risk) {
            return back()->withNotify([['error', 'Save your identity details, upload ID photos, record the live camera check, and accept both consent statements before submitting.']]);
        }
        $application->status = 'submitted';
        $application->submitted_at = now();
        $application->requested_information = null;
        $application->save();
        $this->sendNotice($user, 'Coin vendor application received', 'Your Coin Vendor application is under review. We will notify you when there is an update.');
        return to_route('user.coin-vendor.apply')->withNotify([['success', 'Application submitted. Status: Under review.']]);
    }

    public function deleteRejectedApplication()
    {
        $application = CoinVendorApplication::where('user_id', auth()->id())->latest('id')->firstOrFail();
        abort_unless($application->status === 'rejected', 403);
        app(CoinVendorService::class)->deletePrivate($application->id_front_path);
        app(CoinVendorService::class)->deletePrivate($application->id_back_path);
        app(CoinVendorService::class)->deletePrivate($application->selfie_video_path);
        $application->identity_data = null;
        $application->phone = null;
        $application->id_number_hash = null;
        $application->id_last4 = null;
        $application->device_fingerprint_hash = null;
        $application->id_front_path = $application->id_back_path = $application->selfie_video_path = null;
        $application->challenge_code = $application->challenge_token_hash = $application->challenge_session_hash = null;
        $application->challenge_token_id = $application->challenge_user_agent = $application->challenge_ip = null;
        $application->challenge_expires_at = $application->challenge_used_at = $application->video_recorded_at = null;
        $application->video_duration_ms = null;
        $application->save();
        return back()->withNotify([['success', 'Your identity photos, video, and application details have been deleted. A minimal audit record is retained.']]);
    }

    public function desk(CoinVendorService $service, CoinLedger $ledger)
    {
        $vendor = CoinVendor::with(['currency', 'application'])->where('user_id', auth()->id())->firstOrFail();
        abort_unless($vendor->status === 'active' && (int) auth()->user()->status === 1 && !auth()->user()->coin_vendor_flagged, 403, 'Vendor Desk access is currently suspended or restricted.');
        $pageTitle = 'Vendor Desk';
        $wallet = $ledger->wallet('user', auth()->id());
        $tradeQuery = CoinTrade::where(fn ($q) => $q->where(['coin_seller_type' => 'user', 'coin_seller_id' => auth()->id()])->orWhere(['coin_buyer_type' => 'user', 'coin_buyer_id' => auth()->id()]));
        $openTrades = (clone $tradeQuery)->whereIn('status', ['pending','paid_claimed','disputed'])->count();
        $dailyUsed = (float) (clone $tradeQuery)->where('created_at', '>=', now()->startOfDay())->whereIn('status', ['pending','paid_claimed','completed','disputed'])->sum('amount');
        $trades = $tradeQuery->latest()->paginate(getPaginate());
        $blockedSellerIds = array_values(array_unique(array_merge([(int) auth()->id()], array_map('intval', $vendor->linked_user_ids ?: []))));
        $listings = CoinListing::where('status', 'on_sale')->whereNotIn('seller_user_id', $blockedSellerIds)->latest()->paginate(getPaginate(), ['*'], 'listings_page');
        $band = null;
        try { $band = $service->rateBand($vendor); } catch (InvalidArgumentException) {}
        return view('Template::user.coin.vendor.desk', compact('pageTitle', 'vendor', 'wallet', 'trades', 'listings', 'band', 'openTrades', 'dailyUsed'));
    }

    public function updateSelling(Request $request)
    {
        $vendor = $this->activeVendor();
        $vendor->is_selling = !$vendor->is_selling;
        $vendor->save();
        return back()->withNotify([['success', $vendor->is_selling ? 'Your coin sales are now visible to buyers.' : 'You are no longer taking new coin sales. Open trades are unchanged.']]);
    }

    public function updateRate(Request $request, CoinVendorService $vendors)
    {
        $vendor = $this->activeVendor();
        $data = $request->validate(['sell_rate' => 'required|numeric|gt:0']);
        try { $band = $vendors->rateBand($vendor); }
        catch (InvalidArgumentException $exception) { return back()->withNotify([['error', $exception->getMessage()]]); }
        $rate = (float) $data['sell_rate'];
        if ($rate < $band['min'] || $rate > $band['max']) return back()->withErrors(['sell_rate' => 'Rate must be within ' . number_format($band['min'], 4) . '–' . number_format($band['max'], 4) . ' ' . $vendor->currency->code . ' per coin, and cannot be below the platform sell reference.'])->withInput();
        $before = $vendor->sell_rate;
        $vendor->sell_rate = number_format($rate, 8, '.', '');
        $vendor->save();
        \App\Models\CoinVendorAudit::record('rate_changed', $vendor->application_id, $vendor->id, null, 'sell_rate', json_encode(['before' => $before, 'after' => $vendor->sell_rate]));
        return back()->withNotify([['success', 'Your new rate applies to future trades. Open trades keep their original rate.']]);
    }

    public function updatePaymentMethods(Request $request)
    {
        $vendor = $this->activeVendor();
        $data = $request->validate(['payment_methods' => 'nullable|array|max:6', 'payment_methods.*.type' => 'required_with:payment_methods|string|max:40', 'payment_methods.*.label' => 'required_with:payment_methods|string|max:60', 'payment_methods.*.details' => 'required_with:payment_methods|string|max:1000']);
        $vendor->encrypted_payment_methods = $data['payment_methods'] ?? [];
        $vendor->save();
        return back()->withNotify([['success', 'Your payment details were saved encrypted.']]);
    }

    public function resign()
    {
        $vendor = $this->activeVendor();
        $vendor->status = 'resigned';
        $vendor->is_selling = false;
        $vendor->resigned_at = now();
        $vendor->bond_release_after_at = now()->addDays((int) (gs('coin_vendor_bond_cooldown_days') ?? 14));
        $vendor->status_reason = 'Vendor requested resignation.';
        $vendor->save();
        if ($vendor->application) {
            $vendor->application->status = 'resigned';
            $vendor->application->retention_delete_at = now()->addDays((int) (gs('coin_vendor_revoked_retention_days') ?? 365));
            $vendor->application->save();
        }
        \App\Models\CoinVendorAudit::record('vendor_resigned', $vendor->application_id, $vendor->id, null, null, 'Vendor requested resignation; bond eligible for admin review after cooldown.');
        $this->sendNotice(auth()->user(), 'Vendor resignation received', 'Your Vendor Desk is closed to new trades. Your security bond remains reserved during the cooldown and can be released by an admin once open trades and penalties are resolved.');
        return to_route('user.coin-vendor.apply')->withNotify([['success', 'Your resignation was recorded. Open trades remain available to resolve; your bond enters its cooldown.']]);
    }

    public function claimListing(int $listingId)
    {
        $vendor = $this->activeVendor();
        try { $trade = app(CoinMarketplace::class)->claimListingForVendor(CoinListing::findOrFail($listingId), $vendor); }
        catch (InvalidArgumentException $exception) { return back()->withNotify([['error', $exception->getMessage()]]); }
        return to_route('user.coin-vendor.desk')->withNotify([['success', 'Listing claimed. Arrange payment with the seller in the trade chat.']]);
    }

    public function buyFromVendor(Request $request)
    {
        $request->validate(['vendor_id' => 'required|integer|exists:coin_vendors,id', 'amount' => 'required|integer|gt:0']);
        $vendor = CoinVendor::with('user')->findOrFail($request->integer('vendor_id'));
        try { $trade = app(CoinMarketplace::class)->initiateBuyFromVendor(auth()->user(), $vendor, (string) $request->input('amount')); }
        catch (InvalidArgumentException $exception) { return back()->withNotify([['error', $exception->getMessage()]]); }
        return to_route('user.coin.trade', $trade->id)->withNotify([['success', 'Vendor trade started. Use the trade chat to arrange payment.']]);
    }

    private function activeVendor(): CoinVendor
    {
        $vendor = CoinVendor::where('user_id', auth()->id())->firstOrFail();
        abort_unless($vendor->status === 'active' && (int) auth()->user()->status === 1 && !auth()->user()->coin_vendor_flagged, 403, 'Vendor Desk access is currently suspended or restricted.');
        return $vendor;
    }

    private function sendNotice(User $user, string $subject, string $message): void
    {
        try { notify($user, 'DEFAULT', ['subject' => $subject, 'message' => $message]); }
        catch (\Throwable $exception) { report($exception); }
    }
}
