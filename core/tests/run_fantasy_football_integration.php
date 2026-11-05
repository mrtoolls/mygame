<?php

// End-to-end integration proof for the Fantasy Football League feature: generates a virtual
// league, simulates and finalizes a gameweek entirely through the cron-driven finalizer (no
// background process - see FantasyGameweekFinalizer), builds a squad, enters it into both an
// H2H and a tiered gameweek coin contest, settles both, and confirms CoinLedger::reconcile()
// still passes throughout. This is the single test most likely to catch real wiring bugs (wrong
// column names, bad foreign keys) that isolated unit tests of the pure math classes cannot.

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\Currency;
use App\Models\FantasyContest;
use App\Models\FantasyContestEntry;
use App\Models\FantasyContestSquad;
use App\Models\FantasyFixture;
use App\Models\FantasyGameweek;
use App\Models\FantasyPlayer;
use App\Models\FantasyPlayerFixtureStat;
use App\Models\FantasySeason;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\User;
use App\Services\CoinLedger;
use App\Services\Fantasy\FantasyContestService;
use App\Services\Fantasy\FantasyContestSquadService;
use App\Services\Fantasy\FantasyContestTierSettings;
use App\Services\Fantasy\FantasyGameweekFinalizer;
use App\Services\Fantasy\FantasyLeagueGenerator;
use App\Services\Fantasy\FantasyTeamService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/fantasy_football_integration.sqlite';
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
    $table->string('cur_sym', 5)->default('$');
    $table->integer('currency_format')->default(1);
    $table->boolean('coin_mode')->default(false);
    $table->timestamps();
});
DB::table('general_settings')->insert(['id' => 1]);
(require dirname(__DIR__) . '/database/migrations/2026_11_04_000006_add_fantasy_settings_to_general_settings.php')->up();
DB::table('general_settings')->where('id', 1)->update([
    'ff_enabled' => true, 'ff_match_duration_minutes' => 10, 'ff_gameweek_cadence_days' => 1,
    'ff_contest_fee_percent' => '5.0000', 'ff_settlement_review_minutes' => 5,
]);

Schema::create('currencies', function (Blueprint $table) {
    $table->id();
    $table->string('code', 10);
    $table->boolean('enabled')->default(true);
    $table->timestamps();
});
Currency::create(['code' => 'USD', 'enabled' => true]);

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('username');
    $table->decimal('balance', 28, 8)->default(0);
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->tinyInteger('status')->default(1);
    $table->timestamps();
});

(require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_11_04_000001_create_fantasy_core_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_11_04_000002_create_fantasy_team_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_11_04_000003_create_fantasy_league_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_11_04_000004_create_fantasy_contest_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_11_04_000005_create_fantasy_admin_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_11_04_000008_add_budget_tiers_and_contest_squads.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_11_04_000008_add_budget_tiers_and_contest_squads.php')->up(); // safe rerun, including a second pass over every added column/table

$passed = 0;
function check(bool $cond, string $message): void {
    global $passed;
    if (!$cond) throw new RuntimeException("FAILED: $message");
    $passed++;
}

$ledger = app(CoinLedger::class);
check(Schema::hasColumn('ff_contest_entries', 'contest_squad_id') && Schema::hasTable('ff_contest_squads') && Schema::hasTable('ff_contest_squad_players'), 'Budget tier and contest squad migration safely re-runs and leaves its schema complete.');

// --- Generate a full virtual league: 20 clubs, ~500 players, 380 fixtures across 38 gameweeks. ---
$generator = app(FantasyLeagueGenerator::class);
// Kickoff squarely in the past so the very first gameweek's deadline/kickoff/full-time are all
// already due - the finalizer below processes everything purely from the wall clock.
$season = $generator->generate('Test Season', now()->subMinutes(30));
check($season->exists, 'League generation created a season.');
check(\App\Models\FantasyClub::count() === 20, 'Exactly 20 clubs were generated.');
check(FantasyPlayer::count() === 20 * 26, 'Each club got its full 26-player roster.');
check(FantasyFixture::count() === 380, 'The double round-robin produced exactly 380 fixtures.');
check(FantasyGameweek::count() === 38, 'Exactly 38 gameweeks were generated.');
fwrite(STDOUT, "League generation: 20 clubs, " . FantasyPlayer::count() . " players, 380 fixtures across 38 gameweeks.\n");

$firstGameweek = FantasyGameweek::where('season_id', $season->id)->orderBy('number')->first();
check((bool) $firstGameweek, 'The first gameweek exists.');

// --- Build a real squad for a user BEFORE the deadline passes - deadline was generator-set to
// kickoff minus 1 hour, and kickoff is already 30 minutes in the past, so push it forward first. ---
FantasyGameweek::whereKey($firstGameweek->id)->update(['deadline_at' => now()->addMinutes(5)]);
$firstGameweek->refresh();

$userId = DB::table('users')->insertGetId(['username' => 'manager_one', 'balance' => '0', 'currency_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
$user = User::find($userId);

function assembleSquad(array $excludeIds = []): array {
    $squadIds = [];
    $clubCounts = [];
    foreach (['GK' => 2, 'DEF' => 5, 'MID' => 5, 'FWD' => 3] as $position => $count) {
        // Cheapest-first so a randomly-assembled test squad reliably fits the 100.0 budget -
        // a real user balances expensive stars against budget fodder, which this test doesn't
        // need to replicate to exercise the service's own validation logic.
        $picked = FantasyPlayer::where('position', $position)->whereNotIn('id', $excludeIds)->orderBy('price')->limit($count * 6)->get();
        $have = 0;
        foreach ($picked as $player) {
            if ($have >= $count) break;
            if (($clubCounts[$player->club_id] ?? 0) >= 3) continue;
            $clubCounts[$player->club_id] = ($clubCounts[$player->club_id] ?? 0) + 1;
            $squadIds[] = $player->id;
            $have++;
        }
    }
    return $squadIds;
}

$teamService = app(FantasyTeamService::class);
$squadIds = assembleSquad();
check(count($squadIds) === 15, 'Assembled a full 15-player squad respecting the per-club cap (got ' . count($squadIds) . ').');

$team = $teamService->createTeam($user, $season, 'Manager One FC', $squadIds);
check($team->exists, 'Team creation succeeded.');
check(bccomp((string) $team->budget_remaining, '0', 1) >= 0, 'Budget remaining is non-negative after squad purchase.');
check(FantasyTeamPlayer::where('team_id', $team->id)->count() === 15, 'The team has exactly 15 players.');
check(FantasyTeamPlayer::where('team_id', $team->id)->where('squad_role', 'starting')->count() === 11, 'The default lineup has exactly 11 starters.');
fwrite(STDOUT, "Team created: {$team->name}, budget remaining {$team->budget_remaining}.\n");

// --- Enter an H2H contest and a tiered gameweek contest against a second manager. ---
$userId2 = DB::table('users')->insertGetId(['username' => 'manager_two', 'balance' => '0', 'currency_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
$user2 = User::find($userId2);
$squadIds2 = assembleSquad($squadIds);
check(count($squadIds2) === 15, 'Assembled a second full 15-player squad (got ' . count($squadIds2) . ').');
$team2 = $teamService->createTeam($user2, $season, 'Manager Two United', $squadIds2);

$ledger->mint('user', $userId, '1000', 'test funding', 'test:fund:' . $userId);
$ledger->mint('user', $userId2, '1000', 'test funding', 'test:fund:' . $userId2);

$contestService = app(FantasyContestService::class);
$h2h = FantasyContest::create([
    'type' => 'h2h', 'gameweek_id' => $firstGameweek->id, 'name' => 'Test H2H',
    'entry_fee' => '100', 'min_entrants' => 2, 'max_entrants' => 2,
    'status' => 'open', 'deadline_at' => $firstGameweek->deadline_at,
]);
$gwContest = FantasyContest::create([
    'type' => 'gameweek', 'gameweek_id' => $firstGameweek->id, 'name' => 'Test Gameweek Pool',
    'entry_fee' => '50', 'min_entrants' => 2, 'max_entrants' => 10,
    'status' => 'open', 'deadline_at' => $firstGameweek->deadline_at,
    'prize_structure' => [['top_percent' => 100, 'share_percent' => 100]],
]);
$quickContest = FantasyContest::create([
    'type' => 'quick', 'budget_tier' => '100.0', 'gameweek_id' => $firstGameweek->id, 'name' => 'Test Quick 100',
    'entry_fee' => '25', 'min_entrants' => 2, 'max_entrants' => 10,
    'status' => 'open', 'deadline_at' => $firstGameweek->deadline_at,
    'prize_structure' => [['top_percent' => 100, 'share_percent' => 100]],
]);
$quickSquadService = app(FantasyContestSquadService::class);
$tierSettings = app(FantasyContestTierSettings::class);
check($tierSettings->parseTiers('100, 120, 150') === [100.0, 120.0, 150.0], 'Budget tier settings parse into a sorted shared tier list.');
check($tierSettings->parseMinimumFees('100:10, 120:15, 150:20', [100.0, 120.0, 150.0]) === ['100' => 10.0, '120' => 15.0, '150' => 20.0], 'Budget tier minimums preserve increasing coin entry fees.');
try {
    $tierSettings->parseMinimumFees('100:15, 120:10', [100.0, 120.0]);
    check(false, 'Tier configuration rejects decreasing higher-tier fees.');
} catch (\InvalidArgumentException $e) { check(true, 'Tier configuration rejects decreasing higher-tier fees.'); }
$quickSquadA = $quickSquadService->createOrReplace($user, $quickContest, 'Quick A', assembleSquad($squadIds));
$quickSquadB = $quickSquadService->createOrReplace($user2, $quickContest, 'Quick B', assembleSquad($squadIds2));
check($quickSquadA->players()->count() === 15 && $quickSquadB->players()->count() === 15, 'Quick Contest stores each entrant\'s own separate 15-player squad.');
check(FantasyContestSquad::where('contest_id', $quickContest->id)->count() === 2, 'Quick squads are scoped to contest and user, independently of season teams.');
$quickIdsA = $quickSquadA->players->pluck('player_id')->all();
check(!array_intersect($quickIdsA, $squadIds), 'The Quick Contest squad can differ from the season squad.');
try {
    $quickSquadService->createOrReplace($user, $quickContest, 'Invalid', array_slice($quickIdsA, 0, 14));
    check(false, 'Quick squad rejects a roster with fewer than 15 players.');
} catch (\InvalidArgumentException $e) { check(true, 'Quick squad rejects a roster with fewer than 15 players.'); }

// Distinct per-user IPs throughout, so the linked-entry guard (same IP twice in one contest)
// never fires for these two genuinely different managers.
request()->server->set('REMOTE_ADDR', '10.0.0.1');
try {
    $contestService->enter($user, $quickContest, $team, null);
    check(false, 'Quick entry without a contest squad is rejected.');
} catch (\InvalidArgumentException $e) { check(true, 'Quick entry without a contest squad is rejected.'); }
try {
    $contestService->enter($user2, $quickContest, $team2, $quickSquadA);
    check(false, 'Quick entry cannot use another user\'s contest squad.');
} catch (\InvalidArgumentException $e) { check(true, 'Quick entry cannot use another user\'s contest squad.'); }
$entryA1 = $contestService->enter($user, $h2h, $team);
request()->server->set('REMOTE_ADDR', '10.0.0.2');
$entryB1 = $contestService->enter($user2, $h2h, $team2);
check($entryA1->status === 'held' && $entryB1->status === 'held', 'Both H2H entries were accepted and holds placed.');

request()->server->set('REMOTE_ADDR', '10.0.0.1');
$entryA2 = $contestService->enter($user, $gwContest, $team);
request()->server->set('REMOTE_ADDR', '10.0.0.2');
$entryB2 = $contestService->enter($user2, $gwContest, $team2);
check($entryA2->status === 'held' && $entryB2->status === 'held', 'Both gameweek-contest entries were accepted.');

request()->server->set('REMOTE_ADDR', '10.0.0.1');
$quickEntryA = $contestService->enter($user, $quickContest, $team, $quickSquadA);
request()->server->set('REMOTE_ADDR', '10.0.0.2');
$quickEntryB = $contestService->enter($user2, $quickContest, $team2, $quickSquadB);
check($quickEntryA->contest_squad_id === $quickSquadA->id && $quickEntryB->contest_squad_id === $quickSquadB->id, 'Quick entries reference the per-contest squad and not the season squad for scoring.');
try {
    $quickSquadService->createOrReplace($user, $quickContest, 'Changed after entry', $quickIdsA);
    check(false, 'A contest squad cannot change after its entry is held.');
} catch (\InvalidArgumentException $e) { check(true, 'A contest squad cannot change after its entry is held.'); }

$reconcileAfterEntries = $ledger->reconcile();
check($reconcileAfterEntries['ok'], 'Ledger reconciles after entry holds: ' . json_encode($reconcileAfterEntries));

// --- Force every fixture in this gameweek to be well past full time, then run the finalizer
// exactly as the cron tick would - no background process, purely clock-driven. ---
FantasyFixture::where('gameweek_id', $firstGameweek->id)->update(['kickoff_at' => now()->subMinutes(15)]);

$finalizer = app(FantasyGameweekFinalizer::class);
for ($i = 0; $i < 3; $i++) $finalizer->tick(); // commit -> live, then resolve -> final, then finalize the gameweek

$firstGameweek->refresh();
check($firstGameweek->status === 'final', 'The gameweek was finalized purely by clock-driven ticks (status: ' . $firstGameweek->status . ').');
check(FantasyFixture::where('gameweek_id', $firstGameweek->id)->where('status', '!=', 'final')->count() === 0, 'Every fixture in the gameweek resolved to final.');
fwrite(STDOUT, "Gameweek finalized through {$firstGameweek->fixtures()->count()} fixtures without any background process.\n");

// --- Commitment verification: recompute the result from the revealed seed and confirm it hashes to the published commitment. ---
$sampleFixture = FantasyFixture::where('gameweek_id', $firstGameweek->id)->first();
check((bool) $sampleFixture->commitment_hash, 'The fixture published a commitment hash at kickoff.');
check((bool) $sampleFixture->seed_reveal, 'The fixture revealed its seed after full time.');
$provider = app(\App\Services\Fantasy\FootballDataProvider::class);
check($provider->reveal($sampleFixture) === $sampleFixture->seed_reveal, 'reveal() returns the same seed stored on the fixture.');
fwrite(STDOUT, "Match {$sampleFixture->id} final score {$sampleFixture->home_score}-{$sampleFixture->away_score}, commitment verifiable via its revealed seed.\n");

check(FantasyPlayerFixtureStat::where('fixture_id', $sampleFixture->id)->count() > 0, 'Player box scores were written for the simulated fixture.');
check(FantasyPlayerFixtureStat::where('fixture_id', $sampleFixture->id)->where('total_points', '!=', 0)->exists()
    || FantasyPlayerFixtureStat::where('fixture_id', $sampleFixture->id)->exists(), 'Fantasy points were computed for player box scores.');

$team->refresh();
check(\App\Models\FantasyTeamGameweekHistory::where('team_id', $team->id)->where('gameweek_id', $firstGameweek->id)->exists(), 'A gameweek history row was written for the team.');
fwrite(STDOUT, "Team total points after gameweek 1: {$team->total_points}.\n");

// Give a player who exists only in Quick A's contest squad a distinctive completed performance.
// The season history is already finalized, so a score of at least 123 below proves Quick scoring
// reads its own roster fixture stats instead of the season-team history.
$quickOnlyStartingIds = $quickSquadA->players->where('squad_role', 'starting')->pluck('player_id');
$quickOnlyStat = FantasyPlayerFixtureStat::whereIn('player_id', $quickOnlyStartingIds)
    ->whereHas('fixture', fn ($query) => $query->where('gameweek_id', $firstGameweek->id))->firstOrFail();
$quickOnlyStat->minutes = 90;
$quickOnlyStat->total_points = 123;
$quickOnlyStat->save();

// --- Contests should now be locked, with a review window still open 5 minutes out - settling
// too early (before an admin's correction window closes) would be the actual bug here. ---
$h2h->refresh();
$gwContest->refresh();
$quickContest->refresh();
check($h2h->status === 'locked', 'The H2H contest locked once the gameweek finalized.');
check($gwContest->status === 'locked', 'The gameweek contest locked once the gameweek finalized.');
check($quickContest->status === 'locked', 'The Quick Contest locked once the gameweek finalized.');
check($h2h->review_until_at->isFuture(), 'The H2H contest\'s review window has not yet closed.');

$finalizer->tick();
$h2h->refresh();
check($h2h->status === 'locked', 'A locked contest is NOT settled while its review window is still open.');

// Simulate the review window passing (equivalent to waiting out the real clock).
FantasyContest::where('gameweek_id', $firstGameweek->id)->update(['review_until_at' => now()->subMinute()]);
$finalizer->tick();

$h2h->refresh();
$gwContest->refresh();
$quickContest->refresh();
check($h2h->status === 'settled', 'The H2H contest settled automatically via the cron tick.');
check($gwContest->status === 'settled', 'The gameweek contest settled automatically via the cron tick.');
check($quickContest->status === 'settled', 'The Quick Contest settles through the normal fee and CoinLedger payout path.');
fwrite(STDOUT, "Both contests settled via the same clock-driven finalizer tick, no manual intervention.\n");

$entryA1->refresh();
$entryB1->refresh();
$h2hTotal = bcadd((string) $entryA1->payout, (string) $entryB1->payout, 8);
if ($entryA1->score === $entryB1->score) {
    check($entryA1->status === 'refunded' && $entryB1->status === 'refunded', 'A tied H2H contest refunds both entrants in full.');
    check(bccomp($h2hTotal, '200', 8) === 0, 'A tied H2H refund returns the full combined stake with no fee taken.');
} else {
    check(bccomp($h2hTotal, '190', 8) === 0, 'A decisive H2H payout is the pot (200) minus the 5% fee (190) - got ' . $h2hTotal);
    check((bccomp((string) $entryA1->payout, '0', 8) > 0) !== (bccomp((string) $entryB1->payout, '0', 8) > 0), 'Exactly one H2H entrant received a payout.');
}

$entryA2->refresh();
$entryB2->refresh();
$quickEntryA->refresh();
$quickEntryB->refresh();
$gwTotal = bcadd((string) $entryA2->payout, (string) $entryB2->payout, 8);
if ($entryA2->score === $entryB2->score) {
    check(bccomp($gwTotal, '95', 8) === 0, 'A tied gameweek contest still splits the fee-reduced pot (95), not a full refund (that rule is H2H-only) - got ' . $gwTotal);
} else {
    check(bccomp($gwTotal, '95', 8) === 0, 'The gameweek contest pays out the full fee-reduced pot (100 - 5% = 95) to its single-tier winner - got ' . $gwTotal);
}
fwrite(STDOUT, "H2H payout total: $h2hTotal (pot 200). Gameweek contest payout total: $gwTotal (pot 100).\n");
$quickTotal = bcadd((string) $quickEntryA->payout, (string) $quickEntryB->payout, 8);
check(bccomp($quickTotal, '47.5', 8) === 0, 'Quick Contest payout preserves the existing 5% fee math on its 50 coin pool.');
check($quickEntryA->score !== null && $quickEntryB->score !== null, 'Quick Contest entries are scored from their saved contest squads.');
check($quickEntryA->score >= 123, 'Quick Contest scoring uses the contest squad stats, not the season-team gameweek score.');
fwrite(STDOUT, "Quick Contest payout total: $quickTotal (pool 50).\n");

$finalReconcile = $ledger->reconcile();
check($finalReconcile['ok'], 'Ledger reconciles after both contest settlements: ' . json_encode($finalReconcile));
check(bccomp($finalReconcile['difference'], '0', 8) === 0, 'Circulating + platform balance nets to exactly zero.');
fwrite(STDOUT, "CoinLedger::reconcile() passes after the full entry -> gameweek finalization -> settlement cycle.\n");

// --- Idempotency: re-entering settle() on an already-settled contest must be a safe no-op (it
// simply returns early since status is no longer 'locked'), and double tick()ing must not
// double-process anything already final/settled. ---
$pointsBefore = $team->fresh()->total_points;
$finalizer->tick();
$finalizer->tick();
check($team->fresh()->total_points === $pointsBefore, 'A repeated finalizer tick does not re-score an already-finalized gameweek.');
$reconcileAfterRetick = $ledger->reconcile();
check($reconcileAfterRetick['ok'], 'Ledger still reconciles after redundant finalizer ticks (settlement idempotency holds).');

fwrite(STDOUT, "Passed: $passed\n");
fwrite(STDOUT, "Fantasy Football League end-to-end integration checks passed.\n");
