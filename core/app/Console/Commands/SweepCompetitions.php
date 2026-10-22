<?php

namespace App\Console\Commands;

use App\Models\Competition;
use App\Services\CompetitionService;
use Illuminate\Console\Command;

class SweepCompetitions extends Command
{
    protected $signature = 'competitions:sweep';
    protected $description = 'Ingest newly finished games into live competitions and finalize ended ones past their grace period';

    public function handle(CompetitionService $service): int
    {
        Competition::where('status', Competition::STATUS_SCHEDULED)
            ->where('start_at', '<=', now())
            ->update(['status' => Competition::STATUS_LIVE]);

        Competition::where('status', Competition::STATUS_LIVE)
            ->where('end_at', '<=', now())
            ->update(['status' => Competition::STATUS_ENDED]);

        $live = Competition::where('status', Competition::STATUS_LIVE)->get();
        foreach ($live as $competition) {
            $service->ingest($competition);
        }

        $ended = Competition::where('status', Competition::STATUS_ENDED)->get();
        foreach ($ended as $competition) {
            if ($competition->type === Competition::TYPE_LUCKY_DRAW && $competition->tickets()->count() === 0) {
                $service->freezeTickets($competition);
            }
            $service->finalize($competition);
        }

        $this->info('Competitions swept: ' . $live->count() . ' live, ' . $ended->count() . ' ended.');

        return self::SUCCESS;
    }
}
