<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\Controllers\Admin\AgentCoinFloatController;
use App\Http\Controllers\Admin\CoinSettingsController;
use App\Http\Controllers\CronHandlers\CoinReconciliationCron;
use App\Http\Controllers\Gateway\PaymentController;
use App\Models\AgentCoinFloatGrant;
use App\Models\CoinLedgerEntry;
use App\Models\CoinRate;
use App\Models\CoinWallet;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\Referral;
use App\Models\User;
use App\Services\CoinLedger;
use App\Services\CoinPricing;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/coin_ledger_integration.sqlite';
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
    $table->boolean('dc')->default(0);
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
Schema::create('admin_notifications', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id')->default(0);
    $table->string('title');
    $table->string('click_url')->nullable();
    $table->boolean('is_read')->default(false);
    $table->timestamps();
});
Schema::create('referrals', function (Blueprint $table) { $table->id(); $table->integer('level'); $table->decimal('percent', 8, 4); $table->timestamps(); });
Schema::create('commission_logs', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->unsignedBigInteger('who');
    $table->string('level');
    $table->decimal('amount', 28, 8);
    $table->decimal('main_amo', 28, 8);
    $table->string('title')->nullable();
    $table->string('trx');
    $table->timestamps();
});
Schema::create('support_agents', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('username');
    $table->boolean('is_active')->default(true);
    $table->boolean('is_payment_agent')->default(false);
    $table->timestamps();
});
Schema::create('deposits', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->decimal('amount', 28, 8);
    $table->decimal('charge', 28, 8)->default(0);
    $table->decimal('final_amount', 28, 8)->default(0);
    $table->integer('method_code')->default(5000);
    $table->integer('status')->default(0);
    $table->string('trx');
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->string('confirmed_via')->nullable();
    $table->string('admin_feedback')->nullable();
    $table->timestamps();
});

$coinCoreMigration = require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php';
$coinCoreMigration->up();
$floatGrantsMigration = require dirname(__DIR__) . '/database/migrations/2026_10_24_000012_create_agent_coin_float_grants_table.php';
$floatGrantsMigration->up();
$depositFieldsMigration = require dirname(__DIR__) . '/database/migrations/2026_10_24_000014_add_coin_purchase_fields_to_deposits.php';
$depositFieldsMigration->up();

$ledger = app(CoinLedger::class);
$insertUser = static function (string $username, int $currencyId = 1): User {
    $id = DB::table('users')->insertGetId(['username' => $username, 'currency_id' => $currencyId, 'created_at' => now(), 'updated_at' => now()]);
    return User::findOrFail($id);
};

// --- Core ledger: credit/debit/hold/release, idempotency, and non-negative enforcement. ---
$alice = $insertUser('coin_alice');
$ledger->mint('user', $alice->id, '100', 'test credit', 'test:credit:1');
$ledger->mint('user', $alice->id, '100', 'test credit', 'test:credit:1'); // replay, must not double-apply
if (bccomp((string) $ledger->wallet('user', $alice->id)->fresh()->available_balance, '100', 8) !== 0) throw new RuntimeException('Credit idempotency failed - a replayed credit changed the balance.');

$ledger->hold('user', $alice->id, '40', 'test hold', 'test:hold:1');
$wallet = $ledger->wallet('user', $alice->id)->fresh();
if (bccomp((string) $wallet->available_balance, '60', 8) !== 0 || bccomp((string) $wallet->held_balance, '40', 8) !== 0) throw new RuntimeException('Hold did not move coins from available to held correctly.');

try {
    $ledger->debit('user', $alice->id, '1000', 'overdraw', 'test:debit:overdraw');
    throw new RuntimeException('Debiting more than the available balance did not throw.');
} catch (InvalidArgumentException) {}
if (bccomp((string) $ledger->wallet('user', $alice->id)->fresh()->available_balance, '60', 8) !== 0) throw new RuntimeException('A rejected overdraw still changed the balance.');

$ledger->release('user', $alice->id, '40', 'test release', 'test:release:1');
$wallet = $ledger->wallet('user', $alice->id)->fresh();
if (bccomp((string) $wallet->available_balance, '100', 8) !== 0 || bccomp((string) $wallet->held_balance, '0', 8) !== 0) throw new RuntimeException('Release did not move coins back to available correctly.');
fwrite(STDOUT, "CoinLedger: credit/debit/hold/release and idempotency behave correctly.\n");

// --- Mint/burn and reconciliation. ---
$bob = $insertUser('coin_bob');
$ledger->mint('user', $bob->id, '50', 'test mint', 'test:mint:1');
$reconcile = $ledger->reconcile();
if (!$reconcile['ok']) throw new RuntimeException("Reconciliation should balance after a mint, got difference {$reconcile['difference']}.");
if (bccomp((string) $ledger->wallet('platform', CoinLedger::PLATFORM_OWNER_ID)->fresh()->available_balance, '-150', 8) !== 0) throw new RuntimeException('Platform wallet did not go negative by the total minted amount (100 from alice test + 50 from bob).');

$ledger->burn('user', $bob->id, '20', 'test burn', 'test:burn:1');
$reconcile = $ledger->reconcile();
if (!$reconcile['ok']) throw new RuntimeException("Reconciliation should still balance after a burn, got difference {$reconcile['difference']}.");

// Simulate a ledger bypass (something writing to coin_wallets directly, which CoinLedger never
// does) and confirm reconcile() actually detects it rather than always reporting ok.
DB::table('coin_wallets')->where('owner_type', 'user')->where('owner_id', $bob->id)->update(['available_balance' => DB::raw('available_balance + 5')]);
if ($ledger->reconcile()['ok']) throw new RuntimeException('reconcile() did not detect a manual ledger bypass.');
DB::table('coin_wallets')->where('owner_type', 'user')->where('owner_id', $bob->id)->update(['available_balance' => DB::raw('available_balance - 5')]);
if (!$ledger->reconcile()['ok']) throw new RuntimeException('reconcile() did not recover once the bypass was undone.');
fwrite(STDOUT, "CoinLedger: mint/burn keep the platform wallet in balance, and reconcile() detects a real mismatch.\n");

// --- Trade settlement primitive (held -> another owner's available), for Phase 2 to build on. ---
$carol = $insertUser('coin_carol');
$ledger->mint('user', $alice->id, '30', 'seed for settlement test', 'test:credit:settle');
$ledger->hold('user', $alice->id, '30', 'listing held', 'test:hold:settle');
$ledger->settleHeldToOwner('user', $alice->id, 'user', $carol->id, '30', 'trade settle', 'test:settle:1');
if (bccomp((string) $ledger->wallet('user', $carol->id)->fresh()->available_balance, '30', 8) !== 0) throw new RuntimeException('settleHeldToOwner did not credit the receiving wallet.');
if (bccomp((string) $ledger->wallet('user', $alice->id)->fresh()->held_balance, '0', 8) !== 0) throw new RuntimeException('settleHeldToOwner did not clear the held balance on the source wallet.');
fwrite(STDOUT, "CoinLedger: settleHeldToOwner correctly moves held coins to another owner's available balance.\n");

// --- CoinPricing: USD peg, margins, stale-rate guard, large-swing guard. ---
$ngn = Currency::create(['code' => 'NGN', 'decimals' => 2, 'enabled' => true, 'rate_to_usd' => '0.00065', 'last_updated_at' => now()]);
$pricing = app(CoinPricing::class);
$rate = $pricing->recompute($ngn);
// base price = 0.01 / 0.00065 = 15.384615... NGN per coin; 3% buy margin -> ~15.85; 3% sell margin -> ~14.92
if (bccomp((string) $rate->buy_price, '15', 8) <= 0 || bccomp((string) $rate->buy_price, '17', 8) >= 0) throw new RuntimeException("Computed NGN buy price looks wrong: {$rate->buy_price}");
if (bccomp((string) $rate->sell_price, (string) $rate->buy_price, 8) >= 0) throw new RuntimeException('Sell price should be lower than buy price (the platform margin).');

$stale = Currency::create(['code' => 'XAF', 'decimals' => 0, 'enabled' => true, 'rate_to_usd' => '0.0016', 'last_updated_at' => now()->subHours(72)]);
try {
    $pricing->recompute($stale);
    throw new RuntimeException('Recompute did not reject a stale exchange rate.');
} catch (InvalidArgumentException) {}

// A large automatic swing is flagged, not auto-applied; the same swing triggered by an admin is applied.
$ngn->rate_to_usd = '0.0002'; // a huge jump, well past the default 15% guard
$ngn->last_updated_at = now();
$ngn->save();
$beforeFlagged = CoinRate::where('currency_id', $ngn->id)->first();
$pricing->recompute($ngn); // automatic, no admin id
$afterAuto = CoinRate::where('currency_id', $ngn->id)->first();
if (!$afterAuto->flagged_for_review) throw new RuntimeException('A large automatic rate swing was not flagged for review.');
if (bccomp((string) $afterAuto->buy_price, (string) $beforeFlagged->buy_price, 8) !== 0) throw new RuntimeException('A flagged automatic swing should not have changed the live price.');
$pricing->recompute($ngn, adminId: 1); // admin-triggered, applies regardless
$afterAdmin = CoinRate::where('currency_id', $ngn->id)->first();
if ($afterAdmin->flagged_for_review) throw new RuntimeException('An admin-triggered recompute should clear the flagged state.');
if (bccomp((string) $afterAdmin->buy_price, (string) $beforeFlagged->buy_price, 8) === 0) throw new RuntimeException('An admin-triggered recompute should have applied the new price.');
fwrite(STDOUT, "CoinPricing: USD-peg margins, stale-rate guard, and large-swing guard all behave correctly.\n");

// --- Gateway purchase integration: coin_mode off leaves legacy behavior untouched. ---
$dave = $insertUser('coin_dave', $ngn->id);
$legacyDeposit = Deposit::forceCreate(['user_id' => $dave->id, 'amount' => '500.00000000', 'status' => 0, 'trx' => 'LEGACYDEP001', 'currency_id' => $ngn->id]);
PaymentController::userDataUpdate($legacyDeposit);
if (bccomp((string) $dave->fresh()->balance, '500', 8) !== 0) throw new RuntimeException('coin_mode=off gateway deposit did not credit real-currency balance as before.');
if ($legacyDeposit->fresh()->coin_mode_purchase) throw new RuntimeException('coin_mode=off deposit was incorrectly marked as a coin purchase.');
fwrite(STDOUT, "Gateway deposit: coin_mode off behaves exactly as before (regression check).\n");

// --- Gateway purchase integration: coin_mode on mints coins at the locked rate instead. ---
$general = gs();
$general->coin_mode = true;
$general->dc = true;
$general->save();
Referral::create(['level' => 1, 'percent' => '10.0000']);
$eve = $insertUser('coin_eve', $ngn->id);
DB::table('users')->where('id', $dave->id)->update(['ref_by' => null]);
$referrer = $insertUser('coin_referrer', $ngn->id);
DB::table('users')->where('id', $eve->id)->update(['ref_by' => $referrer->id]);
$eve = $eve->fresh();

$buyPrice = (string) CoinRate::where('currency_id', $ngn->id)->first()->buy_price;
$coinDeposit = Deposit::forceCreate(['user_id' => $eve->id, 'amount' => bcmul($buyPrice, '10', 8), 'status' => 0, 'trx' => 'COINDEP001', 'currency_id' => $ngn->id]);
PaymentController::userDataUpdate($coinDeposit);
$coinDeposit = $coinDeposit->fresh();
if (!$coinDeposit->coin_mode_purchase) throw new RuntimeException('coin_mode=on deposit was not marked as a coin purchase.');
if (bccomp((string) $coinDeposit->coin_amount, '10', 8) !== 0) throw new RuntimeException("coin_mode=on deposit minted the wrong coin amount: {$coinDeposit->coin_amount}");
if (bccomp((string) $eve->fresh()->balance, '0', 8) !== 0) throw new RuntimeException('coin_mode=on deposit incorrectly also credited real-currency balance.');
if (bccomp((string) $ledger->wallet('user', $eve->id)->fresh()->available_balance, '10', 8) !== 0) throw new RuntimeException('coin_mode=on deposit did not mint the correct coin amount to the wallet.');
if (bccomp((string) $ledger->wallet('user', $referrer->id)->fresh()->available_balance, '1', 8) !== 0) throw new RuntimeException('Coin-mode referral commission (10% of 10 coins) was not credited to the referrer.');
if (!$ledger->reconcile()['ok']) throw new RuntimeException('Reconciliation broke after a coin-mode gateway purchase.');
fwrite(STDOUT, "Gateway deposit: coin_mode on mints the correct coin amount at the locked rate, plus referral commission in coins.\n");

// --- No agent-deposit minting in coin mode. ---
$agentDeposit = Deposit::forceCreate(['user_id' => $eve->id, 'amount' => '1000', 'status' => 2, 'trx' => 'AGENTDEP001', 'currency_id' => $ngn->id]);
try {
    PaymentController::userDataUpdate($agentDeposit, true, 'agent');
    throw new RuntimeException('An agent-confirmed deposit was allowed to mint coins while coin mode is on.');
} catch (\RuntimeException $exception) {
    if (!str_contains($exception->getMessage(), 'cannot mint coins')) throw $exception;
}
if ($agentDeposit->fresh()->status !== 2) throw new RuntimeException('A blocked agent-deposit mint attempt still changed the deposit status.');
fwrite(STDOUT, "Gateway deposit: agent-confirmed deposits are correctly blocked from minting coins in coin mode.\n");

// --- Reconciliation cron handler. ---
(new CoinReconciliationCron())->run();
if (!gs()->coin_last_reconciliation_ok) throw new RuntimeException('The reconciliation cron did not record a passing result.');
fwrite(STDOUT, "Reconciliation cron handler runs and records a passing result.\n");

// --- Admin coin_mode toggle: requires confirmation and a passing reconciliation. ---
$general = gs();
$general->coin_mode = false;
$general->save();
$toggleOnNoConfirm = \Illuminate\Http\Request::create('/admin/coin/mode', 'POST', ['enable' => '1']);
(new CoinSettingsController())->toggleCoinMode($toggleOnNoConfirm);
if (gs()->coin_mode) throw new RuntimeException('Coin mode was turned on without the confirmation checkbox.');
$toggleOnConfirmed = \Illuminate\Http\Request::create('/admin/coin/mode', 'POST', ['enable' => '1', 'confirm' => '1']);
(new CoinSettingsController())->toggleCoinMode($toggleOnConfirmed);
if (!gs()->coin_mode) throw new RuntimeException('Coin mode was not turned on despite confirmation and a passing reconciliation.');
fwrite(STDOUT, "Admin coin_mode toggle correctly requires confirmation and a passing reconciliation.\n");

// --- Agent coin float grants: second-approver rule, and the single-approver test override. ---
$agentId = DB::table('support_agents')->insertGetId(['name' => 'Test Agent', 'username' => 'test_agent', 'is_active' => 1, 'is_payment_agent' => 1, 'created_at' => now(), 'updated_at' => now()]);
$adminA = DB::table('admins')->insertGetId(['username' => 'admin_a', 'created_at' => now(), 'updated_at' => now()]);
$adminB = DB::table('admins')->insertGetId(['username' => 'admin_b', 'created_at' => now(), 'updated_at' => now()]);

Auth::guard('admin')->setUser(\App\Models\Admin::findOrFail($adminA));
(new AgentCoinFloatController())->store(\Illuminate\Http\Request::create('/admin/coin/float', 'POST', ['support_agent_id' => $agentId, 'amount' => '200', 'reason' => 'Test float']));
$grant = AgentCoinFloatGrant::first();

(new AgentCoinFloatController())->approve(\Illuminate\Http\Request::create('/x', 'POST'), $grant->id);
if ($grant->fresh()->status !== 'pending') throw new RuntimeException('The same admin who requested a float grant was able to approve it with single-approver mode off.');

Auth::guard('admin')->setUser(\App\Models\Admin::findOrFail($adminB));
(new AgentCoinFloatController())->approve(\Illuminate\Http\Request::create('/x', 'POST'), $grant->id);
if ($grant->fresh()->status !== 'approved') throw new RuntimeException('A different admin approving a float grant did not go through.');
if (bccomp((string) $ledger->wallet('agent', $agentId)->fresh()->available_balance, '200', 8) !== 0) throw new RuntimeException('An approved float grant did not mint coins to the agent wallet.');

$general = gs();
$general->allow_single_approver = true;
$general->save();
Auth::guard('admin')->setUser(\App\Models\Admin::findOrFail($adminA));
(new AgentCoinFloatController())->store(\Illuminate\Http\Request::create('/admin/coin/float', 'POST', ['support_agent_id' => $agentId, 'amount' => '50', 'reason' => 'Single approver test']));
$grant2 = AgentCoinFloatGrant::where('id', '!=', $grant->id)->first();
(new AgentCoinFloatController())->approve(\Illuminate\Http\Request::create('/x', 'POST'), $grant2->id);
if ($grant2->fresh()->status !== 'approved') throw new RuntimeException('Single-approver mode did not allow the same admin to approve their own request.');
fwrite(STDOUT, "Agent coin float grants: second-approver rule enforced, and the single-approver test override works.\n");

// Best-effort teardown.
try {
    $depositFieldsMigration->down();
    $floatGrantsMigration->down();
    $coinCoreMigration->down();
    $coinSettingsMigration->down();
    $frameworkMigration->down();
} catch (\Throwable $teardownException) {
    fwrite(STDERR, 'Note: best-effort schema teardown did not fully complete (' . $teardownException->getMessage() . "), ignoring.\n");
}
DB::disconnect();
@unlink($dbPath);
fwrite(STDOUT, "Coin ledger foundation (Phase 1) integration checks passed.\n");
