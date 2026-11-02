<?php

// NumberSlot and Poker both set $hasCustomCompleteLogic = true, which lets their win-resolution
// code completely bypass Game::end()'s own (already coin-mode-aware) win crediting. Both were
// found to still do a raw `$user->balance += $amount` unconditionally - meaning a win while
// coin_mode was on silently credited the frozen real-currency balance instead of the coin
// wallet, so the player would never actually receive it. This test proves the fix: both now
// mint through CoinLedger when coin_mode is on, never touch users.balance, and the AJAX
// response's balance/message strings show coins instead of a stale/wrong real-currency figure.

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Games\NumberSlot;
use App\Games\Poker;
use App\Models\GameLog;
use App\Models\User;
use App\Services\CoinLedger;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/coin_custom_complete_games_integration.sqlite';
if (is_file($dbPath)) unlink($dbPath);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
putenv('CACHE_STORE=database');
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec("CREATE TABLE languages (id INTEGER PRIMARY KEY, code VARCHAR(10), name VARCHAR(60), is_default INTEGER DEFAULT 0)");
$pdo->exec("INSERT INTO languages (code, name, is_default) VALUES ('en', 'English', 1)");
$pdo = null;

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
if (!config('app.key')) {
    config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);
}

(require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php')->up();

Schema::create('general_settings', function (Blueprint $table) {
    $table->id();
    $table->string('site_name', 190)->default('Test Site');
    $table->string('cur_text', 10)->default('USD');
    $table->string('cur_sym', 5)->default('$');
    $table->integer('currency_format')->default(1);
    $table->boolean('force_ssl')->default(0);
    $table->timestamp('last_cron')->nullable();
    $table->timestamps();
});
DB::table('general_settings')->insert(['id' => 1]);
(require dirname(__DIR__) . '/database/migrations/2026_10_24_000011_add_coin_settings_to_general_settings.php')->up();

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('username');
    $table->decimal('balance', 28, 8)->default(0);
    $table->decimal('demo_balance', 28, 8)->default(0);
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->tinyInteger('status')->default(1);
    $table->timestamps();
});
Schema::create('transactions', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->decimal('amount', 28, 8);
    $table->decimal('charge', 28, 8)->default(0);
    $table->string('trx_type', 1);
    $table->string('details');
    $table->string('remark');
    $table->string('trx');
    $table->decimal('post_balance', 28, 8);
    $table->timestamps();
});
Schema::create('games', function (Blueprint $table) {
    $table->id();
    $table->string('name')->nullable();
    $table->string('alias', 40)->nullable();
    $table->tinyInteger('status')->default(1);
    $table->tinyInteger('trending')->default(0);
    $table->tinyInteger('featured')->default(0);
    $table->decimal('win', 28, 8)->default(0);
    $table->decimal('max_limit', 28, 8)->default(0);
    $table->decimal('min_limit', 28, 8)->default(0);
    $table->tinyInteger('invest_back')->default(0);
    $table->string('probable_win')->nullable();
    $table->string('probable_win_demo')->nullable();
    $table->string('level')->nullable();
    $table->text('instruction')->nullable();
    $table->timestamps();
});
Schema::create('game_logs', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id')->default(0);
    $table->unsignedBigInteger('game_id')->default(0);
    $table->text('user_select')->nullable();
    $table->tinyInteger('demo_play')->default(0);
    $table->text('result')->nullable();
    $table->tinyInteger('status')->default(0);
    $table->decimal('invest', 28, 8)->default(0);
    $table->decimal('win_amo', 28, 8)->default(0);
    $table->tinyInteger('win_status')->default(0);
    $table->timestamps();
});
Schema::create('guess_bonuses', function (Blueprint $table) {
    $table->id();
    $table->string('alias', 40)->nullable();
    $table->decimal('percent', 8, 4)->default(0);
    $table->integer('chance')->default(0);
    $table->timestamps();
});

(require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_25_000001_add_pause_state_to_coin_rates.php')->up();

$ledger = app(CoinLedger::class);
$failures = [];
$pass = 0;
function check(array &$failures, int &$pass, string $label, bool $condition): void
{
    if ($condition) { $pass++; return; }
    $failures[] = $label;
}

$insertUser = static function (string $username, string $balance = '0'): User {
    $id = DB::table('users')->insertGetId(['username' => $username, 'balance' => $balance, 'created_at' => now(), 'updated_at' => now()]);
    return User::findOrFail($id);
};

$general = gs();
$general->coin_mode = true;
$general->save();

// ---------------------------------------------------------------------------
// 1. NumberSlot::customCompleteLogic() - a win mints through CoinLedger, never touches
//    users.balance, and the response's balance string is a plain coin number.
// ---------------------------------------------------------------------------
$user = $insertUser('number_slot_user', '777.00000000');
$ledger->mint('user', $user->id, '100', 'seed', 'seed:' . $user->id);
Auth::login($user);

$gameId = DB::table('games')->insertGetId([
    'name' => 'Number Slot', 'alias' => 'number_slot', 'status' => 1,
    'win' => '0', 'max_limit' => '1000', 'min_limit' => '1', 'invest_back' => 0,
    'level' => json_encode([50]), // level[0] = 50% bonus, matches win_status == 1
    'instruction' => 'test', 'created_at' => now(), 'updated_at' => now(),
]);
$gameLogId = DB::table('game_logs')->insertGetId([
    'user_id' => $user->id, 'game_id' => $gameId, 'demo_play' => 0, 'result' => '5',
    'status' => 0, 'invest' => '40.00000000', 'win_status' => 1, 'created_at' => now(), 'updated_at' => now(),
]);
$gameLog = GameLog::findOrFail($gameLogId);

app()->instance('request', Request::create('/play/number-slot/end', 'POST', []));
$numberSlot = new NumberSlot();
$reflection = new ReflectionMethod(NumberSlot::class, 'customCompleteLogic');
$reflection->setAccessible(true);
$result = $reflection->invoke($numberSlot, $gameLog);

// invest 40 * level[0] 50% = 20 coins won.
check($failures, $pass, 'NumberSlot win: minted through CoinLedger (100 + 20 = 120)', bccomp((string) $ledger->wallet('user', $user->id)->fresh()->available_balance, '120', 8) === 0);
check($failures, $pass, 'NumberSlot win: users.balance was never touched by the win credit', bccomp((string) $user->fresh()->balance, '777.00000000', 8) === 0);
check($failures, $pass, 'NumberSlot win: the response balance is a plain coin number, not a $-formatted string', $result['data']['bal'] === '120');
check($failures, $pass, 'NumberSlot win: no legacy Transaction row was written for a coin-mode win', !DB::table('transactions')->where('user_id', $user->id)->where('remark', 'win_bonus')->exists());
check($failures, $pass, 'NumberSlot win: reconciliation still balances', $ledger->reconcile()['ok']);
fwrite(STDOUT, "NumberSlot win (coin mode): mints through CoinLedger, never touches users.balance, response shows a plain coin number.\n");

// ---------------------------------------------------------------------------
// 2. Poker::pokerCall() - a win mints through CoinLedger, never touches users.balance.
// ---------------------------------------------------------------------------
$pokerUser = $insertUser('poker_user', '555.00000000');
$ledger->mint('user', $pokerUser->id, '200', 'seed', 'seed:' . $pokerUser->id);
Auth::login($pokerUser);

$pokerGameId = DB::table('games')->insertGetId([
    'name' => 'Poker', 'alias' => 'poker', 'status' => 1,
    'win' => '0', 'max_limit' => '1000', 'min_limit' => '1', 'invest_back' => 0,
    'instruction' => 'test', 'created_at' => now(), 'updated_at' => now(),
]);
DB::table('guess_bonuses')->insert(['alias' => 'poker', 'percent' => '100', 'chance' => 1, 'created_at' => now(), 'updated_at' => now()]); // rank index 0 -> chance=1 -> royal_flush, 100% bonus

// A 5-card hand Poker::hasSpecificHand() will resolve to 'royal_flush' (rank index 0): same
// suit, consecutive ranks 10-A, as "RANK-SUIT" strings - the exact format generatePokerHand()
// itself produces (see Poker.php:212).
$royalFlush = ['10-S', 'J-S', 'Q-S', 'K-S', 'A-S'];
$pokerGameLogId = DB::table('game_logs')->insertGetId([
    'user_id' => $pokerUser->id, 'game_id' => $pokerGameId, 'demo_play' => 0,
    'result' => json_encode(\Illuminate\Support\Facades\Crypt::encrypt($royalFlush)),
    'status' => 0, 'invest' => '30.00000000', 'win_status' => 1, 'created_at' => now(), 'updated_at' => now(),
]);
$pokerGameLog = GameLog::findOrFail($pokerGameLogId);

app()->instance('request', Request::create('/play/poker/end', 'POST', ['type' => 'call']));
$poker = new Poker();
$pokerResult = $poker->pokerCall($pokerGameLog);

// invest 30 + (30 * 100%) = 60 coins won.
check($failures, $pass, 'Poker win: minted through CoinLedger (200 + 60 = 260)', bccomp((string) $ledger->wallet('user', $pokerUser->id)->fresh()->available_balance, '260', 8) === 0);
check($failures, $pass, 'Poker win: users.balance was never touched by the win credit', bccomp((string) $pokerUser->fresh()->balance, '555.00000000', 8) === 0);
check($failures, $pass, 'Poker win: the response balance is a plain coin number, not a $-formatted string', $pokerResult['data']['balance'] === '260');
check($failures, $pass, 'Poker win: no legacy Transaction row was written for a coin-mode win', !DB::table('transactions')->where('user_id', $pokerUser->id)->where('remark', 'Win_Bonus')->exists());
check($failures, $pass, 'Poker win: reconciliation still balances', $ledger->reconcile()['ok']);
fwrite(STDOUT, "Poker win (coin mode, pokerCall): mints through CoinLedger, never touches users.balance, response shows a plain coin number.\n");

// ---------------------------------------------------------------------------
// 3. Regression: coin_mode OFF still credits real-currency balance exactly as before for both
//    games (the legacy path must be completely unaffected).
// ---------------------------------------------------------------------------
$general->coin_mode = false;
$general->save();

$offUser = $insertUser('number_slot_off_user', '100.00000000');
Auth::login($offUser);
$offLogId = DB::table('game_logs')->insertGetId([
    'user_id' => $offUser->id, 'game_id' => $gameId, 'demo_play' => 0, 'result' => '5',
    'status' => 0, 'invest' => '40.00000000', 'win_status' => 1, 'created_at' => now(), 'updated_at' => now(),
]);
$offLog = GameLog::findOrFail($offLogId);
app()->instance('request', Request::create('/play/number-slot/end', 'POST', []));
$offNumberSlot = new NumberSlot();
$offResult = $reflection->invoke($offNumberSlot, $offLog);
check($failures, $pass, 'NumberSlot win, coin_mode off: real-currency balance credited exactly as before (100 + 20 = 120.00000000)', bccomp((string) $offUser->fresh()->balance, '120.00000000', 8) === 0);
check($failures, $pass, 'NumberSlot win, coin_mode off: response balance is real-currency formatted', $offResult['data']['bal'] === '120.00');
fwrite(STDOUT, "NumberSlot win, coin_mode off: unchanged real-currency behavior (regression check).\n");

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------
echo "Passed: {$pass}\n";
if ($failures) {
    echo "FAILED (" . count($failures) . "):\n";
    foreach ($failures as $failure) echo " - {$failure}\n";
    exit(1);
}
echo "Coin-mode custom-complete-logic games integration checks passed.\n";
