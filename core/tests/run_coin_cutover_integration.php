<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\Controllers\Admin\CoinCutoverController;
use App\Http\Controllers\Admin\CoinTestKitController;
use App\Models\CoinListing;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\GameLog;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\CoinLedger;
use App\Services\CoinPricing;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/coin_cutover_integration.sqlite';
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
(require dirname(__DIR__) . '/database/migrations/2026_10_24_000011_add_coin_settings_to_general_settings.php')->up();

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('username');
    $table->string('email')->nullable();
    $table->string('password')->nullable();
    $table->decimal('balance', 28, 8)->default(0);
    $table->unsignedBigInteger('ref_by')->nullable();
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->tinyInteger('status')->default(1);
    $table->timestamps();
});
(require dirname(__DIR__) . '/database/migrations/2026_10_18_000003_add_rakeback_claimed_total_to_users_table.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_18_000004_add_last_rakeback_claim_at_to_users_table.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_18_000001_create_vip_tiers_table.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_20_000017_add_badge_image_to_vip_tiers_table.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_21_000005_add_vip_tier_perk_fields_and_seed_packages.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_18_000002_create_vip_rakeback_claims_table.php')->up();
Schema::create('admins', function (Blueprint $table) { $table->id(); $table->string('username')->nullable(); $table->timestamps(); });
Schema::create('support_agents', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->nullable();
    $table->string('username');
    $table->string('password')->nullable();
    $table->boolean('is_active')->default(true);
    $table->boolean('is_payment_agent')->default(false);
    $table->timestamps();
});
Schema::create('currencies', function (Blueprint $table) {
    $table->id();
    $table->string('code', 10);
    $table->integer('decimals')->default(2);
    $table->boolean('enabled')->default(true);
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
Schema::create('game_logs', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->unsignedBigInteger('game_id')->default(1);
    $table->decimal('invest', 28, 8)->default(0);
    $table->decimal('win_amo', 28, 8)->default(0);
    $table->tinyInteger('win_status')->default(0);
    $table->tinyInteger('status')->default(0);
    $table->tinyInteger('demo_play')->default(0);
    $table->timestamps();
});
Schema::create('deposits', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->decimal('amount', 28, 8);
    $table->decimal('charge', 28, 8)->default(0);
    $table->integer('method_code')->default(5000);
    $table->integer('status')->default(0);
    $table->string('trx');
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->string('admin_feedback')->nullable();
    $table->timestamps();
});
Schema::create('withdraw_methods', function (Blueprint $table) { $table->id(); $table->string('name'); $table->timestamps(); });
Schema::create('withdrawals', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->unsignedBigInteger('method_id')->nullable();
    $table->decimal('amount', 28, 8);
    $table->decimal('charge', 28, 8)->default(0);
    $table->decimal('final_amount', 28, 8)->default(0);
    $table->integer('status')->default(0);
    $table->string('trx')->nullable();
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->string('admin_feedback')->nullable();
    $table->timestamps();
});

$coinCoreMigration = require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php';
$coinCoreMigration->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_25_000001_add_pause_state_to_coin_rates.php')->up();
$cutoverArchiveMigration = require dirname(__DIR__) . '/database/migrations/2026_10_25_000002_create_coin_cutover_user_archives_table.php';
$cutoverArchiveMigration->up();
$supervisorFlagMigration = require dirname(__DIR__) . '/database/migrations/2026_10_24_000013_add_is_marketplace_supervisor_to_support_agents.php';
$supervisorFlagMigration->up();
$marketplaceMigration = require dirname(__DIR__) . '/database/migrations/2026_10_24_000020_create_coin_marketplace_tables.php';
$marketplaceMigration->up();
$cutoverLogMigration = require dirname(__DIR__) . '/database/migrations/2026_10_24_000030_create_coin_cutover_logs_table.php';
$cutoverLogMigration->up();

$ledger = app(CoinLedger::class);
$insertUser = static function (string $username, string $balance = '0', ?int $currencyId = null): User {
    $id = DB::table('users')->insertGetId(['username' => $username, 'balance' => $balance, 'currency_id' => $currencyId, 'created_at' => now(), 'updated_at' => now()]);
    return User::findOrFail($id);
};

$ngn = Currency::create(['code' => 'NGN', 'decimals' => 2, 'enabled' => true, 'rate_to_usd' => '0.00065', 'last_updated_at' => now()]);
app(CoinPricing::class)->recompute($ngn);
$buyPrice = (string) \App\Models\CoinRate::where('currency_id', $ngn->id)->first()->buy_price;

$richUser = $insertUser('cutover_rich', '1000.00000000', $ngn->id);
$poorUser = $insertUser('cutover_poor', '0.00000000', $ngn->id);

$stuckGame = GameLog::create(['user_id' => $richUser->id, 'invest' => '25.00000000', 'status' => 0, 'created_at' => now()->subMinutes(10), 'updated_at' => now()->subMinutes(10)]);
$pendingDeposit = Deposit::forceCreate(['user_id' => $richUser->id, 'amount' => '50', 'status' => 0, 'trx' => 'CUTDEP001', 'currency_id' => $ngn->id]);
$pendingWithdrawal = Withdrawal::forceCreate(['user_id' => $richUser->id, 'amount' => '30', 'status' => 2, 'trx' => 'CUTWD001', 'currency_id' => $ngn->id]);

$adminId = DB::table('admins')->insertGetId(['username' => 'cutover_admin', 'created_at' => now(), 'updated_at' => now()]);
$cutover = app(\App\Services\CoinCutover::class);

// --- Dry-run preview must not change anything. ---
$preview = $cutover->preview('convert');
if ((int) $preview['users_affected'] < 2) throw new RuntimeException('Preview did not see the seeded users.');
if (bccomp((string) $richUser->fresh()->balance, '1000', 8) !== 0) throw new RuntimeException('A dry-run preview changed a real balance.');
if ($stuckGame->fresh()->status !== 0) throw new RuntimeException('A dry-run preview touched a running game.');
fwrite(STDOUT, "Cutover: dry-run preview computes totals without changing any data.\n");

// --- Execute: convert mode. ---
$log = $cutover->execute('convert', null, $adminId);
if ($stuckGame->fresh()->status != 2) throw new RuntimeException('Cutover did not settle the running game.');
if ((int) $richUser->fresh()->balance !== 0 && bccomp((string) $richUser->fresh()->balance, '0', 8) !== 0) throw new RuntimeException('Cutover did not zero the legacy balance.');
if ($pendingDeposit->fresh()->status != 3) throw new RuntimeException('Cutover did not cancel the pending deposit.');
if ($pendingWithdrawal->fresh()->status != 3) throw new RuntimeException('Cutover did not cancel the pending withdrawal.');

// The running game refunded 25 to the rich user's real balance BEFORE conversion, and the
// pending withdrawal refunded another 30 before conversion too, so the real balance converted
// should be 1000 (original) + 25 (game refund) + 30 (withdrawal refund) = 1055, at the locked
// buy price. The cutover also materializes unclaimed rakeback in the SAME run: the seeded base
// VIP tier's rakeback_percent is 1%, and the stuck GameLog's $25 invest counts as wagered
// (rakebackClaimable() has no status filter), so an extra 0.25 coins land on top of the
// balance conversion.
$expectedCoins = bcadd(bcdiv('1055', $buyPrice, 0), '0.25', 8);
if (bccomp((string) $ledger->wallet('user', $richUser->id)->fresh()->available_balance, $expectedCoins, 8) !== 0) {
    throw new RuntimeException("Cutover minted the wrong coin amount: expected {$expectedCoins}, got " . $ledger->wallet('user', $richUser->id)->fresh()->available_balance);
}
if (!$ledger->reconcile()['ok']) throw new RuntimeException('Reconciliation broke after the cutover.');
fwrite(STDOUT, "Cutover: convert mode settles open games/withdrawals first, cancels pending deposits, then mints coins at the locked rate and zeroes the legacy balance.\n");

// --- Per-user archive: exactly what was converted, in which currency, at what rate. ---
$richArchive = \App\Models\CoinCutoverUserArchive::where('user_id', $richUser->id)->first();
if (!$richArchive) throw new RuntimeException('The rich user was converted but no archive row was written.');
if (bccomp((string) $richArchive->legacy_balance, '1055', 8) !== 0) throw new RuntimeException("Archive recorded the wrong legacy balance: {$richArchive->legacy_balance}");
if ((int) $richArchive->currency_id !== (int) $ngn->id) throw new RuntimeException('Archive recorded the wrong currency.');
if (bccomp((string) $richArchive->rate_used, $buyPrice, 8) !== 0) throw new RuntimeException('Archive did not record the exact buy price used.');
if (bccomp((string) $richArchive->coins_minted, bcdiv('1055', $buyPrice, 0), 8) !== 0) throw new RuntimeException('Archive did not record the coins minted from the balance conversion.');
if ((int) $richArchive->coin_cutover_log_id !== (int) $log->id) throw new RuntimeException('Archive row was not linked to the cutover run that produced it.');
if (\App\Models\CoinCutoverUserArchive::where('user_id', $poorUser->id)->exists()) throw new RuntimeException('A user with zero legacy balance should not get an archive row.');
fwrite(STDOUT, "Cutover: a per-user archive row records the exact legacy balance, currency, rate, and coins minted.\n");

// --- Re-running the cutover must not double-mint (balances are already zero). ---
$before = (string) $ledger->wallet('user', $richUser->id)->fresh()->available_balance;
$archiveCountBefore = \App\Models\CoinCutoverUserArchive::count();
$cutover->execute('convert', null, $adminId);
$after = (string) $ledger->wallet('user', $richUser->id)->fresh()->available_balance;
if (bccomp($before, $after, 8) !== 0) throw new RuntimeException('Re-running the cutover changed an already-converted balance.');
if (\App\Models\CoinCutoverUserArchive::count() !== $archiveCountBefore) throw new RuntimeException('Re-running the cutover wrote duplicate archive rows for already-converted users.');
fwrite(STDOUT, "Cutover: re-running it is safe - already-zeroed balances are not converted again, and no duplicate archive rows are written.\n");

// --- reset_test mode applies a flat amount to every user, regardless of balance. ---
$testA = $insertUser('cutover_test_a', '999', $ngn->id);
$testB = $insertUser('cutover_test_b', '0', $ngn->id);
$cutover->execute('reset_test', '250', $adminId);
if (bccomp((string) $ledger->wallet('user', $testA->id)->fresh()->available_balance, '250', 8) !== 0) throw new RuntimeException('reset_test did not apply the flat amount regardless of prior balance.');
if (bccomp((string) $ledger->wallet('user', $testB->id)->fresh()->available_balance, '250', 8) !== 0) throw new RuntimeException('reset_test did not apply the flat amount to a zero-balance user.');
if (!$ledger->reconcile()['ok']) throw new RuntimeException('Reconciliation broke after reset_test.');
fwrite(STDOUT, "Cutover: reset_test mode applies a flat coin amount to every account.\n");

if (\App\Models\CoinCutoverLog::count() < 3) throw new RuntimeException('Cutover runs were not logged.');
fwrite(STDOUT, "Cutover: every run is logged with a summary.\n");

// --- Pricing seed: a currency with no exchange rate is paused (with a reason), not thrown away,
// and seeding the rest keeps working around it. ---
$noRateCurrency = Currency::create(['code' => 'XYZ', 'decimals' => 2, 'enabled' => true, 'rate_to_usd' => null]);
$pricing = app(CoinPricing::class);
$results = $pricing->recomputeAll();
if (($results['XYZ']['ok'] ?? true) !== false) throw new RuntimeException('A currency with no exchange rate should fail to price.');
if (($results['NGN']['ok'] ?? false) !== true) throw new RuntimeException('Seeding should still succeed for every other currency when one fails.');
$pausedRate = \App\Models\CoinRate::where('currency_id', $noRateCurrency->id)->first();
if (!$pausedRate || $pausedRate->status !== 'paused' || !$pausedRate->pause_reason) throw new RuntimeException('An unpriceable currency was not persisted as paused with a reason.');
$ngnRate = \App\Models\CoinRate::where('currency_id', $ngn->id)->first();
if ($ngnRate->status !== 'active') throw new RuntimeException('A successfully priced currency should be marked active.');
fwrite(STDOUT, "Pricing seed: a currency with no exchange rate is marked paused with a reason, and does not block pricing every other currency.\n");

// Idempotency: seeding again changes nothing about the paused currency except re-confirming it.
$pricing->recomputeAll();
$pausedRateAgain = \App\Models\CoinRate::where('currency_id', $noRateCurrency->id)->first();
if ($pausedRateAgain->status !== 'paused' || $pausedRateAgain->buy_price !== null) throw new RuntimeException('Re-seeding should not invent a price for a currency that still has no exchange rate.');
fwrite(STDOUT, "Pricing seed: re-running it is idempotent - a currency that is still unpriceable stays paused without a fabricated price.\n");

// Once the currency gets a real rate, the very same seed call prices it and clears the pause.
$noRateCurrency->rate_to_usd = '0.5';
$noRateCurrency->last_updated_at = now();
$noRateCurrency->save();
$pricing->recomputeAll();
$recoveredRate = \App\Models\CoinRate::where('currency_id', $noRateCurrency->id)->first();
if ($recoveredRate->status !== 'active' || !$recoveredRate->buy_price) throw new RuntimeException('A currency that gained a real exchange rate should price successfully and clear its paused state.');
fwrite(STDOUT, "Pricing seed: a currency recovers from paused to active as soon as it has a usable exchange rate.\n");

// --- Admin test kit smoke test. ---
$general = gs();
$general->coin_mode = true;
$general->coin_marketplace_test_kit_enabled = true;
$general->save();
Auth::guard('admin')->setUser(\App\Models\Admin::findOrFail($adminId));

(new CoinTestKitController())->createTestAgent(\Illuminate\Http\Request::create('/x', 'POST', ['type' => 'agent']));
(new CoinTestKitController())->createTestUser(\Illuminate\Http\Request::create('/x', 'POST'));
$kitAgent = \App\Models\SupportAgent::where('username', 'like', 'testkit_agent_%')->first();
$kitUser = User::where('username', 'like', 'testkit_user_%')->first();
if (!$kitAgent || !$kitUser) throw new RuntimeException('Test kit did not create the expected agent/user.');

(new CoinTestKitController())->grantTestFloat(\Illuminate\Http\Request::create('/x', 'POST', ['support_agent_id' => $kitAgent->id, 'amount' => 500]));
if (bccomp((string) $ledger->wallet('agent', $kitAgent->id)->fresh()->available_balance, '500', 8) !== 0) throw new RuntimeException('Test kit float grant did not mint correctly.');

(new CoinTestKitController())->grantTestCoins(\Illuminate\Http\Request::create('/x', 'POST', ['user_id' => $kitUser->id, 'amount' => 300]));
if (bccomp((string) $ledger->wallet('user', $kitUser->id)->fresh()->available_balance, '300', 8) !== 0) throw new RuntimeException('Test kit coin grant did not mint correctly.');

(new CoinTestKitController())->createSampleListing(\Illuminate\Http\Request::create('/x', 'POST', ['user_id' => $kitUser->id, 'amount' => 100]));
$sampleListing = CoinListing::where('seller_user_id', $kitUser->id)->first();
if (!$sampleListing) throw new RuntimeException('Test kit did not create a sample listing.');

(new CoinTestKitController())->simulateTimeout(\Illuminate\Http\Request::create('/x', 'POST', ['listing_id' => $sampleListing->id, 'support_agent_id' => $kitAgent->id]));
if ($sampleListing->fresh()->status !== 'on_sale') throw new RuntimeException('Test kit timeout simulation did not reopen the listing.');
fwrite(STDOUT, "Admin test kit: create agent/user, grant float/coins, sample listing, and simulated timeout all work.\n");

if (!$ledger->reconcile()['ok']) throw new RuntimeException('Reconciliation broke after the test kit run.');

// Best-effort teardown.
try {
    $cutoverArchiveMigration->down();
    $cutoverLogMigration->down();
    $marketplaceMigration->down();
    $supervisorFlagMigration->down();
    $coinCoreMigration->down();
    $frameworkMigration->down();
} catch (\Throwable $teardownException) {
    fwrite(STDERR, 'Note: best-effort schema teardown did not fully complete (' . $teardownException->getMessage() . "), ignoring.\n");
}
DB::disconnect();
@unlink($dbPath);
fwrite(STDOUT, "Coin cutover and admin test kit integration checks passed.\n");
