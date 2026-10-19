<?php

namespace App\Support;

use InvalidArgumentException;

/** Exact decimal-string helpers for money stored as integer minor units. */
final class Money
{
    public function toMinor(string|int $amount, int $decimals): string
    {
        if ($decimals < 0 || $decimals > 3) throw new InvalidArgumentException('Currency decimals must be between zero and three.');
        $amount = trim((string) $amount);
        if (!preg_match('/^(-?)(0|[1-9]\d*)(?:\.(\d+))?$/', $amount, $parts)) {
            throw new InvalidArgumentException('Enter a plain decimal amount without separators.');
        }

        $fraction = $parts[3] ?? '';
        if (strlen($fraction) > $decimals && trim(substr($fraction, $decimals), '0') !== '') {
            throw new InvalidArgumentException('The amount has more decimal places than this currency supports.');
        }
        $fraction = substr(str_pad($fraction, $decimals, '0'), 0, $decimals);
        $digits = ltrim($parts[2] . $fraction, '0');
        $minor = $digits === '' ? '0' : $digits;
        return $parts[1] === '-' && $minor !== '0' ? '-' . $minor : $minor;
    }

    public function formatMinor(string|int $minor, int $decimals, bool $groupThousands = true): string
    {
        if ($decimals < 0 || $decimals > 3) throw new InvalidArgumentException('Currency decimals must be between zero and three.');
        $value = (string) $minor;
        if (!preg_match('/^-?\d+$/', $value)) throw new InvalidArgumentException('Minor-unit amounts must be integers.');
        $negative = str_starts_with($value, '-');
        $digits = ltrim($negative ? substr($value, 1) : $value, '0');
        $digits = $digits === '' ? '0' : $digits;

        if ($decimals > 0) {
            $digits = str_pad($digits, $decimals + 1, '0', STR_PAD_LEFT);
            $whole = substr($digits, 0, -$decimals);
            $fraction = substr($digits, -$decimals);
        } else {
            $whole = $digits;
            $fraction = '';
        }

        if ($groupThousands) $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole);
        return ($negative && $digits !== '0' ? '-' : '') . $whole . ($decimals ? '.' . $fraction : '');
    }
}
