<?php

namespace App\Console\Commands;

use App\Services\CurrencyRateRefresher;
use Illuminate\Console\Command;

class UpdateCurrencyRates extends Command
{
    protected $signature = 'currencies:update-rates';
    protected $description = 'Refresh enabled automatic currency reporting rates from USD';

    public function handle(): int
    {
        try {
            $result = app(CurrencyRateRefresher::class)->refresh();
            $this->info("Updated {$result['updated']} automatic currency reporting rates from the {$result['source']} endpoint.");
            $paused = count(array_filter($result['pricing'] ?? [], fn ($row) => !$row['ok']));
            if ($paused) $this->warn("{$paused} enabled currencies remain paused for coin pricing. Review the Coin Pricing page.");
            if ($result['fallback_notice']) $this->warn($result['fallback_notice']);
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
