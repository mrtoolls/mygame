<?php

namespace App\Services\Fantasy;

use App\Models\FantasyPlayerFixtureStat;
use App\Models\FantasyScoringRule;

/**
 * Every fantasy point value lives in ff_scoring_rules (admin-editable, never hardcoded) - this
 * class is the single place that reads those values and turns a player's box score for one
 * fixture into a points total. calculate() is a pure function of (position, stat fields, rule
 * values) so it can be unit tested without touching the database at all.
 */
class FantasyScoring
{
    private const DEFAULTS = [
        'appearance' => 1, 'appearance_60_plus' => 2,
        'goal_gk' => 6, 'goal_def' => 6, 'goal_mid' => 5, 'goal_fwd' => 4,
        'assist' => 3,
        'clean_sheet_gk' => 4, 'clean_sheet_def' => 4, 'clean_sheet_mid' => 1,
        'saves_per_point' => 3, 'penalty_save' => 5, 'penalty_miss' => -2,
        'goals_conceded_per_point' => 2, 'yellow_card' => -1, 'red_card' => -3,
        'own_goal' => -2, 'bonus_max' => 3,
    ];

    /** @return array<string,float> rule key => value, DB values overriding defaults for anything missing/not yet seeded. */
    public function rules(): array
    {
        $fromDb = FantasyScoringRule::pluck('value', 'key')->map(fn ($v) => (float) $v)->all();
        return array_merge(self::DEFAULTS, $fromDb);
    }

    /**
     * Pure scoring math - no DB access. $stats keys: minutes, goals, assists, yellow_cards,
     * red_cards, own_goals, penalties_saved, penalties_missed, saves, goals_conceded, clean_sheet
     * (bool), bonus. $position is GK/DEF/MID/FWD.
     */
    public function calculate(string $position, array $stats, array $rules): int
    {
        $minutes = (int) ($stats['minutes'] ?? 0);
        $points = 0.0;

        if ($minutes >= 60) {
            $points += $rules['appearance_60_plus'];
        } elseif ($minutes > 0) {
            $points += $rules['appearance'];
        }

        $goalValueKey = match ($position) {
            'GK' => 'goal_gk', 'DEF' => 'goal_def', 'MID' => 'goal_mid', 'FWD' => 'goal_fwd',
            default => 'goal_fwd',
        };
        $points += ($rules[$goalValueKey] ?? 0) * (int) ($stats['goals'] ?? 0);
        $points += $rules['assist'] * (int) ($stats['assists'] ?? 0);

        if (!empty($stats['clean_sheet']) && $minutes >= 60) {
            $csKey = match ($position) {
                'GK' => 'clean_sheet_gk', 'DEF' => 'clean_sheet_def', 'MID' => 'clean_sheet_mid',
                default => null,
            };
            if ($csKey !== null) $points += $rules[$csKey];
        }

        if ($position === 'GK') {
            $savesPerPoint = max(1, (int) $rules['saves_per_point']);
            $points += intdiv((int) ($stats['saves'] ?? 0), $savesPerPoint);
            $points += $rules['penalty_save'] * (int) ($stats['penalties_saved'] ?? 0);
        }
        $points += $rules['penalty_miss'] * (int) ($stats['penalties_missed'] ?? 0);

        if (in_array($position, ['GK', 'DEF'], true)) {
            $concededPerPoint = max(1, (int) $rules['goals_conceded_per_point']);
            $points -= intdiv((int) ($stats['goals_conceded'] ?? 0), $concededPerPoint);
        }

        $points += $rules['yellow_card'] * (int) ($stats['yellow_cards'] ?? 0);
        $points += $rules['red_card'] * (int) ($stats['red_cards'] ?? 0);
        $points += $rules['own_goal'] * (int) ($stats['own_goals'] ?? 0);
        $points += min((int) ($stats['bonus'] ?? 0), (int) $rules['bonus_max']);

        return (int) round($points);
    }

    /** Recomputes and persists total_points for one existing stat row, using its related player's position and the current rules. */
    public function recalculateAndSave(FantasyPlayerFixtureStat $stat): int
    {
        $position = $stat->player->position;
        $rules = $this->rules();
        $total = $this->calculate($position, [
            'minutes' => $stat->minutes, 'goals' => $stat->goals, 'assists' => $stat->assists,
            'yellow_cards' => $stat->yellow_cards, 'red_cards' => $stat->red_cards,
            'own_goals' => $stat->own_goals, 'penalties_saved' => $stat->penalties_saved,
            'penalties_missed' => $stat->penalties_missed, 'saves' => $stat->saves,
            'goals_conceded' => $stat->goals_conceded, 'clean_sheet' => $stat->clean_sheet,
            'bonus' => $stat->bonus,
        ], $rules);
        $stat->total_points = $total;
        $stat->save();
        return $total;
    }
}
