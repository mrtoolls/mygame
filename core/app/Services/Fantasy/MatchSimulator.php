<?php

namespace App\Services\Fantasy;

use App\Support\DeterministicRandom;
use Illuminate\Support\Collection;

/**
 * Pure match-outcome generator: given a seeded RNG and both clubs' active rosters, produces a
 * full result (score, minute-by-minute event timeline, and a per-player box score) with no
 * database access at all, so it is exactly reproducible from the same seed (see
 * DeterministicRandom) and fully unit-testable in isolation. VirtualFootballProvider is the only
 * caller that actually persists what this returns.
 *
 * Goal expectancy is Poisson, with each team's lambda derived from its starting attackers'
 * average rating against the opponent's starting defenders' average rating, a home-advantage
 * multiplier, and the admin-set club strength_rating as an overall quality baseline. Scorers and
 * assist providers are picked by a rating/position-weighted draw from each team's own starting
 * XI. A small share of goals are penalties (which can also be missed without producing a goal),
 * cards and saves are drawn from the same weighted-by-role approach, and late-game substitutions
 * (occasionally injury-triggered) adjust playing minutes.
 */
class MatchSimulator
{
    private const BASE_LAMBDA = 1.23;
    private const HOME_ADVANTAGE = 1.12;
    private const RATING_BASELINE = 70.0;

    private const ATTACK_WEIGHT = ['GK' => 0, 'DEF' => 0.25, 'MID' => 1.0, 'FWD' => 1.6];
    private const DEFENSE_WEIGHT = ['GK' => 1.4, 'DEF' => 1.2, 'MID' => 0.3, 'FWD' => 0];
    private const SCORER_WEIGHT = ['GK' => 0, 'DEF' => 0.4, 'MID' => 1.5, 'FWD' => 3.0];
    private const ASSIST_WEIGHT = ['GK' => 0, 'DEF' => 0.5, 'MID' => 1.8, 'FWD' => 1.2];
    private const CARD_WEIGHT = ['GK' => 0.3, 'DEF' => 1.3, 'MID' => 1.2, 'FWD' => 0.8];

    public function simulate(DeterministicRandom $rng, array $homeClub, Collection $homeRoster, array $awayClub, Collection $awayRoster): array
    {
        $home = $this->pickStartingXi($rng, $homeRoster);
        $away = $this->pickStartingXi($rng, $awayRoster);

        $box = [];
        foreach ([$home, $away] as $side) {
            foreach ($side['starting'] as $player) {
                $box[$player['id']] = $this->blankStat($player, true);
            }
        }

        $homeLambda = $this->lambda($home['starting'], $away['starting'], (float) $homeClub['strength_rating'], (float) $awayClub['strength_rating'], true);
        $awayLambda = $this->lambda($away['starting'], $home['starting'], (float) $awayClub['strength_rating'], (float) $homeClub['strength_rating'], false);

        $events = [];
        $homeGoals = $this->resolveGoals($rng, $home['starting'], $rng->poisson($homeLambda), 'home', $box, $events);
        $awayGoals = $this->resolveGoals($rng, $away['starting'], $rng->poisson($awayLambda), 'away', $box, $events);

        // Own goals: a small independent chance per team that a defender turns one in for the
        // opponent - deliberately not folded into the Poisson draw above, since it is a distinct,
        // much rarer mechanic (conceding side's own player, not the attacking side's).
        if ($rng->chance(0.035)) $homeGoals += $this->resolveOwnGoal($rng, $away['starting'], 'away', $box, $events);
        if ($rng->chance(0.035)) $awayGoals += $this->resolveOwnGoal($rng, $home['starting'], 'home', $box, $events);

        $this->resolveCards($rng, $home['starting'], 'home', $box, $events);
        $this->resolveCards($rng, $away['starting'], 'away', $box, $events);

        // Saves: a proxy for shots faced - scales with the opponent's actual goals plus a base
        // rate of saved/off-target efforts a keeper still has to deal with.
        $this->resolveSaves($rng, $home['starting'], $awayGoals, $box);
        $this->resolveSaves($rng, $away['starting'], $homeGoals, $box);

        $this->resolveSubstitutions($rng, $home, 'home', $box, $events);
        $this->resolveSubstitutions($rng, $away, 'away', $box, $events);

        // Clean sheet and goals_conceded are properties of the TEAM's defensive result, assigned
        // after both teams' goals are final.
        foreach ($home['starting'] as $player) {
            $box[$player['id']]['goals_conceded'] = $awayGoals;
            $box[$player['id']]['clean_sheet'] = $awayGoals === 0;
        }
        foreach ($away['starting'] as $player) {
            $box[$player['id']]['goals_conceded'] = $homeGoals;
            $box[$player['id']]['clean_sheet'] = $homeGoals === 0;
        }

        $this->assignBonus($box);
        usort($events, fn ($a, $b) => $a['minute'] <=> $b['minute']);

        return [
            'home_score' => $homeGoals,
            'away_score' => $awayGoals,
            'events' => $events,
            'player_stats' => array_values($box),
        ];
    }

    /** @return array{starting: array, bench: array} up to 11 starters (1 GK + a sane outfield mix) plus the remaining available squad as bench. */
    private function pickStartingXi(DeterministicRandom $rng, Collection $roster): array
    {
        $byPosition = ['GK' => [], 'DEF' => [], 'MID' => [], 'FWD' => []];
        foreach ($roster as $player) {
            $row = is_array($player) ? $player : $player->toArray();
            if (($row['is_active'] ?? true) && ($row['status'] ?? 'fit') === 'fit') {
                $byPosition[$row['position']][] = $row;
            }
        }
        foreach ($byPosition as $position => $players) {
            usort($players, fn ($a, $b) => $this->tendencyRoll($rng, $b) <=> $this->tendencyRoll($rng, $a));
            $byPosition[$position] = $players;
        }

        $targets = ['GK' => 1, 'DEF' => 4, 'MID' => 4, 'FWD' => 2]; // a standard 4-4-2 shape for the simulated XI
        $starting = [];
        foreach ($targets as $position => $count) {
            $starting = array_merge($starting, array_slice($byPosition[$position], 0, $count));
        }
        // Top up to 11 from whichever outfield position still has depth, if a club is short in one line.
        $allOutfield = array_merge($byPosition['DEF'], $byPosition['MID'], $byPosition['FWD']);
        while (count($starting) < 11 && count($allOutfield) > 0) {
            $candidate = array_shift($allOutfield);
            if (!in_array($candidate, $starting, true)) $starting[] = $candidate;
        }

        $startingIds = array_column($starting, 'id');
        $bench = array_values(array_filter(array_merge(...array_values($byPosition)), fn ($p) => !in_array($p['id'], $startingIds, true)));

        return ['starting' => $starting, 'bench' => $bench];
    }

    private function tendencyRoll(DeterministicRandom $rng, array $player): float
    {
        return (float) ($player['minutes_tendency'] ?? 0.7) + $rng->nextFloat() * 0.05;
    }

    private function lambda(array $attackers, array $defenders, float $attackClubStrength, float $defenseClubStrength, bool $isHome): float
    {
        $attackRating = $this->weightedAverageRating($attackers, self::ATTACK_WEIGHT) ?: self::RATING_BASELINE;
        $defenseRating = $this->weightedAverageRating($defenders, self::DEFENSE_WEIGHT) ?: self::RATING_BASELINE;
        $clubFactor = ($attackClubStrength ?: self::RATING_BASELINE) / ($defenseClubStrength ?: self::RATING_BASELINE);
        $matchupFactor = $attackRating / $defenseRating;
        $lambda = self::BASE_LAMBDA * sqrt($clubFactor * $matchupFactor);
        if ($isHome) $lambda *= self::HOME_ADVANTAGE;
        return max(0.15, min(4.0, $lambda));
    }

    private function weightedAverageRating(array $players, array $weights): float
    {
        $totalWeight = 0.0;
        $totalRating = 0.0;
        foreach ($players as $player) {
            $w = $weights[$player['position']] ?? 0;
            if ($w <= 0) continue;
            $totalWeight += $w;
            $totalRating += $w * (float) $player['rating'];
        }
        return $totalWeight > 0 ? $totalRating / $totalWeight : 0.0;
    }

    private function resolveGoals(DeterministicRandom $rng, array $starters, int $attempts, string $side, array &$box, array &$events): int
    {
        $actualGoals = 0;
        for ($i = 0; $i < $attempts; $i++) {
            $minute = $rng->nextInt(1, 90);
            $isPenalty = $rng->chance(0.10);
            $scorerId = $rng->weightedPick($this->scorerWeights($starters));
            if ($scorerId === null) continue;

            if ($isPenalty) {
                if ($rng->chance(0.78)) {
                    $actualGoals++;
                    $box[$scorerId]['goals']++;
                    $events[] = ['minute' => $minute, 'type' => 'penalty_scored', 'player_id' => $scorerId, 'secondary_player_id' => null, 'side' => $side];
                } else {
                    $box[$scorerId]['penalties_missed']++;
                    $events[] = ['minute' => $minute, 'type' => 'penalty_missed', 'player_id' => $scorerId, 'secondary_player_id' => null, 'side' => $side];
                }
                continue;
            }

            $actualGoals++;
            $box[$scorerId]['goals']++;
            $assistId = null;
            if ($rng->chance(0.65)) {
                $assistCandidates = array_filter($starters, fn ($p) => $p['id'] !== $scorerId);
                $assistId = $rng->weightedPick($this->assistWeights($assistCandidates));
                if ($assistId !== null) $box[$assistId]['assists']++;
            }
            $events[] = ['minute' => $minute, 'type' => 'goal', 'player_id' => $scorerId, 'secondary_player_id' => $assistId, 'side' => $side];
        }
        return $actualGoals;
    }

    private function resolveOwnGoal(DeterministicRandom $rng, array $concedingSideStarters, string $concedingSide, array &$box, array &$events): int
    {
        $defenders = array_values(array_filter($concedingSideStarters, fn ($p) => in_array($p['position'], ['DEF', 'MID'], true))) ?: $concedingSideStarters;
        if (empty($defenders)) return 0;
        $player = $defenders[$rng->nextInt(0, count($defenders) - 1)];
        $box[$player['id']]['own_goals']++;
        $events[] = ['minute' => $rng->nextInt(1, 90), 'type' => 'own_goal', 'player_id' => $player['id'], 'secondary_player_id' => null, 'side' => $concedingSide];
        return 1;
    }

    private function resolveCards(DeterministicRandom $rng, array $starters, string $side, array &$box, array &$events): void
    {
        $yellows = $rng->poisson(1.6);
        for ($i = 0; $i < $yellows; $i++) {
            $playerId = $rng->weightedPick($this->cardWeights($starters));
            if ($playerId === null) continue;
            $box[$playerId]['yellow_cards']++;
            $events[] = ['minute' => $rng->nextInt(1, 90), 'type' => 'yellow', 'player_id' => $playerId, 'secondary_player_id' => null, 'side' => $side];
        }
        if ($rng->chance(0.06)) {
            $playerId = $rng->weightedPick($this->cardWeights($starters));
            if ($playerId !== null) {
                $box[$playerId]['red_cards']++;
                $minute = $rng->nextInt(20, 90);
                $events[] = ['minute' => $minute, 'type' => 'red', 'player_id' => $playerId, 'secondary_player_id' => null, 'side' => $side];
                $box[$playerId]['minutes'] = min($box[$playerId]['minutes'], $minute);
            }
        }
    }

    private function resolveSaves(DeterministicRandom $rng, array $starters, int $goalsConceded, array &$box): void
    {
        $keeper = null;
        foreach ($starters as $player) {
            if ($player['position'] === 'GK') { $keeper = $player; break; }
        }
        if (!$keeper) return;
        $saves = $rng->poisson($goalsConceded * 1.4 + 1.0);
        $box[$keeper['id']]['saves'] = $saves;
    }

    private function resolveSubstitutions(DeterministicRandom $rng, array $side, string $sideLabel, array &$box, array &$events): void
    {
        foreach ($side['starting'] as $player) {
            if ($box[$player['id']]['minutes'] < 90) continue; // already went off via a red card
            if (!$rng->chance(0.18)) continue;

            $minute = $rng->nextInt(55, 88);
            $isInjury = $rng->chance(0.18);
            $box[$player['id']]['minutes'] = $minute;

            $replacement = $this->pickReplacement($rng, $side['bench'], $player['position']);
            if ($isInjury) {
                $events[] = ['minute' => $minute, 'type' => 'injury', 'player_id' => $player['id'], 'secondary_player_id' => null, 'side' => $sideLabel];
            }
            $events[] = ['minute' => $minute, 'type' => 'substitution', 'player_id' => $player['id'], 'secondary_player_id' => $replacement['id'] ?? null, 'side' => $sideLabel];
            if ($replacement) {
                $box[$replacement['id']] ??= $this->blankStat($replacement, false);
                $box[$replacement['id']]['minutes'] = 90 - $minute;
            }
            if ($isInjury) $box[$player['id']]['injured'] = true;
        }
    }

    private function pickReplacement(DeterministicRandom $rng, array $bench, string $preferredPosition): ?array
    {
        $samePosition = array_values(array_filter($bench, fn ($p) => $p['position'] === $preferredPosition));
        $pool = $samePosition ?: array_values(array_filter($bench, fn ($p) => $p['position'] !== 'GK'));
        if (empty($pool)) return null;
        return $pool[$rng->nextInt(0, count($pool) - 1)];
    }

    private function scorerWeights(array $starters): array
    {
        $weights = [];
        foreach ($starters as $player) {
            $w = self::SCORER_WEIGHT[$player['position']] ?? 0;
            if ($w <= 0) continue;
            $weights[$player['id']] = $w * ((float) $player['rating'] / self::RATING_BASELINE);
        }
        return $weights;
    }

    private function assistWeights(array $candidates): array
    {
        $weights = [];
        foreach ($candidates as $player) {
            $w = self::ASSIST_WEIGHT[$player['position']] ?? 0;
            if ($w <= 0) continue;
            $weights[$player['id']] = $w * ((float) $player['rating'] / self::RATING_BASELINE);
        }
        return $weights;
    }

    private function cardWeights(array $starters): array
    {
        $weights = [];
        foreach ($starters as $player) {
            $weights[$player['id']] = self::CARD_WEIGHT[$player['position']] ?? 0.5;
        }
        return $weights;
    }

    private function blankStat(array $player, bool $started): array
    {
        return [
            'id' => $player['id'], 'position' => $player['position'],
            'minutes' => $started ? 90 : 0, 'goals' => 0, 'assists' => 0,
            'yellow_cards' => 0, 'red_cards' => 0, 'own_goals' => 0,
            'penalties_saved' => 0, 'penalties_missed' => 0, 'saves' => 0,
            'goals_conceded' => 0, 'clean_sheet' => false, 'bonus' => 0,
            'started' => $started, 'injured' => false,
        ];
    }

    /** A simple performance index (per the spec) ranks every player who featured; the top 3 distinct scores get bonus 3/2/1, ties sharing the same bonus. */
    private function assignBonus(array &$box): void
    {
        $scored = [];
        foreach ($box as $id => $stat) {
            if ($stat['minutes'] <= 0) continue;
            $index = $stat['goals'] * 6 + $stat['assists'] * 3 + ($stat['clean_sheet'] ? 2 : 0)
                + $stat['saves'] * 0.5 + $stat['penalties_saved'] * 4
                - $stat['yellow_cards'] * 1 - $stat['red_cards'] * 4 - $stat['own_goals'] * 3;
            if ($index > 0) $scored[$id] = $index;
        }
        arsort($scored);
        $distinctValues = array_values(array_unique($scored));
        $tiers = array_slice($distinctValues, 0, 3);
        $bonusForTier = [3, 2, 1];
        foreach ($scored as $id => $value) {
            $tierIndex = array_search($value, $tiers, true);
            if ($tierIndex !== false) $box[$id]['bonus'] = $bonusForTier[$tierIndex];
        }
    }
}
