<?php

// Pure-rules coverage for the "market runs empty mid-game" bug fix in TableGameRules.php
// (whot/rummy/crazy_eights). TableGameRules has no Eloquent/facade dependencies, so these
// run directly against the class without booting Laravel or touching a database - fast,
// deterministic (every scenario is set up via a hand-built $state, not the real shuffle),
// and exactly mirrors how TableGameService::move() calls into it.

require __DIR__ . '/../app/Games/TableGames/TableGameRules.php';

use App\Games\TableGames\TableGameRules;

$rules = new TableGameRules();
$failures = [];
$pass = 0;

function check(array &$failures, int &$pass, string $label, bool $condition): void
{
    if ($condition) { $pass++; return; }
    $failures[] = $label;
}

// ---------------------------------------------------------------------------
// 1. Reshuffle at draw time (whot, v2): stock empty, discard has cards behind
//    the top - a plain draw must reshuffle and succeed instead of erroring.
// ---------------------------------------------------------------------------
$top = ['shape' => 'Circle', 'number' => 5];
$behindA = ['shape' => 'Cross', 'number' => 2];
$behindB = ['shape' => 'Square', 'number' => 7];
$state = [
    'hands' => [0 => [['shape' => 'Star', 'number' => 3]], 1 => [['shape' => 'Star', 'number' => 4]]],
    'stock' => [],
    'discard' => [$behindA, $behindB, $top],
    'active_shape' => 'Circle',
    'pending_penalty' => null,
    'turn' => 0,
    'moves' => 0,
];
$result = $rules->move('whot', $state, 0, ['type' => 'draw'], [], 2);
check($failures, $pass, 'reshuffle-at-draw: message reports the refill', $result['message'] === 'Market refilled from played cards.');
check($failures, $pass, 'reshuffle-at-draw: top card preserved', count($result['state']['discard']) === 1 && $result['state']['discard'][0] === $top);
check($failures, $pass, 'reshuffle-at-draw: one card drawn into hand', count($result['state']['hands'][0]) === 2);
check($failures, $pass, 'reshuffle-at-draw: stock holds exactly what is left over', count($result['state']['stock']) === 1);
check($failures, $pass, 'reshuffle-at-draw: turn advances', $result['state']['turn'] === 1);

// ---------------------------------------------------------------------------
// 2a. Penalty larger than the stock (whot, v2): a Pick-5 that only 2 cards can
//     cover anywhere - the player draws what exists and the penalty ends.
// ---------------------------------------------------------------------------
$top2 = ['shape' => 'Circle', 'number' => 9];
$state = [
    'hands' => [0 => [['shape' => 'Star', 'number' => 3]], 1 => [['shape' => 'Star', 'number' => 4]]],
    'stock' => [],
    'discard' => [['shape' => 'Cross', 'number' => 1], ['shape' => 'Square', 'number' => 6], $top2],
    'active_shape' => 'Circle',
    'pending_penalty' => ['rank' => 2, 'amount' => 5],
    'turn' => 0,
    'moves' => 0,
];
$result = $rules->move('whot', $state, 0, ['type' => 'draw'], [], 2);
check($failures, $pass, 'penalty-exceeds-stock: drew only what existed (2), not the full 5', count($result['state']['hands'][0]) === 1 + 2);
check($failures, $pass, 'penalty-exceeds-stock: penalty cleared instead of erroring', $result['state']['pending_penalty'] === null);
check($failures, $pass, 'penalty-exceeds-stock: stock and discard both now empty of spares', $result['state']['stock'] === [] && count($result['state']['discard']) === 1);
check($failures, $pass, 'penalty-exceeds-stock: turn still advances', $result['state']['turn'] === 1);

// ---------------------------------------------------------------------------
// 2b. Penalty with truly nothing anywhere (not even for part of it) ends the
//     round on lowest hand total instead of looping/erroring.
// ---------------------------------------------------------------------------
$onlyTop = ['shape' => 'Circle', 'number' => 9];
$state = [
    'hands' => [0 => [['shape' => 'Star', 'number' => 3]], 1 => [['shape' => 'Star', 'number' => 4], ['shape' => 'Cross', 'number' => 10]]],
    'stock' => [],
    'discard' => [$onlyTop],
    'active_shape' => 'Circle',
    'pending_penalty' => ['rank' => 2, 'amount' => 3],
    'turn' => 0,
    'moves' => 0,
];
$result = $rules->move('whot', $state, 0, ['type' => 'draw'], [], 2);
check($failures, $pass, 'penalty-nothing-left: round ends rather than drawing', $result['winner'] === 0 && $result['draw'] === false);
check($failures, $pass, 'penalty-nothing-left: lowest-total winner is seat 0 (3 vs 14)', $result['winner'] === 0);
check($failures, $pass, 'penalty-nothing-left: message explains why', str_contains((string) $result['message'], 'lowest card total'));

// ---------------------------------------------------------------------------
// 3. General Market (card 14) with a low stock deals in rotation order
//    starting right after the dealer, not raw ascending seat order - and the
//    seat that lands on the empty stock gets a reshuffled card, not an error.
// ---------------------------------------------------------------------------
$gmTop = ['shape' => 'Star', 'number' => 1];
$card14 = ['shape' => 'Circle', 'number' => 14];
$stockCardA = ['shape' => 'Cross', 'number' => 6];
$stockCardB = ['shape' => 'Square', 'number' => 11];
$state = [
    'hands' => [0 => [], 1 => [], 2 => [$card14, ['shape' => 'Star', 'number' => 2]], 3 => []],
    'stock' => [$stockCardA, $stockCardB],
    'discard' => [$gmTop],
    'active_shape' => 'Circle',
    'pending_penalty' => null,
    'turn' => 2,
    'moves' => 0,
];
$result = $rules->move('whot', $state, 2, ['type' => 'play', 'card' => 0], [], 2);
$hands = $result['state']['hands'];
check($failures, $pass, 'general-market: dealer (seat 2) keeps the turn', $result['state']['turn'] === 2);
check($failures, $pass, 'general-market: seat 3 (next after dealer) dealt first, from the plain stock', count($hands[3]) === 1 && in_array($hands[3][0], [$stockCardA, $stockCardB], true));
check($failures, $pass, 'general-market: seat 0 dealt second, from the plain stock', count($hands[0]) === 1 && in_array($hands[0][0], [$stockCardA, $stockCardB], true));
check($failures, $pass, 'general-market: seat 1 dealt last and is the one who triggered the reshuffle', count($hands[1]) === 1 && $hands[1][0] === $gmTop);
check($failures, $pass, 'general-market: the just-played 14 stayed on top of the discard through the reshuffle', end($result['state']['discard']) === $card14);
check($failures, $pass, 'general-market: refill message set', $result['message'] === 'Market refilled from played cards.');

// ---------------------------------------------------------------------------
// 4. A Whot (number 20) shape request survives a reshuffle that happens right
//    after it - the active_shape and the new top card must not be disturbed.
// ---------------------------------------------------------------------------
$oldTop = ['shape' => 'Triangle', 'number' => 12];
$card20 = ['shape' => 'Whot', 'number' => 20];
$unplayableForSeat1 = ['shape' => 'Cross', 'number' => 7];
$state = [
    'hands' => [0 => [$card20, ['shape' => 'Star', 'number' => 1]], 1 => [$unplayableForSeat1]],
    'stock' => [],
    'discard' => [$oldTop],
    'active_shape' => 'Triangle',
    'pending_penalty' => null,
    'turn' => 0,
    'moves' => 0,
];
$afterRequest = $rules->move('whot', $state, 0, ['type' => 'play', 'card' => 0, 'shape' => 'Star'], [], 2);
check($failures, $pass, 'whot-request: active_shape becomes the requested shape', $afterRequest['state']['active_shape'] === 'Star');
check($failures, $pass, 'whot-request: turn passes to seat 1', $afterRequest['state']['turn'] === 1);
$afterDraw = $rules->move('whot', $afterRequest['state'], 1, ['type' => 'draw'], [], 2);
check($failures, $pass, 'whot-request-survives-reshuffle: active_shape still Star after the reshuffle', $afterDraw['state']['active_shape'] === 'Star');
check($failures, $pass, 'whot-request-survives-reshuffle: the Whot(20) card stayed on top', end($afterDraw['state']['discard']) === $card20);
check($failures, $pass, 'whot-request-survives-reshuffle: reshuffle actually fired', $afterDraw['message'] === 'Market refilled from played cards.');

// ---------------------------------------------------------------------------
// 5. No cards left anywhere ends the round - v2 settles by lowest hand total
//    (with a tie falling back to the existing draw/refund path), v1 keeps its
//    original plain draw.
// ---------------------------------------------------------------------------
$lastTop = ['shape' => 'Circle', 'number' => 9];
$stateWinner = [
    'hands' => [0 => [['shape' => 'Star', 'number' => 3]], 1 => [['shape' => 'Star', 'number' => 4], ['shape' => 'Cross', 'number' => 10]]],
    'stock' => [], 'discard' => [$lastTop], 'active_shape' => 'Circle', 'pending_penalty' => null, 'turn' => 0, 'moves' => 0,
];
$result = $rules->move('whot', $stateWinner, 0, ['type' => 'draw'], [], 2);
check($failures, $pass, 'no-cards-left v2: lower hand total (3) wins, not an error', $result['winner'] === 0 && $result['draw'] === false);

$stateTied = [
    'hands' => [0 => [['shape' => 'Star', 'number' => 5]], 1 => [['shape' => 'Cross', 'number' => 5]]],
    'stock' => [], 'discard' => [$lastTop], 'active_shape' => 'Circle', 'pending_penalty' => null, 'turn' => 0, 'moves' => 0,
];
$result = $rules->move('whot', $stateTied, 0, ['type' => 'draw'], [], 2);
check($failures, $pass, 'no-cards-left v2 tie: falls back to the existing draw/refund path', $result['winner'] === null && $result['draw'] === true);

$stateV1 = [
    'hands' => [0 => [['shape' => 'Star', 'number' => 3]], 1 => [['shape' => 'Star', 'number' => 4]]],
    'stock' => [], 'discard' => [$lastTop], 'active_shape' => 'Circle', 'pending_penalty' => null, 'turn' => 0, 'moves' => 0,
];
$result = $rules->move('whot', $stateV1, 0, ['type' => 'draw'], [], 1);
check($failures, $pass, 'no-cards-left v1: keeps the original plain draw (not lowest-hand settlement)', $result['winner'] === null && $result['draw'] === true);
check($failures, $pass, 'no-cards-left v1: message explains why', str_contains((string) $result['message'], 'ends in a draw'));

// ---------------------------------------------------------------------------
// Rummy: reshuffle at draw time, and no cards left ends in a draw.
// ---------------------------------------------------------------------------
$rummyTop = ['rank' => '9', 'suit' => 'H'];
$state = [
    'hands' => [0 => [['rank' => '2', 'suit' => 'S']], 1 => [['rank' => '3', 'suit' => 'S']]],
    'stock' => [],
    'discard' => [['rank' => '4', 'suit' => 'C'], ['rank' => '5', 'suit' => 'D'], $rummyTop],
    'turn' => 0, 'phase' => 'draw', 'moves' => 0,
];
$result = $rules->move('rummy', $state, 0, ['type' => 'draw_stock'], [], 1);
check($failures, $pass, 'rummy reshuffle-at-draw: message reports the refill', $result['message'] === 'Market refilled from played cards.');
check($failures, $pass, 'rummy reshuffle-at-draw: top card preserved', count($result['state']['discard']) === 1 && $result['state']['discard'][0] === $rummyTop);
check($failures, $pass, 'rummy reshuffle-at-draw: a card was drawn', count($result['state']['hands'][0]) === 2);

$state = [
    'hands' => [0 => [['rank' => '2', 'suit' => 'S']], 1 => [['rank' => '3', 'suit' => 'S']]],
    'stock' => [], 'discard' => [$rummyTop], 'turn' => 0, 'phase' => 'draw', 'moves' => 0,
];
$result = $rules->move('rummy', $state, 0, ['type' => 'draw_stock'], [], 1);
check($failures, $pass, 'rummy no-cards-left: ends in a draw instead of erroring', $result['winner'] === null && $result['draw'] === true);

// ---------------------------------------------------------------------------
// Crazy Eights: reshuffle at draw time, and the pass-based exhaustion ending.
// ---------------------------------------------------------------------------
$ceTop = ['rank' => '9', 'suit' => 'H'];
$state = [
    'hands' => [0 => [['rank' => '2', 'suit' => 'S']], 1 => [['rank' => '3', 'suit' => 'S']]],
    'stock' => [],
    'discard' => [['rank' => '4', 'suit' => 'C'], ['rank' => '5', 'suit' => 'D'], $ceTop],
    'top' => $ceTop, 'current_suit' => 'H', 'passes' => 0, 'turn' => 0, 'moves' => 0,
];
$result = $rules->move('crazy_eights', $state, 0, ['type' => 'draw'], [0, 1], 1);
check($failures, $pass, 'crazy_eights reshuffle-at-draw: top card preserved', end($result['state']['discard']) === $ceTop || count($result['state']['discard']) === 1);
check($failures, $pass, 'crazy_eights reshuffle-at-draw: a card was drawn', count($result['state']['hands'][0]) === 2);

$state = [
    'hands' => [0 => [['rank' => '2', 'suit' => 'S']], 1 => [['rank' => '3', 'suit' => 'S']]],
    'stock' => [], 'discard' => [$ceTop], 'top' => $ceTop, 'current_suit' => 'H', 'passes' => 1, 'turn' => 0, 'moves' => 0,
];
$result = $rules->move('crazy_eights', $state, 0, ['type' => 'pass'], [0, 1], 1);
check($failures, $pass, 'crazy_eights no-cards-left: both seats passed in a row ends in a draw', $result['winner'] === null && $result['draw'] === true);

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------
echo "Passed: {$pass}\n";
if ($failures) {
    echo "FAILED (" . count($failures) . "):\n";
    foreach ($failures as $failure) echo " - {$failure}\n";
    exit(1);
}
echo "All whot/rummy/crazy_eights stock-reshuffle checks passed.\n";
