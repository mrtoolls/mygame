<?php

namespace App\Http\Controllers\User;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Lib\FormProcessor;
use App\Lib\GoogleAuthenticator;
use App\Models\BonusCode;
use App\Models\BonusCodeRedemption;
use App\Models\CommissionLog;
use App\Models\Deposit;
use App\Models\DeviceToken;
use App\Models\Form;
use App\Models\Game;
use App\Models\GameLog;
use App\Models\NotificationLog;
use App\Models\ArenaNotification;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller {
    public function home() {
        $pageTitle                 = 'Dashboard';
        $games                     = Game::active()->get();
        $user                      = auth()->user();
        $widget['total_balance']   = $user->balance;
        $widget['total_deposit']   = Deposit::successful()->where('user_id', $user->id)->sum('amount');
        $widget['total_withdrawn'] = Withdrawal::approved()->where('user_id', $user->id)->sum('amount');

        $widget['total_invest'] = GameLog::where('user_id', $user->id)->sum('invest');
        $widget['total_win']    = GameLog::win()->where('user_id', $user->id)->sum('invest');
        $widget['total_loss']   = GameLog::loss()->where('user_id', $user->id)->sum('invest');
        $claimable              = $user->rakebackClaimable();
        $activeBonuses          = BonusCodeRedemption::where('user_id', $user->id)->with('bonusCode')->orderByDesc('id')->take(5)->get();
        return view('Template::user.dashboard', compact('pageTitle', 'games', 'widget', 'user', 'claimable', 'activeBonuses'));
    }

    public function depositHistory(Request $request) {
        $pageTitle = 'Deposit History';
        $deposits  = auth()->user()->deposits()->searchable(['trx'])->with(['gateway'])->orderBy('id', 'desc')->paginate(getPaginate());
        return view('Template::user.deposit_history', compact('pageTitle', 'deposits'));
    }

    public function show2faForm() {
        $ga        = new GoogleAuthenticator();
        $user      = auth()->user();
        $secret    = $ga->createSecret();
        $qrCodeUrl = $ga->getQRCodeGoogleUrl($user->username . '@' . gs('site_name'), $secret);
        $pageTitle = '2FA Security';
        return view('Template::user.twofactor', compact('pageTitle', 'secret', 'qrCodeUrl', 'user'));
    }

    public function create2fa(Request $request) {
        $user = auth()->user();
        $request->validate([
            'key'  => 'required',
            'code' => 'required',
        ]);
        $response = verifyG2fa($user, $request->code, $request->key);
        if ($response) {
            $user->tsc = $request->key;
            $user->ts  = Status::ENABLE;
            $user->save();
            $notify[] = ['success', 'Two factor authenticator activated successfully'];
            return back()->withNotify($notify);
        } else {
            $notify[] = ['error', 'Wrong verification code'];
            return back()->withNotify($notify);
        }
    }

    public function disable2fa(Request $request) {
        $request->validate([
            'code' => 'required',
        ]);

        $user     = auth()->user();
        $response = verifyG2fa($user, $request->code);
        if ($response) {
            $user->tsc = null;
            $user->ts  = Status::DISABLE;
            $user->save();
            $notify[] = ['success', 'Two factor authenticator deactivated successfully'];
        } else {
            $notify[] = ['error', 'Wrong verification code'];
        }
        return back()->withNotify($notify);
    }

    public function transactions() {
        $pageTitle    = 'Transactions';
        $remarks      = Transaction::where('user_id', auth()->id())->distinct('remark')->orderBy('remark')->get('remark');
        $transactions = Transaction::where('user_id', auth()->id())->searchable(['trx'])->filter(['trx_type', 'remark'])->orderBy('id', 'desc')->paginate(getPaginate());
        return view('Template::user.transactions', compact('pageTitle', 'transactions', 'remarks'));
    }

    public function kycForm() {
        if (auth()->user()->kv == Status::KYC_PENDING) {
            $notify[] = ['error', 'Your KYC is under review'];
            return to_route('user.home')->withNotify($notify);
        }
        if (auth()->user()->kv == Status::KYC_VERIFIED) {
            $notify[] = ['error', 'You are already KYC verified'];
            return to_route('user.home')->withNotify($notify);
        }
        $pageTitle = 'KYC Form';
        $form      = Form::where('act', 'kyc')->first();
        return view('Template::user.kyc.form', compact('pageTitle', 'form'));
    }

    public function kycData() {
        $user      = auth()->user();
        $pageTitle = 'KYC Data';
        abort_if($user->kv == Status::VERIFIED, 403);
        return view('Template::user.kyc.info', compact('pageTitle', 'user'));
    }

    public function kycSubmit(Request $request) {
        $form           = Form::where('act', 'kyc')->firstOrFail();
        $formData       = $form->form_data;
        $formProcessor  = new FormProcessor();
        $validationRule = $formProcessor->valueValidation($formData);
        $request->validate($validationRule);
        $user = auth()->user();
        foreach (@$user->kyc_data ?? [] as $kycData) {
            if ($kycData->type == 'file') {
                fileManager()->removeFile(getFilePath('verify') . '/' . $kycData->value);
            }
        }
        $userData                   = $formProcessor->processFormData($request, $formData);
        $user->kyc_data             = $userData;
        $user->kyc_rejection_reason = null;
        $user->kv                   = Status::KYC_PENDING;
        $user->save();

        $notify[] = ['success', 'KYC data submitted successfully'];
        return to_route('user.home')->withNotify($notify);
    }

    public function userData() {
        $user = auth()->user();

        if ($user->profile_complete == Status::YES) {
            return to_route('user.home');
        }

        $pageTitle  = 'User Data';
        $info       = json_decode(json_encode(getIpInfo()), true);
        $mobileCode = isset($info['code']) ? implode(',', $info['code']) : '';
        $countries  = json_decode(file_get_contents(resource_path('views/partials/country.json')));

        return view('Template::user.user_data', compact('pageTitle', 'user', 'countries', 'mobileCode'));
    }

    public function userDataSubmit(Request $request) {

        $user = auth()->user();

        if ($user->profile_complete == Status::YES) {
            return to_route('user.home');
        }

        $countryData  = (array) json_decode(file_get_contents(resource_path('views/partials/country.json')));
        $countryCodes = implode(',', array_keys($countryData));
        $mobileCodes  = implode(',', array_column($countryData, 'dial_code'));
        $countries    = implode(',', array_column($countryData, 'country'));

        $request->validate([
            'country_code' => 'required|in:' . $countryCodes,
            'country'      => 'required|in:' . $countries,
            'mobile_code'  => 'required|in:' . $mobileCodes,
            'username'     => 'required|unique:users|min:3',
            'mobile'       => ['required', 'regex:/^([0-9]*)$/', Rule::unique('users')->where('dial_code', $request->mobile_code)],
            'gender'       => 'required|in:male,female',
        ]);

        if (preg_match("/[^a-z0-9_]/", trim($request->username))) {
            $notify[] = ['info', 'Username can contain only small letters, numbers and underscore.'];
            $notify[] = ['error', 'No special character, space or capital letters in username.'];
            return back()->withNotify($notify)->withInput($request->all());
        }

        $user->country_code = $request->country_code;
        $user->mobile       = $request->mobile;
        $user->username     = $request->username;

        $user->state         = $request->state;
        $user->country_name  = @$request->country;
        $user->dial_code     = $request->mobile_code;
        $user->gender        = $request->gender;

        // glob() resolves a bare relative path against the PHP process's
        // working directory, which isn't reliably "public/" on every host -
        // anchor it with public_path() so the scan can't silently come back
        // empty (leaving the user with no avatar) depending on server config.
        $pool = glob(public_path('assets/images/profilepics/' . $request->gender . '*.png'));
        if (!empty($pool)) {
            $user->image = 'assets/images/profilepics/' . basename($pool[array_rand($pool)]);
        }

        $user->profile_complete = Status::YES;
        $user->save();

        if (gs('rb')) {
            $user->balance += gs('register_bonus');
            $user->save();

            $transaction               = new Transaction();
            $transaction->user_id      = $user->id;
            $transaction->amount       = gs('register_bonus');
            $transaction->charge       = 0;
            $transaction->trx_type     = '+';
            $transaction->details      = 'You have got register bonus';
            $transaction->remark       = 'register_bonus';
            $transaction->trx          = getTrx();
            $transaction->post_balance = $user->balance;
            $transaction->save();

            notify($user, 'REGISTER_BONUS', [
                'username'     => $user->username,
                'amount'       => showAmount(gs('register_bonus')),
                'trx'          => $transaction->trx,
                'post_balance' => showAmount($user->balance),
            ]);
        }

        return to_route('user.home');
    }

    public function addDeviceToken(Request $request) {

        $validator = Validator::make($request->all(), [
            'token' => 'required',
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'errors' => $validator->errors()->all()];
        }

        $deviceToken = DeviceToken::where('token', $request->token)->first();

        if ($deviceToken) {
            if ((int) $deviceToken->user_id !== (int) auth()->id()) {
                $deviceToken->user_id = auth()->id();
                $deviceToken->save();
            }
            return ['success' => true, 'message' => 'Already exists'];
        }

        $deviceToken          = new DeviceToken();
        $deviceToken->user_id = auth()->user()->id;
        $deviceToken->token   = $request->token;
        $deviceToken->is_app  = Status::NO;
        $deviceToken->save();

        return ['success' => true, 'message' => 'Token saved successfully'];
    }

    public function notificationBadge()
    {
        $userId = auth()->id();
        $pushUnread = NotificationLog::where('user_id', $userId)
            ->where('sender', 'firebase')
            ->where(function ($query) {
                $query->whereNull('user_read')->orWhere('user_read', 0);
            })->count();
        $arenaUnread = ArenaNotification::where('user_id', $userId)->unread()->count();

        return response()->json(['count' => $pushUnread + $arenaUnread]);
    }

    public function notificationsReadAll()
    {
        NotificationLog::where('user_id', auth()->id())
            ->where('sender', 'firebase')
            ->update(['user_read' => 1]);
        ArenaNotification::where('user_id', auth()->id())->unread()->update(['read_at' => now()]);

        return response()->json(['ok' => true, 'count' => 0]);
    }

    public function downloadAttachment($fileHash) {
        $filePath  = decrypt($fileHash);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $title     = slug(gs('site_name')) . '- attachments.' . $extension;
        try {
            $mimetype = mime_content_type($filePath);
        } catch (\Exception $e) {
            $notify[] = ['error', 'File does not exists'];
            return back()->withNotify($notify);
        }
        header('Content-Disposition: attachment; filename="' . $title);
        header("Content-Type: " . $mimetype);
        return readfile($filePath);
    }

    public function referrals() {
        $pageTitle      = 'Referrals';
        $user           = User::where('id', auth()->id())->with('allReferrals')->firstOrFail();
        $maxLevel       = Referral::max('level');
        $currentTier    = $user->vipTier();
        $wagered24h     = $user->wageredLast24Hours();
        $claimable      = $user->rakebackClaimable();
        $rakebackTiers  = \App\Models\VipTier::active()->orderBy('min_wagered')->get();
        $activeBonuses  = BonusCodeRedemption::where('user_id', $user->id)->with('bonusCode')->orderByDesc('id')->take(10)->get();
        return view('Template::user.referrals', compact('pageTitle', 'user', 'maxLevel', 'currentTier', 'wagered24h', 'claimable', 'rakebackTiers', 'activeBonuses'));
    }

    public function activateBonusCode(Request $request) {
        $request->validate([
            'code' => 'required|string|max:60',
        ]);

        $notify = [];
        DB::beginTransaction();
        try {
            $code = BonusCode::where('code', $request->code)->lockForUpdate()->first();

            if (!$code || $code->status != Status::ENABLE) {
                DB::rollBack();
                $notify[] = ['error', 'This code is invalid'];
                return back()->withNotify($notify);
            }
            if ($code->isExpired()) {
                DB::rollBack();
                $notify[] = ['error', 'This code has expired'];
                return back()->withNotify($notify);
            }
            if ($code->isExhausted()) {
                DB::rollBack();
                $notify[] = ['error', 'This code has reached its usage limit'];
                return back()->withNotify($notify);
            }

            $alreadyUsed = BonusCodeRedemption::where('user_id', auth()->id())->where('bonus_code_id', $code->id)->exists();
            if ($alreadyUsed) {
                DB::rollBack();
                $notify[] = ['error', 'You have already used this code'];
                return back()->withNotify($notify);
            }

            $user = User::where('id', auth()->id())->lockForUpdate()->first();
            if (Schema::hasColumn('bonus_codes', 'currency_id') && $code->currency_id && (int) $code->currency_id !== (int) $user->currency_id) {
                DB::rollBack();
                $notify[] = ['error', 'This bonus code is only valid for the matching wallet currency.'];
                return back()->withNotify($notify);
            }
            $trx  = getTrx();

            $user->balance += $code->amount;
            $user->save();

            $code->used_count += 1;
            $code->save();

            $redemption                = new BonusCodeRedemption();
            $redemption->user_id       = $user->id;
            $redemption->bonus_code_id = $code->id;
            $redemption->amount        = $code->amount;
            if (Schema::hasColumn('bonus_code_redemptions', 'currency_id')) $redemption->currency_id = $user->currency_id;
            $redemption->trx           = $trx;
            $redemption->save();

            $transaction               = new Transaction();
            $transaction->user_id      = $user->id;
            $transaction->amount       = $code->amount;
            $transaction->charge       = 0;
            $transaction->trx_type     = '+';
            $transaction->details      = 'Bonus code activated - ' . $code->code;
            $transaction->remark       = 'bonus_code';
            $transaction->trx          = $trx;
            $transaction->post_balance = $user->balance;
            if (Schema::hasColumn('transactions', 'currency_id')) $transaction->currency_id = $user->currency_id;
            $transaction->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $notify[] = ['error', 'Something went wrong, please try again'];
            return back()->withNotify($notify);
        }

        $notify[] = ['success', 'Bonus code activated successfully'];
        return back()->withNotify($notify);
    }

    public function gameLog() {
        $pageTitle = "Game Logs";
        $user      = auth()->user();
        $logs      = GameLog::where('user_id', $user->id)
            ->where('demo_play', Status::NO)
            ->where('status', Status::GAME_FINISHED)
            ->orderByDesc('id')
            ->with('game')
            ->paginate(getPaginate());
        $isDemo = false;
        return view('Template::user.game_log', compact('pageTitle', 'logs', 'isDemo'));
    }

    public function demoGameLog() {
        $pageTitle = "Game Logs";
        $user      = auth()->user();
        $logs      = GameLog::where('user_id', $user->id)
            ->where('demo_play', Status::YES)
            ->where('status', Status::GAME_FINISHED)
            ->orderByDesc('id')
            ->with('game')
            ->paginate(getPaginate());
        $isDemo = true;
        return view('Template::user.game_log', compact('pageTitle', 'logs', 'isDemo'));
    }

    public function commissionLog() {
        $pageTitle = "Commission Logs";
        $logs      = CommissionLog::where('user_id', auth()->id())->with('userFrom')->latest()->paginate(getPaginate());
        return view('Template::user.commission_log', compact('pageTitle', 'logs'));
    }
}
