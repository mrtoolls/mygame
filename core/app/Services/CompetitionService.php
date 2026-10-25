<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\CompetitionDraw;
use App\Models\CompetitionGameEntry;
use App\Models\CompetitionPairCount;
use App\Models\CompetitionResult;
use App\Models\CompetitionTicket;
use App\Models\GameLog;
use App\Models\TableGame;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompetitionService
{
    protected const LEDGER_TYPES = [
        Competition::TYPE_WAGER_RACE,
        Competition::TYPE_LUCKY_DRAW,
        Competition::TYPE_TABLE_LEAGUE,
    ];

    /**
     * Ingest newly finished table_games into competition_game_entries. Safe to call
     * repeatedly - the unique index on (competition_id, table_game_id, user_id) plus the
     * "not already ingested" filter make this idempotent.
     */
    public function ingest(Competition $competition): int
    {
        if (!in_array($competition->type, self::LEDGER_TYPES, true)) {
            return 0;
        }

        $query = TableGame::query()
            ->where('status', 'finished')
            ->where(function ($q) {
                $q->where('result', 'draw')
                    ->orWhere('result', 'like', 'winner:%')
                    ->orWhere('result', 'like', 'winners:%');
            })
            ->whereBetween('finished_at', [$competition->start_at, $competition->end_at])
            ->whereNotExists(function ($sub) use ($competition) {
                $sub->select(DB::raw(1))
                    ->from('competition_game_entries')
                    ->whereColumn('competition_game_entries.table_game_id', 'table_games.id')
                    ->where('competition_game_entries.competition_id', $competition->id);
            })
            ->with(['players' => fn ($q) => $q->orderBy('seat')])
            ->orderBy('finished_at')
            ->orderBy('id');

        if ($competition->eligible_games) {
            $query->whereIn('game_type', $competition->eligible_games);
        }

        $count = 0;
        foreach ($query->get() as $game) {
            $count += $this->ingestGame($competition, $game);
        }

        return $count;
    }

    private function ingestGame(Competition $competition, TableGame $game): int
    {
        return DB::transaction(function () use ($competition, $game) {
            $players = $game->players;
            if ($players->count() < 2) {
                return 0;
            }

            $tableRate = $players->first()->table_rate_to_usd;
            // A coin-mode table has no table_rate_to_usd (the stake IS coins, see
            // TableGameService::walletStakeQuoteFor()) - convert through the coin peg instead of
            // falling through to "no USD value known", which would wrongly exclude every
            // coin-mode table from any competition with a min_bet_usd floor.
            $stakeUsd = $tableRate
                ? bcmul((string) $game->stake, (string) $tableRate, 8)
                : (gs('coin_mode') ? bcmul((string) $game->stake, (string) (gs('coin_peg_usd') ?: '0.01'), 8) : null);

            if ($competition->min_bet_usd !== null) {
                if ($stakeUsd === null || bccomp($stakeUsd, (string) $competition->min_bet_usd, 8) < 0) {
                    return 0; // whole game ineligible - stake is table-wide, so this excludes every seat
                }
            }

            $userIds = $players->pluck('user_id')->unique()->values()->all();
            if (count($userIds) < 2) {
                return 0;
            }

            // Pair-cap every opponent pair in this game once; remember which pairs stayed uncapped.
            $uncappedPairs = [];
            for ($i = 0; $i < count($userIds); $i++) {
                for ($j = $i + 1; $j < count($userIds); $j++) {
                    [$low, $high] = $userIds[$i] < $userIds[$j] ? [$userIds[$i], $userIds[$j]] : [$userIds[$j], $userIds[$i]];
                    $uncappedPairs["{$low}:{$high}"] = $this->pairCapIncrement($competition, $low, $high);
                }
            }

            $pointsConfig = (array) data_get($competition->type_settings, 'points', ['win' => 3, 'draw' => 1, 'loss' => 0]);
            $now = now();
            $created = 0;

            foreach ($players as $player) {
                $opponentIds = array_values(array_diff($userIds, [$player->user_id]));
                if (!$opponentIds) {
                    continue;
                }

                $uncappedCount = 0;
                foreach ($opponentIds as $opponentId) {
                    [$low, $high] = $player->user_id < $opponentId ? [$player->user_id, $opponentId] : [$opponentId, $player->user_id];
                    if ($uncappedPairs["{$low}:{$high}"] ?? false) {
                        $uncappedCount++;
                    }
                }

                $uncappedFraction = round($uncappedCount / count($opponentIds), 4);
                $fullyCounted = $uncappedCount === count($opponentIds);

                // 'refund' only reaches here when the table-level result is a genuine draw -
                // cancelled/expired/force-closed tables were already excluded above.
                $result = match ($player->settlement_type) {
                    'payout' => 'win',
                    'loss' => 'loss',
                    'refund' => 'draw',
                    default => null,
                };

                $basePoints = null;
                $pointsEarned = null;
                if ($competition->type === Competition::TYPE_TABLE_LEAGUE && $result) {
                    $basePoints = (float) ($pointsConfig[$result] ?? 0);
                    $pointsEarned = round($basePoints * $uncappedFraction, 4);
                }

                $countedUsd = $stakeUsd !== null ? round(((float) $stakeUsd) * $uncappedFraction, 8) : null;

                CompetitionGameEntry::create([
                    'competition_id' => $competition->id,
                    'table_game_id' => $game->id,
                    'user_id' => $player->user_id,
                    'game_type' => $game->game_type,
                    'result' => $result,
                    'opponent_ids' => $opponentIds,
                    'stake_usd' => $stakeUsd,
                    'counted_usd' => $countedUsd,
                    'base_points' => $basePoints,
                    'uncapped_fraction' => $uncappedFraction,
                    'points_earned' => $pointsEarned,
                    'fully_counted' => $fullyCounted,
                    'finished_at' => $game->finished_at,
                    'counted_at' => $now,
                ]);
                $created++;
            }

            return $created;
        });
    }

    private function pairCapIncrement(Competition $competition, int $userIdLow, int $userIdHigh): bool
    {
        $maxGames = $competition->max_games_per_opponent_pair;

        $row = CompetitionPairCount::query()
            ->where('competition_id', $competition->id)
            ->where('user_id_low', $userIdLow)
            ->where('user_id_high', $userIdHigh)
            ->lockForUpdate()
            ->first();

        if (!$row) {
            $row = CompetitionPairCount::create([
                'competition_id' => $competition->id,
                'user_id_low' => $userIdLow,
                'user_id_high' => $userIdHigh,
                'games_count' => 0,
            ]);
        }

        if ($maxGames && $row->games_count >= $maxGames) {
            return false;
        }

        $row->increment('games_count');
        return true;
    }

    /**
     * Run the post-end sweep and, once the grace period has passed, freeze the final
     * ranking into competition_results. Safe to call on a schedule - no-ops once the
     * competition is already finalized/paid, and before the grace deadline.
     */
    public function finalize(Competition $competition): bool
    {
        if (in_array($competition->status, [Competition::STATUS_FINALIZED, Competition::STATUS_PAID], true)) {
            return false;
        }

        if (now()->lt($competition->end_at->copy()->addMinutes($competition->grace_minutes))) {
            return false;
        }

        if (in_array($competition->type, self::LEDGER_TYPES, true)) {
            $this->ingest($competition); // catch any late-finishing games before ranking
        }

        DB::transaction(function () use ($competition) {
            match ($competition->type) {
                Competition::TYPE_WAGER_RACE => $this->writeResults($competition, $this->rankWagerRace($competition), 'usd'),
                Competition::TYPE_TABLE_LEAGUE => $this->writeResults($competition, $this->rankTableLeague($competition), 'points'),
                Competition::TYPE_BIGGEST_WIN => $this->writeResults($competition, $this->rankBiggestWin($competition), 'multiplier'),
                Competition::TYPE_LUCKY_DRAW => $this->finalizeLuckyDraw($competition),
                default => null,
            };

            $competition->status = Competition::STATUS_FINALIZED;
            $competition->finalized_at = now();
            $competition->save();
        });

        return true;
    }

    /**
     * Compute the current standings without writing anything - used to show a live
     * leaderboard preview on a competition that hasn't been finalized yet. For an already
     * finalized/paid competition, read the frozen competition_results instead of this.
     */
    public function previewRanking(Competition $competition): array
    {
        return match ($competition->type) {
            Competition::TYPE_WAGER_RACE => ['rows' => $this->rankWagerRace($competition), 'metric_label' => 'usd'],
            Competition::TYPE_TABLE_LEAGUE => ['rows' => $this->rankTableLeague($competition), 'metric_label' => 'points'],
            Competition::TYPE_BIGGEST_WIN => ['rows' => $this->rankBiggestWin($competition), 'metric_label' => 'multiplier'],
            Competition::TYPE_LUCKY_DRAW => ['rows' => $this->previewTickets($competition), 'metric_label' => 'tickets'],
            default => ['rows' => collect(), 'metric_label' => null],
        };
    }

    private function previewTickets(Competition $competition): Collection
    {
        $rows = CompetitionGameEntry::query()
            ->where('competition_id', $competition->id)
            ->selectRaw('user_id, SUM(counted_usd) as total_usd')
            ->groupBy('user_id')
            ->havingRaw('SUM(counted_usd) > 0')
            ->get();

        return $rows->map(function ($row) use ($competition) {
            $tickets = $competition->ticket_usd_per_ticket
                ? (int) floor(((float) $row->total_usd) / (float) $competition->ticket_usd_per_ticket)
                : 0;
            if ($competition->max_tickets_per_user) {
                $tickets = min($tickets, $competition->max_tickets_per_user);
            }
            $row->metric_value = $tickets;
            return $row;
        })->filter(fn ($row) => $row->metric_value > 0)->sortByDesc('metric_value')->values();
    }

    private function writeResults(Competition $competition, Collection $ranked, string $metricLabel): void
    {
        $prizes = $competition->prizes()->get()->keyBy('rank');
        $rank = 1;
        foreach ($ranked as $row) {
            CompetitionResult::updateOrCreate(
                ['competition_id' => $competition->id, 'user_id' => $row->user_id],
                [
                    'rank' => $rank,
                    'metric_value' => $row->metric_value,
                    'metric_label' => $metricLabel,
                    'prize_amount_usd' => $prizes->get($rank)?->amount_usd,
                ]
            );
            $rank++;
        }
    }

    private function rankWagerRace(Competition $competition): Collection
    {
        $rows = CompetitionGameEntry::query()
            ->where('competition_id', $competition->id)
            ->selectRaw('user_id, SUM(counted_usd) as metric_value, MIN(finished_at) as first_seen, COUNT(*) as games_played')
            ->groupBy('user_id')
            ->havingRaw('SUM(counted_usd) > 0')
            ->get();

        if ($competition->min_games) {
            $rows = $rows->filter(fn ($row) => $row->games_played >= $competition->min_games)->values();
        }

        return $rows->sort(function ($a, $b) {
            $cmp = bccomp((string) $b->metric_value, (string) $a->metric_value, 8);
            return $cmp !== 0 ? $cmp : strcmp((string) $a->first_seen, (string) $b->first_seen);
        })->values();
    }

    private function rankTableLeague(Competition $competition): Collection
    {
        $rows = CompetitionGameEntry::query()
            ->where('competition_id', $competition->id)
            ->selectRaw('user_id, SUM(points_earned) as metric_value, COUNT(*) as games_played')
            ->groupBy('user_id')
            ->get();

        foreach ($rows as $row) {
            $fullyCounted = CompetitionGameEntry::query()
                ->where('competition_id', $competition->id)
                ->where('user_id', $row->user_id)
                ->where('fully_counted', true)
                ->get();

            $row->win_rate = $fullyCounted->count() ? $fullyCounted->where('result', 'win')->count() / $fullyCounted->count() : 0;
            $row->distinct_opponents = $fullyCounted->pluck('opponent_ids')->flatten()->unique()->count();
            $row->last_result_at = CompetitionGameEntry::where('competition_id', $competition->id)->where('user_id', $row->user_id)->max('finished_at');
        }

        $rows = $rows->filter(function ($row) use ($competition) {
            if ($competition->min_games && $row->games_played < $competition->min_games) {
                return false;
            }
            if ($competition->min_distinct_opponents && $row->distinct_opponents < $competition->min_distinct_opponents) {
                return false;
            }
            return true;
        })->values();

        // Tie-break order per the approved spec: points -> win rate -> distinct opponents -> earlier last result.
        return $rows->sort(function ($a, $b) {
            if ($a->metric_value != $b->metric_value) return $b->metric_value <=> $a->metric_value;
            if ($a->win_rate != $b->win_rate) return $b->win_rate <=> $a->win_rate;
            if ($a->distinct_opponents != $b->distinct_opponents) return $b->distinct_opponents <=> $a->distinct_opponents;
            return strcmp((string) $a->last_result_at, (string) $b->last_result_at);
        })->values();
    }

    private function rankBiggestWin(Competition $competition): Collection
    {
        // game_logs has no historical FX rate-lock, so this intentionally ranks by the
        // currency-agnostic win multiplier (win_amo / invest) rather than a USD amount.
        $query = GameLog::query()
            ->where('status', 1)
            ->where('win_status', 1)
            ->where('demo_play', 0)
            ->where('invest', '>', 0)
            ->whereBetween('created_at', [$competition->start_at, $competition->end_at]);

        if ($competition->eligible_games) {
            $query->whereIn('game_id', $competition->eligible_games);
        }

        return $query->selectRaw('user_id, MAX(win_amo / invest) as metric_value')
            ->groupBy('user_id')
            ->orderByDesc('metric_value')
            ->get();
    }

    /**
     * Freeze ticket counts from the counted ledger. Call this once, after the entry window
     * closes but before drawing, so the draw pool can't change mid-draw.
     */
    public function freezeTickets(Competition $competition): int
    {
        if ($competition->type !== Competition::TYPE_LUCKY_DRAW || !$competition->ticket_usd_per_ticket) {
            return 0;
        }

        $this->ingest($competition);

        $rows = CompetitionGameEntry::query()
            ->where('competition_id', $competition->id)
            ->selectRaw('user_id, SUM(counted_usd) as total_usd')
            ->groupBy('user_id')
            ->havingRaw('SUM(counted_usd) > 0')
            ->get();

        $now = now();
        $created = 0;
        foreach ($rows as $row) {
            $tickets = (int) floor(((float) $row->total_usd) / (float) $competition->ticket_usd_per_ticket);
            if ($tickets < 1) {
                continue;
            }
            if ($competition->max_tickets_per_user) {
                $tickets = min($tickets, $competition->max_tickets_per_user);
            }

            CompetitionTicket::updateOrCreate(
                ['competition_id' => $competition->id, 'user_id' => $row->user_id],
                ['ticket_count' => $tickets, 'frozen_at' => $now]
            );
            $created++;
        }

        return $created;
    }

    private function finalizeLuckyDraw(Competition $competition): void
    {
        if ($competition->tickets()->count() === 0) {
            $this->freezeTickets($competition);
        }

        $tickets = $competition->tickets()->get();
        if ($tickets->isEmpty()) {
            return;
        }

        $pool = [];
        foreach ($tickets as $ticket) {
            for ($i = 0; $i < $ticket->ticket_count; $i++) {
                $pool[] = $ticket->user_id;
            }
        }

        $prizeCount = max(1, $competition->prizes()->count());
        $serverSeed = bin2hex(random_bytes(32));
        $serverSeedHash = hash('sha256', $serverSeed);
        $clientSeed = bin2hex(random_bytes(8));

        $drawn = [];
        $position = 1;
        $nonce = 0;
        while (count($drawn) < $prizeCount && $pool) {
            $hash = hash('sha256', $serverSeed . ':' . $clientSeed . ':' . $nonce);
            $index = (int) (hexdec(substr($hash, 0, 8)) % count($pool));
            $winnerId = $pool[$index];

            if (!in_array($winnerId, $drawn, true)) {
                $drawn[] = $winnerId;
                CompetitionDraw::create([
                    'competition_id' => $competition->id,
                    'winner_user_id' => $winnerId,
                    'draw_position' => $position,
                    'seed_info' => [
                        'server_seed' => $serverSeed,
                        'server_seed_hash' => $serverSeedHash,
                        'client_seed' => $clientSeed,
                        'nonce' => $nonce,
                        'pool_size' => count($pool),
                    ],
                    'drawn_at' => now(),
                ]);

                $ticketCount = $tickets->firstWhere('user_id', $winnerId)?->ticket_count ?? 0;
                CompetitionResult::updateOrCreate(
                    ['competition_id' => $competition->id, 'user_id' => $winnerId],
                    [
                        'rank' => $position,
                        'metric_value' => $ticketCount,
                        'metric_label' => 'tickets',
                        'prize_amount_usd' => $competition->prizes()->where('rank', $position)->first()?->amount_usd,
                    ]
                );

                $pool = array_values(array_diff($pool, [$winnerId]));
                $position++;
            }

            $nonce++;
            if ($nonce > 100000) {
                break; // pathological safety valve - should never trigger with a sane pool size
            }
        }
    }
}
