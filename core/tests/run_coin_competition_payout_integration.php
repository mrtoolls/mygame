<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\Controllers\Admin\CompetitionController;
use App\Models\Admin;
use App\Models\Competition;
use App\Models\CompetitionResult;
use App\Models\Currency;
use App\Models\User;
use App\Services\CoinLedger;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/coin_competition_payout_integration.sqlite';
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
    $table->string('cur_text', 10)->default('USD');
    $table->string('cur_sym', 5)->default('$');
    $table->integer('currency_format')->default(1);
    $table->boolean('force_ssl')->default(0);
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
    $table->unsignedBigInteger('ref_by')->nullable();
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->tinyInteger('status')->default(1);
    $table->timestamps();
});
Schema::create('admins', function (Blueprint $table) { $table->id(); $table->string('username')->nullable(); $table->timestamps(); });
Schema::create('currencies', function (Blueprint $table) {
    $table->id();
    $table->string('code', 10);
    $table->string('name')->nullable();
    $table->string('symbol', 5)->nullable();
    $table->integer('decimals')->default(2);
    $table->boolean('enabled')->default(true);
    $table->string('symbol_position', 10)->default('left');
    $table->decimal('rate_to_usd', 24, 12)->nullable();
    $table->string('rate_source', 10)->default('manual');
    $table->boolean('auto_update')->default(false);
    $table->timestamp('last_updated_at')->nullable();
    $table->timestamps();
});

$coinCoreMigration = require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php';
$coinCoreMigration->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_25_000001_add_pause_state_to_coin_rates.php')->up();

$competitionsMigration = require dirname(__DIR__) . '/database/migrations/2026_10_22_000004_create_competitions_core_tables.php';
$competitionsMigration->up();

$ledger = app(CoinLedger::class);

$insertUser = static function (string $username, ?int $currencyId = null): User {
    $id = DB::table('users')->insertGetId(['username' => $username, 'currency_id' => $currencyId, 'created_at' => now(), 'updated_at' => now()]);
    return User::findOrFail($id);
};
$insertAdmin = static function (string $username): Admin {
    $id = DB::table('admins')->insertGetId(['username' => $username, 'created_at' => now(), 'updated_at' => now()]);
    return Admin::findOrFail($id);
};
$makeCompetition = static function (string $slug): Competition {
    return Competition::create([
        'title' => 'Test Competition ' . $slug,
        'slug' => $slug,
        'type' => Competition::TYPE_WAGER_RACE,
        'status' => Competition::STATUS_FINALIZED,
        'start_at' => now()->subDays(2),
        'end_at' => now()->subDay(),
        'grace_minutes' => 30,
    ]);
};

$admin = $insertAdmin('coin_payout_admin');
Auth::guard('admin')->setUser($admin);
$controller = new CompetitionController();

// --- coin_mode OFF: regression check - payResult() behaves exactly as before, no coin wallet touched. ---
$general = gs();
$general->coin_mode = false;
$general->save();

$usd = Currency::create(['code' => 'USD', 'decimals' => 2, 'enabled' => true, 'rate_to_usd' => '1', 'last_updated_at' => now()]);
$legacyWinner = $insertUser('legacy_winner', $usd->id);
$legacyCompetition = $makeCompetition('legacy-comp');
$legacyResult = CompetitionResult::create([
    'competition_id' => $legacyCompetition->id,
    'user_id' => $legacyWinner->id,
    'rank' => 1,
    'metric_value' => '1000.00000000',
    'metric_label' => 'usd',
    'prize_amount_usd' => '50.00000000',
]);

$legacyRequest = \Illuminate\Http\Request::create('/admin/competitions/results/' . $legacyResult->id . '/pay', 'POST', [
    'payout_currency_id' => $usd->id,
    'payout_rate_to_usd' => '1',
    'source_ref' => 'manual-wire-123',
]);
$controller->payResult($legacyRequest, $legacyResult->id);
$legacyResult = $legacyResult->fresh();

if (!$legacyResult->isPaid()) throw new RuntimeException('coin_mode=off payResult did not mark the result as paid.');
if ((int) $legacyResult->payout_currency_id !== $usd->id) throw new RuntimeException('coin_mode=off payResult did not store the admin-picked payout currency.');
if (bccomp((string) $legacyResult->payout_rate_to_usd, '1', 8) !== 0) throw new RuntimeException('coin_mode=off payResult did not store the admin-entered rate.');
if ($legacyResult->source_ref !== 'manual-wire-123') throw new RuntimeException('coin_mode=off payResult did not store the admin-entered source_ref as before.');
if (bccomp((string) $legacyResult->payout_amount, '50', 8) !== 0) throw new RuntimeException('coin_mode=off payResult computed the wrong manual payout_amount.');
if (!DB::table('coin_wallets')->where('owner_type', 'user')->where('owner_id', $legacyWinner->id)->doesntExist()) {
    throw new RuntimeException('coin_mode=off payResult incorrectly created/touched a coin wallet for the winner.');
}
fwrite(STDOUT, "payResult: coin_mode off behaves exactly as before - manual currency/rate flow, no coin wallet touched (regression check).\n");

// A second click once already paid must still be rejected the same way as before, untouched.
$legacyRequest2 = \Illuminate\Http\Request::create('/admin/competitions/results/' . $legacyResult->id . '/pay', 'POST', [
    'payout_currency_id' => $usd->id,
    'payout_rate_to_usd' => '2',
    'source_ref' => 'should-not-apply',
]);
$controller->payResult($legacyRequest2, $legacyResult->id);
$legacyResult = $legacyResult->fresh();
if ($legacyResult->source_ref !== 'manual-wire-123' || bccomp((string) $legacyResult->payout_rate_to_usd, '1', 8) !== 0) {
    throw new RuntimeException('coin_mode=off: a second payResult call on an already-paid result incorrectly re-applied new values.');
}
fwrite(STDOUT, "payResult: coin_mode off - a second call on an already-paid result is rejected, exactly as before.\n");

// --- coin_mode ON: correct coin amount minted to the winner's wallet, matching coin_peg_usd. ---
$general = gs();
$general->coin_mode = true;
$general->coin_peg_usd = '0.01000000'; // 1 coin = $0.01, so $50 prize = 5000 coins
$general->save();

$coinWinner = $insertUser('coin_winner', $usd->id);
$coinCompetition = $makeCompetition('coin-comp');
$coinResult = CompetitionResult::create([
    'competition_id' => $coinCompetition->id,
    'user_id' => $coinWinner->id,
    'rank' => 1,
    'metric_value' => '2000.00000000',
    'metric_label' => 'usd',
    'prize_amount_usd' => '50.00000000',
]);

$payRequest = \Illuminate\Http\Request::create('/admin/competitions/results/' . $coinResult->id . '/pay', 'POST', []);
$controller->payResult($payRequest, $coinResult->id);
$coinResult = $coinResult->fresh();

if (!$coinResult->isPaid()) throw new RuntimeException('coin_mode=on payResult did not mark the result as paid.');
if ($coinResult->payout_currency_id !== null) throw new RuntimeException('coin_mode=on payResult should not set a manual payout currency.');

$expectedCoins = bcdiv('50', '0.01', 8); // 5000
if (bccomp((string) $coinResult->payout_amount, $expectedCoins, 8) !== 0) {
    throw new RuntimeException("coin_mode=on payResult stored the wrong coin payout_amount: {$coinResult->payout_amount}, expected {$expectedCoins}");
}

$winnerWallet = $ledger->wallet('user', $coinWinner->id)->fresh();
if (bccomp((string) $winnerWallet->available_balance, $expectedCoins, 8) !== 0) {
    throw new RuntimeException("coin_mode=on payResult minted the wrong coin amount to the winner's wallet: {$winnerWallet->available_balance}, expected {$expectedCoins}");
}
if (!$ledger->reconcile()['ok']) throw new RuntimeException('Reconciliation broke after a coin-mode competition payout.');
fwrite(STDOUT, "payResult: coin_mode on mints the correct coin amount (prize_amount_usd / coin_peg_usd) to the winner's wallet, and the ledger reconciles.\n");

// A different peg produces a different coin amount, confirming the math actually reads coin_peg_usd rather than a hardcoded constant.
$general = gs();
$general->coin_peg_usd = '0.02000000'; // 1 coin = $0.02
$general->save();

$coinWinner2 = $insertUser('coin_winner_2', $usd->id);
$coinResult2 = CompetitionResult::create([
    'competition_id' => $coinCompetition->id,
    'user_id' => $coinWinner2->id,
    'rank' => 2,
    'metric_value' => '1500.00000000',
    'metric_label' => 'usd',
    'prize_amount_usd' => '10.00000000',
]);
$controller->payResult(\Illuminate\Http\Request::create('/x', 'POST'), $coinResult2->id);
$expectedCoins2 = bcdiv('10', '0.02', 8); // 500
$winner2Wallet = $ledger->wallet('user', $coinWinner2->id)->fresh();
if (bccomp((string) $winner2Wallet->available_balance, $expectedCoins2, 8) !== 0) {
    throw new RuntimeException("coin_mode=on payResult did not respect a changed coin_peg_usd: got {$winner2Wallet->available_balance}, expected {$expectedCoins2}");
}
fwrite(STDOUT, "payResult: coin_mode on recomputes against the currently configured coin_peg_usd, not a hardcoded value.\n");

// --- Double-click / retried request must never double-mint. ---
// Scenario A: the ordinary double-click - the result is already paid, so the controller's own
// isPaid() guard rejects the second call outright and nothing is touched again.
$balanceAfterFirstPay = (string) $ledger->wallet('user', $coinWinner->id)->fresh()->available_balance;
$controller->payResult(\Illuminate\Http\Request::create('/x', 'POST'), $coinResult->id);
$balanceAfterSecondClick = (string) $ledger->wallet('user', $coinWinner->id)->fresh()->available_balance;
if (bccomp($balanceAfterFirstPay, $balanceAfterSecondClick, 8) !== 0) {
    throw new RuntimeException("Double-clicking pay on an already-paid result changed the winner's balance: {$balanceAfterFirstPay} -> {$balanceAfterSecondClick}");
}
fwrite(STDOUT, "payResult: double-clicking pay on an already-paid result does not double-mint (isPaid() guard).\n");

// Scenario B: a retried request where the first attempt minted the coins but crashed (or the
// response was lost) before paid_at was persisted - isPaid() is still false, so the controller
// would otherwise try to mint again. CoinLedger's idempotency key (built from this result's own
// primary key) must make the replay a safe no-op instead of a double credit.
$coinWinner3 = $insertUser('coin_winner_3', $usd->id);
$coinResult3 = CompetitionResult::create([
    'competition_id' => $coinCompetition->id,
    'user_id' => $coinWinner3->id,
    'rank' => 3,
    'metric_value' => '1200.00000000',
    'metric_label' => 'usd',
    'prize_amount_usd' => '20.00000000',
]);
// Simulate "mint already happened, but the save of paid_at never committed" by calling the same
// idempotency key the controller will use, directly through the ledger, before the controller
// itself ever runs.
$ledger->mint('user', $coinWinner3->id, bcdiv('20', '0.02', 8), 'Competition prize payout - pre-existing', 'competition_payout:' . $coinResult3->id, 'CompetitionResult', $coinResult3->id, 'admin', $admin->id);
$balanceAfterSimulatedCrash = (string) $ledger->wallet('user', $coinWinner3->id)->fresh()->available_balance;

$controller->payResult(\Illuminate\Http\Request::create('/x', 'POST'), $coinResult3->id);
$coinResult3 = $coinResult3->fresh();
$balanceAfterRetry = (string) $ledger->wallet('user', $coinWinner3->id)->fresh()->available_balance;

if (!$coinResult3->isPaid()) throw new RuntimeException('The retried payResult call did not finish marking the result as paid.');
if (bccomp($balanceAfterSimulatedCrash, $balanceAfterRetry, 8) !== 0) {
    throw new RuntimeException("A retried payResult call double-minted after a simulated crash: {$balanceAfterSimulatedCrash} -> {$balanceAfterRetry}");
}
if (!$ledger->reconcile()['ok']) throw new RuntimeException('Reconciliation broke after the retried-payout scenario.');
fwrite(STDOUT, "payResult: a retried request reusing the same result's idempotency key never double-mints, even if paid_at was never previously persisted.\n");

// Best-effort teardown.
try {
    $competitionsMigration->down();
    $coinCoreMigration->down();
    $coinSettingsMigration->down();
    $frameworkMigration->down();
} catch (\Throwable $teardownException) {
    fwrite(STDERR, 'Note: best-effort schema teardown did not fully complete (' . $teardownException->getMessage() . "), ignoring.\n");
}
DB::disconnect();
@unlink($dbPath);
fwrite(STDOUT, "Coin competition payout integration checks passed.\n");
