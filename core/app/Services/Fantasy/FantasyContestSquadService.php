<?php

namespace App\Services\Fantasy;

use App\Models\FantasyContest;
use App\Models\FantasyContestSquad;
use App\Models\FantasyContestSquadPlayer;
use App\Models\FantasyPlayer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Builds the fresh, per-contest 15-player squad a Quick Contest scores from - never the user's
 * one season-long ff_teams squad (see ff_contest_squads' migration note). Mirrors
 * FantasyTeamService's own shape/budget/club validation exactly, parametrized on the contest's own
 * budget_tier instead of the season's fixed 100.0, so every entrant at a tier drafts under the
 * identical budget regardless of how much coin they have - the mechanism that keeps tiers from
 * being pay-to-win.
 */
class FantasyContestSquadService
{
    private const SQUAD_SHAPE = ['GK' => 2, 'DEF' => 5, 'MID' => 5, 'FWD' => 3];
    private const MAX_PER_CLUB = 3;

    public function createOrReplace(User $user, FantasyContest $contest, string $name, array $playerIds): FantasyContestSquad
    {
        $playerIds = array_map('intval', $playerIds);
        if (count(array_unique($playerIds)) !== 15 || min($playerIds) < 1) throw new InvalidArgumentException('Choose 15 different players.');
        if (count($playerIds) !== 15) throw new InvalidArgumentException('Select exactly 15 players.');
        if ($contest->type !== 'quick') throw new InvalidArgumentException('This contest does not use its own squad.');
        if (!$contest->budget_tier) throw new InvalidArgumentException('This contest has no budget tier configured.');

        return DB::transaction(function () use ($user, $contest, $name, $playerIds) {
            $contest = FantasyContest::whereKey($contest->id)->lockForUpdate()->firstOrFail();
            if ($contest->status !== 'open') throw new InvalidArgumentException('This contest is no longer open for entries.');
            if ($contest->isDeadlinePassed()) throw new InvalidArgumentException('Entries have closed for this contest.');

            $players = FantasyPlayer::whereIn('id', $playerIds)->where('is_active', true)->lockForUpdate()->get()->keyBy('id');
            if ($players->count() !== 15) throw new InvalidArgumentException('One or more selected players could not be found.');

            $this->assertValidSquadShape($players->values()->all());
            $budget = (string) $contest->budget_tier;
            $totalCost = (string) $players->sum('price');
            if (bccomp($totalCost, $budget, 2) > 0) {
                throw new InvalidArgumentException("That squad costs more than this contest's {$budget} credit budget.");
            }

            $existing = FantasyContestSquad::where('contest_id', $contest->id)->where('user_id', $user->id)->first();
            if ($existing && $this->hasEnteredWith($contest->id, $user->id)) {
                throw new InvalidArgumentException('You have already entered this contest with this squad - withdraw first to rebuild it.');
            }

            $squad = $existing ?: new FantasyContestSquad(['contest_id' => $contest->id, 'user_id' => $user->id]);
            $squad->name = $name;
            $squad->budget_tier = $contest->budget_tier;
            $squad->budget_remaining = bcsub($budget, $totalCost, 1);
            $squad->save();

            FantasyContestSquadPlayer::where('contest_squad_id', $squad->id)->delete();
            $benchOutfieldOrder = 0;
            $byPosition = $players->groupBy('position');
            $startingIds = $this->defaultStartingXi($byPosition);
            foreach ($players as $player) {
                $isStarting = in_array($player->id, $startingIds, true);
                FantasyContestSquadPlayer::create([
                    'contest_squad_id' => $squad->id, 'player_id' => $player->id, 'purchase_price' => $player->price,
                    'squad_role' => $isStarting ? 'starting' : 'bench',
                    'bench_order' => (!$isStarting && $player->position !== 'GK') ? $benchOutfieldOrder++ : null,
                ]);
            }
            $topTwo = $players->whereIn('id', $startingIds)->sortByDesc('rating')->take(2)->values();
            if ($topTwo->count() >= 1) FantasyContestSquadPlayer::where('contest_squad_id', $squad->id)->where('player_id', $topTwo[0]->id)->update(['is_captain' => true]);
            if ($topTwo->count() >= 2) FantasyContestSquadPlayer::where('contest_squad_id', $squad->id)->where('player_id', $topTwo[1]->id)->update(['is_vice_captain' => true]);

            return $squad->fresh(['players.player']);
        });
    }

    private function hasEnteredWith(int $contestId, int $userId): bool
    {
        return \App\Models\FantasyContestEntry::where('contest_id', $contestId)->where('user_id', $userId)->exists();
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
        foreach ($clubCounts as $count) {
            if ($count > self::MAX_PER_CLUB) throw new InvalidArgumentException('No more than 3 players from the same club.');
        }
    }
}
