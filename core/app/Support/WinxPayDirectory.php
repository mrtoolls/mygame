<?php

namespace App\Support;

use App\Models\CoinRate;
use App\Models\CoinVendor;
use App\Models\CoinWallet;
use App\Models\SupportAgent;
use App\Models\User;
use App\Models\UserPresence;
use Illuminate\Support\Facades\Schema;

class WinxPayDirectory
{
    public function forBuyer(?User $buyer): array
    {
        if (!Schema::hasTable('coin_vendors') || !(bool) gs('coin_mode')) return [];
        $currency = $buyer?->currency;
        $buyerUsdRate = (float) ($currency?->rate_to_usd ?? 0);
        $vendors = CoinVendor::with(['user.country', 'currency', 'application'])
            ->where('status', 'active')->where('is_selling', true)->whereNotNull('sell_rate')->get();
        $wallets = CoinWallet::where('owner_type', 'user')->whereIn('owner_id', $vendors->pluck('user_id'))->get()->keyBy('owner_id');
        $vendors = $vendors->filter(function (CoinVendor $vendor) use ($buyer, $buyerUsdRate, $wallets) {
            $user = $vendor->user;
            if (!$user || (int) $user->status !== 1 || (bool) $user->coin_vendor_flagged) return false;
            if ($buyer && (int) $user?->id === (int) $buyer->id) return false;
            if ($buyer && in_array((int) $buyer->id, array_map('intval', $vendor->linked_user_ids ?: []), true)) return false;
            if (!$buyer?->country?->coin_marketplace_enabled || !$buyer?->country?->is_allowed) return false;
            if (!$user?->country?->is_allowed || !$user?->country?->is_enabled || !$user?->country?->coin_marketplace_enabled) return false;
            if (!$user || !$user->country?->coin_marketplace_enabled || !$user->country?->vendors_allowed || !$vendor->currency?->enabled) return false;
            $wallet = $wallets->get($user->id);
            if (!$wallet || (float) $wallet->available_balance <= 0) return false;
            return $buyerUsdRate > 0 && (float) ($vendor->currency->rate_to_usd ?? 0) > 0;
        });
        $seen = [];
        try { $seen = UserPresence::whereIn('user_id', $vendors->pluck('user_id'))->where('last_seen_at', '>=', now()->subMinutes(5))->pluck('user_id')->flip()->all(); } catch (\Throwable) {}
        $vendors = $vendors->filter(fn (CoinVendor $vendor) => isset($seen[$vendor->user_id]));
        $minimum = (float) (gs('coin_min_trade_amount') ?? 10);
        $vendors = $vendors->filter(function (CoinVendor $vendor) use ($minimum, $wallets) {
            $balance = (float) $wallets->get($vendor->user_id)?->available_balance;
            return $balance >= $minimum && (float) $vendor->per_trade_limit >= $minimum && (float) $vendor->daily_limit >= $minimum;
        });

        return $vendors->map(function (CoinVendor $vendor) use ($buyer, $currency, $buyerUsdRate, $seen, $wallets) {
            $localRate = (float) $vendor->sell_rate * (float) $vendor->currency->rate_to_usd / $buyerUsdRate;
            $methods = $vendor->encrypted_payment_methods ?: [];
            return [
                'type' => 'vendor', 'id' => $vendor->id, 'user_id' => $vendor->user_id,
                'name' => $vendor->user->username, 'username' => $vendor->user->username,
                'avatar_url' => $vendor->user->avatarUrl(), 'verified' => true,
                'rate_per_coin' => $localRate, 'currency_code' => $currency->code,
                'rate_per_100' => $localRate * 100, 'rate_per_1000' => $localRate * 1000,
                'completed_trades' => $vendor->completed_trades, 'average_release_minutes' => $vendor->average_release_minutes,
                'reliability_score' => (float) $vendor->reliability_score,
                'online' => isset($seen[$vendor->user_id]), 'is_selling' => $vendor->is_selling,
                'payment_methods' => collect($methods)->pluck('type')->unique()->values()->all(),
                'available_coins' => (float) $wallets->get($vendor->user_id)?->available_balance,
                'per_trade_limit' => (float) $vendor->per_trade_limit,
            ];
        })->sortBy('rate_per_coin')->values()->all();
    }

    /** @return array<int,array<string,mixed>> */
    public function agentsForBuyer(?User $buyer): array
    {
        if (!Schema::hasTable('support_agents') || !(bool) gs('coin_mode')) return [];
        $currency = $buyer?->currency;
        $coinRate = $currency ? CoinRate::where('currency_id', $currency->id)->first() : null;
        if (!$coinRate?->buy_price) return [];
        $agents = SupportAgent::paymentAgents()->where('is_selling_coins', true)->with('country.currency')->get();
        $scores = app(AgentSpeed::class)->scoresFor($agents);
        $wallets = CoinWallet::where('owner_type', 'agent')->whereIn('owner_id', $agents->pluck('id'))->get()->keyBy('owner_id');
        return $agents->map(function (SupportAgent $agent) use ($scores, $coinRate, $currency, $wallets) {
            return [
                'type' => 'agent', 'id' => $agent->id, 'user_id' => null,
                'name' => $agent->name, 'username' => $agent->username,
                'avatar_url' => $agent->photo ? asset('assets/images/support-agents/' . $agent->photo) : null,
                'verified' => (bool) $agent->is_verified, 'rate_per_coin' => (float) $coinRate->buy_price,
                'currency_code' => $currency->code, 'rate_per_100' => (float) $coinRate->buy_price * 100,
                'rate_per_1000' => (float) $coinRate->buy_price * 1000,
                'completed_trades' => 0, 'average_release_minutes' => null,
                'reliability_score' => (int) ($scores[$agent->id] ?? 0),
                'online' => $agent->last_seen_at?->gt(now()->subMinutes(5)) ?? false,
                'is_selling' => true, 'payment_methods' => $agent->accepted_payment_types ?? [],
                'available_coins' => (float) ($wallets->get($agent->id)?->available_balance ?? 0),
                'per_trade_limit' => (float) (gs('coin_max_trade_amount') ?? 100000),
            ];
        })->values()->all();
    }
}
