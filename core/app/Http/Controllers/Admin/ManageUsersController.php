<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Lib\UserNotificationSender;
use App\Models\Deposit;
use App\Models\Country;
use App\Models\NotificationLog;
use App\Models\AdminImpersonation;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use App\Rules\FileTypeValidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ManageUsersController extends Controller {

    public function allUsers() {
        $pageTitle = 'All Users';
        $users     = $this->userData();
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function activeUsers() {
        $pageTitle = 'Active Users';
        $users     = $this->userData('active');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function bannedUsers() {
        $pageTitle = 'Banned Users';
        $users     = $this->userData('banned');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function emailUnverifiedUsers() {
        $pageTitle = 'Email Unverified Users';
        $users     = $this->userData('emailUnverified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function kycUnverifiedUsers() {
        $pageTitle = 'KYC Unverified Users';
        $users     = $this->userData('kycUnverified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function kycPendingUsers() {
        $pageTitle = 'KYC Pending Users';
        $users     = $this->userData('kycPending');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function emailVerifiedUsers() {
        $pageTitle = 'Email Verified Users';
        $users     = $this->userData('emailVerified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function mobileUnverifiedUsers() {
        $pageTitle = 'Mobile Unverified Users';
        $users     = $this->userData('mobileUnverified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function mobileVerifiedUsers() {
        $pageTitle = 'Mobile Verified Users';
        $users     = $this->userData('mobileVerified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function usersWithBalance() {
        $pageTitle = 'Users with Balance';
        $users     = $this->userData('withBalance');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    protected function userData($scope = null) {
        if ($scope) {
            $users = User::$scope();
        } else {
            $users = User::query();
        }
        return $users->searchable(['username', 'email'])->orderBy('id', 'desc')->paginate(getPaginate());
    }

    public function detail($id) {
        $user      = User::findOrFail($id);
        $pageTitle = 'User Detail - ' . $user->username;

        $totalDeposit     = Deposit::where('user_id', $user->id)->successful()->sum('amount');
        $totalWithdrawals = Withdrawal::where('user_id', $user->id)->approved()->sum('amount');
        $totalTransaction = Transaction::where('user_id', $user->id)->count();
        $countries        = json_decode(file_get_contents(resource_path('views/partials/country.json')));
        $walletCountries  = Schema::hasTable('countries') ? Country::with('currency')->where('is_enabled', true)->where('is_allowed', true)->orderBy('name')->get() : collect();
        return view('admin.users.detail', compact('pageTitle', 'user', 'totalDeposit', 'totalWithdrawals', 'totalTransaction', 'countries', 'walletCountries'));
    }

    // Hands off to the existing, fully-validated Support Agent create/edit form instead of
    // building a second, parallel "make this user an agent" flow - this just creates (or finds
    // an already-linked) stub support_agents row from the user's name/email/username with a
    // random, never-shown temporary password and is_active/is_payment_agent both OFF, then sends
    // the admin straight to SupportAgentController::edit() to actually configure and activate
    // it. Live/payment-agent accounts are a completely separate guard/table from regular users
    // (see config/auth.php) by design - this never grants the user's own web-guard session any
    // extra access, it only ever lets an already-authenticated admin provision a new, disabled-
    // until-configured agent account.
    public function promoteToAgent($id) {
        if (!Schema::hasTable('support_agents')) {
            return back()->withNotify([['error', 'The Support Agents feature is not set up on this installation yet.']]);
        }

        $user = User::findOrFail($id);
        $agent = \App\Models\SupportAgent::where('email', $user->email)->first();

        if (!$agent) {
            $username = $user->username;
            $suffix = 1;
            while (\App\Models\SupportAgent::where('username', $username)->exists()) {
                $username = $user->username . $suffix++;
            }
            $agent = \App\Models\SupportAgent::create([
                'name'              => trim($user->firstname . ' ' . $user->lastname) ?: $user->username,
                'email'             => $user->email,
                'username'          => $username,
                'password'          => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(24)),
                'is_active'         => false,
                'is_payment_agent'  => false,
            ]);
        }

        $notify[] = ['success', 'Agent account ready - set a password and choose Live/Payment agent access below, then activate it.'];
        return to_route('admin.support.agents.edit', $agent->id)->withNotify($notify);
    }

    public function kycDetails($id) {
        $pageTitle = 'KYC Details';
        $user      = User::findOrFail($id);
        return view('admin.users.kyc_detail', compact('pageTitle', 'user'));
    }

    public function kycApprove($id) {
        $user     = User::findOrFail($id);
        $user->kv = Status::KYC_VERIFIED;
        $user->save();

        notify($user, 'KYC_APPROVE', []);

        $notify[] = ['success', 'KYC approved successfully'];
        return to_route('admin.users.kyc.pending')->withNotify($notify);
    }

    public function kycReject(Request $request, $id) {
        $request->validate([
            'reason' => 'required',
        ]);
        $user                       = User::findOrFail($id);
        $user->kv                   = Status::KYC_UNVERIFIED;
        $user->kyc_rejection_reason = $request->reason;
        $user->save();

        notify($user, 'KYC_REJECT', [
            'reason' => $request->reason,
        ]);

        $notify[] = ['success', 'KYC rejected successfully'];
        return to_route('admin.users.kyc.pending')->withNotify($notify);
    }

    public function update(Request $request, $id) {
        $user         = User::findOrFail($id);
        $countryData  = json_decode(file_get_contents(resource_path('views/partials/country.json')));
        $countryArray = (array) $countryData;
        $countries    = implode(',', array_keys($countryArray));

        $countryCode = $request->country;
        $country     = $countryData->$countryCode->country;
        $dialCode    = $countryData->$countryCode->dial_code;

        $request->validate([
            'firstname' => 'required|string|max:40',
            'lastname'  => 'required|string|max:40',
            'email'     => 'required|email|string|max:40|unique:users,email,' . $user->id,
            'mobile'    => 'required|string|max:40',
            'country'   => 'required|in:' . $countries,
        ]);

        $exists = User::where('mobile', $request->mobile)->where('dial_code', $dialCode)->where('id', '!=', $user->id)->exists();
        if ($exists) {
            $notify[] = ['error', 'The mobile number already exists.'];
            return back()->withNotify($notify);
        }

        $user->mobile    = $request->mobile;
        $user->firstname = $request->firstname;
        $user->lastname  = $request->lastname;
        $user->email     = $request->email;

        $user->address      = $request->address;
        $user->city         = $request->city;
        $user->state        = $request->state;
        $user->zip          = $request->zip;
        $user->country_name = @$country;
        $user->dial_code    = $dialCode;
        $user->country_code = $countryCode;

        $user->ev = $request->ev ? Status::VERIFIED : Status::UNVERIFIED;
        $user->sv = $request->sv ? Status::VERIFIED : Status::UNVERIFIED;
        $user->ts = $request->ts ? Status::ENABLE : Status::DISABLE;
        if (!$request->kv) {
            $user->kv = Status::KYC_UNVERIFIED;
            if ($user->kyc_data) {
                foreach ($user->kyc_data as $kycData) {
                    if ($kycData->type == 'file') {
                        fileManager()->removeFile(getFilePath('verify') . '/' . $kycData->value);
                    }
                }
            }
            $user->kyc_data = null;
        } else {
            $user->kv = Status::KYC_VERIFIED;
        }
        $user->save();

        $notify[] = ['success', 'User details updated successfully'];
        return back()->withNotify($notify);
    }

    public function addSubBalance(Request $request, $id) {
        $request->validate([
            'amount' => 'required|numeric|gt:0',
            'act'    => 'required|in:add,sub',
            'remark' => 'required|string|max:255',
        ]);

        $amount = $request->amount;
        $trx    = getTrx();
        $act    = $request->act;
        $remark = $request->remark;

        // Locked and re-checked under the lock (not the unlocked $user fetched above the
        // validation, which an overlapping admin edit could already have moved on from) so two
        // manual adjustments for the same user can never race, and a "subtract more than the
        // live balance" attempt is rejected against the actual current balance, not a stale one.
        $result = DB::transaction(function () use ($id, $amount, $act, $remark, $trx) {
            $user = User::whereKey($id)->lockForUpdate()->firstOrFail();

            $transaction = new Transaction();

            if ($act == 'add') {
                $user->balance = bcadd((string) $user->balance, (string) $amount, 8);

                $transaction->trx_type = '+';
                $transaction->remark   = 'balance_add';

                $notifyTemplate = 'BAL_ADD';
            } else {
                if (bccomp((string) $amount, (string) $user->balance, 8) > 0) {
                    return ['error' => $user->username . ' doesn\'t have sufficient balance.'];
                }

                $user->balance = bcsub((string) $user->balance, (string) $amount, 8);

                $transaction->trx_type = '-';
                $transaction->remark   = 'balance_subtract';

                $notifyTemplate = 'BAL_SUB';
            }

            $user->save();

            $transaction->user_id      = $user->id;
            $transaction->amount       = $amount;
            $transaction->post_balance = $user->balance;
            $transaction->charge       = 0;
            $transaction->trx          = $trx;
            $transaction->details      = $remark;
            $transaction->save();

            return ['user' => $user, 'template' => $notifyTemplate];
        });

        if (isset($result['error'])) {
            $notify[] = ['error', $result['error']];
            return back()->withNotify($notify);
        }

        $user           = $result['user'];
        $notifyTemplate = $result['template'];
        $notify[]       = ['success', $act == 'add' ? 'Balance added successfully' : 'Balance subtracted successfully'];

        notify($user, $notifyTemplate, [
            'trx'          => $trx,
            'amount'       => showAmount($amount, currencyFormat: false),
            'remark'       => $request->remark,
            'post_balance' => showAmount($user->balance, currencyFormat: false),
        ]);

        return back()->withNotify($notify);
    }

    /**
     * "Login as user" - a real, auditable impersonation, not a guard swap that loses track of
     * who's really at the keyboard. The admin guard's own session is left completely untouched
     * (SessionGuard::logout() only clears that guard's own session keys), so the admin is still
     * authenticated on the admin side the whole time; returnToAdmin() just ends the web-guard
     * session this starts and sends them back. Every start/end is logged in
     * admin_impersonations with the acting admin, the target user, and an IP, since this is a
     * real-money platform and "an admin was looking at this account as the account" needs to be
     * reconstructable later.
     */
    public function login(Request $request, $id) {
        $user = User::findOrFail($id);
        $admin = $request->user('admin');
        $log = AdminImpersonation::create([
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'started_at' => now(),
        ]);
        session([
            'impersonating_admin_id' => $admin->id,
            'impersonating_admin_name' => $admin->username ?: $admin->name,
            'impersonation_log_id' => $log->id,
        ]);
        Auth::guard('web')->loginUsingId($user->id);
        return to_route('user.home');
    }

    public function returnToAdmin(Request $request) {
        $logId = session('impersonation_log_id');
        session()->forget(['impersonating_admin_id', 'impersonating_admin_name', 'impersonation_log_id']);
        Auth::guard('web')->logout();
        if ($logId) {
            AdminImpersonation::whereKey($logId)->whereNull('ended_at')->update(['ended_at' => now()]);
        }
        $request->session()->regenerate();
        return $request->user('admin') ? to_route('admin.dashboard') : to_route('admin.login');
    }

    public function status(Request $request, $id) {
        $user = User::findOrFail($id);
        if ($user->status == Status::USER_ACTIVE) {
            $request->validate([
                'reason' => 'required|string|max:255',
            ]);
            $user->status     = Status::USER_BAN;
            $user->ban_reason = $request->reason;
            $notify[]         = ['success', 'User banned successfully'];
        } else {
            $user->status     = Status::USER_ACTIVE;
            $user->ban_reason = null;
            $notify[]         = ['success', 'User unbanned successfully'];
        }
        $user->save();
        return back()->withNotify($notify);
    }

    public function showNotificationSingleForm($id) {
        $user = User::findOrFail($id);
        if (!gs('en') && !gs('sn') && !gs('pn')) {
            $notify[] = ['warning', 'Notification options are disabled currently'];
            return to_route('admin.users.detail', $user->id)->withNotify($notify);
        }
        $pageTitle = 'Send Notification to ' . $user->username;
        return view('admin.users.notification_single', compact('pageTitle', 'user'));
    }

    public function sendNotificationSingle(Request $request, $id) {
        $request->validate([
            'message' => 'required',
            'via'     => 'required|in:email,sms,push',
            'subject' => 'required_if:via,email,push',
            'image'   => ['nullable', 'image', new FileTypeValidate(['jpg', 'jpeg', 'png'])],
        ]);

        if (!gs('en') && !gs('sn') && !gs('pn')) {
            $notify[] = ['warning', 'Notification options are disabled currently'];
            return to_route('admin.dashboard')->withNotify($notify);
        }

        return (new UserNotificationSender())->notificationToSingle($request, $id);
    }

    public function showNotificationAllForm() {
        if (!gs('en') && !gs('sn') && !gs('pn')) {
            $notify[] = ['warning', 'Notification options are disabled currently'];
            return to_route('admin.dashboard')->withNotify($notify);
        }

        $notifyToUser = User::notifyToUser();
        $users        = User::active()->count();
        $pageTitle    = 'Notification to Verified Users';

        if (session()->has('SEND_NOTIFICATION') && !request()->email_sent) {
            session()->forget('SEND_NOTIFICATION');
        }

        return view('admin.users.notification_all', compact('pageTitle', 'users', 'notifyToUser'));
    }

    public function sendNotificationAll(Request $request) {
        $request->validate([
            'via'                          => 'required|in:email,sms,push',
            'message'                      => 'required',
            'subject'                      => 'required_if:via,email,push',
            'start'                        => 'required|integer|gte:1',
            'batch'                        => 'required|integer|gte:1',
            'being_sent_to'                => 'required',
            'cooling_time'                 => 'required|integer|gte:1',
            'number_of_top_deposited_user' => 'required_if:being_sent_to,topDepositedUsers|integer|gte:0',
            'number_of_days'               => 'required_if:being_sent_to,notLoginUsers|integer|gte:0',
            'image'                        => ["nullable", 'image', new FileTypeValidate(['jpg', 'jpeg', 'png'])],
        ], [
            'number_of_days.required_if'               => "Number of days field is required",
            'number_of_top_deposited_user.required_if' => "Number of top deposited user field is required",
        ]);

        if (!gs('en') && !gs('sn') && !gs('pn')) {
            $notify[] = ['warning', 'Notification options are disabled currently'];
            return to_route('admin.dashboard')->withNotify($notify);
        }

        return (new UserNotificationSender())->notificationToAll($request);
    }

    public function countBySegment($methodName) {
        return User::active()->$methodName()->count();
    }

    public function list() {
        $query = User::active();

        if (request()->search) {
            $query->where(function ($q) {
                $q->where('email', 'like', '%' . request()->search . '%')->orWhere('username', 'like', '%' . request()->search . '%');
            });
        }
        $users = $query->orderBy('id', 'desc')->paginate(getPaginate());
        return response()->json([
            'success' => true,
            'users'   => $users,
            'more'    => $users->hasMorePages(),
        ]);
    }

    public function notificationLog($id) {
        $user      = User::findOrFail($id);
        $pageTitle = 'Notifications Sent to ' . $user->username;
        $logs      = NotificationLog::where('user_id', $id)->with('user')->orderBy('id', 'desc')->paginate(getPaginate());
        return view('admin.reports.notification_history', compact('pageTitle', 'logs', 'user'));
    }

    public function updateDemoBalance(Request $request) {
        $request->validate([
            'demo_balance' => 'required|numeric|gt:0',
        ]);
        User::query()->update(['demo_balance' => $request->demo_balance]);
        $notify[] = ['success', 'Demo balance updated successfully'];
        return back()->withNotify($notify);
    }
}
