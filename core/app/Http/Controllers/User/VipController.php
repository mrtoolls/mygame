<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CoinLedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VipRakebackClaim;
use App\Models\VipTier;
use App\Services\CoinLedger;
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
        $notify  = [];
        $general = gs();

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

            $user->rakeback_claimed_total += $claimable;
            $user->last_rakeback_claim_at  = now();

            // The claim row is saved (and its id obtained) before any balance/coin mutation so
            // its own id can be used to build a mint idempotency key below - a double-submit of
            // this request creates its own claim row with its own id, but the row lock above
            // already prevents a second request from ever reading a nonzero $claimable once the
            // first has committed, so this is a belt-and-suspenders guard against a *retry* of
            // the same already-created claim (e.g. a queued retry of this same mint call).
            $claim                          = new VipRakebackClaim();
            $claim->user_id                 = $user->id;
            $claim->vip_tier_id             = $tier->id;
            $claim->amount                  = $claimable;
            $claim->total_wagered_snapshot  = $user->totalWagered();
            $claim->trx                     = $trx;
            $claim->save();

            if ($general->coin_mode) {
                // Coin mode mints the claimed rakeback straight into the user's coin wallet via
                // CoinLedger - the only code allowed to mutate a coin balance - instead of
                // crediting $user->balance. No legacy Transaction row is created here, matching
                // the convention already established for coin-mode gateway purchases in
                // PaymentController::userDataUpdate() (coin movements are recorded in the coin
                // ledger, not the real-currency transaction log).
                app(CoinLedger::class)->mint(
                    'user',
                    $user->id,
                    number_format($claimable, 8, '.', ''),
                    'VIP rakeback claimed - ' . $tier->name . ' tier',
                    'vip_rakeback:' . $claim->id,
                    'VipRakebackClaim',
                    $claim->id,
                    'user',
                    $user->id
                );
            } else {
                $user->balance += $claimable;

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
            }

            $user->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $notify[] = ['error', 'Something went wrong, please try again'];
            return back()->withNotify($notify);
        }

        $notify[] = ['success', 'Rakeback claimed successfully'];
        return back()->withNotify($notify);
    }

    /**
     * Mints a user's current claimable-but-unclaimed rakeback straight into their coin wallet,
     * without them submitting a claim themselves. Intended to be called ONCE PER USER by the
     * coin-mode cutover tool (built separately in this session), to materialize legacy unclaimed
     * rakeback into coins at the moment of cutover, per the project decision: "Materialize
     * unclaimed rakeback into coins at the cutover".
     *
     * Reuses rakebackClaimable() so the amount is computed exactly the same way a normal claim
     * would compute it. Safe to call on a user with nothing claimable (no-ops, returns null,
     * never throws for that case). Safe to call twice for the same user: it advances
     * last_rakeback_claim_at just like a normal claim does, so a second call naturally
     * recomputes a claimable amount of 0 and no-ops before ever touching the ledger - the fixed
     * 'rakeback_cutover:<user id>' idempotency key is a backstop on top of that for the case
     * where it is somehow invoked twice for the same user without that recomputation taking
     * effect (e.g. a retried call racing the row lock).
     */
    public function materializeRakebackToCoins(User $user, string $actorType, int $actorId): ?CoinLedgerEntry
    {
        return DB::transaction(function () use ($user, $actorType, $actorId) {
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->first();
            $tier       = $lockedUser->vipTier();
            $claimable  = $lockedUser->rakebackClaimable();

            if (!$tier || $claimable <= 0) {
                return null;
            }

            $result = app(CoinLedger::class)->mint(
                'user',
                $lockedUser->id,
                number_format($claimable, 8, '.', ''),
                'VIP rakeback materialized to coins at coin-mode cutover',
                'rakeback_cutover:' . $lockedUser->id,
                'User',
                $lockedUser->id,
                $actorType,
                $actorId
            );

            $lockedUser->rakeback_claimed_total += $claimable;
            $lockedUser->last_rakeback_claim_at  = now();
            $lockedUser->save();

            $claim                          = new VipRakebackClaim();
            $claim->user_id                 = $lockedUser->id;
            $claim->vip_tier_id             = $tier->id;
            $claim->amount                  = $claimable;
            $claim->total_wagered_snapshot  = $lockedUser->totalWagered();
            $claim->trx                     = 'rakeback_cutover:' . $lockedUser->id;
            $claim->save();

            return $result['credit'];
        });
    }
}
