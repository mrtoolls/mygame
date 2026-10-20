<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** USD per one unit of local currency, inverted from the provider's USD-base snapshot. */
    private const INITIAL_RATES = [
        'USD' => '1.000000000000',
        'GBP' => '1.332789555195',
        'NGN' => '0.000750966924',
        'GHS' => '0.085807580619',
        'KES' => '0.007704862469',
        'UGX' => '0.000246252200',
        'TZS' => '0.000379920763',
        'ZAR' => '0.060746318333',
        'XAF' => '0.001711257974',
        'ZMW' => '0.049614970464',
        'SLE' => '0.041054396788',
        'RWF' => '0.000677741060',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('currencies') || !Schema::hasColumn('currencies', 'auto_update')) return;

        // ExchangeRate-API USD-base snapshot last published 2026-10-19 00:02 UTC.
        $sourceTime = '2026-10-19 00:02:32';
        foreach (self::INITIAL_RATES as $code => $rate) {
            $currency = DB::table('currencies')->where('code', $code)->first();
            if (!$currency) continue;
            DB::table('currencies')->where('id', $currency->id)->update([
                'rate_to_usd' => $rate,
                'rate_source' => 'api',
                'auto_update' => true,
                'last_updated_at' => $sourceTime,
                'updated_at' => now(),
            ]);
            if (Schema::hasTable('currency_rate_logs') && (string) $currency->rate_to_usd !== $rate) {
                DB::table('currency_rate_logs')->insert([
                    'currency_id' => $currency->id,
                    'old_rate_to_usd' => $currency->rate_to_usd,
                    'new_rate_to_usd' => $rate,
                    'source' => 'api',
                    'changed_by' => null,
                    'created_at' => $sourceTime,
                ]);
            }
        }
        DB::table('currencies')->where('enabled', true)->update(['auto_update' => true]);
    }

    public function down(): void
    {
        // Keep the cached rates; disabling automatic refresh is deliberately not destructive.
    }
};
