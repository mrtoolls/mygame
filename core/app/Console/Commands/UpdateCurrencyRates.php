<?php

namespace App\Console\Commands;

use App\Models\Currency;
use App\Services\CoinPricing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class UpdateCurrencyRates extends Command
{
    protected $signature = 'currencies:update-rates';
    protected $description = 'Refresh enabled automatic currency reporting rates from USD';

    public function handle(): int
    {
        $response = Http::timeout(12)->get('https://open.er-api.com/v6/latest/USD');
        if (!$response->successful() || $response->json('result') !== 'success') {
            $this->error('Currency rate source did not return a valid response.');
            return self::FAILURE;
        }
        if ($response->json('base_code') !== 'USD' || !$response->json('time_last_update_unix')) {
            $this->error('Currency rate source returned an incomplete USD rate set.');
            return self::FAILURE;
        }
        $rates = $response->json('rates', []);
        $rateTimestamp = \Illuminate\Support\Carbon::createFromTimestampUTC((int) $response->json('time_last_update_unix'));
        $updated = 0;
        foreach (Currency::where('auto_update', true)->where('enabled', true)->get() as $currency) {
            $usdToCurrency = $rates[$currency->code] ?? null;
            if (!is_numeric($usdToCurrency) || (float) $usdToCurrency <= 0) continue;
            $newRate = number_format(1 / (float) $usdToCurrency, 12, '.', '');
            DB::transaction(function () use ($currency, $newRate, $rateTimestamp) {
                $locked = Currency::whereKey($currency->id)->lockForUpdate()->first();
                if (!$locked || !$locked->auto_update) return;
                $oldRate = $locked->rate_to_usd;
                $locked->rate_to_usd = $newRate;
                $locked->rate_source = 'api';
                if ($locked->last_updated_at && $locked->last_updated_at->greaterThanOrEqualTo($rateTimestamp)) return;
                $locked->last_updated_at = $rateTimestamp;
                $locked->save();
                if ((string) $oldRate !== (string) $newRate) {
                    $locked->rateLogs()->create([
                        'old_rate_to_usd' => $oldRate,
                        'new_rate_to_usd' => $newRate,
                        'source' => 'api',
                    ]);
                }
            });
            $updated++;
        }
        $this->info("Updated {$updated} automatic currency reporting rates.");

        // Keep coin pricing in step with every rate refresh - a currency whose FX rate just came
        // back from stale/missing gets re-priced automatically instead of staying paused until an
        // admin happens to click "Seed Pricing for All Currencies".
        if (\Illuminate\Support\Facades\Schema::hasTable('coin_rates')) {
            $results = app(CoinPricing::class)->recomputeAll();
            $paused = count(array_filter($results, fn ($r) => !$r['ok']));
            if ($paused) $this->warn("{$paused} currenc" . ($paused === 1 ? 'y is' : 'ies are') . " still paused for coin pricing.");
        }

        return self::SUCCESS;
    }
}
