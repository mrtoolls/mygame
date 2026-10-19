<?php

namespace App\Games\TableGames;

use InvalidArgumentException;

/** Exact decimal calculations shared by table settlement and its unit tests. */
final class TableGameSettlementMath
{
    public static function payout(array $stakes, string $commissionPercent): array
    {
        if (!function_exists('bcadd')) throw new InvalidArgumentException('BC Math is required for table settlements.');
        if (bccomp($commissionPercent, '0', 2) < 0 || bccomp($commissionPercent, '100', 2) > 0) {
            throw new InvalidArgumentException('Commission must be between 0 and 100 percent.');
        }

        $pot = '0.00000000';
        foreach ($stakes as $stake) {
            if (!is_string($stake) && !is_int($stake) && !is_float($stake)) throw new InvalidArgumentException('Invalid stake amount.');
            $stake = (string) $stake;
            if (bccomp($stake, '0', 8) < 0) throw new InvalidArgumentException('Stake amounts cannot be negative.');
            $pot = bcadd($pot, $stake, 8);
        }

        $commission = bcdiv(bcmul($pot, $commissionPercent, 10), '100', 8);
        return ['pot' => $pot, 'commission' => $commission, 'winner_amount' => bcsub($pot, $commission, 8)];
    }

    public static function refunds(array $stakes): array
    {
        $refunds = [];
        foreach ($stakes as $playerId => $stake) {
            $stake = (string) $stake;
            if (!function_exists('bccomp') || bccomp($stake, '0', 8) < 0) throw new InvalidArgumentException('Invalid refund amount.');
            $refunds[$playerId] = $stake;
        }
        return $refunds;
    }
}
