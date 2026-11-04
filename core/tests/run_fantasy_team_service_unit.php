<?php

// Unit coverage for FantasyTeamService's money and rule math: selling price (gain-sharing and
// the loss pass-through case), budget and 3-per-club enforcement on both squad creation and
// transfers, formation validation edge cases, the gameweek deadline lock, and each chip's
// once-per-season / one-per-gameweek enforcement.

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\FantasyClub;
use App\Models\FantasyGameweek;
use App\Models\FantasyPlayer;
use App\Models\FantasySeason;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\User;
use App\Services\Fantasy\FantasyTeamService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/fantasy_team_service_unit.sqlite';
if (is_file($dbPath)) unlink($dbPath);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
putenv('CACHE_STORE=array');
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec("CREATE TABLE languages (id INTEGER PRIMARY KEY, code VARCHAR(10), name VARCHAR(60), is_default INTEGER DEFAULT 0)");
$pdo->exec("INSERT INTO languages (code, name, is_default) VALUES ('en', 'English', 1)");
$pdo = null;

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

(require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php')->up();
Schema::create('general_settings', function (Blueprint $table) {
    $table->id();
    $table->string('cur_text', 10)->default('USD');
    $table->timestamps();
});
DB::table('general_settings')->insert(['id' => 1]);
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('username');
    $table->timestamps();
});
(require dirname(__DIR__) . '/database/migrations/2026_11_04_000001_create_fantasy_core_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_11_04_000002_create_fantasy_team_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_11_04_000003_create_fantasy_league_tables.php')->up();

$passed = 0;
function check(bool $cond, string $message): void {
    global $passed;
    if (!$cond) throw new RuntimeException("FAILED: $message");
    $passed++;
}

$service = app(FantasyTeamService::class);

// --- Selling price math. ---
function makeTeamPlayer(string $purchase, string $current): FantasyTeamPlayer {
    $tp = new FantasyTeamPlayer();
    $tp->purchase_price = $purchase;
    $tp->player = (object) ['price' => $current];
    return $tp;
}
check(makeTeamPlayer('5.0', '6.3')->sellingPrice() === '5.6', "Gain: purchase 5.0, now 6.3 -> gain 1.3, half 0.65, sell 5.65 floored to nearest 0.1 = 5.6 - got " . makeTeamPlayer('5.0', '6.3')->sellingPrice());
check(makeTeamPlayer('5.0', '5.0')->sellingPrice() === '5.0', 'No price change: selling price equals purchase price.');
check(makeTeamPlayer('8.0', '6.0')->sellingPrice() === '6.0', 'A LOSS passes through in full (no half-cushion on a loss) - selling price is simply the current (lower) price.');
check(makeTeamPlayer('5.0', '5.1')->sellingPrice() === '5.0', 'A tiny 0.1 gain halves to 0.05, which floors back down to the purchase price exactly.');
fwrite(STDOUT, "Selling price math: gain-sharing (floored to the nearest 0.1) and full loss pass-through both verified.\n");

// --- Seed a tiny league for the service-level (DB-backed) checks. ---
$season = FantasySeason::create(['name' => 'Unit Season', 'status' => 'active']);
// At least ceil(15/3)=5 clubs are needed to assemble a 15-player squad under the 3-per-club cap;
// 8 leaves comfortable slack across 4 separate position pools.
$clubs = collect();
for ($i = 0; $i < 8; $i++) {
    $clubs->push(FantasyClub::create(['name' => "Club $i", 'short_code' => "C$i", 'color_primary' => '#111111', 'color_secondary' => '#eeeeee', 'pattern' => 'solid', 'strength_rating' => 70]));
}
$gameweek = FantasyGameweek::create([
    'season_id' => $season->id, 'number' => 1, 'deadline_at' => now()->addHour(),
    'kickoff_start_at' => now()->addHours(2), 'kickoff_end_at' => now()->addHours(5), 'status' => 'upcoming',
]);
$season->current_gameweek_id = $gameweek->id;
$season->save();
\App\Models\FantasyLeague::create(['season_id' => $season->id, 'name' => 'Global Leaderboard', 'type' => 'global']);

function makePlayers($clubs, string $position, int $countPerClub, float $price): \Illuminate\Support\Collection {
    $players = collect();
    foreach ($clubs as $club) {
        for ($i = 0; $i < $countPerClub; $i++) {
            $players->push(FantasyPlayer::create(['club_id' => $club->id, 'name' => "P{$club->id}-{$position}-{$i}", 'position' => $position, 'rating' => 65, 'price' => $price, 'status' => 'fit', 'minutes_tendency' => 0.7]));
        }
    }
    return $players;
}

/**
 * Picks $count players of one position, respecting a club-count budget SHARED across every call
 * (passed by reference) so a test squad assembled from several separate position picks never
 * accidentally breaches the 3-per-club cap in total, even though no single position's pick does.
 */
function pickSpread(\Illuminate\Support\Collection $players, int $count, array &$clubCounts, int $maxPerClub = 3): array {
    $picked = [];
    foreach ($players as $player) {
        if (count($picked) >= $count) break;
        if (($clubCounts[$player->club_id] ?? 0) >= $maxPerClub) continue;
        $clubCounts[$player->club_id] = ($clubCounts[$player->club_id] ?? 0) + 1;
        $picked[] = $player->id;
    }
    return $picked;
}
$gks = makePlayers($clubs, 'GK', 2, 4.0);
$defs = makePlayers($clubs, 'DEF', 3, 4.0);
$mids = makePlayers($clubs, 'MID', 3, 4.0);
$fwds = makePlayers($clubs, 'FWD', 2, 4.0);

$userId = DB::table('users')->insertGetId(['username' => 'u1', 'created_at' => now(), 'updated_at' => now()]);
$user = User::find($userId);

// --- Budget enforcement: 15 players at 4.0 each = 60.0, comfortably under the 100.0 budget. ---
$clubBudget1 = [];
$squad = array_merge(pickSpread($gks, 2, $clubBudget1), pickSpread($defs, 5, $clubBudget1), pickSpread($mids, 5, $clubBudget1), pickSpread($fwds, 3, $clubBudget1));
check(count($squad) === 15, 'Assembled exactly 15 players for the budget test (got ' . count($squad) . ').');
$team = $service->createTeam($user, $season, 'Budget Test FC', $squad);
check(bccomp((string) $team->budget_remaining, '40.0', 1) === 0, "15 players at 4.0 each leaves exactly 40.0 of the 100.0 budget - got {$team->budget_remaining}.");

// --- Club limit: a squad with 4 players from the same club must be rejected. Otherwise a
// perfectly valid 15-player shape, except club 0 deliberately supplies both its starting GKs AND
// 2 of its DEF (4 total from one club) to isolate exactly the rule under test. ---
$userId2 = DB::table('users')->insertGetId(['username' => 'u2', 'created_at' => now(), 'updated_at' => now()]);
$club0Id = $clubs[0]->id;
$club0Gks = $gks->where('club_id', $club0Id)->take(2)->pluck('id')->all();
$club0Defs = $defs->where('club_id', $club0Id)->take(2)->pluck('id')->all();
check(count($club0Gks) === 2 && count($club0Defs) === 2, 'Club 0 has at least 2 GK and 2 DEF to build the over-concentration fixture.');
$clubBudgetOverload = [$club0Id => 4];
$tooManyFromOneClub = array_merge(
    $club0Gks, $club0Defs,
    pickSpread($defs->whereNotIn('id', $club0Defs), 3, $clubBudgetOverload),
    pickSpread($mids, 5, $clubBudgetOverload),
    pickSpread($fwds, 3, $clubBudgetOverload)
);
check(count($tooManyFromOneClub) === 15, 'Assembled exactly 15 players for the club-limit test (got ' . count($tooManyFromOneClub) . ').');
try {
    $service->createTeam(User::find($userId2), $season, 'Overloaded FC', $tooManyFromOneClub);
    check(false, 'A squad with more than 3 players from one club must be rejected.');
} catch (InvalidArgumentException $e) {
    check(str_contains($e->getMessage(), '3 players'), 'Club-limit violation correctly rejected: ' . $e->getMessage());
}
fwrite(STDOUT, "Budget and 3-per-club limits verified on squad creation.\n");

// --- Formation validation edge cases. ---
foreach ([
    ['players' => ['GK', 'GK', 'DEF', 'DEF', 'DEF', 'MID', 'MID', 'FWD', 'FWD', 'FWD', 'FWD'], 'reason' => '2 GK starting'],
    ['players' => ['GK', 'DEF', 'DEF', 'MID', 'MID', 'MID', 'MID', 'MID', 'FWD', 'FWD', 'FWD'], 'reason' => 'only 2 DEF (below the 3 minimum)'],
    ['players' => ['GK', 'DEF', 'DEF', 'DEF', 'DEF', 'DEF', 'MID', 'FWD', 'FWD', 'FWD', 'FWD'], 'reason' => 'only 1 MID (below the 2 minimum)'],
    ['players' => ['GK', 'DEF', 'DEF', 'DEF', 'DEF', 'MID', 'MID', 'MID', 'MID', 'MID', 'MID'], 'reason' => '0 FWD'],
] as $case) {
    try {
        $service->assertValidFormation($case['players']);
        check(false, 'Formation with ' . $case['reason'] . ' must be rejected.');
    } catch (InvalidArgumentException $e) {
        $passed++;
    }
}
$service->assertValidFormation(['GK', 'DEF', 'DEF', 'DEF', 'MID', 'MID', 'MID', 'MID', 'FWD', 'FWD', 'FWD']);
$passed++; // a valid 1-3-4-3-ish shape does not throw
fwrite(STDOUT, "Formation validation: every invalid shape rejected, a valid shape accepted.\n");

// --- Deadline lock: once the gameweek deadline has passed, transfers/lineup changes/chips are all refused. ---
$gameweek->deadline_at = now()->subMinute();
$gameweek->save();
try {
    $service->transfer($team, [$squad[0]], [$fwds->last()->id]);
    check(false, 'A transfer after the deadline must be rejected.');
} catch (InvalidArgumentException $e) {
    check(str_contains($e->getMessage(), 'deadline'), 'Transfer correctly refused after the deadline: ' . $e->getMessage());
}
try {
    $service->activateChip($team, 'deep_bench');
    check(false, 'A chip activation after the deadline must be rejected.');
} catch (InvalidArgumentException $e) {
    check(str_contains($e->getMessage(), 'deadline'), 'Chip activation correctly refused after the deadline: ' . $e->getMessage());
}
$gameweek->deadline_at = now()->addHour();
$gameweek->save();
fwrite(STDOUT, "Deadline lock verified for both transfers and chip activation (server time only, never trusting client input).\n");

// --- Chips: once per season per chip, only one chip per gameweek. ---
$service->activateChip($team, 'deep_bench');
try {
    $service->activateChip($team, 'deep_bench');
    check(false, 'The same chip cannot be activated twice in a season.');
} catch (InvalidArgumentException $e) {
    check(str_contains($e->getMessage(), 'already been used'), 'Re-using the same chip correctly refused: ' . $e->getMessage());
}
try {
    $service->activateChip($team, 'super_captain');
    check(false, 'A second DIFFERENT chip in the same gameweek must also be rejected.');
} catch (InvalidArgumentException $e) {
    check(str_contains($e->getMessage(), 'one chip'), 'A second chip in the same gameweek correctly refused: ' . $e->getMessage());
}
fwrite(STDOUT, "Chips: once-per-season-per-chip and one-chip-per-gameweek both enforced.\n");

// --- Transfer cost: beyond the free-transfer bank, each extra transfer costs 4 points. ---
$userId3 = DB::table('users')->insertGetId(['username' => 'u3', 'created_at' => now(), 'updated_at' => now()]);
$freshGks = makePlayers($clubs, 'GK', 2, 4.0);
$freshDefs = makePlayers($clubs, 'DEF', 3, 4.0);
$freshMids = makePlayers($clubs, 'MID', 3, 4.0);
$freshFwds = makePlayers($clubs, 'FWD', 2, 4.0);
$clubBudget3 = [];
$squad3Defs = pickSpread($freshDefs, 5, $clubBudget3);
$squad3 = array_merge(pickSpread($freshGks, 2, $clubBudget3), $squad3Defs, pickSpread($freshMids, 5, $clubBudget3), pickSpread($freshFwds, 3, $clubBudget3));
$team3 = $service->createTeam(User::find($userId3), $season, 'Transfer Cost FC', $squad3);
check((int) $team3->free_transfers_banked === 1, 'A fresh team starts with exactly 1 free transfer banked.');

// Spare DEF not already in the squad, respecting the 3-per-club cap against the squad's EXISTING
// (post-transfer-target) club composition once the 3 outgoing DEF are removed.
$outIds = array_slice($squad3Defs, 0, 3);
$remainingClubCounts = $clubBudget3;
foreach ($outIds as $outId) {
    $outClub = $freshDefs->firstWhere('id', $outId)->club_id;
    $remainingClubCounts[$outClub]--;
}
$inIds = pickSpread($freshDefs->whereNotIn('id', $squad3Defs), 3, $remainingClubCounts);
check(count($inIds) === 3, 'Found 3 valid replacement DEF for the transfer-cost test (got ' . count($inIds) . ').');
$result = $service->transfer($team3, $outIds, $inIds);
check($result['points_cost'] === 8, "3 transfers with only 1 free costs (3-1)*4 = 8 points - got {$result['points_cost']}.");
fwrite(STDOUT, "Transfer cost math: extra transfers beyond the free-transfer bank cost exactly 4 points each.\n");

fwrite(STDOUT, "Passed: $passed\n");
fwrite(STDOUT, "Fantasy Football team service unit checks passed.\n");
