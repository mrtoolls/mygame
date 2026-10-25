<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class CoinMarketplaceCountryAccess
{
    public const BLOCKED_MESSAGE = 'Coin Marketplace and cash-out are not available in your country. Gateway coin purchases follow your country and currency settings.';

    public function enabledFor(?User $user): bool
    {
        if (!$user || !Schema::hasColumn('countries', 'coin_marketplace_enabled')) return true;
        return (bool) $user->country?->coin_marketplace_enabled;
    }

    public function assertEnabled(User $user): void
    {
        if (!$this->enabledFor($user)) {
            throw new InvalidArgumentException($user->country
                ? self::BLOCKED_MESSAGE
                : 'Coin Marketplace and cash-out are unavailable because your account has no country set.');
        }
    }
}
