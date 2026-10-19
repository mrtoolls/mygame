<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Withdrawal;
use App\Models\Currency;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class WithdrawalController extends Controller {
    public function pending($userId = null) {
        $pageTitle   = 'Pending Withdrawals';
        $withdrawals = $this->withdrawalData('pending', userId: $userId);
        return view('admin.withdraw.withdrawals', compact('pageTitle', 'withdrawals'));
    }

    public function approved($userId = null) {
        $pageTitle   = 'Approved Withdrawals';
        $withdrawals = $this->withdrawalData('approved', userId: $userId);
        return view('admin.withdraw.withdrawals', compact('pageTitle', 'withdrawals'));
    }

    public function rejected($userId = null) {
        $pageTitle   = 'Rejected Withdrawals';
        $withdrawals = $this->withdrawalData('rejected', userId: $userId);
        return view('admin.withdraw.withdrawals', compact('pageTitle', 'withdrawals'));
    }

    public function all($userId = null) {
        $pageTitle      = 'All Withdrawals';
        $withdrawalData = $this->withdrawalData($scope = null, $summary = true, userId: $userId);
        $withdrawals    = $withdrawalData['data'];
        $summary        = $withdrawalData['summary'];
        $successful     = $summary['successful'];
        $pending        = $summary['pending'];
        $rejected       = $summary['rejected'];

        return view('admin.withdraw.withdrawals', compact('pageTitle', 'withdrawals', 'successful', 'pending', 'rejected'));
    }

    protected function withdrawalData($scope = null, $summary = false, $userId = null) {
        if (Schema::hasTable('currencies') && Schema::hasTable('countries')) {
            view()->share('reportCurrencies', Currency::orderBy('code')->get());
            view()->share('reportCountries', Country::orderBy('name')->get());
        }
        if ($scope) {
            $withdrawals = Withdrawal::$scope();
        } else {
            $withdrawals = Withdrawal::where('status', '!=', Status::PAYMENT_INITIATE);
        }

        if ($userId) {
            $withdrawals = $withdrawals->where('user_id', $userId);
        }

        $withdrawals = $withdrawals->searchable(['trx', 'user:username'])->dateFilter();

        $request = request();
        if (Schema::hasColumn('withdrawals', 'currency_id') && $request->filled('currency_id')) $withdrawals->where('currency_id', $request->integer('currency_id'));
        if ($request->filled('country_id')) $withdrawals->whereHas('user', fn ($query) => $query->where('country_id', $request->integer('country_id')));
        if ($request->method) {
            $withdrawals = $withdrawals->where('method_id', $request->method);
        }
        if (!$summary) {
            return $withdrawals->with(['user', 'method'])->orderBy('id', 'desc')->paginate(getPaginate());
        } else {

            $successful = clone $withdrawals;
            $pending    = clone $withdrawals;
            $rejected   = clone $withdrawals;

            $successfulSummary = $successful->where('status', Status::PAYMENT_SUCCESS)->sum('amount');
            $pendingSummary    = $pending->where('status', Status::PAYMENT_PENDING)->sum('amount');
            $rejectedSummary   = $rejected->where('status', Status::PAYMENT_REJECT)->sum('amount');

            return [
                'data'    => $withdrawals->with(['user', 'method'])->orderBy('id', 'desc')->paginate(getPaginate()),
                'summary' => [
                    'successful' => $successfulSummary,
                    'pending'    => $pendingSummary,
                    'rejected'   => $rejectedSummary,
                ],
            ];
        }
    }

    public function details($id) {
        $withdrawal = Withdrawal::where('id', $id)->where('status', '!=', Status::PAYMENT_INITIATE)->with(['user', 'method'])->firstOrFail();
        $pageTitle  = 'Withdrawal Details';
        $details    = $withdrawal->withdraw_information ? json_encode($withdrawal->withdraw_information) : null;

        return view('admin.withdraw.detail', compact('pageTitle', 'withdrawal', 'details'));
    }

    public function approve(Request $request) {
        $request->validate(['id' => 'required|integer']);
        $withdraw = DB::transaction(function () use ($request) {
            $locked = Withdrawal::whereKey($request->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->status === Status::PAYMENT_PENDING, 409, 'This withdrawal has already been processed.');
            $locked->status = Status::PAYMENT_SUCCESS;
            $locked->admin_feedback = $request->details;
            $locked->save();
            return $locked->load(['user', 'method']);
        });

        notify($withdraw->user, 'WITHDRAW_APPROVE', [
            'method_name'     => $withdraw->method->name,
            'method_currency' => $withdraw->currency,
            'method_amount'   => showAmount($withdraw->final_amount, currencyFormat: false),
            'amount'          => showAmount($withdraw->amount, currencyFormat: false),
            'charge'          => showAmount($withdraw->charge, currencyFormat: false),
            'rate'            => showAmount($withdraw->rate, currencyFormat: false),
            'trx'             => $withdraw->trx,
            'admin_details'   => $request->details,
        ]);

        $notify[] = ['success', 'Withdrawal approved successfully'];
        return to_route('admin.withdraw.data.pending')->withNotify($notify);
    }

    public function reject(Request $request) {
        $request->validate(['id' => 'required|integer']);
        [$withdraw, $user] = DB::transaction(function () use ($request) {
            $locked = Withdrawal::whereKey($request->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->status === Status::PAYMENT_PENDING, 409, 'This withdrawal has already been processed.');
            $user = \App\Models\User::whereKey($locked->user_id)->lockForUpdate()->firstOrFail();
            if ($user->currency_id && $locked->currency_id && (int) $user->currency_id !== (int) $locked->currency_id) abort(422, 'Withdrawal currency no longer matches the wallet currency.');
            $locked->status = Status::PAYMENT_REJECT;
            $locked->admin_feedback = $request->details;
            $locked->save();
            $user->balance += $locked->amount;
            $user->save();
            $transaction = new Transaction();
            $transaction->user_id = $locked->user_id;
            $transaction->amount = $locked->amount;
            $transaction->post_balance = $user->balance;
            $transaction->charge = 0;
            $transaction->trx_type = '+';
            $transaction->remark = 'withdraw_reject';
            $transaction->details = 'Refunded for withdrawal rejection';
            $transaction->trx = $locked->trx;
            if (Schema::hasColumn('transactions', 'currency_id')) $transaction->currency_id = $user->currency_id;
            $transaction->save();
            return [$locked->load('method'), $user];
        });

        notify($user, 'WITHDRAW_REJECT', [
            'method_name'     => $withdraw->method->name,
            'method_currency' => $withdraw->currency,
            'method_amount'   => showAmount($withdraw->final_amount, currencyFormat: false),
            'amount'          => showAmount($withdraw->amount, currencyFormat: false),
            'charge'          => showAmount($withdraw->charge, currencyFormat: false),
            'rate'            => showAmount($withdraw->rate, currencyFormat: false),
            'trx'             => $withdraw->trx,
            'post_balance'    => showAmount($user->balance, currencyFormat: false),
            'admin_details'   => $request->details,
        ]);

        $notify[] = ['success', 'Withdrawal rejected successfully'];
        return to_route('admin.withdraw.data.pending')->withNotify($notify);
    }

}
