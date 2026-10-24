<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Games\CasinoDice;
use App\Http\Controllers\User\UserController;
use App\Models\BonusCode;
use App\Models\BonusCodeRedemption;
use App\Models\CoinLedgerEntry;
use App\Models\CoinWallet;
use App\Models\GameplayBonusLog;
use App\Models\GameLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CoinLedger;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/coin_games_bonuses_integration.sqlite';
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

$frameworkMigration = require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php';
$frameworkMigration->up();

Schema::create('general_settings', function (Blueprint $table) {
    $table->id();
    $table->string('site_name', 190)->default('Test Site');
    $table->string('cur_text', 10)->default('USD');
    $table->string('cur_sym', 5)->default('$');
    $table->integer('currency_format')->default(1);
    $table->boolean('force_ssl')->default(0);
    $table->boolean('rb')->default(0);
    $table->decimal('register_bonus', 28, 8)->default(0);
    $table->boolean('game_play')->default(0);
    $table->decimal('gameplay_number_for_bonus', 28, 8)->default(0);
    $table->decimal('gameplay_bonus', 28, 8)->default(0);
    $table->timestamp('last_cron')->nullable();
    $table->timestamps();
});
DB::table('general_settings')->insert(['id' => 1]);
$coinSettingsMigration = require dirname(__DIR__) . '/database/migrations/2026_10_24_000011_add_coin_settings_to_general_settings.php';
$coinSettingsMigration->up();

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('username');
    $table->decimal('balance', 28, 8)->default(0);
    $table->decimal('demo_balance', 28, 8)->default(0);
    $table->unsignedBigInteger('ref_by')->nullable();
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->tinyInteger('status')->default(1);
    $table->tinyInteger('profile_complete')->default(0);
    $table->string('country_code')->nullable();
    $table->string('country_name')->nullable();
    $table->string('dial_code')->nullable();
    $table->string('mobile')->nullable();
    $table->string('state')->nullable();
    $table->string('gender')->nullable();
    $table->string('image')->nullable();
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
    $table->string('image')->nullable();
    $table->tinyInteger('status')->default(1);
    $table->tinyInteger('trending')->default(0);
    $table->tinyInteger('featured')->default(0);
    $table->decimal('win', 28, 8)->default(0);
    $table->decimal('max_limit', 28, 8)->default(0);
    $table->decimal('min_limit', 28, 8)->default(0);
    $table->tinyInteger('invest_back')->default(0);
    $table->string('probable_win')->nullable();
    $table->string('probable_win_demo')->nullable();
    $table->string('type')->nullable();
    $table->string('level')->nullable();
    $table->text('instruction');
    $table->text('short_desc')->nullable();
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
Schema::create('gameplay_bonus_logs', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id')->default(0);
    $table->unsignedBigInteger('game_id')->default(0);
    $table->decimal('amount', 28, 8)->default(0);
    $table->timestamps();
});

$coinCoreMigration = require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php';
$coinCoreMigration->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_25_000001_add_pause_state_to_coin_rates.php')->up();
$bonusCodesMigration = require dirname(__DIR__) . '/database/migrations/2026_10_18_000005_create_bonus_codes_table.php';
$bonusCodesMigration->up();

$ledger = app(CoinLedger::class);
$insertUser = static function (string $username, string $balance = '0', string $demoBalance = '0') use (&$userSeq): User {
    $id = DB::table('users')->insertGetId([
        'username' => $username,
        'balance' => $balance,
        'demo_balance' => $demoBalance,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    return User::findOrFail($id);
};

// A single, simple, deterministic mini-game: percent=100 on CasinoDice always wins (its
// internal rand(1,98) can never reach or exceed 100), so the win/loss outcome never depends on
// chance - only the configured win% (20) and invest_back flag (off) drive the credited amount,
// which is what this test actually needs to be deterministic about.
$gameId = DB::table('games')->insertGetId([
    'name' => 'Casino Dice', 'alias' => 'casino_dice', 'status' => 1,
    'win' => '20.00000000', 'max_limit' => '100000.00000000', 'min_limit' => '1.00000000',
    'invest_back' => 0, 'instruction' => 'Test instructions', 'created_at' => now(), 'updated_at' => now(),
]);

$playRound = static function (User $user, string $invest) {
    Auth::setUser($user->fresh());
    $startRequest = Request::create('/play/casino-dice', 'POST', ['invest' => $invest, 'percent' => '100', 'choose' => 'low']);
    app()->instance('request', $startRequest);
    $game = new CasinoDice();
    $playResponse = $game->play();
    $playData = $playResponse->getData(true);
    if (!isset($playData['game_log_id'])) {
        throw new RuntimeException('Game play did not return a game_log_id: ' . json_encode($playData));
    }
    $gameLogId = $playData['game_log_id'];

    Auth::setUser($user->fresh());
    $completeRequest = Request::create('/play/casino-dice/complete', 'POST', ['game_log_id' => $gameLogId]);
    app()->instance('request', $completeRequest);
    $completeGame = new CasinoDice();
    $completeResult = $completeGame->complete();

    return ['game_log_id' => $gameLogId, 'complete' => $completeResult];
};

// --- Scenario 1: coin_mode OFF - regression check. Balance moves exactly as before, and no
// coin wallet is touched at all. ---
$offPlayer = $insertUser('coin_games_off', '1000.00000000');
$roundOff = $playRound($offPlayer, '100');
if ($roundOff['complete']['win_status'] != 1) throw new RuntimeException('Test game was not forced to win - fixture is broken.');
// 1000 - 100 (stake) + 20 (20% win bonus, no invest-back) = 920.
if (bccomp((string) $offPlayer->fresh()->balance, '920', 8) !== 0) throw new RuntimeException("coin_mode=off: invest+win did not move real-currency balance exactly as before (expected 920, got {$offPlayer->fresh()->balance}).");
if (CoinWallet::where('owner_type', 'user')->where('owner_id', $offPlayer->id)->exists()) throw new RuntimeException('coin_mode=off: a coin wallet was created for a legacy-mode player - it must never be touched.');
if (Transaction::where('user_id', $offPlayer->id)->where('remark', 'invest')->count() !== 1) throw new RuntimeException('coin_mode=off: the legacy invest Transaction row was not written.');
if (Transaction::where('user_id', $offPlayer->id)->where('remark', 'Win_Bonus')->count() !== 1) throw new RuntimeException('coin_mode=off: the legacy win-bonus Transaction row was not written.');
fwrite(STDOUT, "Game invest+win round, coin_mode off: real-currency balance moves exactly as before and no coin wallet is touched (regression check).\n");

// --- Switch coin mode on for the remaining scenarios. ---
$general = gs();
$general->coin_mode = true;
$general->save();

// --- Scenario 2: coin_mode ON - the coin wallet (not $user->balance) moves correctly for both
// the stake debit and the win credit. ---
$onPlayer = $insertUser('coin_games_on');
$ledger->mint('user', $onPlayer->id, '500', 'test seed for gameplay', 'seed:' . $onPlayer->id);
if (bccomp((string) $ledger->wallet('user', $onPlayer->id)->fresh()->available_balance, '500', 8) !== 0) throw new RuntimeException('Coin seed mint did not land in the wallet.');

Auth::setUser($onPlayer->fresh());
$startRequest = Request::create('/play/casino-dice', 'POST', ['invest' => '100', 'percent' => '100', 'choose' => 'low']);
app()->instance('request', $startRequest);
$game = new CasinoDice();
$playResponse = $game->play();
$playData = $playResponse->getData(true);
$round1LogId = $playData['game_log_id'];

if (bccomp((string) $onPlayer->fresh()->balance, '0', 8) !== 0) throw new RuntimeException('coin_mode=on: invest incorrectly touched real-currency balance.');
if (bccomp((string) $ledger->wallet('user', $onPlayer->id)->fresh()->available_balance, '400', 8) !== 0) throw new RuntimeException('coin_mode=on: invest did not debit the coin wallet correctly (expected 500 - 100 = 400).');
if (Transaction::where('user_id', $onPlayer->id)->where('remark', 'invest')->exists()) throw new RuntimeException('coin_mode=on: a legacy invest Transaction row was written - coin mode must not write one.');

// --- Scenario 3: replaying the exact same invest burn (same GameLog id, same idempotency key
// invest() itself would use) must not double-debit. This exercises the real replay-safety net
// invest() relies on, using the real GameLog row this round just created. ---
$ledger->burn('user', $onPlayer->id, '100', 'Invest to Casino Dice', 'game_invest:' . $round1LogId, 'GameLog', $round1LogId, 'user', $onPlayer->id);
if (bccomp((string) $ledger->wallet('user', $onPlayer->id)->fresh()->available_balance, '400', 8) !== 0) throw new RuntimeException('Replaying the same invest idempotency key double-debited the coin wallet.');
fwrite(STDOUT, "Game invest attempted twice with the same GameLog: the coin debit is not applied a second time (replay protection).\n");

Auth::setUser($onPlayer->fresh());
$completeRequest = Request::create('/play/casino-dice/complete', 'POST', ['game_log_id' => $round1LogId]);
app()->instance('request', $completeRequest);
$completeGame = new CasinoDice();
$completeResult = $completeGame->complete();
if ($completeResult['win_status'] != 1) throw new RuntimeException('Test game was not forced to win - fixture is broken.');
if (bccomp((string) $onPlayer->fresh()->balance, '0', 8) !== 0) throw new RuntimeException('coin_mode=on: the win-bonus credit incorrectly touched real-currency balance.');
if (bccomp((string) $ledger->wallet('user', $onPlayer->id)->fresh()->available_balance, '420', 8) !== 0) throw new RuntimeException("coin_mode=on: win bonus did not credit the coin wallet correctly (expected 400 + 20 = 420, got {$ledger->wallet('user', $onPlayer->id)->fresh()->available_balance}).");
if (Transaction::where('user_id', $onPlayer->id)->where('remark', 'Win_Bonus')->exists()) throw new RuntimeException('coin_mode=on: a legacy win-bonus Transaction row was written - coin mode must not write one.');
if (!CoinLedgerEntry::where('idempotency_key', 'game_invest:' . $round1LogId . ':burn_out')->exists()) throw new RuntimeException('Expected a CoinLedgerEntry for the invest burn (player side).');
if (!CoinLedgerEntry::where('idempotency_key', 'game_win:' . $round1LogId . ':mint_in')->exists()) throw new RuntimeException('Expected a CoinLedgerEntry for the win mint (player side).');
if (!$ledger->reconcile()['ok']) throw new RuntimeException('Reconciliation broke after a coin-mode game round.');
fwrite(STDOUT, "Game invest+win round, coin_mode on: the coin wallet (not real-currency balance) moves correctly for the stake debit and the win credit, and the ledger reconciles.\n");

// --- Scenario 4: gameplay bonus fires once, not twice, after the qualifying play count, in
// coin mode - minting to the coin wallet instead of crediting real-currency balance. ---
$general = gs();
$general->game_play = 1;
$general->gameplay_number_for_bonus = '2';
$general->gameplay_bonus = '15';
$general->save();

// Round 2: invest 50, forced win (win% 20 -> +10). playCount becomes 2, meeting the configured
// threshold, so the gameplay bonus (15 coins) should mint on top of the round's own debit/credit.
Auth::setUser($onPlayer->fresh());
$startRequest = Request::create('/play/casino-dice', 'POST', ['invest' => '50', 'percent' => '100', 'choose' => 'low']);
app()->instance('request', $startRequest);
$game2 = new CasinoDice();
$round2LogId = $game2->play()->getData(true)['game_log_id'];
Auth::setUser($onPlayer->fresh());
$completeRequest = Request::create('/play/casino-dice/complete', 'POST', ['game_log_id' => $round2LogId]);
app()->instance('request', $completeRequest);
(new CasinoDice())->complete();

// 420 (after round 1) - 50 (round 2 stake) + 10 (round 2 win) + 15 (gameplay bonus) = 395.
if (bccomp((string) $ledger->wallet('user', $onPlayer->id)->fresh()->available_balance, '395', 8) !== 0) throw new RuntimeException("Gameplay bonus did not mint the correct amount to the coin wallet (expected 395, got {$ledger->wallet('user', $onPlayer->id)->fresh()->available_balance}).");
if (GameplayBonusLog::where('user_id', $onPlayer->id)->where('game_id', $gameId)->count() !== 1) throw new RuntimeException('Gameplay bonus did not record exactly one GameplayBonusLog row.');
if (bccomp((string) $onPlayer->fresh()->balance, '0', 8) !== 0) throw new RuntimeException('Gameplay bonus incorrectly touched real-currency balance in coin mode.');

// Round 3: another qualifying play. The bonus must NOT fire again.
Auth::setUser($onPlayer->fresh());
$startRequest = Request::create('/play/casino-dice', 'POST', ['invest' => '10', 'percent' => '100', 'choose' => 'low']);
app()->instance('request', $startRequest);
$game3 = new CasinoDice();
$round3LogId = $game3->play()->getData(true)['game_log_id'];
Auth::setUser($onPlayer->fresh());
$completeRequest = Request::create('/play/casino-dice/complete', 'POST', ['game_log_id' => $round3LogId]);
app()->instance('request', $completeRequest);
(new CasinoDice())->complete();

// 395 - 10 (round 3 stake) + 2 (round 3 win, 20% of 10) = 387. No second bonus.
if (bccomp((string) $ledger->wallet('user', $onPlayer->id)->fresh()->available_balance, '387', 8) !== 0) throw new RuntimeException("A third qualifying play incorrectly re-triggered (or skipped) movements (expected 387, got {$ledger->wallet('user', $onPlayer->id)->fresh()->available_balance}).");
if (GameplayBonusLog::where('user_id', $onPlayer->id)->where('game_id', $gameId)->count() !== 1) throw new RuntimeException('Gameplay bonus fired a second time for the same user+game - it must fire at most once.');
if (!$ledger->reconcile()['ok']) throw new RuntimeException('Reconciliation broke after the gameplay bonus scenario.');
fwrite(STDOUT, "Gameplay bonus: fires exactly once after the qualifying play count, minting the flat coin amount to the coin wallet (coin mode on).\n");

// --- Scenario 5: register bonus, coin mode on - mints the configured flat amount through
// CoinLedger instead of crediting real-currency balance. ---
$general = gs();
$general->rb = 1;
$general->register_bonus = '25';
$general->save();

$registrant = $insertUser('coin_games_registrant');
DB::table('users')->where('id', $registrant->id)->update(['profile_complete' => 0]);
Auth::setUser($registrant->fresh());
$registerRequest = Request::create('/user/data/submit', 'POST', [
    'country_code' => 'AF',
    'country'      => 'Afghanistan',
    'mobile_code'  => '93',
    'username'     => 'coin_games_registrant_u',
    'mobile'       => '5551234567',
    'gender'       => 'male',
    'state'        => 'Kabul',
]);
$registerRequest->setUserResolver(fn () => $registrant->fresh());
app()->instance('request', $registerRequest);
(new UserController())->userDataSubmit($registerRequest);

if (bccomp((string) $registrant->fresh()->balance, '0', 8) !== 0) throw new RuntimeException('Register bonus incorrectly touched real-currency balance in coin mode.');
if (bccomp((string) $ledger->wallet('user', $registrant->id)->fresh()->available_balance, '25', 8) !== 0) throw new RuntimeException("Register bonus did not mint the correct coin amount (expected 25, got {$ledger->wallet('user', $registrant->id)->fresh()->available_balance}).");
if (!CoinLedgerEntry::where('idempotency_key', 'register_bonus:' . $registrant->id . ':mint_in')->exists()) throw new RuntimeException('Expected a CoinLedgerEntry for the register bonus mint.');
fwrite(STDOUT, "Register bonus, coin mode on: mints the correct flat coin amount through CoinLedger instead of crediting real-currency balance.\n");

// --- Scenario 6: bonus code redemption, coin mode on - mints the code's flat amount through
// CoinLedger instead of crediting real-currency balance. ---
$bonusCode = BonusCode::create(['code' => 'COINTEST50', 'amount' => '50.00000000', 'status' => 1]);
$codeRedeemer = $insertUser('coin_games_code_redeemer');
Auth::setUser($codeRedeemer->fresh());
$redeemRequest = Request::create('/user/bonus-code/activate', 'POST', ['code' => 'COINTEST50']);
$redeemRequest->setUserResolver(fn () => $codeRedeemer->fresh());
app()->instance('request', $redeemRequest);
(new UserController())->activateBonusCode($redeemRequest);

if (bccomp((string) $codeRedeemer->fresh()->balance, '0', 8) !== 0) throw new RuntimeException('Bonus code redemption incorrectly touched real-currency balance in coin mode.');
if (bccomp((string) $ledger->wallet('user', $codeRedeemer->id)->fresh()->available_balance, '50', 8) !== 0) throw new RuntimeException("Bonus code redemption did not mint the correct coin amount (expected 50, got {$ledger->wallet('user', $codeRedeemer->id)->fresh()->available_balance}).");
$redemption = BonusCodeRedemption::where('user_id', $codeRedeemer->id)->where('bonus_code_id', $bonusCode->id)->first();
if (!$redemption) throw new RuntimeException('Bonus code redemption did not record a BonusCodeRedemption row.');
if (!CoinLedgerEntry::where('idempotency_key', 'bonus_code:' . $redemption->id . ':mint_in')->exists()) throw new RuntimeException('Expected a CoinLedgerEntry for the bonus code mint.');
if (Transaction::where('user_id', $codeRedeemer->id)->where('remark', 'bonus_code')->exists()) throw new RuntimeException('coin_mode=on: a legacy bonus_code Transaction row was written - coin mode must not write one.');
if (!$ledger->reconcile()['ok']) throw new RuntimeException('Reconciliation broke after the bonus-code redemption.');
fwrite(STDOUT, "Bonus code redemption, coin mode on: mints the correct flat coin amount through CoinLedger instead of crediting real-currency balance.\n");

// Best-effort teardown.
try {
    $bonusCodesMigration->down();
    $coinCoreMigration->down();
    $coinSettingsMigration->down();
    $frameworkMigration->down();
} catch (\Throwable $teardownException) {
    fwrite(STDERR, 'Note: best-effort schema teardown did not fully complete (' . $teardownException->getMessage() . "), ignoring.\n");
}
DB::disconnect();
@unlink($dbPath);
fwrite(STDOUT, "Coin-mode casino games and bonus mechanisms integration checks passed.\n");
