<?php

namespace App\Services\Fantasy;

/**
 * Pure squad-scoring math for one gameweek - no database access, so every rule (auto-subs,
 * captain fallback, chip effects) is independently unit-testable. Takes a plain array describing
 * the 15-player squad and each player's already-known fixture points/minutes for this gameweek,
 * and returns exactly how many points the team actually scores.
 */
class FantasyLineupScorer
{
    /**
     * @param array $squad 15 rows: ['player_id','position' (GK/DEF/MID/FWD),'squad_role' (starting/bench),
     *   'bench_order' (0-2 for outfield bench, null for bench GK),'is_captain','is_vice_captain',
     *   'points' (int, this gameweek),'minutes' (int, this gameweek)]
     * @param array $chips e.g. ['deep_bench' => bool, 'super_captain' => bool]
     */
    public function score(array $squad, array $chips = []): array
    {
        $starting = array_values(array_filter($squad, fn ($p) => $p['squad_role'] === 'starting'));
        $bench = array_values(array_filter($squad, fn ($p) => $p['squad_role'] === 'bench'));
        usort($bench, fn ($a, $b) => ($a['bench_order'] ?? 99) <=> ($b['bench_order'] ?? 99));

        [$finalXi, $substitutions, $usedBenchIds] = $this->applyAutoSubstitutions($starting, $bench);

        $basePoints = array_sum(array_column($finalXi, 'points'));

        $captain = $this->find($squad, fn ($p) => !empty($p['is_captain']));
        $vice = $this->find($squad, fn ($p) => !empty($p['is_vice_captain']));
        $bonusPlayer = null;
        if ($captain && (int) $captain['minutes'] > 0) {
            $bonusPlayer = $captain;
        } elseif ($vice && (int) $vice['minutes'] > 0) {
            $bonusPlayer = $vice;
        }
        $multiplier = !empty($chips['super_captain']) ? 3 : 2;
        $captainExtra = $bonusPlayer ? (int) $bonusPlayer['points'] * ($multiplier - 1) : 0;

        $benchPoints = 0;
        if (!empty($chips['deep_bench'])) {
            foreach ($bench as $player) {
                if (!in_array($player['player_id'], $usedBenchIds, true)) {
                    $benchPoints += (int) $player['points'];
                }
            }
        }

        return [
            'final_xi_ids' => array_column($finalXi, 'player_id'),
            'base_points' => $basePoints,
            'captain_id' => $bonusPlayer['player_id'] ?? null,
            'captain_multiplier' => $bonusPlayer ? $multiplier : 1,
            'captain_extra' => $captainExtra,
            'bench_points' => $benchPoints,
            'substitutions' => $substitutions,
            'total' => $basePoints + $captainExtra + $benchPoints,
        ];
    }

    /**
     * Real-FPL-style auto-substitution: every starter with 0 minutes is a "blank" that needs
     * replacing. A blank GK can only be replaced by the bench GK. A blank outfield player tries
     * each bench outfield player in bench order, accepting the first one whose swap keeps the
     * resulting XI a legal formation (1 GK, 3-5 DEF, 2-5 MID, 1-3 FWD, 11 total) - exactly
     * mirroring how a real fantasy platform's auto-sub engine works, not just "fill blanks
     * greedily" (which can produce an illegal back line).
     *
     * @return array{0: array, 1: array, 2: array} [finalXi, substitutionLog, usedBenchPlayerIds]
     */
    private function applyAutoSubstitutions(array $starting, array $bench): array
    {
        $finalXi = $starting;
        $usedBenchIds = [];
        $subLog = [];

        $benchGk = $this->find($bench, fn ($p) => $p['position'] === 'GK');
        $benchOutfield = array_values(array_filter($bench, fn ($p) => $p['position'] !== 'GK'));

        foreach ($finalXi as $index => $player) {
            if ((int) $player['minutes'] > 0) continue; // not a blank

            if ($player['position'] === 'GK') {
                if ($benchGk && (int) $benchGk['minutes'] > 0 && !in_array($benchGk['player_id'], $usedBenchIds, true)) {
                    $subLog[] = ['out' => $player['player_id'], 'in' => $benchGk['player_id']];
                    $finalXi[$index] = $benchGk;
                    $usedBenchIds[] = $benchGk['player_id'];
                }
                continue;
            }

            foreach ($benchOutfield as $candidate) {
                if (in_array($candidate['player_id'], $usedBenchIds, true)) continue;
                if ((int) $candidate['minutes'] <= 0) continue;
                $hypothetical = $finalXi;
                $hypothetical[$index] = $candidate;
                if ($this->isLegalFormation($hypothetical)) {
                    $subLog[] = ['out' => $player['player_id'], 'in' => $candidate['player_id']];
                    $finalXi[$index] = $candidate;
                    $usedBenchIds[] = $candidate['player_id'];
                    break;
                }
            }
        }

        return [$finalXi, $subLog, $usedBenchIds];
    }

    private function isLegalFormation(array $xi): bool
    {
        $counts = ['GK' => 0, 'DEF' => 0, 'MID' => 0, 'FWD' => 0];
        foreach ($xi as $player) {
            $counts[$player['position']] = ($counts[$player['position']] ?? 0) + 1;
        }
        return count($xi) === 11
            && $counts['GK'] === 1
            && $counts['DEF'] >= 3 && $counts['DEF'] <= 5
            && $counts['MID'] >= 2 && $counts['MID'] <= 5
            && $counts['FWD'] >= 1 && $counts['FWD'] <= 3;
    }

    private function find(array $rows, callable $predicate): ?array
    {
        foreach ($rows as $row) {
            if ($predicate($row)) return $row;
        }
        return null;
    }
}
