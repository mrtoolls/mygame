<?php

namespace App\Console\Commands;

use App\Services\CoinVendorService;
use Illuminate\Console\Command;

class CleanupCoinVendorApplications extends Command
{
    protected $signature = 'coin-vendors:cleanup-application-data';
    protected $description = 'Delete retained identity files after the configured vendor application retention period';

    public function handle(CoinVendorService $vendors): int
    {
        $deleted = $vendors->cleanupExpiredApplicationData();
        $this->info("Deleted identity data for {$deleted} expired application(s).");
        return self::SUCCESS;
    }
}
