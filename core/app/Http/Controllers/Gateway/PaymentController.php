<?php

namespace App\Http\Controllers\Gateway;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Lib\FormProcessor;
use App\Models\AdminNotification;
use App\Models\Deposit;
use App\Models\GatewayCurrency;
use App\Models\Transaction;
use App\Models\User;
use App\Models\SupportAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller {
    public function deposit() {
        $gatewayCurrency = GatewayCurrency::whereHas('method', function ($gate) {
            $gate->where('status', Status::ENABLE);
        })->where(function ($query) {
            $query->whereNull('support_agent_id')
                ->orWhereHas('supportAgent', fn ($agent) => $agent->paymentAgents());
        })->with(['method', 'supportAgent:id,name'])->orderby('name')->get();
        $deposits = auth()->user()->deposits()->where('status', '!=', Status::PAYMENT_INITIATE)
            ->with('gateway')->latest('id')->paginate(5, ['*'], 'deposit_page');
        $pageTitle = 'Deposit Methods';
        return view('Template::user.payment.deposit', compact('gatewayCurrency', 'deposits', 'pageTitle'));
    }

    public function depositInsert(Request $request) {
        $request->validate([
            'amount'   => 'required|numeric|gt:0',
            'gateway'  => 'required',
            'currency' => 'required',
        ]);

        $user = auth()->user();
        $gate = GatewayCurrency::whereHas('method', function ($gate) {
            $gate->where('status', Status::ENABLE);
        })->where(function ($query) {
            $query->whereNull('support_agent_id')
                ->orWhereHas('supportAgent', fn ($agent) => $agent->paymentAgents());
        })->where('method_code', $request->gateway)->where('currency', $request->currency)->first();
        if (!$gate) {
            $notify[] = ['error', 'Invalid gateway'];
            return back()->withNotify($notify);
        }

        if ($gate->min_amount > $request->amount || $gate->max_amount < $request->amount) {
            $notify[] = ['error', 'Please follow deposit limit'];
            return back()->withNotify($notify);
        }

        $charge      = $gate->fixed_charge + ($request->amount * $gate->percent_charge / 100);
        $payable     = $request->amount + $charge;
        $finalAmount = $payable * $gate->rate;

        $data                  = new Deposit();
        $data->user_id         = $user->id;
        $data->method_code     = $gate->method_code;
        if ($gate->support_agent_id) {
            $agent = SupportAgent::paymentAgents()->findOrFail($gate->support_agent_id);
            $data->payment_agent_snapshot = [
                'id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'photo' => $agent->photo,
                'payment_details' => $agent->payment_details,
            ];
        }
        $data->method_currency = strtoupper($gate->currency);
        $data->amount          = $request->amount;
        $data->charge          = $charge;
        $data->rate            = $gate->rate;
        $data->final_amount    = $finalAmount;
        $data->btc_amount      = 0;
        $data->btc_wallet      = "";
        $data->trx             = getTrx();
        $data->success_url = route('user.deposit.history');
        $data->failed_url = route('user.deposit.history');
        $data->save();
        session()->put('Track', $data->trx);
        return to_route('user.deposit.confirm');
    }

    public function appDepositConfirm($hash) {
        try {
            $id = decrypt($hash);
        } catch (\Exception $ex) {
            abort(404);
        }
        $data = Deposit::where('id', $id)->where('status', Status::PAYMENT_INITIATE)->orderBy('id', 'DESC')->firstOrFail();
        $user = User::findOrFail($data->user_id);
        auth()->login($user);
        session()->put('Track', $data->trx);
        return to_route('user.deposit.confirm');
    }

    public function depositConfirm() {
        $track   = session()->get('Track');
        $deposit = Deposit::where('trx', $track)->where('status', Status::PAYMENT_INITIATE)->orderBy('id', 'DESC')->with('gateway')->firstOrFail();

        if ($deposit->method_code >= 1000) {
            return to_route('user.deposit.manual.confirm');
        }

        $dirName = $deposit->gateway->alias;
        $new     = __NAMESPACE__ . '\\' . $dirName . '\\ProcessController';

        $data = $new::process($deposit);
        $data = json_decode($data);

        if (isset($data->error)) {
            $notify[] = ['error', $data->message];
            return back()->withNotify($notify);
        }
        if (isset($data->redirect)) {
            return redirect($data->redirect_url);
        }

        // for Stripe V3
        if (@$data->session) {
            $deposit->btc_wallet = $data->session->id;
            $deposit->save();
        }

        $pageTitle = 'Payment Confirm';
        return view("Template::$data->view", compact('data', 'pageTitle', 'deposit'));
    }

    public static function userDataUpdate($deposit, $isManual = null) {
        $result = DB::transaction(function () use ($deposit, $isManual) {
            $lockedDeposit = Deposit::whereKey($deposit->id)->lockForUpdate()->first();
            if (!$lockedDeposit || !in_array((int) $lockedDeposit->status, [Status::PAYMENT_INITIATE, Status::PAYMENT_PENDING], true)) {
                return null;
            }

            $user = User::whereKey($lockedDeposit->user_id)->lockForUpdate()->firstOrFail();
            $methodName = $lockedDeposit->methodName();
            $user->balance += $lockedDeposit->amount;
            $user->save();

            $lockedDeposit->status = Status::PAYMENT_SUCCESS;
            $lockedDeposit->save();

            $transaction = new Transaction();
            $transaction->user_id = $user->id;
            $transaction->amount = $lockedDeposit->amount;
            $transaction->post_balance = $user->balance;
            $transaction->charge = $lockedDeposit->charge;
            $transaction->trx_type = '+';
            $transaction->details = 'Deposit Via ' . $methodName;
            $transaction->trx = $lockedDeposit->trx;
            $transaction->remark = 'deposit';
            $transaction->save();

            if (!$isManual) {
                $adminNotification = new AdminNotification();
                $adminNotification->user_id = $user->id;
                $adminNotification->title = 'Deposit successful via ' . $methodName;
                $adminNotification->click_url = urlPath('admin.deposit.successful');
                $adminNotification->save();
            }

            $general = gs();
            if ($general->dc) {
                levelCommission($user->id, $lockedDeposit->amount, 'Deposit Commission');
            }

            return ['user' => $user, 'deposit' => $lockedDeposit, 'method_name' => $methodName];
        });

        if (!$result) {
            return;
        }

        $user = $result['user'];
        $approvedDeposit = $result['deposit'];
        notify($user, $isManual ? 'DEPOSIT_APPROVE' : 'DEPOSIT_COMPLETE', [
            'method_name'     => $result['method_name'],
            'method_currency' => $approvedDeposit->method_currency,
            'method_amount'   => showAmount($approvedDeposit->final_amount, currencyFormat: false),
            'amount'          => showAmount($approvedDeposit->amount, currencyFormat: false),
            'charge'          => showAmount($approvedDeposit->charge, currencyFormat: false),
            'rate'            => showAmount($approvedDeposit->rate, currencyFormat: false),
            'trx'             => $approvedDeposit->trx,
            'post_balance'    => showAmount($user->balance),
        ]);
    }

    public function manualDepositConfirm() {
        $track = session()->get('Track');
        $data  = Deposit::with('gateway')->where('status', Status::PAYMENT_INITIATE)->where('trx', $track)->first();
        abort_if(!$data, 404);
        if ($data->method_code > 999) {
            $pageTitle = 'Confirm Deposit';
            $method    = $data->gatewayCurrency();
            $gateway   = $method->method;
            return view('Template::user.payment.manual', compact('data', 'pageTitle', 'method', 'gateway'));
        }
        abort(404);
    }

    public function manualDepositUpdate(Request $request) {
        $track = session()->get('Track');
        $data  = Deposit::with('gateway')->where('status', Status::PAYMENT_INITIATE)->where('trx', $track)->first();
        abort_if(!$data, 404);
        $gatewayCurrency = $data->gatewayCurrency();
        $gateway         = $gatewayCurrency->method;
        $formData        = $gateway->form?->form_data ?? [];

        $formProcessor  = new FormProcessor();
        $validationRule = $formProcessor->valueValidation($formData);
        $request->validate($validationRule);
        $userData = $formProcessor->processFormData($request, $formData);

        $pendingDeposit = DB::transaction(function () use ($data, $userData) {
            $lockedDeposit = Deposit::whereKey($data->id)->lockForUpdate()->first();
            if (!$lockedDeposit || (int) $lockedDeposit->status !== Status::PAYMENT_INITIATE) {
                return null;
            }

            $lockedDeposit->detail = $userData;
            $lockedDeposit->status = Status::PAYMENT_PENDING;
            $lockedDeposit->save();

            $adminNotification = new AdminNotification();
            $adminNotification->user_id = $lockedDeposit->user_id;
            $adminNotification->title = 'Deposit request from ' . $lockedDeposit->user->username;
            $adminNotification->click_url = urlPath('admin.deposit.details', $lockedDeposit->id);
            $adminNotification->save();

            return $lockedDeposit->load('user');
        });

        if (!$pendingDeposit) {
            $notify[] = ['error', 'This deposit request has already been submitted.'];
            return to_route('user.deposit.history')->withNotify($notify);
        }

        notify($pendingDeposit->user, 'DEPOSIT_REQUEST', [
            'method_name'     => $pendingDeposit->gatewayCurrency()->name,
            'method_currency' => $pendingDeposit->method_currency,
            'method_amount'   => showAmount($pendingDeposit->final_amount, currencyFormat: false),
            'amount'          => showAmount($pendingDeposit->amount, currencyFormat: false),
            'charge'          => showAmount($pendingDeposit->charge, currencyFormat: false),
            'rate'            => showAmount($pendingDeposit->rate, currencyFormat: false),
            'trx'             => $pendingDeposit->trx,
        ]);

        $notify[] = ['success', 'You have deposit request has been taken'];
        return to_route('user.deposit.history')->withNotify($notify);
    }

}
