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
use Illuminate\Support\Facades\Schema;

class PaymentController extends Controller {
    public function deposit() {
        $user = auth()->user();
        $query = GatewayCurrency::whereHas('method', function ($gate) {
            $gate->where('status', Status::ENABLE);
        })->where(function ($query) {
            $query->whereNull('support_agent_id')
                ->orWhereHas('supportAgent', fn ($agent) => $agent->paymentAgents());
        });
        if (Schema::hasColumn('gateway_currencies', 'currency')) {
            $walletCode = $user->currency?->code;
            // Fiat automatic gateways need a row in the player's wallet currency. Automatic
            // crypto gateways charge in the selected crypto asset, so their currency must not
            // be compared with the player's fiat wallet currency. Manual gateways likewise
            // use their own currency label.
            if ($walletCode) $query->where(fn ($q) => $q
                ->where('method_code', '>=', 1000)
                ->orWhereHas('method', fn ($method) => $method->where('crypto', 1))
                ->orWhereRaw('UPPER(currency) = ?', [strtoupper($walletCode)]));
        }
        $gatewayCurrency = $query->with(['method', 'supportAgent:id,name'])->orderby('name')->get()
            ->filter(fn ($gateway) => app(\App\Support\CountryPaymentLimits::class)->allowsMethod($user, [$gateway->currency, $gateway->method_code, $gateway->method?->alias, $gateway->name]))
            ->values();
        $deposits = auth()->user()->deposits()->where('status', '!=', Status::PAYMENT_INITIATE)
            ->with('gateway')->latest('id')->paginate(5, ['*'], 'deposit_page');

        // Display-only: show the user, before they pay, exactly how many coins they'd receive and
        // at what locked rate - the same app(CoinPricing::class)->buyPrice($currency) call
        // PaymentController::userDataUpdate() makes when it actually mints, so this preview can
        // never disagree with what gets minted. buyPrice() throws when no coin price has been
        // computed yet for the user's currency - caught here so a missing price shows a clear
        // message instead of a 500 page; userDataUpdate() itself is untouched and still throws if
        // reached without a price (which this message is meant to prevent the user from doing).
        $coinMode = (bool) gs('coin_mode');
        $coinBuyPrice = null;
        $coinPricingError = null;
        $coinPegUsd = null;
        if ($coinMode) {
            $coinPegUsd = (string) (gs('coin_peg_usd') ?: '0.01');
            $currency = auth()->user()->currency;
            if (!$currency) {
                $coinPricingError = "A wallet currency is required to price coin purchases.";
            } else {
                try {
                    $coinBuyPrice = app(\App\Services\CoinPricing::class)->buyPrice($currency);
                } catch (\InvalidArgumentException $exception) {
                    // Never a dead end: the exact local price isn't ready, but the USD peg always
                    // is - show that plus the reason instead of leaving the page with just an
                    // error and no numbers at all. The actual purchase stays disabled until an
                    // admin prices this currency (Coin Settings > Seed Pricing for All Currencies).
                    $coinPricingError = "Coin pricing isn't set up for {$currency->code} yet - purchases are paused for this currency until an admin sets a price.";
                }
            }
        }

        $pageTitle = $coinMode ? 'Buy Coins' : 'Deposit Methods';
        $vendorOffers = $coinMode ? app(\App\Support\WinxPayDirectory::class)->forBuyer($user) : [];
        return view('Template::user.payment.deposit', compact('gatewayCurrency', 'deposits', 'pageTitle', 'coinMode', 'coinBuyPrice', 'coinPricingError', 'coinPegUsd', 'vendorOffers'));
    }

    public function depositInsert(Request $request) {
        $request->validate([
            'amount'   => 'required|numeric|gt:0',
            'gateway'  => 'required',
            'currency' => 'required',
        ]);

        $user = auth()->user();
        app(\App\Support\CountryPaymentLimits::class)->assert($user, 'deposit', $request->input('amount'));
        $query = GatewayCurrency::whereHas('method', function ($gate) {
            $gate->where('status', Status::ENABLE);
        })->where(function ($query) {
            $query->whereNull('support_agent_id')
                ->orWhereHas('supportAgent', fn ($agent) => $agent->paymentAgents());
        })->where('method_code', $request->gateway)->where('currency', $request->currency);
        $gate = $query->with('method')->first();
        if (!$gate) {
            if ($request->wantsJson()) return response()->json(['message' => 'Invalid gateway'], 422);
            $notify[] = ['error', 'Invalid gateway'];
            return back()->withNotify($notify);
        }
        $walletCode = $user->currency?->code;
        if ($walletCode && (int) $request->gateway < 1000 && !(bool) $gate->method?->crypto
            && strtoupper($walletCode) !== strtoupper((string) $request->currency)) {
            if ($request->wantsJson()) return response()->json(['message' => 'Choose a deposit method in your wallet currency.'], 422);
            return back()->withErrors(['currency' => 'Choose a deposit method in your wallet currency.'])->withInput();
        }
        app(\App\Support\CountryPaymentLimits::class)->assertMethodAllowed($user, [$gate->currency, $gate->method_code, $gate->method?->alias, $gate->name]);

        if ($gate->min_amount > $request->amount || $gate->max_amount < $request->amount) {
            if ($request->wantsJson()) return response()->json(['message' => 'Please follow deposit limit'], 422);
            $notify[] = ['error', 'Please follow deposit limit'];
            return back()->withNotify($notify);
        }

        $charge      = $gate->fixed_charge + ($request->amount * $gate->percent_charge / 100);
        $payable     = $request->amount + $charge;
        $finalAmount = $payable * $gate->rate;

        $data                  = new Deposit();
        $data->user_id         = $user->id;
        if (Schema::hasColumn('deposits', 'currency_id')) $data->currency_id = $user->currency_id;
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
            if (Schema::hasColumn('deposits', 'support_agent_id')) $data->support_agent_id = $agent->id;
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

        if ($request->wantsJson()) {
            return response()->json([
                'ok'           => true,
                'manual'       => $gate->method_code >= 1000,
                'crypto'       => (bool) $gate->method?->crypto,
                'trx'          => $data->trx,
                'confirm_url'  => route('user.deposit.confirm'),
            ]);
        }

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

    public static function userDataUpdate($deposit, $isManual = null, $confirmedVia = null, $adminFeedback = null) {
        $general = gs();

        // Coin mode mints coins at a locked rate instead of crediting real-currency balance - and
        // never mints off a human agent's manual confirmation, only an automatic gateway or an
        // admin's own manual approval. An agent confirming "I received the payment" is attesting
        // to a real-world event the platform cannot verify itself; letting that attestation alone
        // mint coins would make every payment agent an unsupervised money printer.
        if ($general->coin_mode && $confirmedVia === 'agent') {
            throw new \RuntimeException('Agent-confirmed deposits cannot mint coins while coin mode is on. Use an automatic gateway, or have an admin review and approve this deposit directly.');
        }

        $result = DB::transaction(function () use ($deposit, $isManual, $confirmedVia, $adminFeedback, $general) {
            $lockedDeposit = Deposit::whereKey($deposit->id)->lockForUpdate()->first();
            if (!$lockedDeposit || !in_array((int) $lockedDeposit->status, [Status::PAYMENT_INITIATE, Status::PAYMENT_PENDING], true)) {
                return null;
            }

            $user = User::whereKey($lockedDeposit->user_id)->lockForUpdate()->firstOrFail();
            if ($user->currency_id && $lockedDeposit->currency_id && (int) $user->currency_id !== (int) $lockedDeposit->currency_id) {
                throw new \RuntimeException('Deposit currency no longer matches the user wallet currency.');
            }
            $methodName = $lockedDeposit->methodName();

            if ($confirmedVia && Schema::hasColumn('deposits', 'confirmed_via')) {
                $lockedDeposit->confirmed_via = $confirmedVia;
            }
            if ($adminFeedback) {
                $lockedDeposit->admin_feedback = $adminFeedback;
            }
            $lockedDeposit->status = Status::PAYMENT_SUCCESS;

            if ($general->coin_mode) {
                $currency = $user->currency_id ? \App\Models\Currency::find($user->currency_id) : null;
                if (!$currency) throw new \RuntimeException('A wallet currency is required to price a coin purchase.');

                $pricing = app(\App\Services\CoinPricing::class);
                $buyPrice = $pricing->buyPrice($currency);
                $coinAmount = bcdiv((string) $lockedDeposit->amount, $buyPrice, 0);
                if (bccomp($coinAmount, '0', 8) <= 0) {
                    throw new \RuntimeException('This deposit amount is too small to purchase a whole coin at the current rate.');
                }

                $lockedDeposit->coin_amount = $coinAmount;
                $lockedDeposit->coin_buy_price_snapshot = $buyPrice;
                $lockedDeposit->coin_rate_id = \App\Models\CoinRate::where('currency_id', $currency->id)->value('id');
                $lockedDeposit->coin_mode_purchase = true;
                $lockedDeposit->save();

                app(\App\Services\CoinLedger::class)->mint(
                    'user',
                    $user->id,
                    $coinAmount,
                    'Coin purchase via ' . $methodName,
                    'deposit_mint:' . $lockedDeposit->id,
                    'Deposit',
                    $lockedDeposit->id,
                    $isManual ? 'admin' : 'system'
                );

                if ($general->dc) {
                    app(\App\Services\CoinReferral::class)->creditOnCoinPurchase($user->id, $coinAmount, 'Deposit Commission', 'deposit_mint:' . $lockedDeposit->id);
                }
            } else {
                $user->balance += $lockedDeposit->amount;
                $user->save();
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
                if (Schema::hasColumn('transactions', 'currency_id')) $transaction->currency_id = $user->currency_id;
                $transaction->save();

                if ($general->dc) {
                    levelCommission($user->id, $lockedDeposit->amount, 'Deposit Commission');
                }
            }

            if (!$isManual) {
                $adminNotification = new AdminNotification();
                $adminNotification->user_id = $user->id;
                $adminNotification->title = 'Deposit successful via ' . $methodName;
                $adminNotification->click_url = urlPath('admin.deposit.successful');
                $adminNotification->save();
            }

            return ['user' => $user->fresh(), 'deposit' => $lockedDeposit, 'method_name' => $methodName];
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
            // $user->balance is frozen once coin_mode is on - the deposit may have just minted
            // coins into the CoinLedger wallet instead, which is what "your balance is now X"
            // actually needs to reflect.
            'post_balance'    => $general->coin_mode ? coinAmountText(app(\App\Services\CoinLedger::class)->wallet('user', $user->id)->available_balance) : showAmount($user->balance),
            'admin_feedback'  => $adminFeedback ?? '',
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
            if ($gateway->crypto == Status::ENABLE) {
                return view('Template::user.payment.crypto_confirm', compact('data', 'pageTitle', 'method', 'gateway'));
            }
            return view('Template::user.payment.manual', compact('data', 'pageTitle', 'method', 'gateway'));
        }
        abort(404);
    }

    public function confirmationSubmit(Request $request) {
        $track   = session()->get('Track');
        $deposit = Deposit::with(['gateway', 'user'])->where('status', Status::PAYMENT_INITIATE)->where('trx', $track)->where('user_id', auth()->id())->first();
        abort_if(!$deposit, 404);
        abort_unless($deposit->gateway && $deposit->gateway->crypto == Status::ENABLE, 404);

        $request->validate([
            'payment_proof'        => 'required|image|mimes:jpg,jpeg,png|max:5120',
            'payer_wallet_address' => 'required|string|max:255',
        ]);

        $directory = date('Y') . '/' . date('m') . '/' . date('d');
        $proof     = $directory . '/' . fileUploader($request->file('payment_proof'), getFilePath('verify') . '/' . $directory);
        $sla       = (int) (gs('payment_confirmation_sla_minutes') ?: 15);

        $pendingDeposit = DB::transaction(function () use ($deposit, $request, $proof, $sla) {
            $lockedDeposit = Deposit::whereKey($deposit->id)->lockForUpdate()->first();
            if (!$lockedDeposit || (int) $lockedDeposit->status !== Status::PAYMENT_INITIATE) {
                return null;
            }

            $lockedDeposit->payment_proof            = $proof;
            $lockedDeposit->payer_wallet_address      = $request->payer_wallet_address;
            $lockedDeposit->confirmation_started_at   = now();
            $lockedDeposit->confirmation_sla_minutes  = $sla;
            $lockedDeposit->confirmation_deadline_at  = now()->addMinutes($sla);
            $lockedDeposit->status                    = Status::PAYMENT_PENDING;
            $lockedDeposit->save();

            $adminNotification             = new AdminNotification();
            $adminNotification->user_id    = $lockedDeposit->user_id;
            $adminNotification->title      = 'Crypto deposit awaiting confirmation from ' . $lockedDeposit->user->username;
            $adminNotification->click_url  = urlPath('admin.deposit.details', $lockedDeposit->id);
            $adminNotification->save();

            return $lockedDeposit->load('user');
        });

        if (!$pendingDeposit) {
            @unlink(getFilePath('verify') . '/' . $proof);
            if ($request->wantsJson()) return response()->json(['message' => 'This deposit request has already been submitted.'], 422);
            $notify[] = ['error', 'This deposit request has already been submitted.'];
            return to_route('user.deposit.history')->withNotify($notify);
        }

        $this->notifySubmission($pendingDeposit, $pendingDeposit->gatewayCurrency()?->support_agent_id, 'Crypto deposit');

        notify($pendingDeposit->user, 'DEPOSIT_REQUEST', [
            'method_name'     => $pendingDeposit->gatewayCurrency()->name,
            'method_currency' => $pendingDeposit->method_currency,
            'method_amount'   => showAmount($pendingDeposit->final_amount, currencyFormat: false),
            'amount'          => showAmount($pendingDeposit->amount, currencyFormat: false),
            'charge'          => showAmount($pendingDeposit->charge, currencyFormat: false),
            'rate'            => showAmount($pendingDeposit->rate, currencyFormat: false),
            'trx'             => $pendingDeposit->trx,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'ok'       => true,
                'status'   => 'pending',
                'deadline' => $pendingDeposit->confirmation_deadline_at?->timestamp * 1000,
                'sla_minutes' => $pendingDeposit->confirmation_sla_minutes,
            ]);
        }

        return to_route('user.deposit.confirmation.pending');
    }

    public function confirmationPending() {
        $track   = session()->get('Track');
        $deposit = Deposit::with('gateway')->where('trx', $track)->where('user_id', auth()->id())->first();
        abort_if(!$deposit || !$deposit->confirmation_deadline_at, 404);
        $pageTitle = 'Confirming Deposit';
        return view('Template::user.payment.crypto_pending', compact('deposit', 'pageTitle'));
    }

    public function confirmationStatus() {
        $track   = session()->get('Track');
        $deposit = Deposit::where('trx', $track)->where('user_id', auth()->id())->first();
        if (!$deposit) {
            return response()->json(['status' => 'unknown']);
        }
        $status = match ((int) $deposit->status) {
            Status::PAYMENT_SUCCESS => 'success',
            Status::PAYMENT_REJECT => 'rejected',
            default => 'pending',
        };
        return response()->json([
            'status'    => $status,
            'disputed'  => (bool) $deposit->disputed_at,
            'deadline'  => $deposit->confirmation_deadline_at?->timestamp * 1000,
            'history_url' => route('user.deposit.history'),
        ]);
    }

    public function confirmationDispute(Request $request) {
        $track   = session()->get('Track');
        $deposit = Deposit::where('trx', $track)->where('user_id', auth()->id())->where('status', Status::PAYMENT_PENDING)->first();
        abort_if(!$deposit, 404);

        $request->validate(['reason' => 'nullable|string|max:500']);

        $deposit->disputed_at    = now();
        $deposit->dispute_reason = $request->reason;
        $deposit->save();

        $adminEmails = \App\Models\Admin::pluck('email')->filter()->all();
        if ($adminEmails) {
            try {
                \Illuminate\Support\Facades\Mail::raw(
                    "A deposit dispute was raised by {$deposit->user?->username} on trx {$deposit->trx}.\n\nReason: " . ($request->reason ?: 'No reason given') . "\n\nReview it in the admin panel.",
                    function ($mail) use ($adminEmails) {
                        $mail->to($adminEmails)->subject('Deposit disputed - needs review');
                    }
                );
            } catch (\Throwable $exception) {
                \Illuminate\Support\Facades\Log::warning('Admin dispute email notification failed.', ['deposit_id' => $deposit->id, 'error' => $exception->getMessage()]);
            }
        }

        if ($deposit->support_agent_id) {
            $agent = \App\Models\SupportAgent::find($deposit->support_agent_id);
            if ($agent) {
                \App\Models\AgentNotification::create([
                    'recipient_type' => 'agent',
                    'recipient_id'   => $agent->id,
                    'deposit_id'     => $deposit->id,
                    'title'          => 'Deposit disputed - needs review',
                    'body'           => $deposit->user?->username . ' · ' . showAmount($deposit->amount, currencyFormat: false) . ' ' . $deposit->method_currency,
                    'click_url'      => route('support-agent.payments.show', $deposit->id),
                ]);
                if ($agent->email) {
                    try {
                        \Illuminate\Support\Facades\Mail::raw(
                            "A deposit dispute was raised by {$deposit->user?->username} on trx {$deposit->trx}.\n\nReason: " . ($request->reason ?: 'No reason given') . "\n\nReview it in your Winxpot Care payments queue.",
                            function ($mail) use ($agent) {
                                $mail->to($agent->email)->subject('Deposit disputed - needs review');
                            }
                        );
                    } catch (\Throwable $exception) {
                        \Illuminate\Support\Facades\Log::warning('Agent dispute email notification failed.', ['deposit_id' => $deposit->id, 'error' => $exception->getMessage()]);
                    }
                }
            }
        }

        return response()->json(['ok' => true]);
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

        $sla = (int) (gs('payment_confirmation_sla_minutes') ?: 15);

        $pendingDeposit = DB::transaction(function () use ($data, $userData, $gatewayCurrency, $sla) {
            $lockedDeposit = Deposit::whereKey($data->id)->lockForUpdate()->first();
            if (!$lockedDeposit || (int) $lockedDeposit->status !== Status::PAYMENT_INITIATE) {
                return null;
            }

            $lockedDeposit->detail = $userData;
            $lockedDeposit->status = Status::PAYMENT_PENDING;
            if ($gatewayCurrency->support_agent_id && Schema::hasColumn('deposits', 'confirmation_deadline_at')) {
                $lockedDeposit->confirmation_started_at  = now();
                $lockedDeposit->confirmation_sla_minutes = $sla;
                $lockedDeposit->confirmation_deadline_at = now()->addMinutes($sla);
            }
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

        $this->notifySubmission($pendingDeposit, $gatewayCurrency->support_agent_id, 'Deposit');

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
        if ($gatewayCurrency->support_agent_id) {
            return to_route('user.deposit.confirmation.pending');
        }
        return to_route('user.deposit.history')->withNotify($notify);
    }

    private function notifySubmission($deposit, $supportAgentId = null, $label = 'Deposit') {
        $adminEmails = \App\Models\Admin::pluck('email')->filter()->all();
        if ($adminEmails) {
            try {
                \Illuminate\Support\Facades\Mail::raw(
                    "A {$label} from {$deposit->user->username} is awaiting confirmation.\n\nAmount: " . showAmount($deposit->amount, currencyFormat: false) . " {$deposit->method_currency}\nTrx: {$deposit->trx}\n\nReview it in the admin panel.",
                    function ($mail) use ($adminEmails, $label) {
                        $mail->to($adminEmails)->subject("{$label} awaiting confirmation");
                    }
                );
            } catch (\Throwable $exception) {
                \Illuminate\Support\Facades\Log::warning('Admin deposit email notification failed.', ['deposit_id' => $deposit->id, 'error' => $exception->getMessage()]);
            }
        }

        if (!$supportAgentId) {
            return;
        }

        $agent = \App\Models\SupportAgent::find($supportAgentId);
        if (!$agent) {
            return;
        }

        \App\Models\AgentNotification::create([
            'recipient_type' => 'agent',
            'recipient_id'   => $agent->id,
            'deposit_id'     => $deposit->id,
            'title'          => 'New deposit awaiting your confirmation',
            'body'           => $deposit->user->username . ' · ' . showAmount($deposit->amount, currencyFormat: false) . ' ' . $deposit->method_currency,
            'click_url'      => route('support-agent.payments.show', $deposit->id),
        ]);

        if ($agent->email) {
            try {
                \Illuminate\Support\Facades\Mail::raw(
                    "A new deposit from {$deposit->user->username} is waiting for your confirmation.\n\nAmount: " . showAmount($deposit->amount, currencyFormat: false) . " {$deposit->method_currency}\nTrx: {$deposit->trx}\n\nReview it in your Winxpot Care payments queue.",
                    function ($mail) use ($agent) {
                        $mail->to($agent->email)->subject('New deposit awaiting your confirmation');
                    }
                );
            } catch (\Throwable $exception) {
                \Illuminate\Support\Facades\Log::warning('Agent deposit email notification failed.', ['deposit_id' => $deposit->id, 'agent_id' => $agent->id, 'error' => $exception->getMessage()]);
            }
        }
    }

}
