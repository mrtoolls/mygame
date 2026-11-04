<?php

namespace App\Services\Fantasy;

use App\Models\FantasyChipUse;
use App\Models\FantasyContest;
use App\Models\FantasyFixture;
use App\Models\FantasyGameweek;
use App\Models\FantasyPlayer;
use App\Models\FantasyPlayerFixtureStat;
use App\Models\FantasyPlayerPriceHistory;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamGameweekHistory;
use App\Models\FantasyTeamPlayer;
use App\Models\FantasyTransfer;
use Illuminate\Support\Facades\DB;

/**
 * The cron-driven heart of the virtual league: no background process runs a match, so this is
 * called once a minute (see FantasyTick console command) and does whatever is due right now -
 * publishing kickoff commitments, resolving matches whose simulated full time has passed, and
 * finalizing gameweeks once every one of their fixtures is final (computing every team's points
 * via FantasyLineupScorer, applying chips, rolling transfers, and opening each gameweek's
 * contests for settlement after their review window).
 */
class FantasyGameweekFinalizer
{
    public function __construct(
        private readonly FootballDataProvider $provider,
        private readonly FantasyLineupScorer $lineupScorer,
        private readonly FantasyTeamService $teamService,
    ) {}

    public function tick(): void
    {
        $this->commitUpcomingKickoffs();
        $this->resolveFinishedMatches();
        $this->finalizeReadyGameweeks();
        $this->settleDueContests();
    }

    private function commitUpcomingKickoffs(): void
    {
        FantasyFixture::where('status', 'scheduled')->where('kickoff_at', '<=', now())->limit(200)->get()->each(function ($fixture) {
            $this->provider->commit($fixture);
            $fixture->status = 'live';
            $fixture->save();
        });
    }

    private function resolveFinishedMatches(): void
    {
        FantasyFixture::where('status', 'live')->limit(200)->get()
            ->filter(fn ($fixture) => $fixture->isFullTime())
            ->each(fn ($fixture) => $this->provider->resolve($fixture));
    }

    private function finalizeReadyGameweeks(): void
    {
        FantasyGameweek::where('status', '!=', 'final')->get()->each(function ($gameweek) {
            if ($gameweek->fixtures()->count() === 0) return;
            if ($gameweek->fixtures()->where('status', '!=', 'final')->exists()) return;
            $this->finalizeGameweek($gameweek);
        });
    }

    public function finalizeGameweek(FantasyGameweek $gameweek): void
    {
        DB::transaction(function () use ($gameweek) {
            $gameweek = FantasyGameweek::whereKey($gameweek->id)->lockForUpdate()->first();
            if (!$gameweek || $gameweek->status === 'final') return;

            FantasyTeam::where('season_id', $gameweek->season_id)->get()->each(fn ($team) => $this->finalizeTeamGameweek($team, $gameweek));

            $gameweek->status = 'final';
            $gameweek->finalized_at = now();
            $gameweek->save();

            $next = FantasyGameweek::where('season_id', $gameweek->season_id)->where('number', '>', $gameweek->number)->orderBy('number')->first();
            if ($next) {
                $season = $gameweek->season;
                $season->current_gameweek_id = $next->id;
                $season->save();
            }

            $reviewMinutes = (int) (gs('ff_settlement_review_minutes') ?? 15);
            FantasyContest::where('gameweek_id', $gameweek->id)->where('status', 'open')
                ->update(['status' => 'locked', 'review_until_at' => now()->addMinutes($reviewMinutes)]);

            $this->recomputeOverallRanks($gameweek->season_id);
        });

        $this->runPriceChanges($gameweek);
    }

    private function finalizeTeamGameweek(FantasyTeam $team, FantasyGameweek $gameweek): void
    {
        $teamPlayers = FantasyTeamPlayer::with('player')->where('team_id', $team->id)->get();
        $playerIds = $teamPlayers->pluck('player_id');
        $fixtureStats = FantasyPlayerFixtureStat::whereIn('player_id', $playerIds)
            ->whereHas('fixture', fn ($q) => $q->where('gameweek_id', $gameweek->id))
            ->get()->keyBy('player_id');

        $squad = $teamPlayers->map(function ($teamPlayer) use ($fixtureStats) {
            $stat = $fixtureStats->get($teamPlayer->player_id);
            return [
                'player_id' => $teamPlayer->player_id, 'position' => $teamPlayer->player->position,
                'squad_role' => $teamPlayer->squad_role, 'bench_order' => $teamPlayer->bench_order,
                'is_captain' => $teamPlayer->is_captain, 'is_vice_captain' => $teamPlayer->is_vice_captain,
                'points' => $stat->total_points ?? 0, 'minutes' => $stat->minutes ?? 0,
            ];
        })->all();

        $activeChip = FantasyChipUse::where('team_id', $team->id)->where('gameweek_id', $gameweek->id)->first();
        $chips = [
            'deep_bench' => $activeChip?->chip_type === 'deep_bench',
            'super_captain' => $activeChip?->chip_type === 'super_captain',
        ];

        $result = $this->lineupScorer->score($squad, $chips);

        $transferCost = (int) FantasyTransfer::where('team_id', $team->id)->where('gameweek_id', $gameweek->id)->sum('cost');
        $netPoints = $result['total'] - $transferCost;

        FantasyTeamGameweekHistory::updateOrCreate(
            ['team_id' => $team->id, 'gameweek_id' => $gameweek->id],
            [
                'points' => $netPoints, 'bench_points' => $result['bench_points'],
                'transfers_made' => FantasyTransfer::where('team_id', $team->id)->where('gameweek_id', $gameweek->id)->count(),
                'transfer_cost' => $transferCost, 'chip_used' => $activeChip?->chip_type,
            ]
        );

        $team->total_points = (int) $team->total_points + $netPoints;
        $team->save();

        if (!FantasyTransfer::where('team_id', $team->id)->where('gameweek_id', $gameweek->id)->exists()) {
            $this->teamService->rolloverFreeTransfer($team);
        }

        if ($team->one_week_reset_gameweek_id === $gameweek->id) {
            $this->teamService->revertOneWeekReset($team);
        }
    }

    private function recomputeOverallRanks(int $seasonId): void
    {
        $teams = FantasyTeam::where('season_id', $seasonId)->orderByDesc('total_points')->orderBy('id')->get(['id', 'total_points']);
        $rank = 0;
        foreach ($teams as $team) {
            $rank++;
            FantasyTeam::whereKey($team->id)->update(['overall_rank' => $rank]);
        }
    }

    /**
     * Daily (admin-gated) price movement from net transfer activity this gameweek: more players
     * bought in than sold out nudges the price up one step, more sold than bought nudges it down
     * - bounded by ff_price_min/ff_price_max and the admin's configured step, never more than one
     * step per gameweek regardless of how lopsided the net transfer count is.
     */
    private function runPriceChanges(FantasyGameweek $gameweek): void
    {
        if (!gs('ff_price_change_enabled')) return;
        $step = (float) (gs('ff_price_change_step') ?? 0.1);
        $min = (float) (gs('ff_price_min') ?? 3.5);
        $max = (float) (gs('ff_price_max') ?? 15.0);

        $boughtCounts = FantasyTransfer::where('gameweek_id', $gameweek->id)->whereNotNull('player_in_id')
            ->selectRaw('player_in_id as player_id, COUNT(*) as c')->groupBy('player_in_id')->pluck('c', 'player_id');
        $soldCounts = FantasyTransfer::where('gameweek_id', $gameweek->id)->whereNotNull('player_out_id')
            ->selectRaw('player_out_id as player_id, COUNT(*) as c')->groupBy('player_out_id')->pluck('c', 'player_id');

        $playerIds = $boughtCounts->keys()->merge($soldCounts->keys())->unique();
        foreach ($playerIds as $playerId) {
            $net = ((int) ($boughtCounts[$playerId] ?? 0)) - ((int) ($soldCounts[$playerId] ?? 0));
            if ($net === 0) continue;
            $player = FantasyPlayer::find($playerId);
            if (!$player) continue;
            $old = (float) $player->price;
            $new = $net > 0 ? min($max, $old + $step) : max($min, $old - $step);
            $new = round($new * 10) / 10;
            if ($new === $old) continue;
            $player->price = $new;
            $player->save();
            FantasyPlayerPriceHistory::create([
                'player_id' => $playerId, 'gameweek_id' => $gameweek->id,
                'old_price' => $old, 'new_price' => $new, 'net_transfers' => $net,
            ]);
        }
    }

    private function settleDueContests(): void
    {
        FantasyContest::where('status', 'locked')->where('review_until_at', '<=', now())->limit(50)->get()
            ->each(fn ($contest) => app(FantasyContestService::class)->settle($contest));
    }
}
