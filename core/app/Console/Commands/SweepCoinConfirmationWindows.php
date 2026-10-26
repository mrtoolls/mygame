<?php

namespace App\Console\Commands;

use App\Services\CoinMarketplace;
use Illuminate\Console\Command;

class SweepCoinConfirmationWindows extends Command
{
    protected $signature = 'coin-marketplace:sweep-confirmation-windows';
    protected $description = 'Escalate overdue coin marketplace confirmations for human review';

    public function handle(CoinMarketplace $marketplace): int
    {
        if (gs('coin_mode')) {
            $this->info('Escalated ' . $marketplace->sweepConfirmationWindowEscalation() . ' coin trade(s) for review.');
        }

        return self::SUCCESS;
    }
}
