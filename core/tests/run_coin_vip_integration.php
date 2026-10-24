<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\Controllers\User\VipController;
use App\Models\CoinWallet;
use App\Models\User;
use App\Models\VipRakebackClaim;
use App\Models\VipTier;
use App\Services\CoinLedger;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/coin_vip_integration.sqlite';
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
$rakebackTotalMigration = require dirname(__DIR__) . '/database/migrations/2026_10_18_000003_add_rakeback_claimed_total_to_users_table.php';
$rakebackTotalMigration->up();
$lastClaimAtMigration = require dirname(__DIR__) . '/database/migrations/2026_10_18_000004_add_last_rakeback_claim_at_to_users_table.php';
$lastClaimAtMigration->up();

// game_logs has no dedicated migration in this codebase (it predates the migration-based
// schema); hand-build the minimal columns totalWagered()/rakebackClaimable() actually touch.
Schema::create('game_logs', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->decimal('invest', 28, 8)->default(0);
    $table->boolean('demo_play')->default(0);
    $table->timestamps();
});

// Needed by the coin_mode=off legacy branch of claimRakeback(), which still writes a normal
// Transaction row (same as any other legacy balance credit).
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

$vipTiersMigration = require dirname(__DIR__) . '/database/migrations/2026_10_18_000001_create_vip_tiers_table.php';
$vipTiersMigration->up();
$badgeImageMigration = require dirname(__DIR__) . '/database/migrations/2026_10_20_000017_add_badge_image_to_vip_tiers_table.php';
$badgeImageMigration->up();
$perkFieldsMigration = require dirname(__DIR__) . '/database/migrations/2026_10_21_000005_add_vip_tier_perk_fields_and_seed_packages.php';
$perkFieldsMigration->up(); // also seeds the five real Bronze/Copper/Silver/Gold/Platinum tiers
$rakebackClaimsMigration = require dirname(__DIR__) . '/database/migrations/2026_10_18_000002_create_vip_rakeback_claims_table.php';
$rakebackClaimsMigration->up();

$coinCoreMigration = require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php';
$coinCoreMigration->up();

$ledger = app(CoinLedger::class);
$insertUser = static function (string $username) {
    $id = DB::table('users')->insertGetId(['username' => $username, 'created_at' => now(), 'updated_at' => now()]);
    return User::findOrFail($id);
};
$wager = static function (User $user, float $invest, bool $demo = false, ?\Carbon\Carbon $at = null) {
    DB::table('game_logs')->insert([
        'user_id' => $user->id,
        'invest' => $invest,
        'demo_play' => $demo,
        'created_at' => $at ?? now(),
        'updated_at' => $at ?? now(),
    ]);
};

// Sanity-check the seeded tiers this whole test relies on: Bronze(0,1%), Copper(500,2%),
// Silver(2500,3%), Gold(10000,5%), Platinum(50000,7%).
if (VipTier::active()->count() !== 5) throw new RuntimeException('Expected the perk-fields migration to seed exactly 5 VIP tiers.');
$silver = VipTier::where('name', 'Silver')->first();
if (!$silver || bccomp((string) $silver->min_wagered, '2500', 8) !== 0) throw new RuntimeException('Expected Silver tier to have min_wagered=2500.');

// --- coin_mode OFF: totalWagered()/vipTier() behave exactly as before (regression, bug and all). ---
// game_logs.invest is summed with zero currency conversion regardless of what currency the
// wager was actually placed in - that pre-existing bug is intentionally NOT fixed here. This
// just confirms the raw-sum-vs-raw-threshold comparison still resolves tiers the same way it
// always has.
$alice = $insertUser('vip_alice');
$wager($alice, 1000.00000000); // raw invest units, whatever currency they came from
$wager($alice, 2000.00000000);
$wager($alice, 500.00000000, demo: true); // excluded: demo play never counts
if (bccomp((string) $alice->totalWagered(), '3000', 8) !== 0) throw new RuntimeException("coin_mode=off totalWagered() regression: expected 3000, got {$alice->totalWagered()}.");
$tier = $alice->vipTier();
if (!$tier || $tier->name !== 'Silver') throw new RuntimeException('coin_mode=off vipTier() regression: expected Silver tier at 3000 raw wagered, got ' . ($tier->name ?? 'none') . '.');
$next = $alice->nextVipTier();
if (!$next || $next->name !== 'Gold') throw new RuntimeException('coin_mode=off nextVipTier() regression: expected Gold, got ' . ($next->name ?? 'none') . '.');
fwrite(STDOUT, "coin_mode OFF: totalWagered()/vipTier()/nextVipTier() behave exactly as before (regression check, legacy no-conversion bug included).\n");

// --- coin_mode ON: the same raw invest sum is now correctly a coin amount, compared directly
// against a coin-denominated min_wagered threshold with no conversion step, same as before. ---
$general = gs();
$general->coin_mode = true;
$general->save();

$bob = $insertUser('vip_bob');
$wager($bob, 12000.00000000); // coin amounts, once coin_mode is on
if (bccomp((string) $bob->totalWagered(), '12000', 8) !== 0) throw new RuntimeException("coin_mode=on totalWagered(): expected 12000 coins, got {$bob->totalWagered()}.");
$bobTier = $bob->vipTier();
if (!$bobTier || $bobTier->name !== 'Gold') throw new RuntimeException('coin_mode=on vipTier(): expected Gold tier at 12000 coins wagered, got ' . ($bobTier->name ?? 'none') . '.');
fwrite(STDOUT, "coin_mode ON: raw coin invest sum is compared directly against coin-denominated min_wagered with no USD conversion, and resolves the correct tier.\n");

// --- Rakeback claim in coin mode mints to the coin wallet, not $user->balance, and the ledger
// stays reconciled afterward. ---
$carol = $insertUser('vip_carol');
$wager($carol, 12000.00000000); // Gold tier, 5% rakeback
$carolBalanceBefore = (string) $carol->fresh()->balance;
Auth::login($carol);
$vip = new VipController();
$response = $vip->claimRakeback();
$carol = $carol->fresh();
if (bccomp((string) $carol->balance, $carolBalanceBefore, 8) !== 0) throw new RuntimeException('coin_mode=on claimRakeback() incorrectly also credited $user->balance.');
$expectedClaim = round(12000 * 0.05, 8); // 600
$wallet = $ledger->wallet('user', $carol->id)->fresh();
if (bccomp((string) $wallet->available_balance, (string) $expectedClaim, 8) !== 0) throw new RuntimeException("coin_mode=on claimRakeback() did not mint the expected amount: expected {$expectedClaim}, got {$wallet->available_balance}.");
if (bccomp((string) $carol->rakeback_claimed_total, (string) $expectedClaim, 8) !== 0) throw new RuntimeException('coin_mode=on claimRakeback() did not update rakeback_claimed_total.');
if (!$carol->last_rakeback_claim_at) throw new RuntimeException('coin_mode=on claimRakeback() did not record last_rakeback_claim_at.');
$claimRow = VipRakebackClaim::where('user_id', $carol->id)->first();
if (!$claimRow) throw new RuntimeException('coin_mode=on claimRakeback() did not record a VipRakebackClaim row.');
if (!$ledger->reconcile()['ok']) throw new RuntimeException('Ledger reconciliation broke after a coin-mode rakeback claim.');
Auth::logout();
fwrite(STDOUT, "Rakeback claim (coin_mode ON): mints the claimed amount to the coin wallet (not \$user->balance), and the ledger stays reconciled.\n");

// A second claim immediately after should have nothing left to claim (wagering is unchanged
// since the claim), proving the claim actually advanced last_rakeback_claim_at.
Auth::login($carol->fresh());
$vip->claimRakeback();
if (bccomp((string) $ledger->wallet('user', $carol->id)->fresh()->available_balance, (string) $expectedClaim, 8) !== 0) throw new RuntimeException('A second immediate rakeback claim incorrectly minted more coins.');
Auth::logout();
fwrite(STDOUT, "Rakeback claim (coin_mode ON): a second immediate claim has nothing left to claim and does not double-mint.\n");

// --- coin_mode OFF regression: claimRakeback() still credits $user->balance as before. ---
$general->coin_mode = false;
$general->save();
$dave = $insertUser('vip_dave');
$wager($dave, 12000.00000000);
Auth::login($dave);
(new VipController())->claimRakeback();
$dave = $dave->fresh();
if (bccomp((string) $dave->balance, (string) $expectedClaim, 8) !== 0) throw new RuntimeException("coin_mode=off claimRakeback() regression: expected balance credited with {$expectedClaim}, got {$dave->balance}.");
if (bccomp((string) $ledger->wallet('user', $dave->id)->fresh()->available_balance, '0', 8) !== 0) throw new RuntimeException('coin_mode=off claimRakeback() incorrectly also minted coins.');
Auth::logout();
$general->coin_mode = true;
$general->save();
fwrite(STDOUT, "Rakeback claim (coin_mode OFF): still credits \$user->balance exactly as before (regression check).\n");

// --- Cutover materialization: mints the correct unclaimed amount exactly once. ---
$eve = $insertUser('vip_eve');
$wager($eve, 50000.00000000); // Platinum tier, 7% rakeback -> 3500 claimable
$vip = new VipController();
$entry = $vip->materializeRakebackToCoins($eve, 'admin', 1);
if (!$entry) throw new RuntimeException('materializeRakebackToCoins() returned null for a user with claimable rakeback.');
$expectedCutover = round(50000 * 0.07, 8); // 3500
$eveWallet = $ledger->wallet('user', $eve->id)->fresh();
if (bccomp((string) $eveWallet->available_balance, (string) $expectedCutover, 8) !== 0) throw new RuntimeException("materializeRakebackToCoins() minted the wrong amount: expected {$expectedCutover}, got {$eveWallet->available_balance}.");
if (!$ledger->reconcile()['ok']) throw new RuntimeException('Ledger reconciliation broke after cutover materialization.');

// Calling it again for the same user must not double-mint (no new wagering happened since).
$secondEntry = $vip->materializeRakebackToCoins($eve->fresh(), 'admin', 1);
if ($secondEntry !== null) throw new RuntimeException('A second materializeRakebackToCoins() call for the same user minted again instead of no-opping.');
if (bccomp((string) $ledger->wallet('user', $eve->id)->fresh()->available_balance, (string) $expectedCutover, 8) !== 0) throw new RuntimeException('A second materializeRakebackToCoins() call changed the wallet balance.');
fwrite(STDOUT, "Cutover materialization: mints the correct unclaimed rakeback exactly once, and a second call does not double-mint.\n");

// --- Cutover materialization no-ops safely for a user with nothing claimable. ---
$frank = $insertUser('vip_frank'); // no wagering at all -> Bronze tier, but Bronze's rakeback_percent is >0 (1%) with 0 wagered since claim
$noopEntry = $vip->materializeRakebackToCoins($frank, 'admin', 1);
if ($noopEntry !== null) throw new RuntimeException('materializeRakebackToCoins() should no-op (return null) for a user with zero claimable rakeback.');
if (bccomp((string) $ledger->wallet('user', $frank->id)->fresh()->available_balance, '0', 8) !== 0) throw new RuntimeException('materializeRakebackToCoins() minted coins for a user with nothing claimable.');
fwrite(STDOUT, "Cutover materialization: safely no-ops (no throw, no mint) for a user with zero claimable rakeback.\n");

if (!$ledger->reconcile()['ok']) throw new RuntimeException('Final ledger reconciliation check failed.');

// php -l sanity check on every file this task touched.
foreach ([
    dirname(__DIR__) . '/app/Models/User.php',
    dirname(__DIR__) . '/app/Http/Controllers/User/VipController.php',
    __FILE__,
] as $file) {
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $exitCode);
    if ($exitCode !== 0) throw new RuntimeException("php -l failed for {$file}: " . implode("\n", $output));
    $output = [];
}
fwrite(STDOUT, "php -l passes on every touched file.\n");

// Best-effort teardown.
try {
    $coinCoreMigration->down();
    $rakebackClaimsMigration->down();
    $perkFieldsMigration->down();
    $badgeImageMigration->down();
    $vipTiersMigration->down();
    $lastClaimAtMigration->down();
    $rakebackTotalMigration->down();
    $coinSettingsMigration->down();
    $frameworkMigration->down();
} catch (\Throwable $teardownException) {
    fwrite(STDERR, 'Note: best-effort schema teardown did not fully complete (' . $teardownException->getMessage() . "), ignoring.\n");
}
DB::disconnect();
@unlink($dbPath);
fwrite(STDOUT, "Coin-aware VIP/rakeback integration checks passed.\n");
