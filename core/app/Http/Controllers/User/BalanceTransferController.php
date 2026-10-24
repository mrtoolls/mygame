<?php

namespace App\Http\Controllers\User;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\BalanceTransfer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BalanceTransferController extends Controller {
    public function index() {
        $pageTitle        = "Balance Transfer";
        $user             = auth()->user();
        $balanceTransfers = BalanceTransfer::where('sender_id', $user->id)->with('receiveUser')->searchable(['trx', "receiveUser:username"])->latest()->paginate(getPaginate());
        return view('Template::user.transfer.index', compact('pageTitle', 'balanceTransfers', 'user'));
    }

    public function store(Request $request) {
        $request->validate([
            'username' => 'required',
            'amount'   => 'required|numeric|gt:0',
        ]);

        if (!gs('balance_transfer')) {
            $notify[] = ['error', 'Balance transfer is not available!'];
            return back()->withNotify($notify);
        }

        $sender   = auth()->user();
        $receiver = User::where('username', $request->username)->where('status', Status::USER_ACTIVE)->first();

        if (!$receiver) {
            $notify[] = ['error', 'Receiver not found!'];
            return back()->withNotify($notify)->withInput();
        }

        if ($sender->id == $receiver->id) {
            $notify[] = ['error', 'You can\'t transfer balance to your own account!'];
            return back()->withNotify($notify)->withInput();
        }

        $fixedCharge   = gs('balance_transfer_fixed_charge');
        $percentCharge = gs('balance_transfer_percent_charge');
        $charge        = bcadd((string) $fixedCharge, bcdiv(bcmul((string) $percentCharge, (string) $request->amount, 8), '100', 8), 8);
        $finalAmount   = bcadd((string) $request->amount, $charge, 8);

        // Both rows locked, in a fixed id order, inside one transaction: this is the fix for a
        // real pre-existing double-spend race, where the sender's debit and the receiver's
        // credit were each an independent, unlocked read-modify-write - two transfers from the
        // same sender arriving close together could each read the same starting balance and
        // both succeed even with only enough for one. Locking both ids in a consistent order
        // (not sender-then-receiver) also avoids two simultaneous transfers between the same
        // pair of users deadlocking each other.
        $result = DB::transaction(function () use ($sender, $receiver, $request, $finalAmount, $charge) {
            $ids = [$sender->id, $receiver->id];
            sort($ids);
            $locked = User::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
            $lockedSender   = $locked[$sender->id];
            $lockedReceiver = $locked[$receiver->id];

            if (bccomp((string) $lockedSender->balance, $finalAmount, 8) < 0) {
                return ['error' => 'Insufficient balance to complete the transaction'];
            }

            $balanceTransfer               = new BalanceTransfer();
            $balanceTransfer->sender_id    = $lockedSender->id;
            $balanceTransfer->receiver_id  = $lockedReceiver->id;
            $balanceTransfer->amount       = $request->amount;
            $balanceTransfer->charge       = $charge;
            $balanceTransfer->final_amount = $finalAmount;
            $balanceTransfer->trx          = getTrx();
            $balanceTransfer->save();

            $lockedSender->balance = bcsub((string) $lockedSender->balance, $finalAmount, 8);
            $lockedSender->save();

            $transaction               = new Transaction();
            $transaction->user_id      = $lockedSender->id;
            $transaction->amount       = $finalAmount;
            $transaction->charge       = $charge;
            $transaction->post_balance = $lockedSender->balance;
            $transaction->trx_type     = '-';
            $transaction->trx          = $balanceTransfer->trx;
            $transaction->details      = "Balance Transfer " . showAmount($request->amount) . " to $lockedReceiver->username.";
            $transaction->remark       = "balance_transfer";
            $transaction->save();

            $lockedReceiver->balance = bcadd((string) $lockedReceiver->balance, (string) $request->amount, 8);
            $lockedReceiver->save();

            $transaction               = new Transaction();
            $transaction->user_id      = $lockedReceiver->id;
            $transaction->amount       = $request->amount;
            $transaction->charge       = 0;
            $transaction->post_balance = $lockedReceiver->balance;
            $transaction->trx_type     = '+';
            $transaction->trx          = $balanceTransfer->trx;
            $transaction->details      = "Balance Transfer received " . showAmount($request->amount) . " from $lockedSender->username.";
            $transaction->remark       = "balance_received";
            $transaction->save();

            return ['transfer' => $balanceTransfer, 'receiver' => $lockedReceiver, 'sender' => $lockedSender];
        });

        if (isset($result['error'])) {
            $notify[] = ['error', $result['error']];
            return back()->withNotify($notify)->withInput();
        }

        $balanceTransfer = $result['transfer'];
        $receiver        = $result['receiver'];
        $sender          = $result['sender'];

        notify($receiver, 'BALANCE_TRANSFER', [
            'sender'       => $sender->username,
            'sent_at'      => showDateTime($balanceTransfer->created_at),
            'amount'       => showAmount($request->amount, currencyFormat: false),
            'trx'          => $balanceTransfer->trx,
            'post_balance' => showAmount($receiver->balance, currencyFormat: false),
        ]);

        $notify[] = ['success', 'Balance transfer successful!'];
        return redirect()->route('user.transfer.index')->withNotify($notify);
    }

    public function validateUsername(Request $request) {
        $request->validate([
            'username' => 'required',
        ]);

        $user = auth()->user();

        $receivedUser = User::where('username', $request->username)->first();

        if (!$receivedUser) {
            return response()->json(['exists' => false, 'error' => 'Receiver not found']);
        }
        if ($user->id == $receivedUser->id) {
            return response()->json(['exists' => false, 'error' => 'Cannot transfer to yourself']);
        }
        return response()->json(['exists' => true]);
    }
}
