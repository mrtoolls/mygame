<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Games\TableGames\TableGameRules;
use App\Http\Controllers\Admin\TableGameAdminController;
use App\Models\TableGameSetting;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

// Reproduces the real-world report: an admin raises "Maximum stake" in the Global Defaults
// section of Admin > Table Game Management, but table creation keeps enforcing the OLD limit
// for every actual game - because every game that has ever been configured or played already
// has its own ff_... er, table_game_settings row, and the admin form resubmits every section's
// CURRENT (unedited, still-showing-the-old-number) fields together in one POST. Before the fix,
// that stale submission overwrote every per-game row right back to its old value, so "Global
// defaults" never actually reached any real game.
$dbPath = __DIR__ . '/table_game_settings_cascade.sqlite';
if (is_file($dbPath)) unlink($dbPath);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
putenv('CACHE_STORE=array');
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec("CREATE TABLE general_settings (id INTEGER PRIMARY KEY, force_ssl INTEGER DEFAULT 0, cur_text VARCHAR(10) DEFAULT 'USD')");
$pdo->exec("INSERT INTO general_settings (id, cur_text) VALUES (1, 'USD')");
$pdo = null;

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

$frameworkMigration = require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php';
$frameworkMigration->up();
$migration = require dirname(__DIR__) . '/database/migrations/2026_10_19_000001_create_multiplayer_game_tables.php';
$migration->up();
if (!Schema::hasTable('table_game_settings')) throw new RuntimeException('table_game_settings was not created.');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

// Seed state matching the real report: every game was configured a while ago at the defaults
// (max_stake 1000), including the global row - so right now every per-game row is, in effect,
// "just inheriting" even though it's a real, separately-saved row.
TableGameSetting::create(['game_type' => '*', 'enabled' => true, 'commission_percent' => 5, 'min_stake' => 1, 'max_stake' => 1000, 'turn_seconds' => 60, 'empty_timeout_minutes' => 1440, 'max_open_tables_per_user' => 5, 'max_concurrent_tables' => 500]);
TableGameSetting::create(['game_type' => 'ludo', 'enabled' => true, 'commission_percent' => 5, 'min_stake' => 1, 'max_stake' => 1000, 'turn_seconds' => 60, 'empty_timeout_minutes' => 1440, 'max_open_tables_per_user' => 5, 'max_concurrent_tables' => 500]);
// Chess was deliberately given a lower cap by an admin at some point - a genuine override, not
// just an untouched copy of the old global default. The fix must never disturb this.
TableGameSetting::create(['game_type' => 'chess', 'enabled' => true, 'commission_percent' => 5, 'min_stake' => 1, 'max_stake' => 500, 'turn_seconds' => 60, 'empty_timeout_minutes' => 1440, 'max_open_tables_per_user' => 5, 'max_concurrent_tables' => 500]);

// Build the exact form payload the admin page would submit: every game's <details> section
// resubmits its own CURRENT values verbatim (the admin only edited the Global Defaults section
// in the browser, raising its max_stake from 1000 to 1000000 - every other field everywhere is
// untouched/identical to what's already stored).
$payload = ['settings' => []];
foreach (array_merge(['*'], array_keys(TableGameRules::GAMES)) as $game) {
    $row = TableGameSetting::where('game_type', $game)->first();
    $payload['settings'][$game] = [
        'enabled' => $row ? ($row->enabled ? '1' : '0') : '1',
        'commission_percent' => $row ? (string) $row->commission_percent : '5',
        'min_stake' => $row ? (string) $row->min_stake : '1',
        'max_stake' => $row ? (string) $row->max_stake : '1000',
        'turn_seconds' => $row ? (string) $row->turn_seconds : '60',
        'max_open_tables_per_user' => $row ? (string) $row->max_open_tables_per_user : '5',
        'max_concurrent_tables' => $row ? (string) $row->max_concurrent_tables : '500',
    ];
}
$payload['settings']['*']['max_stake'] = '1000000'; // the admin's actual edit

$request = Request::create('/admin/table-games/settings', 'POST', $payload);
$controller = $app->make(TableGameAdminController::class);
$response = $controller->settings($request);
$assert($response->getStatusCode() < 400, 'Settings save unexpectedly failed: ' . $response->getStatusCode());

$ludo = TableGameSetting::where('game_type', 'ludo')->first();
$assert((float) $ludo->max_stake === 1000000.0, "Ludo's max_stake should have cascaded from the new global default (1000000), got {$ludo->max_stake}.");
echo "A per-game row that was merely inheriting the old global default correctly cascades to the new one.\n";

$chess = TableGameSetting::where('game_type', 'chess')->first();
$assert((float) $chess->max_stake === 500.0, "Chess's deliberate 500 override must never be disturbed by a global defaults change, got {$chess->max_stake}.");
echo "A genuine per-game override (chess, 500) is left untouched by the same global defaults change.\n";

$global = TableGameSetting::where('game_type', '*')->first();
$assert((float) $global->max_stake === 1000000.0, 'Global defaults max_stake itself did not save.');
echo "Global defaults row itself saved the new value.\n";

// Second save, now that ludo really is at 1000000 too (equal to global again) - if the admin
// lowers the global default afterward, ludo (still "inheriting") should follow it back down,
// while chess (still a deliberate 500) still must not move.
$payload['settings']['*']['max_stake'] = '2000';
$payload['settings']['ludo']['max_stake'] = '1000000'; // what's now actually stored, unedited in the browser
$payload['settings']['chess']['max_stake'] = '500';
$request2 = Request::create('/admin/table-games/settings', 'POST', $payload);
$controller->settings($request2);
$ludoAfter = TableGameSetting::where('game_type', 'ludo')->first();
$chessAfter = TableGameSetting::where('game_type', 'chess')->first();
$assert((float) $ludoAfter->max_stake === 2000.0, "Ludo should keep tracking the global default on a second change, got {$ludoAfter->max_stake}.");
$assert((float) $chessAfter->max_stake === 500.0, "Chess's override must still survive a second global change, got {$chessAfter->max_stake}.");
echo "A still-inheriting row keeps tracking Global Defaults across repeated saves; a real override keeps surviving every one of them.\n";

// A field the admin DOES deliberately change for a specific game in the SAME request as a
// global change must win as the explicit per-game value, not get cascaded over.
$payload2 = $payload;
$payload2['settings']['*']['max_concurrent_tables'] = '900';
$payload2['settings']['ludo']['max_concurrent_tables'] = '42'; // admin typed this for ludo specifically, same submit
$request3 = Request::create('/admin/table-games/settings', 'POST', $payload2);
$controller->settings($request3);
$ludoFinal = TableGameSetting::where('game_type', 'ludo')->first();
$assert((int) $ludoFinal->max_concurrent_tables === 42, "A deliberate per-game edit made in the same submission as a global change must win, got {$ludoFinal->max_concurrent_tables}.");
echo "A deliberate per-game edit made in the same request as a global change is respected, not overwritten by the cascade.\n";

\Illuminate\Support\Facades\DB::disconnect();
@unlink($dbPath);
echo "Table game settings cascade integration checks passed.\n";
