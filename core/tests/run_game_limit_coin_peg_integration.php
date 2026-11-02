<?php

// $game->min_limit/max_limit are stored as a real-currency figure (the admin edits them in
// currency) - every game template already divides them by coin_peg_usd to show/validate the bet
// field in coins once coin mode is on (e.g. mines.blade.php's minLimit/maxLimit JS variables).
// But Game::fallback()'s own server-side min/max check compared the submitted coin amount
// against the RAW, unconverted dollar figure - at the default 0.01 peg, a nominal "$1 minimum"
// bet (shown to the player as "100 coins minimum") would actually only block bets below 1 COIN,
// nearly 100x too permissive. This test proves the fix: the server now checks against the same
// peg-converted figure the player was shown, for both real and demo play.

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Games\CasinoDice;
use App\Models\User;
use App\Services\CoinLedger;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/game_limit_coin_peg_integration.sqlite';
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

(require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php')->up();

Schema::create('general_settings', function (Blueprint $table) {
    $table->id();
    $table->string('site_name', 190)->default('Test Site');
    $table->string('cur_text', 10)->default('USD');
    $table->string('cur_sym', 5)->default('$');
    $table->integer('currency_format')->default(1);
    $table->boolean('force_ssl')->default(0);
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
    $table->decimal('win', 28, 8)->default(0);
    $table->decimal('max_limit', 28, 8)->default(0);
    $table->decimal('min_limit', 28, 8)->default(0);
    $table->tinyInteger('invest_back')->default(0);
    $table->string('probable_win')->nullable();
    $table->string('probable_win_demo')->nullable();
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
    $table->tinyInteger('mines')->default(0);
    $table->integer('mine_available')->default(0);
    $table->timestamps();
});
(require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php')->up();

$general = \App\Models\GeneralSetting::first();
$ledger = app(CoinLedger::class);

// $1 min, $100 max - at the default 0.01 peg that's a 100-coin minimum, 10,000-coin maximum.
$gameId = DB::table('games')->insertGetId([
    'name' => 'Casino Dice', 'alias' => 'casino_dice', 'status' => 1,
    'win' => '20.00000000', 'max_limit' => '100.00000000', 'min_limit' => '1.00000000',
    'invest_back' => 0, 'instruction' => 'Test', 'created_at' => now(), 'updated_at' => now(),
]);

$passed = 0;
function check(bool $cond, string $message): void {
    global $passed;
    if (!$cond) throw new RuntimeException("FAILED: $message");
    $passed++;
}

$attemptPlay = static function (User $user, string $invest, bool $demo = false) {
    Auth::setUser($user->fresh());
    app()->instance('request', Request::create('/x', 'POST', ['invest' => $invest, 'percent' => '100', 'choose' => 'low']));
    $game = new CasinoDice();
    $game->demoPlay = $demo;
    return $game->play()->getData(true);
};

// --- coin_mode OFF: limits enforced in plain currency, unchanged (regression check). ---
$offUserId = DB::table('users')->insertGetId(['username' => 'limit_off', 'balance' => '1000.00000000', 'created_at' => now(), 'updated_at' => now()]);
$offUser = User::find($offUserId);
$belowMin = $attemptPlay($offUser, '0.50');
check(isset($belowMin['error']) && str_contains($belowMin['error'], 'minimum'), 'coin_mode off: a $0.50 bet is still rejected below the $1 minimum (regression check).');
$validOff = $attemptPlay($offUser, '5');
check(isset($validOff['game_log_id']), 'coin_mode off: a valid $5 bet is still accepted.');
fwrite(STDOUT, "Game bet limits, coin_mode off: enforced in plain currency exactly as before (regression check).\n");

// --- coin_mode ON: limits enforced against the SAME peg-converted figure the player was shown. ---
$general->coin_mode = true;
$general->save();

// Each attempt below uses its own fresh user - fallback() rejects a second play() for a user
// with an already-incomplete round, so a rejected attempt (which never reaches complete()) can't
// reuse the same user for the next check.
$freshCoinUser = static function (string $username) use ($ledger) {
    $id = DB::table('users')->insertGetId(['username' => $username, 'demo_balance' => '100000.00000000', 'created_at' => now(), 'updated_at' => now()]);
    $ledger->mint('user', $id, '100000', 'seed', 'limit_test_seed:' . $id);
    return User::find($id);
};

// 50 coins is below the 100-coin minimum (== $0.50, below $1) - must be rejected, not silently
// accepted the way comparing 50 against the raw $1 minimum would have.
$belowCoinMin = $attemptPlay($freshCoinUser('limit_on_below'), '50');
check(isset($belowCoinMin['error']) && str_contains($belowCoinMin['error'], 'minimum'), 'coin_mode on: a 50-coin bet is correctly rejected below the peg-converted 100-coin minimum (expected ' . json_encode($belowCoinMin) . ').');

// 200 coins is within [100, 10000] coins - must be accepted.
$validOn = $attemptPlay($freshCoinUser('limit_on_valid'), '200');
check(isset($validOn['game_log_id']), 'coin_mode on: a 200-coin bet within the peg-converted [100, 10000] range is accepted (got ' . json_encode($validOn) . ').');

// 20000 coins exceeds the 10,000-coin maximum - must be rejected.
$aboveCoinMax = $attemptPlay($freshCoinUser('limit_on_above'), '20000');
check(isset($aboveCoinMax['error']) && str_contains($aboveCoinMax['error'], 'maximum'), 'coin_mode on: a 20,000-coin bet is correctly rejected above the peg-converted 10,000-coin maximum (expected ' . json_encode($aboveCoinMax) . ').');

// Demo play must enforce the identical peg-converted limits.
$demoUserId = DB::table('users')->insertGetId(['username' => 'limit_demo', 'demo_balance' => '100000.00000000', 'created_at' => now(), 'updated_at' => now()]);
$demoUser = User::find($demoUserId);
$demoBelowMin = $attemptPlay($demoUser, '50', true);
check(isset($demoBelowMin['error']) && str_contains($demoBelowMin['error'], 'minimum'), 'coin_mode on, demo play: a 50-coin bet is correctly rejected below the peg-converted 100-coin minimum.');
$demoValid = $attemptPlay($demoUser, '200', true);
check(isset($demoValid['game_log_id']), 'coin_mode on, demo play: a 200-coin bet within range is accepted.');
fwrite(STDOUT, "Game bet limits, coin_mode on: enforced against the same peg-converted coin figure the player was shown, for both real and demo play.\n");

fwrite(STDOUT, "Passed: $passed\n");
fwrite(STDOUT, "Game bet limit coin-peg-conversion integration checks passed.\n");
