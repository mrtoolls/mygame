<?php

namespace App\Console\Commands;

use App\Games\TableGames\TableGameService;
use App\Services\Fantasy\FantasyDuelService;
use Illuminate\Console\Command;

class SweepExpiredTableGames extends Command
{
    protected $signature = 'table-games:sweep-expired';
    protected $description = 'Finish expired waiting tables and refund unsettled player stakes';

    public function handle(TableGameService $service, FantasyDuelService $duels): int
    {
        $service->sweepExpired();
        $changed = $duels->sweep();
        $this->info("Expired table games were swept; {$changed} Live Duel phase changes were processed.");

        return self::SUCCESS;
    }
}
