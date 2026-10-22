<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\Competition;
use App\Models\CompetitionAuditLog;
use App\Models\CompetitionPrize;
use App\Services\CompetitionLaunchSeeder;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/competition_launch_seeder_integration.sqlite';
if (is_file($dbPath)) unlink($dbPath);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
putenv('CACHE_STORE=database');
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec('CREATE TABLE general_settings (id INTEGER PRIMARY KEY, force_ssl INTEGER DEFAULT 0)');
$pdo = null;

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

$frameworkMigration = require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php';
$frameworkMigration->up();

// competition_results/competition_flags FK-reference users, even though the launch seeder
// never writes to either table - the migration needs the referenced table to exist.
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->timestamps();
});

$competitionsMigration = require dirname(__DIR__) . '/database/migrations/2026_10_22_000004_create_competitions_core_tables.php';
$competitionsMigration->up();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$seeder = new CompetitionLaunchSeeder();
$expectedSlugs = array_column($seeder->definitions(), 'slug');
$assert(count($expectedSlugs) === 5, 'Expected all 5 launch competitions to be defined (chess is available in this build) - got ' . count($expectedSlugs) . '.');
$assert(!$seeder->allSlugsExist(), 'allSlugsExist() should be false before seeding.');

// First run: everything should be created.
$first = $seeder->seed(42);
$assert(count($first['created']) === 5, 'First seed() run should create all 5 competitions, created: ' . count($first['created']));
$assert(count($first['skipped']) === 0, 'First seed() run should skip nothing.');
$assert(Competition::count() === 5, 'Expected 5 competitions in the database after the first run.');
$assert(CompetitionPrize::count() === (5 + 3 + 5 + 3 + 3), 'Prize row count mismatch after the first run: ' . CompetitionPrize::count());
$assert(CompetitionAuditLog::where('action', 'launch_seeded')->count() === 5, 'Expected one audit log row per competition created.');
$assert($seeder->allSlugsExist(), 'allSlugsExist() should be true after seeding.');

$ludo = Competition::where('slug', 'ludo-league-nov-2026')->firstOrFail();
$assert((int) $ludo->created_by === 42, 'created_by was not recorded from the admin id passed to seed().');
$assert($ludo->min_games === 10 && $ludo->min_distinct_opponents === 5, 'Ludo League eligibility fields did not match the spec.');
$assert($ludo->type_settings['points']['win'] === 3, 'Ludo League points_config.win did not match the adapted win/draw/loss shape.');

$wagerRace = Competition::where('slug', 'wager-race-nov-2026')->firstOrFail();
$assert($wagerRace->eligible_games === null, 'Wager Race eligible_games should be null (all table games).');
$assert($wagerRace->prizes()->count() === 3, 'Wager Race should have 3 prize rows.');

$luckyDraw = Competition::where('slug', 'lucky-draw-nov-2026')->firstOrFail();
$assert((float) $luckyDraw->ticket_usd_per_ticket === 10.0 && (int) $luckyDraw->max_tickets_per_user === 100, 'Lucky Draw ticket fields did not match the spec.');
$assert($luckyDraw->prizes()->count() === 5, 'Lucky Draw should have 5 prize rows of $20 each.');

$biggestWin = Competition::where('slug', 'biggest-win-nov-2026')->firstOrFail();
$assert((float) $biggestWin->min_bet_usd === 1.0, 'Biggest Win min_bet_usd did not match the spec (stored for reference; not enforced by ranking logic).');

// Idempotency: running seed() again must create nothing new and must not touch existing rows.
$ludoIdBefore = $ludo->id;
$ludo->title = 'Admin-edited title - must survive a second seed run';
$ludo->min_games = 999; // simulate an admin edit after launch
$ludo->save();

$second = $seeder->seed(99);
$assert(count($second['created']) === 0, 'Second seed() run should create nothing new, created: ' . count($second['created']));
$assert(count($second['skipped']) === 5, 'Second seed() run should report all 5 as skipped, skipped: ' . count($second['skipped']));
$assert(Competition::count() === 5, 'Competition count changed after a second seed() run - it must stay idempotent.');
$assert(CompetitionAuditLog::where('action', 'launch_seeded')->count() === 5, 'A second seed() run must not write new audit log rows for already-existing competitions.');

$ludoAfter = Competition::whereKey($ludoIdBefore)->firstOrFail();
$assert($ludoAfter->title === 'Admin-edited title - must survive a second seed run', 'seed() overwrote an admin edit made after launch - it must never touch an existing row.');
$assert($ludoAfter->min_games === 999, 'seed() overwrote an admin-edited field on an existing competition.');

echo "OK: competition launch seeder is idempotent and never overwrites existing competitions.\n";
// The sqlite file is left in place (and removed at the top of the next run) rather than
// unlinked here, since the open PDO connection can still hold a Windows file lock on it.
