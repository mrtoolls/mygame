<?php

namespace App\Games\TableGames\Snooker;

use App\Games\TableGames\Snooker\SnookerConfig as C;

/**
 * A simple, easy-strength heuristic opponent for practice tables - deliberately not a serious
 * engine (no search/lookahead, no safety play, no break-building strategy). It picks the nearest
 * legal ball-on it has a clear line of sight to (falling back to the nearest legal ball at all if
 * every one is snookered), aims straight at its centre, and plays with a modest, slightly
 * randomised amount of power - enough to look like a real (if not very good) player without
 * needing anything resembling real AI. Produces the same {angle,power,spinX,spinY[,nominate]}
 * shape a human's client sends, so it goes through the exact same SnookerRules::applyShot() path.
 */
class SnookerBot
{
    private SnookerRules $rules;

    public function __construct(?SnookerRules $rules = null)
    {
        $this->rules = $rules ?? new SnookerRules();
    }

    public function chooseShot(array $state): array
    {
        $balls = $state['balls'];
        $cue = $balls['cue'];
        $ballOn = !empty($state['awaiting_nomination']) ? C::COLOUR_ORDER[0] : $state['ball_on'];

        $targets = $ballOn === 'red' || $ballOn === 'any'
            ? array_filter($balls, fn ($b, $id) => str_starts_with($id, 'red') && !$b['potted'], ARRAY_FILTER_USE_BOTH)
            : (isset($balls[$ballOn]) && !$balls[$ballOn]['potted'] ? [$ballOn => $balls[$ballOn]] : []);
        if (empty($targets)) {
            $targets = array_filter($balls, fn ($b, $id) => $id !== 'cue' && !$b['potted'], ARRAY_FILTER_USE_BOTH);
        }

        // Prefer a target with a clear line of sight (so the bot doesn't foul every time it's
        // snookered) but fall back to the nearest target regardless if every one is blocked -
        // an easy bot is allowed to occasionally foul from a snooker, a real player would too.
        $clear = array_filter($targets, fn ($ball, $id) => $this->hasClearPath($balls, 'cue', $id), ARRAY_FILTER_USE_BOTH);
        $pool = $clear ?: $targets;

        $nearestId = null; $nearestDist = INF;
        foreach ($pool as $id => $ball) {
            $dx = $ball['x'] - $cue['x']; $dy = $ball['y'] - $cue['y'];
            $dist = $dx * $dx + $dy * $dy;
            if ($dist < $nearestDist) { $nearestDist = $dist; $nearestId = $id; }
        }

        $angle = $nearestId ? atan2($balls[$nearestId]['y'] - $cue['y'], $balls[$nearestId]['x'] - $cue['x']) : 0.0;
        // A small random wobble keeps every shot from looking robotically identical - still an
        // "easy" opponent, not a precision one.
        $angle += (mt_rand(-40, 40) / 1000);
        $power = 0.28 + (mt_rand(0, 180) / 1000);

        $shot = ['angle' => $angle, 'power' => min(0.65, $power), 'spinX' => 0.0, 'spinY' => 0.0];
        if (!empty($state['cue_ball_in_hand'])) {
            $shot['cue_placement'] = ['x' => C::BAULK_LINE_X - C::D_RADIUS * 0.4, 'y' => C::TABLE_HEIGHT / 2 + mt_rand(-150, 150)];
        }
        if (!empty($state['awaiting_nomination'])) {
            $shot['nominate'] = C::COLOUR_ORDER[0];
        }

        return $shot;
    }

    private function hasClearPath(array $balls, string $fromId, string $toId): bool
    {
        $from = $balls[$fromId]; $to = $balls[$toId];
        $dx = $to['x'] - $from['x']; $dy = $to['y'] - $from['y'];
        $len2 = $dx * $dx + $dy * $dy;
        if ($len2 < 1e-6) return true;
        $r2 = (2 * C::BALL_RADIUS) ** 2;
        foreach ($balls as $id => $ball) {
            if ($id === $fromId || $id === $toId || $ball['potted']) continue;
            $px = $ball['x'] - $from['x']; $py = $ball['y'] - $from['y'];
            $t = max(0, min(1, ($px * $dx + $py * $dy) / $len2));
            $closestX = $from['x'] + $dx * $t; $closestY = $from['y'] + $dy * $t;
            $ddx = $ball['x'] - $closestX; $ddy = $ball['y'] - $closestY;
            if ($ddx * $ddx + $ddy * $ddy < $r2) return false;
        }
        return true;
    }
}
