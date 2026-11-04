<?php

namespace App\Services\Fantasy;

use InvalidArgumentException;

/** Exact decimal tiered-payout calculation shared by FantasyContestService and its unit tests. */
final class FantasyContestPayoutMath
{
    /**
     * @param int $entrantCount total ranked entrants
     * @param array $tiers ascending by top_percent: [['top_percent' => float, 'share_percent' => float], ...] -
     *   top_percent is cumulative (e.g. 10, 30, 100 means bands 1-10%, 10-30%, 30-100%), share_percent values must sum to <= 100.
     * @param string $distributablePool the pot after the fee has already been deducted
     * @return array<int,string> finishing position (1-indexed) => payout, for every position 1..entrantCount
     */
    public static function tieredPayouts(int $entrantCount, array $tiers, string $distributablePool): array
    {
        if ($entrantCount <= 0) throw new InvalidArgumentException('At least one entrant is required.');
        if (bccomp($distributablePool, '0', 8) < 0) throw new InvalidArgumentException('The distributable pool cannot be negative.');

        $payouts = [];
        for ($p = 1; $p <= $entrantCount; $p++) $payouts[$p] = '0.00000000';

        $prevCutoff = 0;
        foreach ($tiers as $tier) {
            $cutoff = (int) ceil($entrantCount * (float) $tier['top_percent'] / 100);
            $cutoff = min($entrantCount, max($cutoff, $prevCutoff));
            $from = $prevCutoff + 1;
            $to = $cutoff;
            $prevCutoff = $cutoff;
            $count = $to - $from + 1;
            if ($count <= 0) continue;

            $tierAmount = bcdiv(bcmul($distributablePool, (string) $tier['share_percent'], 10), '100', 8);
            $perPosition = bcdiv($tierAmount, (string) $count, 8);
            $paid = '0.00000000';
            for ($pos = $from; $pos <= $to; $pos++) {
                $amount = $pos === $to ? bcsub($tierAmount, $paid, 8) : $perPosition;
                $payouts[$pos] = $amount;
                $paid = bcadd($paid, $amount, 8);
            }
        }

        return $payouts;
    }

    /**
     * Averages per-position payouts across a group of tied positions (same score), so every
     * tied entrant receives an identical share - the sum across the group is preserved exactly,
     * with any fractional remainder absorbed by the last position in the group.
     *
     * @param array<int,string> $positionPayouts from tieredPayouts()
     * @param array<int,int[]> $tieGroups list of position-number groups, e.g. [[1],[2,3],[4]]
     * @return array<int,string> position => payout, after averaging within each group
     */
    public static function averageTiedPositions(array $positionPayouts, array $tieGroups): array
    {
        $result = [];
        foreach ($tieGroups as $positions) {
            $sum = '0.00000000';
            foreach ($positions as $p) $sum = bcadd($sum, $positionPayouts[$p] ?? '0', 8);
            $count = count($positions);
            $share = bcdiv($sum, (string) $count, 8);
            $paid = '0.00000000';
            foreach ($positions as $i => $p) {
                $amount = $i === $count - 1 ? bcsub($sum, $paid, 8) : $share;
                $result[$p] = $amount;
                $paid = bcadd($paid, $amount, 8);
            }
        }
        return $result;
    }
}
