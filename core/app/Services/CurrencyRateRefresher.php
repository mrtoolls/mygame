<?php

namespace App\Services;

use App\Models\Currency;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CurrencyRateRefresher
{
    public function refresh(?int $adminId = null): array
    {
        $settings = gs();
        $key = null;
        try {
            if ($settings->currency_rate_api_key) $key = Crypt::decryptString($settings->currency_rate_api_key);
        } catch (\Throwable) {
            $key = null;
        }

        $error = null;
        $payload = null;
        $source = 'free';
        if ($key) {
            try {
                $payload = $this->fetch("https://v6.exchangerate-api.com/v6/{$key}/latest/USD", true);
                $source = 'keyed';
            } catch (\Throwable $exception) {
                $error = 'Keyed ExchangeRate-API request failed: ' . $exception->getMessage();
            }
        }
        if (!$payload) {
            try {
                $payload = $this->fetch('https://open.er-api.com/v6/latest/USD', false);
                $source = 'free';
                if ($error) $error .= ' Free endpoint fallback succeeded.';
            } catch (\Throwable $exception) {
                $error = trim(($error ? $error . ' ' : '') . 'Free ExchangeRate-API request failed: ' . $exception->getMessage());
                $settings->currency_rates_last_error = mb_substr($error, 0, 1000);
                $settings->save();
                throw new RuntimeException($error, previous: $exception);
            }
        }

        $rates = $payload['rates'];
        $sourceTime = $payload['updated_at'];
        $updated = 0;
        foreach (Currency::where('auto_update', true)->where('enabled', true)->get() as $currency) {
            $usdToLocal = $rates[$currency->code] ?? null;
            if (!is_numeric($usdToLocal) || (float) $usdToLocal <= 0) continue;
            $newRate = number_format(1 / (float) $usdToLocal, 12, '.', '');
            DB::transaction(function () use ($currency, $newRate, $sourceTime, $source, &$updated) {
                $locked = Currency::whereKey($currency->id)->lockForUpdate()->first();
                if (!$locked || !$locked->auto_update) return;
                $oldRate = $locked->rate_to_usd;
                $oldSource = $locked->rate_source;

                // The provider snapshot is the freshness clock. Crucially, persist a timestamp
                // even when an existing row has a rate but has never had a refresh timestamp.
                // Reapply an equal-date snapshot too: a previous refresh may have stored the
                // timestamp but failed to persist the matching value.
                if (!$locked->last_updated_at || $sourceTime->greaterThanOrEqualTo($locked->last_updated_at)) {
                    $locked->rate_to_usd = $newRate;
                    $locked->last_updated_at = $sourceTime;
                } elseif ($locked->last_updated_at->isSameDay($sourceTime)) {
                    // Keep the newer timestamp while reconciling the value to that same-day snapshot.
                    $locked->rate_to_usd = $newRate;
                } else {
                    return;
                }
                $locked->rate_source = 'api';
                $locked->save();
                $updated++;

                if ((string) $oldRate !== (string) $locked->rate_to_usd || $oldSource !== 'api') {
                    $locked->rateLogs()->create([
                        'old_rate_to_usd' => $oldRate,
                        'new_rate_to_usd' => $locked->rate_to_usd,
                        'source' => 'api',
                    ]);
                }
            });
        }

        $settings->currency_rates_last_success_at = now();
        $nextScheduled = now()->startOfDay()->setTime(3, 0);
        if ($nextScheduled->lte(now())) $nextScheduled->addDay();
        $settings->currency_rates_next_at = $nextScheduled;
        $settings->currency_rates_last_error = $error;
        $settings->save();

        $pricing = null;
        if (\Illuminate\Support\Facades\Schema::hasTable('coin_rates')) {
            $pricing = app(CoinPricing::class)->recomputeAll($adminId);
        }

        return ['source' => $source, 'updated' => $updated, 'rates_updated_at' => $sourceTime, 'next_at' => $payload['next_at'], 'fallback_notice' => $error, 'pricing' => $pricing];
    }

    private function fetch(string $url, bool $keyed): array
    {
        $response = Http::timeout(12)->get($url);
        if (!$response->successful()) throw new RuntimeException('The provider returned HTTP ' . $response->status() . '.');
        $json = $response->json();
        if (($json['result'] ?? null) !== 'success') throw new RuntimeException($json['error-type'] ?? 'The provider did not return a valid rate set.');
        $base = $json['base_code'] ?? $json['base'] ?? null;
        $rates = $json[$keyed ? 'conversion_rates' : 'rates'] ?? null;
        $updatedUnix = $json['time_last_update_unix'] ?? null;
        $nextUnix = $json['time_next_update_unix'] ?? null;
        if ($base !== 'USD' || !is_array($rates) || !is_numeric($rates['USD'] ?? null) || (float) $rates['USD'] !== 1.0 || !is_numeric($updatedUnix)) {
            throw new RuntimeException('The provider returned an incomplete USD-based rate set.');
        }
        return [
            'rates' => $rates,
            'updated_at' => Carbon::createFromTimestampUTC((int) $updatedUnix),
            'next_at' => is_numeric($nextUnix) ? Carbon::createFromTimestampUTC((int) $nextUnix) : Carbon::createFromTimestampUTC((int) $updatedUnix)->addDay(),
        ];
    }
}
