<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\CoinRate;
use App\Models\CoinRateLog;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Coins are pegged to USD (1 coin = general_settings.coin_peg_usd, default $0.01). The price a
 * user actually sees per currency is that peg converted through the existing
 * Currency::rate_to_usd pivot (the same one Table Games, Gifts and Competitions already use),
 * with an admin-set margin layered on each side: buy_price is what a user pays per coin (above
 * the peg), sell_price is what an agent pays a user per coin when buying coins off them (below
 * the peg) - the spread between the two is the platform's margin, matching the project brief.
 *
 * Two guards, both deliberately conservative: a stale FX rate blocks recompute entirely (same
 * convention GiftService already uses), and a large swing in the computed price vs. the
 * currently-live price is flagged for review rather than silently applied, unless an admin is
 * the one triggering the change (an admin editing margins/currency themselves is a deliberate
 * action, not an unattended nightly FX refresh - the guard exists for the latter).
 */
class CoinPricing
{
    public function recompute(Currency $currency, ?int $adminId = null): CoinRate
    {
        return DB::transaction(function () use ($currency, $adminId) {
            $general = gs();
            $staleHours = (int) ($general->coin_rate_stale_hours ?? 48);
            if (!$currency->rate_to_usd || bccomp((string) $currency->rate_to_usd, '0', 12) <= 0) {
                throw new InvalidArgumentException("{$currency->code}: no exchange rate is set. Refresh rates or enter a manual rate.");
            }
            if (!$currency->last_updated_at) {
                throw new InvalidArgumentException("{$currency->code}: the rate has no update time. Refresh rates now.");
            }
            if ($currency->last_updated_at->lt(now()->subHours($staleHours))) {
                throw new InvalidArgumentException("{$currency->code}: the rate is older than {$staleHours} hours. Click Refresh rates.");
            }

            $rate = CoinRate::firstOrCreate(['currency_id' => $currency->id], [
                'buy_margin_percent' => $general->coin_default_buy_margin_percent ?? 5,
                'sell_margin_percent' => $general->coin_default_sell_margin_percent ?? 2,
            ]);
            $rate = CoinRate::whereKey($rate->id)->lockForUpdate()->first();

            $peg = (string) ($general->coin_peg_usd ?? '0.01');
            $decimals = min(8, max(0, (int) $currency->decimals));
            $precision = max(12, $decimals + 4);

            $basePrice = bcdiv($peg, (string) $currency->rate_to_usd, $precision);
            $buyMargin = $rate->buy_margin_override ?? $general->coin_default_buy_margin_percent ?? 5;
            $sellMargin = $rate->sell_margin_override ?? $general->coin_default_sell_margin_percent ?? 2;
            $buyPrice = $this->roundTo(bcmul($basePrice, bcadd('1', bcdiv((string) $buyMargin, '100', $precision), $precision), $precision), $decimals);
            $sellPrice = $this->roundTo(bcmul($basePrice, bcsub('1', bcdiv((string) $sellMargin, '100', $precision), $precision), $precision), $decimals);

            $changePercent = null;
            $isLargeSwing = false;
            if ($rate->buy_price !== null && bccomp((string) $rate->buy_price, '0', 8) > 0) {
                $delta = bcsub($buyPrice, (string) $rate->buy_price, $precision);
                $changePercent = bcmul(bcdiv($delta, (string) $rate->buy_price, $precision), '100', 4);
                $guardPercent = (int) ($general->coin_rate_change_guard_percent ?? 15);
                $isLargeSwing = bccomp($this->abs($changePercent), (string) $guardPercent, 4) > 0;
            }

            $oldBuyPrice = $rate->buy_price;
            $oldSellPrice = $rate->sell_price;

            // An admin-triggered recompute (changing margins, or explicitly re-pricing a
            // currency) always applies. An automatic/unattended recompute that swings past the
            // guard is logged and flagged, but the live price is left untouched until an admin
            // looks at it.
            if ($adminId !== null || !$isLargeSwing) {
                $rate->buy_price = $buyPrice;
                $rate->sell_price = $sellPrice;
                $rate->flagged_for_review = false;
                $rate->status = 'active';
                $rate->pause_reason = null;
                $rate->last_computed_at = now();
                $rate->updated_by_admin_id = $adminId;
                $rate->save();
            } else {
                $rate->flagged_for_review = true;
                $rate->save();
            }

            CoinRateLog::create([
                'currency_id' => $currency->id,
                'old_buy_price' => $oldBuyPrice,
                'new_buy_price' => $buyPrice,
                'old_sell_price' => $oldSellPrice,
                'new_sell_price' => $sellPrice,
                'change_percent' => $changePercent,
                'changed_by_admin_id' => $adminId,
                'created_at' => now(),
            ]);

            return $rate->fresh();
        });
    }

    /**
     * Prices every enabled currency that can be priced right now, and persists a 'paused' state
     * (with the reason) for any that can't - called by the admin "Seed pricing for all
     * currencies" button, automatically when coin_mode is switched on, and after the daily
     * currency-rate refresh. Idempotent: an already-priced currency just gets re-recomputed (same
     * as always), and a currency that's already paused for the same reason is simply re-marked,
     * nothing duplicates. Never overwrites an existing buy_price/sell_price on failure - pausing
     * only touches the status/reason fields.
     */
    public function recomputeAll(?int $adminId = null): array
    {
        $results = [];
        foreach (Currency::where('enabled', true)->get() as $currency) {
            try {
                $results[$currency->code] = ['ok' => true, 'rate' => $this->recompute($currency, $adminId)];
            } catch (InvalidArgumentException $exception) {
                $this->markPaused($currency, $exception->getMessage());
                $results[$currency->code] = ['ok' => false, 'error' => $exception->getMessage()];
            }
        }
        return $results;
    }

    private function markPaused(Currency $currency, string $reason): void
    {
        $general = gs();
        $rate = CoinRate::firstOrCreate(['currency_id' => $currency->id], [
            'buy_margin_percent' => $general->coin_default_buy_margin_percent ?? 5,
            'sell_margin_percent' => $general->coin_default_sell_margin_percent ?? 2,
        ]);
        $rate->status = 'paused';
        $rate->pause_reason = $reason;
        $rate->save();
    }

    /** The current, live buy price per coin in this currency - throws if none has been computed yet. */
    public function buyPrice(Currency $currency): string
    {
        $rate = CoinRate::where('currency_id', $currency->id)->first();
        if (!$rate?->buy_price) throw new InvalidArgumentException("No coin buy price is set for {$currency->code} yet.");
        return (string) $rate->buy_price;
    }

    private function roundTo(string $value, int $decimals): string
    {
        $half = '0.' . str_repeat('0', $decimals) . '5';
        $rounded = bcadd($value, $half, max(16, $decimals + 8));
        return bcadd($rounded, '0', $decimals);
    }

    private function abs(string $value): string
    {
        return str_starts_with($value, '-') ? substr($value, 1) : $value;
    }
}
