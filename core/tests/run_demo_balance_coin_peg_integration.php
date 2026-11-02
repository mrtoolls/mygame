<?php

// users.demo_balance is a literal real-currency figure - it meant dollars before coin mode
// existed and still does today. Once coin mode is on, the demo player types and sees coins (the
// exact same bet field, min/max hints as real play), so the stored dollar figure has to be
// peg-converted at every read/write boundary (balance check, stake debit, win credit, response
// display) - the same convention already used for other legacy USD-denominated settings (VIP
// tier bonuses, game bet limits), not reinterpreted as if it were already a native coin count
// (which would silently devalue a demo balance configured in dollars to 1/peg of its worth - a
// $400 demo balance showing as a mere 400 coins instead of 40,000 at the default 0.01 peg).
// This test proves the fix end-to-end through the real Game engine (not just template display).

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Games\CasinoDice;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/demo_balance_coin_peg_integration.sqlite';
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

// Deterministic: percent=100 on CasinoDice always wins, win=20% of invest, invest_back off.
$gameId = DB::table('games')->insertGetId([
    'name' => 'Casino Dice', 'alias' => 'casino_dice', 'status' => 1,
    'win' => '20.00000000', 'max_limit' => '100000.00000000', 'min_limit' => '1.00000000',
    'invest_back' => 0, 'instruction' => 'Test', 'created_at' => now(), 'updated_at' => now(),
]);

$passed = 0;
function check(bool $cond, string $message): void {
    global $passed;
    if (!$cond) throw new RuntimeException("FAILED: $message");
    $passed++;
}

$playDemoRound = static function (User $user, string $invest) {
    Auth::setUser($user->fresh());
    app()->instance('request', Request::create('/x', 'POST', ['invest' => $invest, 'percent' => '100', 'choose' => 'low']));
    $game = new CasinoDice();
    $game->demoPlay = true;
    $playResponse = $game->play();
    $playData = $playResponse->getData(true);

    Auth::setUser($user->fresh());
    app()->instance('request', Request::create('/x', 'POST', ['game_log_id' => $playData['game_log_id']]));
    $completeGame = new CasinoDice();
    $completeGame->demoPlay = true;
    $completeResult = $completeGame->complete();

    return ['play' => $playData, 'complete' => $completeResult];
};

// --- coin_mode OFF: demo balance moves in plain currency, unchanged (regression check). ---
$offUserId = DB::table('users')->insertGetId(['username' => 'demo_off', 'demo_balance' => '1000.00000000', 'created_at' => now(), 'updated_at' => now()]);
$offUser = User::find($offUserId);
$roundOff = $playDemoRound($offUser, '100');
check($roundOff['complete']['win_status'] == 1, 'Fixture is broken: the deterministic test game did not win.');
// 1000 - 100 (stake) + 20 (20% win, no invest-back) = 920, no peg conversion off.
check(bccomp((string) $offUser->fresh()->demo_balance, '920', 8) === 0, 'coin_mode off: demo_balance moves in plain currency exactly as before (expected 920).');
check((string) $roundOff['play']['balance'] === '900.00', 'coin_mode off: play() response balance is unconverted currency (1000 - 100 = 900.00).');
fwrite(STDOUT, "Demo play, coin_mode off: unchanged plain-currency balance movement and response display (regression check).\n");

// --- coin_mode ON: demo_balance is peg-converted (0.01 default) at every boundary. ---
$general->coin_mode = true;
$general->save();

// $400 demo balance (pre-coin-mode dollars) should present as 40,000 coins at peg 0.01, not 400.
$onUserId = DB::table('users')->insertGetId(['username' => 'demo_on', 'demo_balance' => '400.00000000', 'created_at' => now(), 'updated_at' => now()]);
$onUser = User::find($onUserId);

// A bet of "100" here is typed in COINS (same bet field/min-max hints as real play) - at peg
// 0.01 that is $1.00 of the underlying demo_balance column, not $100.
$roundOn = $playDemoRound($onUser, '100');
check($roundOn['complete']['win_status'] == 1, 'Fixture is broken: the deterministic test game did not win (coin mode on).');

// Stake: 400 - (100 coins * 0.01) = 400 - 1 = 399. Win: 20% of 100 coins = 20 coins = $0.20.
// 399 + 0.20 = 399.20 USD-equivalent stored in the column.
check(bccomp((string) $onUser->fresh()->demo_balance, '399.20000000', 8) === 0, 'coin_mode on: demo_balance (stored as USD) moves by the PEG-CONVERTED coin stake/win, not the raw coin number (expected 399.20, got ' . $onUser->fresh()->demo_balance . ').');

// Displayed/returned balance must be the COIN-equivalent of the USD figure at each point: right
// after the stake debit (399.00 / 0.01 = 39900), then after the win credit (399.20 / 0.01 = 39920).
check((string) $roundOn['play']['balance'] === '39900', 'play() response balance is peg-converted to whole coins after the stake debit (expected 39900, got ' . $roundOn['play']['balance'] . ').');
check((string) $roundOn['complete']['bal'] === '39920', 'complete()/end() response balance is peg-converted to whole coins after the win credit (expected 39920, got ' . $roundOn['complete']['bal'] . ').');
fwrite(STDOUT, "Demo play, coin_mode on: demo_balance stays a USD-denominated column internally but is correctly peg-converted to coins for the balance check, stake debit, win credit, and every response display - a \$400 demo balance correctly shows as 40,000 coins, not a bare relabelled 400.\n");

fwrite(STDOUT, "Passed: $passed\n");
fwrite(STDOUT, "Demo balance coin-peg-conversion integration checks passed.\n");
