<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\CoinMarketplaceCountryAccess;

class EnsureCountryCoinMarketplaceEnabled
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && !app(CoinMarketplaceCountryAccess::class)->enabledFor($user)) {
            abort(403, $user->country
                ? CoinMarketplaceCountryAccess::BLOCKED_MESSAGE
                : 'Coin Marketplace and cash-out are unavailable because your account has no country set.');
        }

        return $next($request);
    }
}
