<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VipRakebackClaim;
use App\Models\VipTier;
use Illuminate\Support\Facades\DB;

class VipController extends Controller
{
    public function index()
    {
        $pageTitle   = 'VIP Club';
        $user        = auth()->user();
        $tiers       = VipTier::active()->orderBy('min_wagered')->get();
        $wagered     = $user->totalWagered();
        $currentTier = $user->vipTier();
        $nextTier    = $user->nextVipTier();
        $claimable   = $user->rakebackClaimable();
        return view('Template::user.vip', compact('pageTitle', 'tiers', 'wagered', 'currentTier', 'nextTier', 'claimable'));
    }

    public function claimRakeback()
    {
        $notify = [];

        DB::beginTransaction();
        try {
            // Lock the row for the duration of the transaction so two
            // concurrent claim requests from the same user can't both read
            // the same "claimable" figure and get paid twice.
            $user = User::where('id', auth()->id())->lockForUpdate()->first();

            $tier      = $user->vipTier();
            $claimable = $user->rakebackClaimable();

            if (!$tier || $claimable <= 0) {
                DB::rollBack();
                $notify[] = ['error', 'You have no rakeback available to claim right now'];
                return back()->withNotify($notify);
            }

            $trx = getTrx();

            $user->balance                 += $claimable;
            $user->rakeback_claimed_total  += $claimable;
            $user->save();

            $transaction               = new Transaction();
            $transaction->user_id      = $user->id;
            $transaction->amount       = $claimable;
            $transaction->charge       = 0;
            $transaction->trx_type     = '+';
            $transaction->details      = 'VIP rakeback claimed - ' . $tier->name . ' tier';
            $transaction->remark       = 'vip_rakeback';
            $transaction->trx          = $trx;
            $transaction->post_balance = $user->balance;
            $transaction->save();

            $claim                          = new VipRakebackClaim();
            $claim->user_id                 = $user->id;
            $claim->vip_tier_id             = $tier->id;
            $claim->amount                  = $claimable;
            $claim->total_wagered_snapshot  = $user->totalWagered();
            $claim->trx                     = $trx;
            $claim->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $notify[] = ['error', 'Something went wrong, please try again'];
            return back()->withNotify($notify);
        }

        $notify[] = ['success', 'Rakeback claimed successfully'];
        return back()->withNotify($notify);
    }
}
