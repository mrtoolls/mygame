<?php

namespace App\Services\Fantasy;

use InvalidArgumentException;

class FantasyContestTierSettings
{
    public function tiers(): array
    {
        $raw = gs('ff_budget_tiers');
        $tiers = is_array($raw) ? $raw : (is_string($raw) ? json_decode($raw, true) : null);
        $tiers = is_array($tiers) ? array_map('floatval', $tiers) : [100.0, 120.0, 150.0];
        $tiers = array_values(array_unique(array_filter($tiers, fn ($tier) => $tier > 0)));
        sort($tiers, SORT_NUMERIC);
        return $tiers ?: [100.0, 120.0, 150.0];
    }

    public function minimumFees(): array
    {
        $raw = gs('ff_tier_min_fees');
        $fees = is_array($raw) ? $raw : (is_string($raw) ? json_decode($raw, true) : null);
        $fees = is_array($fees) ? $fees : [];
        $defaults = [100 => 10, 120 => 15, 150 => 20];
        $result = [];
        foreach ($this->tiers() as $tier) {
            $key = $this->tierKey((float) $tier);
            $result[$key] = (float) ($fees[$key] ?? $defaults[$key] ?? 0);
        }
        return $result;
    }

    public function parseTiers(string $input): array
    {
        $tokens = preg_split('/[,\s]+/', trim($input), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($tokens as $token) {
            if (!preg_match('/^\d+(?:\.\d)?$/', $token)) throw new InvalidArgumentException('Budget tiers must be positive numbers with at most one decimal place.');
        }
        $tiers = array_values(array_unique(array_map('floatval', $tokens)));
        sort($tiers, SORT_NUMERIC);
        if (!$tiers || count($tiers) > 10 || min($tiers) <= 0 || max($tiers) > 999.9) {
            throw new InvalidArgumentException('Enter between 1 and 10 positive budget tiers (maximum 999.9).');
        }
        foreach ($tiers as $tier) {
            if (abs(round($tier, 1) - $tier) > 0.00001) throw new InvalidArgumentException('Budget tiers can use at most one decimal place.');
        }
        return $tiers;
    }

    public function parseMinimumFees(string $input, array $tiers): array
    {
        $fees = [];
        foreach (preg_split('/[,\s]+/', trim($input), -1, PREG_SPLIT_NO_EMPTY) as $item) {
            if (!preg_match('/^([0-9]+(?:\.[0-9])?):([0-9]+(?:\.[0-9]{1,8})?)$/', $item, $matches)) {
                throw new InvalidArgumentException('Minimum fees must use tier:fee pairs, for example 100:10, 120:15.');
            }
            $fees[$this->tierKey((float) $matches[1])] = (float) $matches[2];
        }
        $previous = -1;
        foreach ($tiers as $tier) {
            $key = $this->tierKey($tier);
            if (!array_key_exists($key, $fees) || $fees[$key] <= 0 || $fees[$key] <= $previous) {
                throw new InvalidArgumentException('Set a minimum entry fee for every tier, and make each higher tier fee larger.');
            }
            $previous = $fees[$key];
        }
        if (count($fees) !== count($tiers)) throw new InvalidArgumentException('Minimum fee tiers must match the configured budget tiers.');
        return $fees;
    }

    public function isConfiguredTier($tier): bool
    {
        foreach ($this->tiers() as $allowed) if (abs((float) $tier - $allowed) < 0.001) return true;
        return false;
    }

    public function minimumFee($tier): float
    {
        return (float) ($this->minimumFees()[$this->tierKey((float) $tier)] ?? INF);
    }

    public function tierKey(float $tier): string
    {
        return rtrim(rtrim(number_format($tier, 1, '.', ''), '0'), '.');
    }
}
