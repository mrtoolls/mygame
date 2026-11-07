<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Games\TableGames\TableGameService;
use App\Models\TableGame;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\CoinLedger;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/table_game_notification_triggers_integration.sqlite';
if (is_file($dbPath)) unlink($dbPath);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
putenv('CACHE_STORE=database');
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec("CREATE TABLE general_settings (id INTEGER PRIMARY KEY, force_ssl INTEGER DEFAULT 0, cur_text VARCHAR(10) DEFAULT 'USD', cur_sym VARCHAR(10) DEFAULT '$', site_name VARCHAR(191) DEFAULT 'Winxpot', push_template TEXT, created_at DATETIME, updated_at DATETIME)");
$pdo->exec("INSERT INTO general_settings (id, cur_text) VALUES (1, 'USD')");
$pdo = null;

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

Schema::create('users', function (Blueprint $table) {
    $table->id(); $table->string('username'); $table->string('fullname')->nullable(); $table->decimal('balance', 28, 8)->default(0); $table->unsignedBigInteger('currency_id')->nullable();
    $table->tinyInteger('status')->default(1); $table->tinyInteger('ev')->default(1); $table->tinyInteger('sv')->default(1);
    $table->timestamps();
});
Schema::create('admins', function (Blueprint $table) { $table->id(); $table->timestamps(); });
Schema::create('transactions', function (Blueprint $table) {
    $table->id(); $table->unsignedBigInteger('user_id'); $table->decimal('amount', 28, 8); $table->decimal('charge', 28, 8)->default(0);
    $table->string('trx_type', 1); $table->string('details'); $table->string('remark'); $table->string('trx')->unique();
    $table->decimal('post_balance', 28, 8); $table->timestamps();
});

(require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php')->up();
$migration = require dirname(__DIR__) . '/database/migrations/2026_10_19_000001_create_multiplayer_game_tables.php';
$migration->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_25_000003_add_is_coin_mode_to_table_game_commissions.php')->up();
Schema::table('table_games', fn (Blueprint $table) => $table->unsignedBigInteger('currency_id')->nullable()->index());
Schema::table('table_game_players', fn (Blueprint $table) => $table->unsignedBigInteger('currency_id')->nullable()->index());
(require dirname(__DIR__) . '/database/migrations/2026_10_19_000002_create_table_game_chat_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_19_000003_create_currency_and_country_controls.php')->up();
foreach ([
    '2026_10_20_000001_add_wallet_stake_to_table_game_players.php',
    '2026_10_20_000002_enable_automatic_currency_rates.php',
    '2026_10_20_000003_add_locked_fx_rates_to_table_game_players.php',
    '2026_10_20_000004_add_settlement_amount_to_table_game_players.php',
    '2026_10_20_000009_create_table_game_viewers.php',
    '2026_10_21_000014_create_table_game_rematches_table.php',
    '2026_10_22_000011_add_rules_version_to_table_games.php',
] as $file) (require dirname(__DIR__) . '/database/migrations/' . $file)->up();

(require dirname(__DIR__) . '/database/migrations/2026_10_24_000011_add_coin_settings_to_general_settings.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_25_000001_add_pause_state_to_coin_rates.php')->up();

// Minimal notification_templates stand-in (no tracked migration for the base table - same
// situation as every other test touching it), then the real in-app-channel migration on top.
Schema::create('notification_templates', function (Blueprint $table) {
    $table->id(); $table->string('name'); $table->string('act'); $table->string('subject')->nullable();
    $table->boolean('email_status')->default(0); $table->text('email_body')->nullable();
    $table->boolean('sms_status')->default(0); $table->text('sms_body')->nullable();
    $table->boolean('push_status')->default(0); $table->string('push_title')->nullable(); $table->text('push_body')->nullable();
    $table->text('shortcodes')->nullable(); $table->timestamps();
});
(require dirname(__DIR__) . '/database/migrations/2026_11_07_000001_create_in_app_notification_channel.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_11_07_000002_create_table_game_announcement_queue.php')->up();

$insertUser = static function (string $username, string $balance): User {
    $id = DB::table('users')->insertGetId(['username' => $username, 'fullname' => $username, 'balance' => $balance, 'currency_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
    return User::findOrFail($id);
};
$service = new TableGameService();
$ledger = app(CoinLedger::class);
$failures = [];
$pass = 0;
function check(array &$failures, int &$pass, string $label, bool $condition): void
{
    if ($condition) { $pass++; return; }
    $failures[] = $label;
}

// ---------------------------------------------------------------------------
// 1. Real-currency win/loss: settle() fires exactly one GAME_WIN for the winner and one
//    GAME_LOSS for the loser, with a sensible push-formatted amount and the table's url.
// ---------------------------------------------------------------------------
$general = gs();
$general->coin_mode = false;
$general->save();

$winner = $insertUser('rc_winner', '200.00000000');
$loser = $insertUser('rc_loser', '200.00000000');
$table = $service->create($winner, 'tic_tac_toe', 2, '10', 'rc_create001');
$table = $service->join($loser, $table->id);
$moves = [[$winner, ['cell' => 0]], [$loser, ['cell' => 3]], [$winner, ['cell' => 1]], [$loser, ['cell' => 4]], [$winner, ['cell' => 2]]];
foreach ($moves as $index => [$user, $action]) {
    $table = $service->move($user, $table->id, $action, sprintf('rc_move_%06d', $index + 1), (int) $table->version);
}
check($failures, $pass, 'Real-currency: table finished with a winner', $table->status === 'finished');

$winNotif = UserNotification::where('user_id', $winner->id)->where('type', 'GAME_WIN')->first();
$lossNotif = UserNotification::where('user_id', $loser->id)->where('type', 'GAME_LOSS')->first();
check($failures, $pass, 'Real-currency: winner got exactly one GAME_WIN notification', UserNotification::where('user_id', $winner->id)->where('type', 'GAME_WIN')->count() === 1);
check($failures, $pass, 'Real-currency: loser got exactly one GAME_LOSS notification', UserNotification::where('user_id', $loser->id)->where('type', 'GAME_LOSS')->count() === 1);
check($failures, $pass, 'Real-currency: winner notification mentions the opponent', $winNotif && str_contains($winNotif->message, 'rc_loser'));
check($failures, $pass, 'Real-currency: winner notification has a trophy icon', $winNotif && $winNotif->icon === 'trophy');
check($failures, $pass, 'Real-currency: loser notification has a skull icon', $lossNotif && $lossNotif->icon === 'skull');
check($failures, $pass, 'Real-currency: winner notification links to the table', $winNotif && str_contains($winNotif->url, (string) $table->id));
check($failures, $pass, 'Real-currency: winner amount is formatted in real currency (not Coins)', $winNotif && str_contains($winNotif->message, 'USD') && !str_contains($winNotif->message, 'Coins'));
fwrite(STDOUT, "Real-currency win/loss: exactly one GAME_WIN and one GAME_LOSS notification fired, correctly formatted and linked.\n");

// ---------------------------------------------------------------------------
// 2. Draw/refund: no win/loss notification for either player.
// ---------------------------------------------------------------------------
$d1 = $insertUser('draw_p1', '200.00000000');
$d2 = $insertUser('draw_p2', '200.00000000');
$drawTable = $service->create($d1, 'tic_tac_toe', 2, '10', 'draw_create01');
$drawTable = $service->join($d2, $drawTable->id);
$drawActions = [[$d1,0],[$d2,1],[$d1,2],[$d2,4],[$d1,3],[$d2,5],[$d1,7],[$d2,6],[$d1,8]];
foreach ($drawActions as $index => [$user, $cell]) {
    $drawTable = $service->move($user, $drawTable->id, ['cell' => $cell], sprintf('draw_move_%06d', $index + 1), (int) $drawTable->version);
}
check($failures, $pass, 'Draw: finished as a draw', $drawTable->result === 'draw');
check($failures, $pass, 'Draw: no GAME_WIN/GAME_LOSS notification for either player', UserNotification::whereIn('user_id', [$d1->id, $d2->id])->count() === 0);
fwrite(STDOUT, "Draw settlement: correctly silent - no win/loss notification for a refund.\n");

// ---------------------------------------------------------------------------
// 3. Coin-mode win/loss: same trigger path, amount formatted in Coins.
// ---------------------------------------------------------------------------
$general->coin_mode = true;
$general->save();
$coinWinner = $insertUser('coin_winner', '0');
$coinLoser = $insertUser('coin_loser', '0');
$ledger->mint('user', $coinWinner->id, '500', 'seed', 'seed:' . $coinWinner->id);
$ledger->mint('user', $coinLoser->id, '500', 'seed', 'seed:' . $coinLoser->id);
$coinTable = $service->create($coinWinner, 'tic_tac_toe', 2, '50', 'coin_create01');
$coinTable = $service->join($coinLoser, $coinTable->id);
$coinMoves = [[$coinWinner, ['cell' => 0]], [$coinLoser, ['cell' => 3]], [$coinWinner, ['cell' => 1]], [$coinLoser, ['cell' => 4]], [$coinWinner, ['cell' => 2]]];
foreach ($coinMoves as $index => [$user, $action]) {
    $coinTable = $service->move($user, $coinTable->id, $action, sprintf('coin_move_%06d', $index + 1), (int) $coinTable->version);
}
$coinWinNotif = UserNotification::where('user_id', $coinWinner->id)->where('type', 'GAME_WIN')->first();
check($failures, $pass, 'Coin-mode: winner got exactly one GAME_WIN notification', UserNotification::where('user_id', $coinWinner->id)->where('type', 'GAME_WIN')->count() === 1);
check($failures, $pass, 'Coin-mode: loser got exactly one GAME_LOSS notification', UserNotification::where('user_id', $coinLoser->id)->where('type', 'GAME_LOSS')->count() === 1);
check($failures, $pass, 'Coin-mode: amount formatted in Coins, not real currency', $coinWinNotif && str_contains($coinWinNotif->message, 'Coins') && !str_contains($coinWinNotif->message, 'USD'));
fwrite(STDOUT, "Coin-mode win/loss: notifications fire the same way, formatted in Coins.\n");

// ---------------------------------------------------------------------------
// 4. New live table: creating a table queues an announcement row, and the scheduled command
//    notifies every OTHER active user (not the creator) in-app, resuming correctly across ticks.
// ---------------------------------------------------------------------------
$nlCreator = $insertUser('nl_creator', '200.00000000');
$others = [];
for ($i = 0; $i < 5; $i++) $others[] = $insertUser('nl_bystander_' . $i, '0.00000000');

$queuedBefore = DB::table('table_game_announcement_queue')->count();
$nlTable = $service->create($nlCreator, 'tic_tac_toe', 2, '0', 'nl_create0001');
check($failures, $pass, 'A table_game_announcement_queue row was queued on create()', DB::table('table_game_announcement_queue')->count() === $queuedBefore + 1);

$reflectionBatch = new ReflectionClass(\App\Console\Commands\AnnounceNewLiveTables::class);
$batchSize = $reflectionBatch->getConstant('BATCH_SIZE');
check($failures, $pass, 'Sanity: test has fewer bystanders than one batch (single-tick completion expected)', count($others) < $batchSize);

\Illuminate\Support\Facades\Artisan::call('table-games:announce-new-tables');

// Earlier sections (win/loss, draw, coin-mode) also each created a table, which queues its own
// announcement row too - the command just processed all of them in this one tick. Scope every
// assertion here to nlTable's own notification specifically (by its message content), not just
// "any NEW_LIVE_TABLE row", since every one of these bystanders now legitimately has several.
$nlMessage = 'nl_creator just opened a Tic tac toe table.';
check($failures, $pass, 'Every bystander got a NEW_LIVE_TABLE notification for THIS table', UserNotification::whereIn('user_id', array_map(fn ($u) => $u->id, $others))->where('type', 'NEW_LIVE_TABLE')->where('message', $nlMessage)->count() === count($others));
check($failures, $pass, 'The creator did NOT get notified about their own table', UserNotification::where('user_id', $nlCreator->id)->where('type', 'NEW_LIVE_TABLE')->where('message', $nlMessage)->count() === 0);
check($failures, $pass, 'Queue row marked completed after a single tick (bystander count under batch size)', DB::table('table_game_announcement_queue')->where('table_game_id', $nlTable->id)->whereNotNull('completed_at')->exists());
check($failures, $pass, 'NEW_LIVE_TABLE notification mentions the creator and game', UserNotification::where('user_id', $others[0]->id)->where('message', $nlMessage)->exists());

// Re-running the command is a no-op once every row is already completed (no duplicate notifications).
$beforeRerun = UserNotification::where('type', 'NEW_LIVE_TABLE')->count();
\Illuminate\Support\Facades\Artisan::call('table-games:announce-new-tables');
check($failures, $pass, 'Re-running the announce command after completion does not duplicate notifications', UserNotification::where('type', 'NEW_LIVE_TABLE')->count() === $beforeRerun);
fwrite(STDOUT, "New live table: queued on create(), fanned out in-app to every other active user via the scheduled command, creator excluded, idempotent on re-run.\n");

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------
echo "Passed: {$pass}\n";
if ($failures) {
    echo "FAILED (" . count($failures) . "):\n";
    foreach ($failures as $failure) echo " - {$failure}\n";
    exit(1);
}
echo "Table-game notification trigger checks passed.\n";
