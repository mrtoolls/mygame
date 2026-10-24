<?php

namespace App\Http\Controllers\CronHandlers;

use App\Services\CoinMarketplace;

class CoinPaymentWindowSweep
{
    public function run(): void
    {
        if (!gs('coin_mode')) return;
        app(CoinMarketplace::class)->sweepPaymentWindowExpiry();
    }
}
