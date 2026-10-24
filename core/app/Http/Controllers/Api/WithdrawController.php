<?php

namespace App\Http\Controllers\Api;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Lib\FormProcessor;
use App\Models\AdminNotification;
use App\Models\Transaction;
use App\Models\Withdrawal;
use App\Models\WithdrawMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class WithdrawController extends Controller
{
    /** Same rule as the web controller: no platform withdrawals in coin mode - cash out via the agent marketplace instead. History stays readable, nothing else does. */
    private function blockedInCoinMode()
    {
        if (!gs('coin_mode')) return null;
        return responseError('coin_mode_withdrawals_disabled', ['Withdrawals are not available in coin mode. Cash out by selling your coins in the Coin Marketplace.']);
    }

    public function withdrawMethod()
    {
        if ($blocked = $this->blockedInCoinMode()) return $blocked;
        $query = WithdrawMethod::active()->where(function ($query) {
            $query->whereNull('support_agent_id')
                ->orWhereHas('supportAgent', fn ($agent) => $agent->paymentAgents());
        });
        if (auth()->user()?->currency?->code) $query->where('currency', auth()->user()->currency->code);
        $user = auth()->user();
        $withdrawMethod = $query->get()->filter(fn ($method) => app(\App\Support\CountryPaymentLimits::class)->allowsMethod($user, [$method->id, $method->name, $method->currency]))->values();
        $notify[]       = 'Withdrawals methods';
        return responseSuccess('withdraw_methods', $notify, [
            'withdrawMethod' => $withdrawMethod,
            'imagePath' => getFilePath('withdrawMethod')
        ]);
    }

    public function withdrawStore(Request $request)
    {
        if ($blocked = $this->blockedInCoinMode()) return $blocked;
        $validator = Validator::make($request->all(), [
            'method_code' => 'required',
            'amount'      => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $method = WithdrawMethod::where('id', $request->method_code)->where(function ($query) {
            $query->whereNull('support_agent_id')
                ->orWhereHas('supportAgent', fn ($agent) => $agent->paymentAgents());
        })->active()->first();
        if (!$method) {
            $notify[] = 'Withdraw method not found.';
            return responseError('validation_error', $notify);
        }

        $user = auth()->user();
        if ($user->currency?->code && strtoupper($user->currency->code) !== strtoupper((string) $method->currency)) {
            return responseError('invalid_currency', ['Withdrawal currency must match your wallet currency.']);
        }
        app(\App\Support\CountryPaymentLimits::class)->assertMethodAllowed($user, [$method->id, $method->name, $method->currency]);
        app(\App\Support\CountryPaymentLimits::class)->assert($user, 'withdrawal', $request->input('amount'));
        if ($request->amount < $method->min_limit) {
            $notify[] = 'Your requested amount is smaller than minimum amount';
            return responseError('validation_error', $notify);
        }
        if ($request->amount > $method->max_limit) {
            $notify[] = 'Your requested amount is larger than maximum amount';
            return responseError('validation_error', $notify);
        }

        if ($request->amount > $user->balance) {
            $notify[] = 'Insufficient balance for withdrawal';
            return responseError('validation_error', $notify);
        }

        $userMinimumBalance = getAmount(gs('min_balance'));

        if ($user->balance < $userMinimumBalance) {
            $notify[] = ['error', 'To proceed with the withdrawal, your balance must be at least ' . getAmount($userMinimumBalance) . ' ' . $method->currency . '.'];
            return responseError('limit_error', $notify);
        }

        $charge      = $method->fixed_charge + ($request->amount * $method->percent_charge / 100);
        $afterCharge = $request->amount - $charge;

        if ($afterCharge <= 0) {
            $notify[] = 'Withdraw amount must be sufficient for charges';
            return responseError('validation_error', $notify);
        }

        $finalAmount = $afterCharge * $method->rate;

        $withdraw               = new Withdrawal();
        $withdraw->method_id    = $method->id; // wallet method ID
        $withdraw->user_id      = $user->id;
        if (Schema::hasColumn('withdrawals', 'currency_id')) $withdraw->currency_id = $user->currency_id;
        $withdraw->amount       = $request->amount;
        $withdraw->currency     = $method->currency;
        $withdraw->rate         = $method->rate;
        $withdraw->charge       = $charge;
        $withdraw->final_amount = $finalAmount;
        $withdraw->after_charge = $afterCharge;
        $withdraw->trx          = getTrx();
        $withdraw->save();

        $notify[] = 'Withdraw request created';
        return responseSuccess('withdraw_request_created', $notify, [
            'trx'           => $withdraw->trx,
            'withdraw_data' => $withdraw,
            'form'          => $method->form->form_data
        ]);
    }

    public function withdrawSubmit(Request $request)
    {
        if ($blocked = $this->blockedInCoinMode()) return $blocked;
        $validator = Validator::make($request->all(), [
            'trx' => 'required',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $withdraw = Withdrawal::with('method', 'user')->where('trx', $request->trx)->where('status', Status::PAYMENT_INITIATE)->orderBy('id', 'desc')->first();
        if (!$withdraw) {
            $notify[] = 'Withdrawal request not found';
            return responseError('validation_error', $notify);
        }

        $method = $withdraw->method;

        if ($method->status == Status::DISABLE) {
            $notify[] = 'Withdraw method not found.';
            return responseError('validation_error', $notify);
        }

        $formData = $method->form->form_data;

        $formProcessor  = new FormProcessor();
        $validationRule = $formProcessor->valueValidation($formData);

        $validator = Validator::make($request->all(), $validationRule);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $userData = $formProcessor->processFormData($request, $formData);

        $user = auth()->user();
        if ($user->ts) {
            if (!$request->authenticator_code) {
                $notify[] = 'Google authentication is required';
                return responseError('validation_error', $notify);
            }
            $response = verifyG2fa($user, $request->authenticator_code);
            if (!$response) {
                $notify[] = 'Wrong verification code';
                return responseError('validation_error', $notify);
            }
        }

        try {
            [$withdraw, $user] = DB::transaction(function () use ($withdraw, $userData, $user) {
                $lockedWithdrawal = Withdrawal::whereKey($withdraw->id)->lockForUpdate()->firstOrFail();
                if ((int) $lockedWithdrawal->status !== Status::PAYMENT_INITIATE) abort(409, 'This withdrawal has already been submitted.');
                $lockedUser = \App\Models\User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                if ($lockedWithdrawal->amount > $lockedUser->balance) abort(422, 'Insufficient balance for withdrawal.');
                if ($lockedUser->currency_id && $lockedWithdrawal->currency_id && (int) $lockedUser->currency_id !== (int) $lockedWithdrawal->currency_id) abort(422, 'Withdrawal currency does not match your wallet.');

                $lockedWithdrawal->status = Status::PAYMENT_PENDING;
                $lockedWithdrawal->withdraw_information = $userData;
                $lockedWithdrawal->save();
                $lockedUser->balance -= $lockedWithdrawal->amount;
                $lockedUser->save();
                $transaction = new Transaction();
                $transaction->user_id = $lockedUser->id;
                $transaction->amount = $lockedWithdrawal->amount;
                $transaction->post_balance = $lockedUser->balance;
                $transaction->charge = $lockedWithdrawal->charge;
                $transaction->trx_type = '-';
                $transaction->details = 'Withdraw request via ' . $lockedWithdrawal->method->name;
                $transaction->trx = $lockedWithdrawal->trx;
                $transaction->remark = 'withdraw';
                if (Schema::hasColumn('transactions', 'currency_id')) $transaction->currency_id = $lockedUser->currency_id;
                $transaction->save();

                $adminNotification = new AdminNotification();
                $adminNotification->user_id = $lockedUser->id;
                $adminNotification->title = 'New withdraw request from ' . $lockedUser->username;
                $adminNotification->click_url = urlPath('admin.withdraw.data.details', $lockedWithdrawal->id);
                $adminNotification->save();
                return [$lockedWithdrawal, $lockedUser];
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            return responseError('withdraw_submit_failed', [$exception->getMessage()]);
        }

        notify($user, 'WITHDRAW_REQUEST', [
            'method_name'     => $withdraw->method->name,
            'method_currency' => $withdraw->currency,
            'method_amount'   => showAmount($withdraw->final_amount, currencyFormat: false),
            'amount'          => showAmount($withdraw->amount, currencyFormat: false),
            'charge'          => showAmount($withdraw->charge, currencyFormat: false),
            'rate'            => showAmount($withdraw->rate, currencyFormat: false),
            'trx'             => $withdraw->trx,
            'post_balance'    => showAmount($user->balance, currencyFormat: false),
        ]);

        $notify[] = 'Withdraw request sent successfully';
        return responseSuccess('withdraw_confirmed', $notify);
    }

    public function withdrawLog(Request $request)
    {
        $withdraws = Withdrawal::where('user_id', auth()->id());
        if ($request->search) {
            $withdraws = $withdraws->where('trx', $request->search);
        }
        $withdraws = $withdraws->where('status', '!=', Status::PAYMENT_INITIATE)->with('method')->orderBy('id', 'desc')->paginate(getPaginate());
        $notify[]  = 'Withdrawals';
        return responseSuccess('withdrawals', $notify, [
            'withdrawals' => $withdraws
        ]);
    }
}
