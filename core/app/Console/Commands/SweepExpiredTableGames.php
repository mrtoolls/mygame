<?php

namespace App\Console\Commands;

use App\Games\TableGames\TableGameService;
use Illuminate\Console\Command;

class SweepExpiredTableGames extends Command
{
    protected $signature = 'table-games:sweep-expired';
    protected $description = 'Finish expired waiting tables and refund unsettled player stakes';

    public function handle(TableGameService $service): int
    {
        $service->sweepExpired();
        $this->info('Expired waiting tables were swept.');

        return self::SUCCESS;
    }
}
