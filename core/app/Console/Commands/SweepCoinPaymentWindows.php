<?php

namespace App\Console\Commands;

use App\Services\CoinMarketplace;
use Illuminate\Console\Command;

class SweepCoinPaymentWindows extends Command
{
    protected $signature = 'coin-marketplace:sweep-payment-windows';
    protected $description = 'Expire unclaimed coin marketplace payment windows';

    public function handle(CoinMarketplace $marketplace): int
    {
        if (gs('coin_mode')) {
            $this->info('Expired ' . $marketplace->sweepPaymentWindowExpiry() . ' coin payment window(s).');
        }

        return self::SUCCESS;
    }
}
