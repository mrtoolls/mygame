<?php

// Pure-math unit tests for the Fantasy Football engine's scoring and simulation building blocks -
// none of these classes touch the database, so this runs without any schema setup. Covers every
// scoring rule, every auto-substitution case (including the formation-legality check and the
// bench-GK-only-replaces-GK rule), captain/vice-captain fallback, each chip's scoring effect,
// the deterministic RNG's reproducibility, the match simulator's event-count bookkeeping and
// impossible-event guard, and the tiered contest payout math including tie averaging.

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\DeterministicRandom as SupportDeterministicRandom;
use App\Services\Fantasy\FantasyContestPayoutMath;
use App\Services\Fantasy\FantasyLineupScorer;
use App\Services\Fantasy\FantasyScoring;
use App\Services\Fantasy\MatchSimulator;

$passed = 0;
function check(bool $cond, string $message): void {
    global $passed;
    if (!$cond) throw new RuntimeException("FAILED: $message");
    $passed++;
}

// --- FantasyScoring: every rule, in isolation. ---
$scoring = new FantasyScoring();
$ref = new ReflectionClass($scoring);
$rules = $ref->getConstant('DEFAULTS');

check($scoring->calculate('MID', ['minutes' => 0], $rules) === 0, 'A player who did not play scores 0.');
check($scoring->calculate('MID', ['minutes' => 45], $rules) === 1, '1-59 minutes is a 1-point appearance.');
check($scoring->calculate('MID', ['minutes' => 60], $rules) === 2, '60+ minutes is a 2-point appearance (replacing, not adding to, the 1-point appearance).');
check($scoring->calculate('GK', ['minutes' => 90, 'goals' => 1], $rules) === 2 + 6, 'A GK goal is worth 6.');
check($scoring->calculate('DEF', ['minutes' => 90, 'goals' => 1], $rules) === 2 + 6, 'A DEF goal is worth 6.');
check($scoring->calculate('MID', ['minutes' => 90, 'goals' => 1], $rules) === 2 + 5, 'A MID goal is worth 5.');
check($scoring->calculate('FWD', ['minutes' => 90, 'goals' => 1], $rules) === 2 + 4, 'A FWD goal is worth 4.');
check($scoring->calculate('MID', ['minutes' => 90, 'assists' => 2], $rules) === 2 + 6, 'Each assist is worth 3.');
check($scoring->calculate('GK', ['minutes' => 90, 'clean_sheet' => true], $rules) === 2 + 4, 'GK clean sheet (60+ minutes) is worth 4.');
check($scoring->calculate('DEF', ['minutes' => 90, 'clean_sheet' => true], $rules) === 2 + 4, 'DEF clean sheet (60+ minutes) is worth 4.');
check($scoring->calculate('MID', ['minutes' => 90, 'clean_sheet' => true], $rules) === 2 + 1, 'MID clean sheet (60+ minutes) is worth 1.');
check($scoring->calculate('FWD', ['minutes' => 90, 'clean_sheet' => true], $rules) === 2, 'FWD gets no clean sheet bonus.');
check($scoring->calculate('MID', ['minutes' => 45, 'clean_sheet' => true], $rules) === 1, 'A clean sheet under 60 minutes earns no clean-sheet bonus.');
check($scoring->calculate('GK', ['minutes' => 90, 'saves' => 8], $rules) === 2 + 2, '8 saves at 1-per-3 rounds down to 2 points (not 3).');
check($scoring->calculate('GK', ['minutes' => 90, 'penalties_saved' => 1], $rules) === 2 + 5, 'A penalty save is worth 5.');
check($scoring->calculate('FWD', ['minutes' => 90, 'penalties_missed' => 1], $rules) === 2 - 2, 'A penalty miss costs 2, for any position.');
check($scoring->calculate('DEF', ['minutes' => 90, 'goals_conceded' => 3], $rules) === 2 - 1, '3 goals conceded at 1-per-2 rounds down to -1 (not -1.5).');
check($scoring->calculate('DEF', ['minutes' => 90, 'goals_conceded' => 1], $rules) === 2, '1 goal conceded (under the 2-per-point threshold) costs nothing yet.');
check($scoring->calculate('FWD', ['minutes' => 90, 'goals_conceded' => 4], $rules) === 2, 'Goals-conceded penalty never applies to MID/FWD.');
check($scoring->calculate('MID', ['minutes' => 90, 'yellow_cards' => 1], $rules) === 2 - 1, 'A yellow card costs 1.');
check($scoring->calculate('MID', ['minutes' => 90, 'red_cards' => 1], $rules) === 2 - 3, 'A red card costs 3.');
check($scoring->calculate('DEF', ['minutes' => 90, 'own_goals' => 1], $rules) === 2 - 2, 'An own goal costs 2.');
check($scoring->calculate('MID', ['minutes' => 90, 'bonus' => 3], $rules) === 2 + 3, 'Bonus up to the configured max is added directly.');
check($scoring->calculate('MID', ['minutes' => 90, 'bonus' => 9], $rules) === 2 + 3, 'Bonus is capped at bonus_max even if a caller passes more.');
fwrite(STDOUT, "FantasyScoring: every scoring rule verified in isolation.\n");

// --- FantasyLineupScorer: auto-subs, captaincy, chips. ---
$lineup = new FantasyLineupScorer();
function ffPlayer($id, $pos, $role, $points, $minutes, $captain = false, $vice = false, $benchOrder = null) {
    return ['player_id' => $id, 'position' => $pos, 'squad_role' => $role, 'bench_order' => $benchOrder, 'is_captain' => $captain, 'is_vice_captain' => $vice, 'points' => $points, 'minutes' => $minutes];
}
$baseSquad = [
    ffPlayer(1, 'GK', 'starting', 6, 90),
    ffPlayer(2, 'DEF', 'starting', 2, 90), ffPlayer(3, 'DEF', 'starting', 8, 90), ffPlayer(4, 'DEF', 'starting', 2, 90), ffPlayer(5, 'DEF', 'starting', 2, 90),
    ffPlayer(6, 'MID', 'starting', 5, 90, true), ffPlayer(7, 'MID', 'starting', 3, 90, false, true),
    ffPlayer(8, 'MID', 'starting', 2, 90), ffPlayer(9, 'MID', 'starting', 1, 90),
    ffPlayer(10, 'FWD', 'starting', 4, 90), ffPlayer(11, 'FWD', 'starting', 2, 90),
    ffPlayer(12, 'GK', 'bench', 0, 0),
    ffPlayer(13, 'DEF', 'bench', 1, 90, false, false, 0),
    ffPlayer(14, 'MID', 'bench', 3, 90, false, false, 1),
    ffPlayer(15, 'FWD', 'bench', 2, 90, false, false, 2),
];

$result = $lineup->score($baseSquad);
check($result['total'] === 42, "Full-strength captain-doubled total is 42 (37 base + 5 captain extra) - got {$result['total']}.");
check(empty($result['substitutions']), 'No substitutions when every starter played.');

$blankCaptain = $baseSquad;
$blankCaptain[5] = ffPlayer(6, 'MID', 'starting', 0, 0, true);
$r2 = $lineup->score($blankCaptain);
check($r2['captain_id'] === 7, 'The vice-captain takes over when the captain plays no minutes.');
check(count($r2['substitutions']) === 1 && $r2['substitutions'][0] === ['out' => 6, 'in' => 13], 'The blank MID is auto-subbed for the first eligible bench outfield player (DEF13), keeping the formation legal.');

$blankGkNoBenchGk = $baseSquad;
$blankGkNoBenchGk[0] = ffPlayer(1, 'GK', 'starting', 0, 0);
$r3 = $lineup->score($blankGkNoBenchGk);
check(empty($r3['substitutions']), 'A blank GK with a also-blank bench GK gets no substitution (bench GK only replaces a GK).');

$blankGkWithBenchGk = $baseSquad;
$blankGkWithBenchGk[0] = ffPlayer(1, 'GK', 'starting', 0, 0);
$blankGkWithBenchGk[11] = ffPlayer(12, 'GK', 'bench', 5, 90);
$r4 = $lineup->score($blankGkWithBenchGk);
check(count($r4['substitutions']) === 1 && $r4['substitutions'][0] === ['out' => 1, 'in' => 12], 'A blank GK is replaced by the bench GK when the bench GK played.');

$bothBlank = $baseSquad;
$bothBlank[5] = ffPlayer(6, 'MID', 'starting', 0, 0, true);
$bothBlank[6] = ffPlayer(7, 'MID', 'starting', 0, 0, false, true);
$r5 = $lineup->score($bothBlank);
check($r5['captain_id'] === null, 'No captaincy bonus when both captain and vice-captain blank.');

$r6 = $lineup->score($baseSquad, ['super_captain' => true]);
check($r6['total'] === 47, "Super Captain triples instead of doubles (37 base + 10 extra = 47) - got {$r6['total']}.");

$r7 = $lineup->score($blankCaptain, ['deep_bench' => true]);
check($r7['bench_points'] === 5, "Deep Bench sums every unused bench player's points (GK12=0 + MID14=3 + FWD15=2 = 5; DEF13 excluded since it was already auto-subbed in) - got {$r7['bench_points']}.");

// Formation-legality is actually enforced, not just "first bench player wins": a starting line at
// 3 DEF (the minimum) and 5 MID (the maximum) has a DEF blank - the first two bench candidates in
// order (a MID, then a FWD) would both produce an illegal formation if swapped straight in (MID
// would hit 6, or DEF would drop to 2), so both must be skipped in favor of the third candidate
// (a DEF, a legal like-for-like swap).
$skipsIllegalCandidates = [
    ffPlayer(1, 'GK', 'starting', 1, 90),
    ffPlayer(2, 'DEF', 'starting', 1, 90), ffPlayer(3, 'DEF', 'starting', 1, 90), ffPlayer(4, 'DEF', 'starting', 0, 0),
    ffPlayer(5, 'MID', 'starting', 1, 90), ffPlayer(6, 'MID', 'starting', 1, 90), ffPlayer(7, 'MID', 'starting', 1, 90), ffPlayer(8, 'MID', 'starting', 1, 90), ffPlayer(9, 'MID', 'starting', 1, 90, true),
    ffPlayer(10, 'FWD', 'starting', 1, 90), ffPlayer(11, 'FWD', 'starting', 1, 90, false, true),
    ffPlayer(12, 'GK', 'bench', 0, 0),
    ffPlayer(13, 'MID', 'bench', 9, 90, false, false, 0), // first in order - would push MID to 6 (illegal) and DEF to 2 (also illegal)
    ffPlayer(14, 'FWD', 'bench', 9, 90, false, false, 1), // second in order - would push FWD to 3 (legal on its own) but DEF still drops to 2 (illegal)
    ffPlayer(15, 'DEF', 'bench', 9, 90, false, false, 2), // third in order - a like-for-like DEF swap, DEF count stays at 3, legal
];
$r8 = $lineup->score($skipsIllegalCandidates);
check(count($r8['substitutions']) === 1 && $r8['substitutions'][0] === ['out' => 4, 'in' => 15], 'Two formation-illegal bench candidates are skipped in order before the legal one (a like-for-like DEF) is used - got ' . json_encode($r8['substitutions']));
fwrite(STDOUT, "FantasyLineupScorer: auto-subs, formation legality, captain fallback, and both chips verified.\n");

// --- DeterministicRandom: reproducibility. ---
$r = new SupportDeterministicRandom('seed-a');
$r2same = new SupportDeterministicRandom('seed-a');
$r3diff = new SupportDeterministicRandom('seed-b');
$seqA = []; $seqB = []; $seqC = [];
for ($i = 0; $i < 5; $i++) { $seqA[] = $r->nextFloat(); $seqB[] = $r2same->nextFloat(); $seqC[] = $r3diff->nextFloat(); }
check($seqA === $seqB, 'The same seed produces an identical sequence.');
check($seqA !== $seqC, 'A different seed produces a different sequence.');
fwrite(STDOUT, "DeterministicRandom: reproducible from its seed, independent of PHP's global RNG state.\n");

// --- MatchSimulator: a real, larger stress run - no impossible events, internally consistent bookkeeping, determinism holds end to end. ---
function ffMakeRoster(string $tag): \Illuminate\Support\Collection {
    $players = [];
    $id = 1;
    foreach (array_merge(array_fill(0, 3, 'GK'), array_fill(0, 8, 'DEF'), array_fill(0, 8, 'MID'), array_fill(0, 6, 'FWD')) as $pos) {
        $players[] = ['id' => $tag . '-' . $id++, 'position' => $pos, 'rating' => 55 + ($id * 7) % 35, 'minutes_tendency' => 0.6, 'is_active' => true, 'status' => 'fit'];
    }
    return collect($players);
}
$simulator = new MatchSimulator();
$homeClub = ['strength_rating' => 72];
$awayClub = ['strength_rating' => 69];
$totalGoals = 0;
$n = 380; // the spec's own stress-test size - a full simulated season's worth of fixtures
for ($i = 0; $i < $n; $i++) {
    $rng = new SupportDeterministicRandom("stress-$i");
    $result = $simulator->simulate($rng, $homeClub, ffMakeRoster('H' . $i), $awayClub, ffMakeRoster('A' . $i));
    $totalGoals += $result['home_score'] + $result['away_score'];
    foreach ($result['events'] as $event) {
        check($event['minute'] >= 1 && $event['minute'] <= 90, "Event minute {$event['minute']} is within [1,90] (fixture $i).");
    }
    $scoredGoals = array_sum(array_column($result['player_stats'], 'goals')) + array_sum(array_column($result['player_stats'], 'own_goals'));
    check($scoredGoals === $result['home_score'] + $result['away_score'], "Fixture $i: recorded goals + own goals sum to the final score.");
}
$average = $totalGoals / $n;
check($average > 2.2 && $average < 3.2, "Average goals per match across $n simulated fixtures is within a realistic band of 2.7 (got " . round($average, 3) . ").");
fwrite(STDOUT, "MatchSimulator stress test: $n fixtures (the spec's own 380-fixture target), average " . round($average, 3) . " goals/match, no impossible events, consistent bookkeeping throughout.\n");

$detRng1 = new SupportDeterministicRandom('determinism-full');
$detRng2 = new SupportDeterministicRandom('determinism-full');
$rosterH = ffMakeRoster('DH');
$rosterA = ffMakeRoster('DA');
$res1 = $simulator->simulate($detRng1, $homeClub, $rosterH, $awayClub, $rosterA);
$res2 = $simulator->simulate($detRng2, $homeClub, $rosterH, $awayClub, $rosterA);
check($res1 === $res2, 'The full simulated result (score, events, box scores) is byte-identical given the same seed and rosters.');
fwrite(STDOUT, "MatchSimulator determinism confirmed end to end - the exact mechanism \"Verify this match\" relies on.\n");

// --- FantasyContestPayoutMath: tiered payouts and tie averaging. ---
$tiers = [['top_percent' => 10, 'share_percent' => 50], ['top_percent' => 30, 'share_percent' => 30], ['top_percent' => 100, 'share_percent' => 20]];
$payouts = FantasyContestPayoutMath::tieredPayouts(10, $tiers, '1000.00000000');
$sum = '0';
foreach ($payouts as $amount) $sum = bcadd($sum, $amount, 8);
check(bccomp($sum, '1000.00000000', 8) === 0, "Tiered payouts across all positions sum to exactly the distributable pool - got $sum.");
check(bccomp($payouts[1], '500.00000000', 8) === 0, 'The sole top-10% position gets the full 50% tier share.');
check(bccomp($payouts[2], $payouts[3], 8) === 0, 'Positions within the same tier split that tier\'s share equally.');

$avg = FantasyContestPayoutMath::averageTiedPositions($payouts, [[1], [2, 3], [4], [5], [6], [7], [8], [9], [10]]);
check(bccomp($avg[2], $avg[3], 8) === 0, 'Tied positions receive identical payouts after averaging.');
$sumAfter = '0';
foreach ($avg as $amount) $sumAfter = bcadd($sumAfter, $amount, 8);
check(bccomp($sumAfter, '1000.00000000', 8) === 0, 'Averaging within tie groups preserves the total payout exactly.');
fwrite(STDOUT, "FantasyContestPayoutMath: tiered distribution and tie-averaging verified exact to 8 decimals.\n");

fwrite(STDOUT, "Passed: $passed\n");
fwrite(STDOUT, "Fantasy Football scoring and simulation unit checks passed.\n");
