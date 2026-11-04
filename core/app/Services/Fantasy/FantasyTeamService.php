<?php

namespace App\Services\Fantasy;

use App\Models\FantasyChipUse;
use App\Models\FantasyGameweek;
use App\Models\FantasyLeague;
use App\Models\FantasyLeagueMember;
use App\Models\FantasyPlayer;
use App\Models\FantasySeason;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\FantasyTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Squad management: creating a team, picking the starting XI/bench/captaincy, transfers (with
 * the standard selling-price and free-transfer-bank rules), and chip activation. Every write
 * here re-validates budget, the 15-player squad shape (2 GK/5 DEF/5 MID/3 FWD), the 3-per-club
 * cap, and the gameweek deadline under a DB transaction - never trusts client-side validation.
 */
class FantasyTeamService
{
    private const SQUAD_SHAPE = ['GK' => 2, 'DEF' => 5, 'MID' => 5, 'FWD' => 3];
    private const BUDGET = '100.0';
    private const MAX_PER_CLUB = 3;
    private const MAX_FREE_TRANSFERS_BANKED = 5;
    private const TRANSFER_COST = 4;

    public function createTeam(User $user, FantasySeason $season, string $name, array $playerIds): FantasyTeam
    {
        if (count($playerIds) !== 15) throw new InvalidArgumentException('Select exactly 15 players.');

        return DB::transaction(function () use ($user, $season, $name, $playerIds) {
            if (FantasyTeam::where('user_id', $user->id)->where('season_id', $season->id)->exists()) {
                throw new InvalidArgumentException('You already have a team for this season.');
            }
            $players = FantasyPlayer::whereIn('id', $playerIds)->lockForUpdate()->get()->keyBy('id');
            if ($players->count() !== 15) throw new InvalidArgumentException('One or more selected players could not be found.');

            $this->assertValidSquadShape($players->values()->all());
            $totalCost = $players->sum('price');
            if (bccomp((string) $totalCost, self::BUDGET, 2) > 0) {
                throw new InvalidArgumentException('That squad costs more than your 100.0 credit budget.');
            }

            $team = FantasyTeam::create([
                'user_id' => $user->id, 'season_id' => $season->id, 'name' => $name,
                'budget_remaining' => bcsub(self::BUDGET, (string) $totalCost, 1),
                'free_transfers_banked' => 1, 'total_points' => 0,
            ]);

            $benchOutfieldOrder = 0;
            $byPosition = $players->groupBy('position');
            $startingIds = $this->defaultStartingXi($byPosition);
            foreach ($players as $player) {
                $isStarting = in_array($player->id, $startingIds, true);
                FantasyTeamPlayer::create([
                    'team_id' => $team->id, 'player_id' => $player->id, 'purchase_price' => $player->price,
                    'squad_role' => $isStarting ? 'starting' : 'bench',
                    'bench_order' => (!$isStarting && $player->position !== 'GK') ? $benchOutfieldOrder++ : null,
                ]);
            }
            // Captain/vice default to the two highest-rated starters until the user picks their own.
            $topTwo = $players->whereIn('id', $startingIds)->sortByDesc('rating')->take(2)->values();
            if ($topTwo->count() >= 1) FantasyTeamPlayer::where('team_id', $team->id)->where('player_id', $topTwo[0]->id)->update(['is_captain' => true]);
            if ($topTwo->count() >= 2) FantasyTeamPlayer::where('team_id', $team->id)->where('player_id', $topTwo[1]->id)->update(['is_vice_captain' => true]);

            FantasyLeagueMember::create([
                'league_id' => FantasyLeague::where('season_id', $season->id)->where('type', 'global')->value('id'),
                'team_id' => $team->id, 'joined_at' => now(),
            ]);

            return $team->fresh(['players.player']);
        });
    }

    private function defaultStartingXi($byPosition): array
    {
        $pick = fn ($pos, $n) => ($byPosition[$pos] ?? collect())->sortByDesc('rating')->take($n)->pluck('id')->all();
        return array_merge($pick('GK', 1), $pick('DEF', 4), $pick('MID', 4), $pick('FWD', 2));
    }

    private function assertValidSquadShape(array $players): void
    {
        $counts = ['GK' => 0, 'DEF' => 0, 'MID' => 0, 'FWD' => 0];
        $clubCounts = [];
        foreach ($players as $player) {
            $counts[$player->position] = ($counts[$player->position] ?? 0) + 1;
            $clubCounts[$player->club_id] = ($clubCounts[$player->club_id] ?? 0) + 1;
        }
        foreach (self::SQUAD_SHAPE as $position => $required) {
            if (($counts[$position] ?? 0) !== $required) {
                throw new InvalidArgumentException("A squad needs exactly {$required} {$position} players.");
            }
        }
        foreach ($clubCounts as $clubId => $count) {
            if ($count > self::MAX_PER_CLUB) throw new InvalidArgumentException('No more than 3 players from the same club.');
        }
    }

    /** Validates a proposed starting-XI/bench assignment: 1 GK, 3-5 DEF, 2-5 MID, 1-3 FWD, 11 starters + 4 bench (1 GK + 3 outfield). */
    public function assertValidFormation(array $startingPositions): void
    {
        $counts = ['GK' => 0, 'DEF' => 0, 'MID' => 0, 'FWD' => 0];
        foreach ($startingPositions as $position) $counts[$position] = ($counts[$position] ?? 0) + 1;
        if (array_sum($counts) !== 11) throw new InvalidArgumentException('Exactly 11 starters are required.');
        if ($counts['GK'] !== 1) throw new InvalidArgumentException('Exactly 1 starting goalkeeper is required.');
        if ($counts['DEF'] < 3) throw new InvalidArgumentException('At least 3 starting defenders are required.');
        if ($counts['MID'] < 2) throw new InvalidArgumentException('At least 2 starting midfielders are required.');
        if ($counts['FWD'] < 1) throw new InvalidArgumentException('At least 1 starting forward is required.');
    }

    /**
     * Sets the starting XI/bench order/captain/vice for a team, all-or-nothing. $picks is
     * [player_id => ['role' => starting|bench, 'bench_order' => int|null, 'captain' => bool, 'vice' => bool]].
     * Refuses once the team's current gameweek deadline has passed, unless a One-Week Reset chip
     * is currently open for this team (see activateChip()).
     */
    public function setLineup(FantasyTeam $team, array $picks): void
    {
        DB::transaction(function () use ($team, $picks) {
            $this->assertNotLocked($team);
            $teamPlayers = FantasyTeamPlayer::with('player')->where('team_id', $team->id)->lockForUpdate()->get()->keyBy('player_id');
            if ($teamPlayers->count() !== 15 || count($picks) !== 15) throw new InvalidArgumentException('A full 15-player squad is required.');

            $startingPositions = [];
            $captainCount = 0;
            $viceCount = 0;
            foreach ($picks as $playerId => $pick) {
                $teamPlayer = $teamPlayers->get($playerId);
                if (!$teamPlayer) throw new InvalidArgumentException('That player is not in your squad.');
                if ($pick['role'] === 'starting') $startingPositions[] = $teamPlayer->player->position;
                if (!empty($pick['captain'])) $captainCount++;
                if (!empty($pick['vice'])) $viceCount++;
            }
            if ($captainCount !== 1 || $viceCount !== 1) throw new InvalidArgumentException('Exactly one captain and one vice-captain are required.');
            $this->assertValidFormation($startingPositions);

            foreach ($picks as $playerId => $pick) {
                FantasyTeamPlayer::where('team_id', $team->id)->where('player_id', $playerId)->update([
                    'squad_role' => $pick['role'],
                    'bench_order' => $pick['role'] === 'bench' ? ($pick['bench_order'] ?? null) : null,
                    'is_captain' => !empty($pick['captain']),
                    'is_vice_captain' => !empty($pick['vice']),
                ]);
            }
        });
    }

    /**
     * Executes a batch of transfers for one gameweek: $outIds/$inIds are parallel arrays of equal
     * length. Cost: the first (free_transfers_banked) transfers are free; every one beyond that
     * costs TRANSFER_COST points, deducted from this gameweek's score at finalization (see
     * FantasyGameweekFinalizer). Fresh Start (unlimited free) and an open One-Week Reset both
     * bypass the free-transfer count entirely.
     */
    public function transfer(FantasyTeam $team, array $outIds, array $inIds): array
    {
        if (count($outIds) !== count($inIds) || empty($outIds)) throw new InvalidArgumentException('Invalid transfer request.');

        return DB::transaction(function () use ($team, $outIds, $inIds) {
            $this->assertNotLocked($team);
            $team = FantasyTeam::whereKey($team->id)->lockForUpdate()->firstOrFail();
            $gameweek = $this->currentGameweek($team);

            $unlimited = $team->fresh_start_active || $team->one_week_reset_gameweek_id === $gameweek->id;
            $transferCount = count($outIds);
            $freeAvailable = $unlimited ? $transferCount : min($transferCount, (int) $team->free_transfers_banked);
            $paidCount = $transferCount - $freeAvailable;
            $pointsCost = $paidCount * self::TRANSFER_COST;

            $budget = (string) $team->budget_remaining;
            $log = [];
            foreach ($outIds as $i => $outId) {
                $inId = $inIds[$i];
                $teamPlayer = FantasyTeamPlayer::where('team_id', $team->id)->where('player_id', $outId)->lockForUpdate()->firstOrFail();
                $incoming = FantasyPlayer::whereKey($inId)->lockForUpdate()->firstOrFail();
                if (FantasyTeamPlayer::where('team_id', $team->id)->where('player_id', $inId)->exists()) {
                    throw new InvalidArgumentException('That incoming player is already in your squad.');
                }
                $sellPrice = $teamPlayer->sellingPrice();
                $budget = bcadd($budget, $sellPrice, 1);
                $budget = bcsub($budget, (string) $incoming->price, 1);
                if (bccomp($budget, '0', 1) < 0) throw new InvalidArgumentException('That transfer exceeds your budget.');

                $clubCount = FantasyTeamPlayer::where('team_id', $team->id)
                    ->where('player_id', '!=', $outId)
                    ->whereHas('player', fn ($q) => $q->where('club_id', $incoming->club_id))
                    ->count();
                if ($clubCount >= self::MAX_PER_CLUB) throw new InvalidArgumentException('No more than 3 players from the same club.');
                if ($incoming->position !== $teamPlayer->player->position) {
                    throw new InvalidArgumentException('A transfer must replace a player with one of the same position.');
                }

                FantasyTransfer::create([
                    'team_id' => $team->id, 'gameweek_id' => $gameweek->id,
                    'player_out_id' => $outId, 'player_in_id' => $inId,
                    'sell_price' => $sellPrice, 'buy_price' => $incoming->price,
                    'is_free' => $i < $freeAvailable, 'cost' => $i < $freeAvailable ? 0 : self::TRANSFER_COST,
                ]);

                $teamPlayer->player_id = $inId;
                $teamPlayer->purchase_price = $incoming->price;
                $teamPlayer->is_captain = false;
                $teamPlayer->is_vice_captain = false;
                $teamPlayer->save();

                $log[] = ['out' => $outId, 'in' => $inId];
            }

            $team->budget_remaining = $budget;
            $team->free_transfers_banked = $unlimited
                ? $team->free_transfers_banked
                : max(0, min(self::MAX_FREE_TRANSFERS_BANKED, (int) $team->free_transfers_banked - $freeAvailable));
            $team->save();

            return ['transfers' => $log, 'points_cost' => $pointsCost];
        });
    }

    /** Called at gameweek finalization: a team that made no transfers banks one more free transfer (capped at 5), consistent with "1 free per gameweek, up to 5 banked". */
    public function rolloverFreeTransfer(FantasyTeam $team): void
    {
        if ($team->fresh_start_active) return;
        $team->free_transfers_banked = min(self::MAX_FREE_TRANSFERS_BANKED, (int) $team->free_transfers_banked + 1);
        $team->save();
    }

    public function activateChip(FantasyTeam $team, string $chipType): void
    {
        $validChips = ['deep_bench', 'super_captain', 'fresh_start', 'one_week_reset'];
        if (!in_array($chipType, $validChips, true)) throw new InvalidArgumentException('Unknown chip.');

        DB::transaction(function () use ($team, $chipType) {
            $team = FantasyTeam::whereKey($team->id)->lockForUpdate()->firstOrFail();
            $this->assertNotLocked($team);
            $gameweek = $this->currentGameweek($team);

            if (FantasyChipUse::where('team_id', $team->id)->where('chip_type', $chipType)->exists()) {
                throw new InvalidArgumentException('That chip has already been used this season.');
            }
            if (FantasyChipUse::where('team_id', $team->id)->where('gameweek_id', $gameweek->id)->exists()) {
                throw new InvalidArgumentException('Only one chip can be active per gameweek.');
            }

            FantasyChipUse::create([
                'team_id' => $team->id, 'season_id' => $team->season_id, 'gameweek_id' => $gameweek->id,
                'chip_type' => $chipType, 'used_at' => now(),
            ]);

            if ($chipType === 'fresh_start') {
                $team->fresh_start_active = true;
            } elseif ($chipType === 'one_week_reset') {
                $team->one_week_reset_gameweek_id = $gameweek->id;
                $team->one_week_reset_snapshot = FantasyTeamPlayer::where('team_id', $team->id)
                    ->get(['player_id', 'purchase_price', 'squad_role', 'bench_order', 'is_captain', 'is_vice_captain'])
                    ->toArray();
            }
            $team->save();
        });
    }

    /** Called at gameweek finalization for a team whose One-Week Reset gameweek just ended: reverts the squad to its pre-chip snapshot. */
    public function revertOneWeekReset(FantasyTeam $team): void
    {
        if (!$team->one_week_reset_snapshot) return;
        DB::transaction(function () use ($team) {
            FantasyTeamPlayer::where('team_id', $team->id)->delete();
            foreach ($team->one_week_reset_snapshot as $row) {
                FantasyTeamPlayer::create(array_merge($row, ['team_id' => $team->id]));
            }
            $team->one_week_reset_gameweek_id = null;
            $team->one_week_reset_snapshot = null;
            $team->save();
        });
    }

    private function assertNotLocked(FantasyTeam $team): void
    {
        $gameweek = $this->currentGameweek($team);
        if ($gameweek->isDeadlinePassed()) {
            throw new InvalidArgumentException('The deadline for this gameweek has passed.');
        }
    }

    private function currentGameweek(FantasyTeam $team): FantasyGameweek
    {
        $season = $team->season ?? FantasySeason::findOrFail($team->season_id);
        $gameweek = $season->current_gameweek_id ? FantasyGameweek::find($season->current_gameweek_id) : null;
        if (!$gameweek) throw new InvalidArgumentException('No active gameweek for this season.');
        return $gameweek;
    }
}
