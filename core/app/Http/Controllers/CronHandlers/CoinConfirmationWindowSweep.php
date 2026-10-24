<?php

namespace App\Http\Controllers\CronHandlers;

use App\Services\CoinMarketplace;

/** Escalates an overdue payment-claimed trade to DISPUTED for a human to review - never auto-releases coins. */
class CoinConfirmationWindowSweep
{
    public function run(): void
    {
        if (!gs('coin_mode')) return;
        app(CoinMarketplace::class)->sweepConfirmationWindowEscalation();
    }
}
