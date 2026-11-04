<?php

namespace App\Console\Commands;

use App\Services\Fantasy\FantasyGameweekFinalizer;
use Illuminate\Console\Command;

/**
 * The entire virtual league runs off this one command, called every minute by the existing cron
 * scheduler (see routes/console.php) - there is no long-running match process. Each tick
 * publishes kickoff commitments that are now due, resolves matches whose simulated full time has
 * passed, finalizes gameweeks once every one of their fixtures is final, and settles contests
 * whose post-finalization review window has closed.
 */
class FantasyFootballTick extends Command
{
    protected $signature = 'fantasy-football:tick';
    protected $description = 'Advance the virtual football league: commit kickoffs, resolve finished matches, finalize gameweeks, settle due contests';

    public function handle(FantasyGameweekFinalizer $finalizer): int
    {
        if (!gs('ff_enabled')) {
            $this->info('Fantasy Football is disabled (kill switch) - skipping this tick.');
            return self::SUCCESS;
        }

        $finalizer->tick();
        $this->info('Fantasy Football tick complete.');
        return self::SUCCESS;
    }
}
