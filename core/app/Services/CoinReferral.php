<?php

namespace App\Services;

use App\Models\CommissionLog;
use App\Models\Referral;
use App\Models\User;

/**
 * Coin-mode mirror of the legacy levelCommission() helper (app/Http/Helpers/helpers.php) - same
 * multi-level referral walk, but mints coins to each referrer's coin wallet via CoinLedger
 * instead of mutating real-currency balance. Per decision 8, in coin mode referral commission is
 * only paid on gateway coin purchases, in coins.
 */
class CoinReferral
{
    public function __construct(private CoinLedger $ledger)
    {
    }

    public function creditOnCoinPurchase(int $buyerUserId, string $coinAmount, string $title, string $idempotencyPrefix): void
    {
        $level = Referral::count();
        $currentUserId = $buyerUserId;

        for ($i = 1; $i <= $level; $i++) {
            $current = User::find($currentUserId);
            if (!$current || !$current->ref_by) break;
            $referrer = User::find($current->ref_by);
            if (!$referrer) break;

            $commission = Referral::where('level', $i)->first();
            if (!$commission) break;

            $commissionCoins = bcdiv(bcmul($coinAmount, (string) $commission->percent, 8), '100', 0);
            if (bccomp($commissionCoins, '0', 8) > 0) {
                $this->ledger->mint(
                    'user',
                    $referrer->id,
                    $commissionCoins,
                    $i . ' level referral commission (coin purchase)',
                    $idempotencyPrefix . ':ref_level_' . $i,
                    'User',
                    $buyerUserId,
                    'system'
                );

                CommissionLog::create([
                    'user_id' => $referrer->id,
                    'who' => $buyerUserId,
                    'level' => $i . ' level Referral Commission',
                    'amount' => $commissionCoins,
                    'main_amo' => $this->ledger->wallet('user', $referrer->id)->fresh()->available_balance,
                    'title' => $title,
                    'trx' => $idempotencyPrefix,
                ]);

                notify($referrer, 'REFERRAL_COMMISSION', [
                    'amount' => $commissionCoins,
                    'post_balance' => $this->ledger->wallet('user', $referrer->id)->fresh()->available_balance,
                    'trx' => $idempotencyPrefix,
                    'level' => $i . ' level Referral Commission',
                    'currency' => 'coins',
                ]);
            }

            $currentUserId = $referrer->id;
        }
    }
}
