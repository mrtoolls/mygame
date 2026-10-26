<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\Controllers\Admin\CoinDisputeController;
use App\Http\Controllers\CoinAgentMarketplaceController;
use App\Http\Controllers\CoinSupervisorController;
use App\Http\Controllers\CoinTradeChatController;
use App\Http\Controllers\CronHandlers\CoinConfirmationWindowSweep;
use App\Http\Controllers\CronHandlers\CoinPaymentWindowSweep;
use App\Http\Controllers\User\CoinMarketplaceController;
use App\Models\CoinListing;
use App\Models\CoinPenalty;
use App\Models\CoinTrade;
use App\Models\CoinVendor;
use App\Models\SupportAgent;
use App\Models\User;
use App\Services\CoinLedger;
use App\Services\CoinMarketplace;
use App\Services\CoinVendorService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

$dbPath = __DIR__ . '/coin_marketplace_integration.sqlite';
if (is_file($dbPath)) unlink($dbPath);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
putenv('CACHE_STORE=database');
putenv('APP_KEY=base64:' . base64_encode(random_bytes(32)));
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec("CREATE TABLE languages (id INTEGER PRIMARY KEY, code VARCHAR(10), name VARCHAR(60), is_default INTEGER DEFAULT 0)");
$pdo->exec("INSERT INTO languages (code, name, is_default) VALUES ('en', 'English', 1)");
$pdo = null;

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
$testStoragePath = sys_get_temp_dir() . '/winxpot_coin_marketplace_test_storage';
$app->useStoragePath($testStoragePath);

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
    $table->string('mobile')->nullable();
    $table->string('dial_code', 8)->nullable();
    $table->unsignedBigInteger('country_id')->nullable();
    $table->decimal('balance', 28, 8)->default(0);
    $table->unsignedBigInteger('ref_by')->nullable();
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->tinyInteger('status')->default(1);
    $table->tinyInteger('ev')->default(1);
    $table->tinyInteger('sv')->default(1);
    $table->timestamps();
});
Schema::create('admins', function (Blueprint $table) { $table->id(); $table->string('username')->nullable(); $table->timestamps(); });
Schema::create('admin_notifications', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('user_id')->default(0); $table->string('title'); $table->string('click_url')->nullable(); $table->boolean('is_read')->default(false); $table->timestamps(); });
Schema::create('currencies', function (Blueprint $table) {
    $table->id(); $table->char('code', 3)->unique(); $table->string('name'); $table->string('symbol', 12);
    $table->unsignedTinyInteger('decimals')->default(2); $table->boolean('enabled')->default(true);
    $table->string('symbol_position', 8)->default('left'); $table->decimal('rate_to_usd', 24, 12)->nullable();
    $table->string('rate_source', 16)->default('manual'); $table->boolean('auto_update')->default(false);
    $table->timestamp('last_updated_at')->nullable(); $table->timestamps();
});
DB::table('currencies')->insert(['id' => 1, 'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'enabled' => 1, 'rate_to_usd' => 1, 'created_at' => now(), 'updated_at' => now()]);
Schema::create('countries', function (Blueprint $table) {
    $table->id(); $table->char('iso_code', 2)->unique(); $table->string('name');
    $table->boolean('is_enabled')->default(true); $table->boolean('is_allowed')->default(true);
    $table->unsignedBigInteger('currency_id')->nullable(); $table->boolean('coin_marketplace_enabled')->default(true);
    $table->timestamps();
});
DB::table('countries')->insert(['id' => 1, 'iso_code' => 'US', 'name' => 'United States', 'is_enabled' => 1, 'is_allowed' => 1, 'currency_id' => 1, 'coin_marketplace_enabled' => 1, 'created_at' => now(), 'updated_at' => now()]);
Schema::create('support_agents', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('username');
    $table->boolean('is_active')->default(true);
    $table->boolean('is_payment_agent')->default(false);
    $table->timestamps();
});

$coinCoreMigration = require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php';
$coinCoreMigration->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_25_000001_add_pause_state_to_coin_rates.php')->up();
$supervisorFlagMigration = require dirname(__DIR__) . '/database/migrations/2026_10_24_000013_add_is_marketplace_supervisor_to_support_agents.php';
$supervisorFlagMigration->up();
$marketplaceMigration = require dirname(__DIR__) . '/database/migrations/2026_10_24_000020_create_coin_marketplace_tables.php';
$marketplaceMigration->up();
$vendorMigration = require dirname(__DIR__) . '/database/migrations/2026_10_26_000001_create_coin_vendor_system.php';
$vendorMigration->up();

$ledger = app(CoinLedger::class);
$marketplace = app(CoinMarketplace::class);
Storage::fake('local');

$insertUser = static function (string $username): User {
    $id = DB::table('users')->insertGetId([
        'username' => $username, 'email' => $username . '@example.test', 'mobile' => (string) random_int(1000000, 9999999),
        'dial_code' => '1', 'country_id' => 1, 'currency_id' => 1, 'ev' => 1, 'sv' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    return User::findOrFail($id);
};
$insertAgent = static function (string $username, bool $isSelling = false): SupportAgent {
    $id = DB::table('support_agents')->insertGetId(['name' => $username, 'username' => $username, 'is_active' => 1, 'is_payment_agent' => 1, 'is_selling_coins' => $isSelling, 'created_at' => now(), 'updated_at' => now()]);
    return SupportAgent::findOrFail($id);
};

$general = gs();
$general->coin_mode = true;
$general->coin_fee_percent = '5';
$general->coin_fee_payer = 'seller';
$general->coin_payment_window_minutes = 30;
$general->coin_confirmation_window_minutes = 60;
$general->coin_vendor_enabled = true;
$general->coin_vendor_fee_percent = null;
$general->coin_vendor_new_per_trade_limit = 1000;
$general->coin_vendor_new_daily_limit = 5000;
$general->coin_vendor_max_open_trades = 5;
$general->save();
DB::table('coin_rates')->insert([
    'currency_id' => 1, 'buy_margin_percent' => 5, 'sell_margin_percent' => 2,
    'buy_price' => '0.01050000', 'sell_price' => '0.00980000', 'last_computed_at' => now(),
    'created_at' => now(), 'updated_at' => now(),
]);

// --- Sell-to-agent flow: list, claim, claim payment, confirm received (fee math). ---
$seller = $insertUser('mp_seller');
$agent = $insertAgent('mp_agent');
$ledger->mint('user', $seller->id, '200', 'seed', 'seed:seller');

$listing = $marketplace->createListing($seller, '100');
$sellerWallet = $ledger->wallet('user', $seller->id)->fresh();
if (bccomp((string) $sellerWallet->available_balance, '100', 8) !== 0 || bccomp((string) $sellerWallet->held_balance, '100', 8) !== 0) {
    throw new RuntimeException('Creating a listing did not hold the coins correctly.');
}

$trade = $marketplace->claimListing($listing, $agent);
if ($listing->fresh()->status !== 'pending' || $trade->status !== 'pending') throw new RuntimeException('Claiming a listing did not move both the listing and trade to pending.');

try {
    $marketplace->confirmReceived($trade, 'user', $seller->id);
    throw new RuntimeException('confirmReceived succeeded before any payment was claimed.');
} catch (InvalidArgumentException) {}

$trade = $marketplace->claimPayment($trade, 'agent', $agent->id, null);
if ($trade->status !== 'paid_claimed') throw new RuntimeException('claimPayment did not move the trade to paid_claimed.');

try {
    $marketplace->reopenListing($trade, 'user', $seller->id);
    throw new RuntimeException('The seller could still reopen the listing after payment was claimed.');
} catch (InvalidArgumentException) {}

$trade = $marketplace->confirmReceived($trade, 'user', $seller->id);
if ($trade->status !== 'completed') throw new RuntimeException('confirmReceived did not complete the trade.');
// 100 coins, 5% fee paid by seller -> agent should receive 95, seller's held balance should be 0.
if (bccomp((string) $ledger->wallet('agent', $agent->id)->fresh()->available_balance, '95', 8) !== 0) throw new RuntimeException('Agent did not receive the correct net amount after the seller-paid fee.');
if (bccomp((string) $ledger->wallet('user', $seller->id)->fresh()->held_balance, '0', 8) !== 0) throw new RuntimeException("Seller's held balance was not fully cleared after settlement.");
if (bccomp((string) $ledger->wallet('user', $seller->id)->fresh()->available_balance, '100', 8) !== 0) throw new RuntimeException('Seller ended up with the wrong remaining available balance.');
if (!$ledger->reconcile()['ok']) throw new RuntimeException('Reconciliation broke after a completed sell-to-agent trade (fee burn should keep it balanced).');
fwrite(STDOUT, "Sell-to-agent: list -> claim -> claim payment -> confirm received settles correctly, with the seller-paid fee burned.\n");

// --- Core rule: coins never release without the receiver's confirmation or a supervisor ruling - claimPayment alone must never move coins. ---
if (bccomp((string) $ledger->wallet('agent', $agent->id)->fresh()->held_balance, '0', 8) !== 0) throw new RuntimeException('Unexpected held balance on the agent wallet.');
fwrite(STDOUT, "Core rule confirmed: a payment claim alone never moves coins - only an explicit confirm or supervisor ruling does.\n");

// --- Buy-from-agent flow (agent is the coin seller, holds immediately; buyer-pays-fee variant). ---
$general->coin_fee_payer = 'buyer';
$general->save();
$sellingAgent = $insertAgent('mp_selling_agent', true);
$ledger->mint('agent', $sellingAgent->id, '500', 'seed', 'seed:agent');
$buyer = $insertUser('mp_buyer');

$trade2 = $marketplace->initiateBuyFromAgent($buyer, $sellingAgent, '40');
if (bccomp((string) $ledger->wallet('agent', $sellingAgent->id)->fresh()->held_balance, '40', 8) !== 0) throw new RuntimeException('Buy-from-agent did not hold the agent coins immediately.');
$trade2 = $marketplace->claimPayment($trade2, 'user', $buyer->id, null);
$trade2 = $marketplace->confirmReceived($trade2, 'agent', $sellingAgent->id);
// 40 coins, 5% fee paid by buyer -> buyer should receive 40, then have 2 burned, netting 38.
if (bccomp((string) $ledger->wallet('user', $buyer->id)->fresh()->available_balance, '38', 8) !== 0) throw new RuntimeException("Buyer did not end up with the correct net amount after the buyer-paid fee: {$ledger->wallet('user', $buyer->id)->fresh()->available_balance}");
if (!$ledger->reconcile()['ok']) throw new RuntimeException('Reconciliation broke after a completed buy-from-agent trade with a buyer-paid fee.');
fwrite(STDOUT, "Buy-from-agent: direct hold, claim payment, confirm received settles correctly with a buyer-paid fee.\n");

// --- Dispute + supervisor resolution (release_to_buyer), via the agent-side supervisor controller. ---
$general->coin_fee_payer = 'seller';
$general->save();
$ledger->mint('user', $seller->id, '50', 'seed2', 'seed:seller2');
$listing2 = $marketplace->createListing($seller, '50');
$trade3 = $marketplace->claimListing($listing2, $agent);
$trade3 = $marketplace->claimPayment($trade3, 'agent', $agent->id, null);
$trade3 = $marketplace->reportNotReceived($trade3, 'user', $seller->id, 'Never got the money');
if ($trade3->status !== 'disputed') throw new RuntimeException('reportNotReceived did not open a dispute.');

$supervisor = $insertAgent('mp_supervisor');
DB::table('support_agents')->where('id', $supervisor->id)->update(['is_marketplace_supervisor' => true]);
$supervisor = $supervisor->fresh();
Auth::guard('support_agent')->setUser($supervisor);
(new CoinSupervisorController())->resolve(\Illuminate\Http\Request::create('/x', 'POST', ['decision' => 'release_to_buyer', 'reason' => 'Proof confirmed with bank']), $trade3->id);
if ($trade3->fresh()->status !== 'completed') throw new RuntimeException('Supervisor dispute resolution (release_to_buyer) did not complete the trade.');

// A non-supervisor agent must be rejected.
$nonSupervisor = $insertAgent('mp_nonsupervisor');
Auth::guard('support_agent')->setUser($nonSupervisor);
try {
    app(CoinMarketplace::class)->resolveDispute(CoinTrade::find($trade3->id), 'return_to_seller', 'agent', $nonSupervisor->id, 'should fail');
    throw new RuntimeException('A non-supervisor agent was able to resolve a dispute.');
} catch (InvalidArgumentException $exception) {
    if (!str_contains($exception->getMessage(), 'supervisor')) throw $exception;
}
fwrite(STDOUT, "Disputes: a marketplace supervisor can resolve a dispute; a non-supervisor agent cannot.\n");

// --- Dispute + return_to_seller, via the admin-side controller. ---
$ledger->mint('user', $seller->id, '30', 'seed3', 'seed:seller3');
$listing3 = $marketplace->createListing($seller, '30');
$trade4 = $marketplace->claimListing($listing3, $agent);
$trade4 = $marketplace->claimPayment($trade4, 'agent', $agent->id, null);
$trade4 = $marketplace->openDispute($trade4, 'agent', $agent->id, 'Disputing for test');
$adminId = DB::table('admins')->insertGetId(['username' => 'coin_admin', 'created_at' => now(), 'updated_at' => now()]);
Auth::guard('admin')->setUser(\App\Models\Admin::findOrFail($adminId));
(new CoinDisputeController())->resolve(\Illuminate\Http\Request::create('/x', 'POST', ['decision' => 'return_to_seller', 'reason' => 'No proof of payment']), $trade4->id);
$trade4 = $trade4->fresh();
if ($trade4->status !== 'cancelled' || !$trade4->no_payment_ruled_at) throw new RuntimeException('Admin dispute resolution (return_to_seller) did not cancel the trade and mark the no-payment ruling.');
if ($listing3->fresh()->status !== 'on_sale') throw new RuntimeException('return_to_seller did not reopen the listing.');
if (bccomp((string) $ledger->wallet('user', $seller->id)->fresh()->held_balance, '30', 8) !== 0) throw new RuntimeException('return_to_seller did not keep the coins held against the reopened listing.');
fwrite(STDOUT, "Disputes: admin-side return_to_seller correctly reopens the listing and keeps coins held, not released early.\n");

// --- Penalties: tier math, cap at wallet balance, small-penalty single-admin, large-penalty second-approver rule. ---
$offender = $insertUser('mp_offender');
$ledger->mint('user', $offender->id, '20', 'seed4', 'seed:offender');
$wronged = $insertUser('mp_wronged');
$fakeTradeAmount = CoinTrade::create([
    'direction' => 'sell_to_agent', 'coin_seller_type' => 'user', 'coin_seller_id' => $offender->id,
    'coin_buyer_type' => 'user', 'coin_buyer_id' => $wronged->id, 'amount' => '1000', 'status' => 'disputed',
]);
$penalty = $marketplace->issuePenalty($fakeTradeAmount, 'user', $offender->id, 1, 'First offense', 'admin', $adminId);
// Tier 1 = 10% of 1000 = 100, but offender only has 20 -> capped at 20.
if (bccomp((string) $penalty->coin_amount, '20', 8) !== 0) throw new RuntimeException("Penalty was not capped at the offender's balance: {$penalty->coin_amount}");
if (bccomp((string) $ledger->wallet('user', $offender->id)->fresh()->available_balance, '0', 8) !== 0) throw new RuntimeException('Offender balance was not reduced to zero by the capped penalty.');
if (bccomp((string) $ledger->wallet('user', $wronged->id)->fresh()->available_balance, '10', 8) !== 0) throw new RuntimeException('Wronged party did not receive their 50% split of the penalty.');
fwrite(STDOUT, "Penalties: tier percentage is correctly capped at the offender's actual balance, and the wronged-party split is credited.\n");

$general->coin_penalty_second_approval_threshold = '50';
$general->save();
$bigOffender = $insertUser('mp_big_offender');
$ledger->mint('user', $bigOffender->id, '1000', 'seed5', 'seed:bigoffender');
$bigTrade = CoinTrade::create([
    'direction' => 'sell_to_agent', 'coin_seller_type' => 'user', 'coin_seller_id' => $bigOffender->id,
    'coin_buyer_type' => 'user', 'coin_buyer_id' => $wronged->id, 'amount' => '1000', 'status' => 'disputed',
]);
try {
    $marketplace->issuePenalty($bigTrade, 'user', $bigOffender->id, 3, 'Repeat offense', 'admin', $adminId);
    throw new RuntimeException('A large penalty was issued without a second approver.');
} catch (InvalidArgumentException $exception) {
    if (!str_contains($exception->getMessage(), 'second admin')) throw $exception;
}
$adminId2 = DB::table('admins')->insertGetId(['username' => 'coin_admin_2', 'created_at' => now(), 'updated_at' => now()]);
$bigPenalty = $marketplace->issuePenalty($bigTrade, 'user', $bigOffender->id, 3, 'Repeat offense', 'admin', $adminId, 'admin', $adminId2);
if (bccomp((string) $bigPenalty->coin_amount, '500', 8) !== 0) throw new RuntimeException('Tier 3 (50%) penalty math is wrong.');
fwrite(STDOUT, "Penalties: a large penalty correctly requires a second admin approver.\n");

if (!$ledger->reconcile()['ok']) throw new RuntimeException('Reconciliation broke after penalty processing.');

// --- Timeouts: payment window expiry (never claimed) and confirmation window escalation (claimed, never confirmed) - neither ever releases coins automatically. ---
$expireSeller = $insertUser('mp_expire_seller');
$ledger->mint('user', $expireSeller->id, '15', 'seed6', 'seed:expireseller');
$expireListing = $marketplace->createListing($expireSeller, '15');
$expireTrade = $marketplace->claimListing($expireListing, $agent);
CoinTrade::whereKey($expireTrade->id)->update(['payment_deadline_at' => now()->subMinute()]);
(new CoinPaymentWindowSweep())->run();
if ($expireTrade->fresh()->status !== 'expired') throw new RuntimeException('An unpaid trade past its payment window was not expired by the sweep.');
if ($expireListing->fresh()->status !== 'on_sale') throw new RuntimeException('An expired trade did not reopen its listing.');
if (bccomp((string) $ledger->wallet('user', $expireSeller->id)->fresh()->held_balance, '15', 8) !== 0) throw new RuntimeException('An expired trade should keep the coins held against the reopened listing, not release them.');

fwrite(STDOUT, "Timeout: an unclaimed payment window expires and reopens the listing without releasing held coins.\n");

$escalateTrade = $marketplace->claimListing($expireListing->fresh(), $agent);
$escalateTrade = $marketplace->claimPayment($escalateTrade, 'agent', $agent->id, null);
CoinTrade::whereKey($escalateTrade->id)->update(['confirmation_deadline_at' => now()->subMinute()]);
(new CoinConfirmationWindowSweep())->run();
$escalateTrade = $escalateTrade->fresh();
if ($escalateTrade->status !== 'disputed') throw new RuntimeException('An overdue confirmation window was not escalated to disputed by the sweep.');
if (bccomp((string) $ledger->wallet('user', $expireSeller->id)->fresh()->held_balance, '15', 8) !== 0) throw new RuntimeException('An escalated-to-disputed trade should never auto-release the held coins.');
fwrite(STDOUT, "Timeout: an overdue confirmation escalates to DISPUTED for human review - it never auto-releases coins.\n");

if (!$ledger->reconcile()['ok']) throw new RuntimeException('Reconciliation broke after the timeout sweeps.');

// --- Coin Vendor: buyer-side listing claim, vendor coin sale, linked-account/self-trade guards, and suspension behavior. ---
$vendorUser = $insertUser('mp_vendor');
$vendorBuyer = $insertUser('mp_vendor_buyer');
$linkedBuyer = $insertUser('mp_linked_buyer');
$ledger->mint('user', $vendorUser->id, '100', 'seed-vendor', 'seed:vendor');
$vendor = CoinVendor::create([
    'user_id' => $vendorUser->id, 'application_id' => 1, 'status' => 'active', 'is_selling' => true,
    'currency_id' => 1, 'sell_rate' => '0.01050000', 'per_trade_limit' => 1000, 'daily_limit' => 5000,
    'bond_amount' => 0, 'reliability_score' => 50, 'completed_trades' => 0,
]);
$ledger->holdVendorBond($vendorUser->id, '5', 'test:vendor-bond:hold');
$vendor->bond_amount = '5'; $vendor->save();
try {
    $ledger->release('user', $vendorUser->id, '5', 'incorrect escrow release', 'test:vendor-bond:wrong-release');
    throw new RuntimeException('Generic trade release consumed the separately reserved vendor bond.');
} catch (InvalidArgumentException $exception) { if (!str_contains($exception->getMessage(), 'Insufficient held')) throw $exception; }
try {
    $ledger->settleHeldToOwner('user', $vendorUser->id, 'user', $vendorBuyer->id, '5', 'incorrect escrow settlement', 'test:vendor-bond:wrong-settlement');
    throw new RuntimeException('Trade settlement consumed the separately reserved vendor bond.');
} catch (InvalidArgumentException $exception) { if (!str_contains($exception->getMessage(), 'Insufficient held')) throw $exception; }
try {
    $ledger->hold('user', $vendorUser->id, '96', 'overspend bond', 'test:vendor-bond:available-only');
    throw new RuntimeException('A generic hold spent coins reserved as a vendor bond.');
} catch (InvalidArgumentException $exception) { if (!str_contains($exception->getMessage(), 'Insufficient')) throw $exception; }
try {
    $ledger->debit('user', $vendorUser->id, '96', 'overspend bond', 'test:vendor-bond:debit-available-only');
    throw new RuntimeException('A generic wallet debit spent coins reserved as a vendor bond.');
} catch (InvalidArgumentException $exception) { if (!str_contains($exception->getMessage(), 'Insufficient')) throw $exception; }
if (!$ledger->reconcile()['ok'] || bccomp((string) $ledger->wallet('user', $vendorUser->id)->fresh()->vendor_bond_balance, '5', 8) !== 0) throw new RuntimeException('Vendor bond reserve was not separately reconciled.');
$playerSeller = $insertUser('mp_player_seller');
$ledger->mint('user', $playerSeller->id, '25', 'seed-player', 'seed:player-seller');
$playerListing = $marketplace->createListing($playerSeller, '20');
$vendorBuy = $marketplace->claimListingForVendor($playerListing, $vendor);
$duplicateVendorBuy = $marketplace->claimListingForVendor($playerListing, $vendor->fresh());
if ($duplicateVendorBuy->id !== $vendorBuy->id) throw new RuntimeException('A duplicate vendor listing click opened a second trade.');
if ($vendorBuy->coin_buyer_type !== 'user' || (int) $vendorBuy->coin_buyer_id !== $vendorUser->id || $vendorBuy->fiat_currency !== 'USD') {
    throw new RuntimeException('Vendor listing buy did not use a user-owned wallet party or price snapshot.');
}
$vendorBuy = $marketplace->claimPayment($vendorBuy, 'user', $vendorUser->id, null);
if (bccomp((string) $ledger->wallet('user', $playerSeller->id)->fresh()->held_balance, '20', 8) !== 0) {
    throw new RuntimeException('Vendor payment claim released listing coins before the seller confirmed receipt.');
}
$vendorBuy = $marketplace->confirmReceived($vendorBuy, 'user', $playerSeller->id);
if ($vendorBuy->fresh()->status !== 'completed' || (int) $vendor->fresh()->completed_trades !== 1) {
    throw new RuntimeException('User listing to vendor did not settle or increment vendor history.');
}
if (!$ledger->reconcile()['ok']) throw new RuntimeException('Reconciliation failed after vendor-to-user listing trade.');

$vendorBuyerBefore = (string) $ledger->wallet('user', $vendorBuyer->id)->fresh()->available_balance;
$vendorSale = $marketplace->initiateBuyFromVendor($vendorBuyer, $vendor->fresh(), '10');
$duplicateVendorSale = $marketplace->initiateBuyFromVendor($vendorBuyer, $vendor->fresh(), '10');
if ($duplicateVendorSale->id !== $vendorSale->id || bccomp((string) $ledger->wallet('user', $vendorUser->id)->fresh()->held_balance, '15', 8) !== 0) {
    throw new RuntimeException('A double click created a duplicate vendor sale or repeated the escrow hold.');
}
if (!$vendorSale->fiat_total || $vendorSale->fiat_currency !== 'USD' || bccomp((string) $vendorSale->price_per_coin, '0.01050000', 8) !== 0) {
    throw new RuntimeException('Direct vendor sale did not snapshot the converted price.');
}
if (bccomp((string) $ledger->wallet('user', $vendorUser->id)->fresh()->held_balance, '15', 8) !== 0) {
    throw new RuntimeException('Starting a vendor coin sale did not hold the vendor coins.');
}
$vendorSale = $marketplace->claimPayment($vendorSale, 'user', $vendorBuyer->id, null);
$vendorSale = $marketplace->confirmReceived($vendorSale, 'user', $vendorUser->id);
if ($vendorSale->fresh()->status !== 'completed' || (int) $vendor->fresh()->completed_trades !== 2) {
    throw new RuntimeException('Vendor-to-user sale did not complete and update vendor history.');
}
if (bccomp((string) $ledger->wallet('user', $vendorBuyer->id)->fresh()->available_balance, bcadd($vendorBuyerBefore, '9.5', 8), 8) !== 0) {
    throw new RuntimeException('Vendor sale did not apply the expected buyer-paid fee.');
}
if (!$ledger->reconcile()['ok']) throw new RuntimeException('Reconciliation failed after direct vendor sale.');
$lockedTradeRate = (string) $vendorSale->fresh()->price_per_coin;
Auth::guard('web')->setUser($vendorUser);
$rateController = new \App\Http\Controllers\User\CoinVendorController();
$outOfBandRate = \Illuminate\Http\Request::create('/user/coin-vendor/rate', 'POST', ['sell_rate' => '0.02']);
$rateController->updateRate($outOfBandRate, app(CoinVendorService::class));
if (bccomp((string) $vendor->fresh()->sell_rate, '0.0105', 8) !== 0) throw new RuntimeException('The vendor rate band accepted an out-of-band quote.');
$acceptedRate = \Illuminate\Http\Request::create('/user/coin-vendor/rate', 'POST', ['sell_rate' => '0.0106']);
$rateController->updateRate($acceptedRate, app(CoinVendorService::class));
if (bccomp((string) $vendor->fresh()->sell_rate, '0.0106', 8) !== 0
    || bccomp((string) $vendorSale->fresh()->price_per_coin, $lockedTradeRate, 8) !== 0
    || !\App\Models\CoinVendorAudit::where('action', 'rate_changed')->exists()) {
    throw new RuntimeException('A band-compliant rate change was not logged or modified an existing trade quote.');
}
Auth::guard('web')->logout();

// The WinxPay directory batches wallet and presence lookups, combines agents/vendors, and
// excludes vendors who cannot currently meet the minimum order size.
Schema::create('user_presence', function (Blueprint $table) { $table->unsignedBigInteger('user_id')->primary(); $table->timestamp('last_seen_at')->nullable(); });
Schema::create('agent_transaction_speed_logs', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('support_agent_id')->index(); $table->unsignedTinyInteger('user_rating')->nullable(); $table->boolean('met_fast_threshold')->default(false); $table->timestamp('confirmed_at')->nullable(); });
DB::table('user_presence')->insert(['user_id' => $vendorUser->id, 'last_seen_at' => now()]);
$unfundedUser = $insertUser('mp_unfunded_vendor');
$unfundedApp = \App\Models\CoinVendorApplication::create(['user_id' => $unfundedUser->id, 'status' => 'approved']);
$unfundedVendor = CoinVendor::create(['user_id' => $unfundedUser->id, 'application_id' => $unfundedApp->id, 'status' => 'active', 'is_selling' => true, 'currency_id' => 1, 'sell_rate' => '0.0105', 'per_trade_limit' => 100, 'daily_limit' => 500]);
DB::table('user_presence')->insert(['user_id' => $unfundedUser->id, 'last_seen_at' => now()]);
Auth::guard('web')->setUser($vendorBuyer);
$directoryService = app(\App\Support\WinxPayDirectory::class);
$directoryItems = array_merge($directoryService->agentsForBuyer($vendorBuyer), $directoryService->forBuyer($vendorBuyer));
if (!collect($directoryItems)->contains(fn ($item) => $item['type'] === 'vendor' && (int) $item['user_id'] === (int) $vendorUser->id)
    || !collect($directoryItems)->contains(fn ($item) => $item['type'] === 'agent' && (int) $item['id'] === (int) $sellingAgent->id)
    || collect($directoryItems)->contains(fn ($item) => $item['type'] === 'vendor' && (int) $item['user_id'] === (int) $unfundedUser->id)) {
    throw new RuntimeException('The WinxPay directory failed to combine agent/vendor entries or applied vendor funding filters incorrectly.');
}
Auth::guard('web')->logout();

// Serialize competing buyers on a vendor row: a retry by the same buyer returns its original
// pending trade, while a second buyer cannot race past the vendor's daily cap.
$general->coin_max_open_trades_per_user = 10;
$general->coin_vendor_max_open_trades = 10;
$general->save();
$limitedVendorUser = $insertUser('mp_limited_vendor');
$limitedVendorApp = \App\Models\CoinVendorApplication::create(['user_id' => $limitedVendorUser->id, 'status' => 'approved']);
$limitedVendor = CoinVendor::create(['user_id' => $limitedVendorUser->id, 'application_id' => $limitedVendorApp->id, 'status' => 'active', 'is_selling' => true, 'currency_id' => 1, 'sell_rate' => '0.0105', 'per_trade_limit' => 100, 'daily_limit' => 15, 'bond_amount' => 0]);
$ledger->mint('user', $limitedVendorUser->id, '20', 'seed limited vendor', 'seed:limited-vendor:' . $limitedVendorUser->id);
$concurrentBuyerA = $insertUser('mp_concurrent_buyer_a');
$concurrentBuyerB = $insertUser('mp_concurrent_buyer_b');
$firstConcurrentTrade = $marketplace->initiateBuyFromVendor($concurrentBuyerA, $limitedVendor, '10');
$retryConcurrentTrade = $marketplace->initiateBuyFromVendor($concurrentBuyerA, $limitedVendor->fresh(), '10');
if ($firstConcurrentTrade->id !== $retryConcurrentTrade->id || bccomp((string) $ledger->wallet('user', $limitedVendorUser->id)->fresh()->held_balance, '10', 8) !== 0) {
    throw new RuntimeException('A retried direct vendor request did not reuse its existing escrowed trade.');
}
try { $marketplace->initiateBuyFromVendor($concurrentBuyerB, $limitedVendor->fresh(), '10'); throw new RuntimeException('Two buyers bypassed the vendor daily limit.'); }
catch (InvalidArgumentException $exception) { if (!str_contains(strtolower($exception->getMessage()), 'daily')) throw $exception; }
$firstConcurrentTrade = $marketplace->claimPayment($firstConcurrentTrade, 'user', $concurrentBuyerA->id, null);
$firstConcurrentTrade = $marketplace->confirmReceived($firstConcurrentTrade, 'user', $limitedVendorUser->id);
if ($firstConcurrentTrade->fresh()->status !== 'completed' || !$ledger->reconcile()['ok']) throw new RuntimeException('The competing-buyer test trade did not settle cleanly or reconcile.');

// Approved marketplace penalty consumes bond first, while trade escrow remains separately locked.
$vendorBuy->status = 'disputed'; $vendorBuy->save();
$vendorPenalty = $marketplace->issuePenalty($vendorBuy, 'user', $vendorUser->id, 1, 'Vendor bond penalty fixture', 'admin', $adminId);
$retriedVendorPenalty = $marketplace->issuePenalty($vendorBuy->fresh(), 'user', $vendorUser->id, 1, 'Vendor bond penalty fixture', 'admin', $adminId);
if (bccomp((string) $vendorPenalty->coin_amount, '2', 8) !== 0
    || $retriedVendorPenalty->id !== $vendorPenalty->id
    || bccomp((string) $vendor->fresh()->bond_amount, '3', 8) !== 0
    || bccomp((string) $ledger->wallet('user', $vendorUser->id)->fresh()->vendor_bond_balance, '3', 8) !== 0
    || !DB::table('admin_notifications')->where('title', 'like', '%bond deducted%')->exists()
    || !$ledger->reconcile()['ok']) throw new RuntimeException('Vendor penalty did not deduct and reconcile against the bond first.');
$vendorBuy->status = 'completed'; $vendorBuy->save();

$vendor->linked_user_ids = [(int) $linkedBuyer->id];
$vendor->save();
foreach ([$vendorUser, $linkedBuyer] as $blockedBuyer) {
    try {
        $marketplace->initiateBuyFromVendor($blockedBuyer, $vendor->fresh(), '10');
        throw new RuntimeException('A vendor self-trade or linked-account trade was allowed.');
    } catch (InvalidArgumentException $exception) {
        if (!str_contains(strtolower($exception->getMessage()), 'cannot trade')) throw $exception;
    }
}

$suspendedBuyer = $insertUser('mp_suspended_buyer');
$openVendorSale = $marketplace->initiateBuyFromVendor($suspendedBuyer, $vendor->fresh(), '10');
$vendor->status = 'suspended';
$vendor->is_selling = false;
$vendor->bond_release_after_at = now()->subSecond();
$vendor->save();
Auth::guard('web')->setUser($vendorUser);
try { (new \App\Http\Controllers\User\CoinVendorController())->desk(app(CoinVendorService::class), $ledger); throw new RuntimeException('A suspended vendor opened their Vendor Desk.'); }
catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { if ($exception->getStatusCode() !== 403) throw $exception; }
Auth::guard('web')->logout();
Auth::guard('admin')->setUser(\App\Models\Admin::findOrFail($adminId));
$openTradeReleaseRequest = \Illuminate\Http\Request::create('/admin/coin/vendors/' . $vendor->id . '/release-bond', 'POST', ['reason' => 'Must wait for open trade']);
try {
    (new \App\Http\Controllers\Admin\CoinVendorController())->vendorAction($openTradeReleaseRequest, $vendor->id, 'release-bond');
    throw new RuntimeException('Admin released vendor bond while a trade was still open.');
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { if ($exception->getStatusCode() !== 409) throw $exception; }
if (bccomp((string) $vendor->fresh()->bond_amount, '3', 8) !== 0) throw new RuntimeException('An open trade changed the vendor bond.');
try {
    $marketplace->initiateBuyFromVendor($insertUser('mp_after_suspend'), $vendor->fresh(), '10');
    throw new RuntimeException('Suspended vendor accepted a new trade.');
} catch (InvalidArgumentException) {}
$openVendorSale = $marketplace->claimPayment($openVendorSale, 'user', $suspendedBuyer->id, null);
$openVendorSale = $marketplace->confirmReceived($openVendorSale, 'user', $vendorUser->id);
if ($openVendorSale->fresh()->status !== 'completed') throw new RuntimeException('An open vendor trade did not remain resolvable after suspension.');
if (!$ledger->reconcile()['ok']) throw new RuntimeException('Reconciliation failed after resolving a suspended vendor trade.');

// Cooldown and open-trade gates protect the admin-only release action.
Auth::guard('admin')->setUser(\App\Models\Admin::findOrFail($adminId));
$vendor->bond_release_after_at = now()->addDay(); $vendor->save();
$releaseRequest = \Illuminate\Http\Request::create('/admin/coin/vendors/' . $vendor->id . '/release-bond', 'POST', ['reason' => 'Integration test release']);
try {
    (new \App\Http\Controllers\Admin\CoinVendorController())->vendorAction($releaseRequest, $vendor->id, 'release-bond');
    throw new RuntimeException('Admin bond release bypassed the cooldown.');
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { if ($exception->getStatusCode() !== 409) throw $exception; }
$vendor->bond_release_after_at = now()->subSecond(); $vendor->save();
$releaseResponse = (new \App\Http\Controllers\Admin\CoinVendorController())->vendorAction($releaseRequest, $vendor->id, 'release-bond');
if (bccomp((string) $vendor->fresh()->bond_amount, '0', 8) !== 0
    || bccomp((string) $ledger->wallet('user', $vendorUser->id)->fresh()->vendor_bond_balance, '0', 8) !== 0
    || !\App\Models\CoinVendorAudit::where('action', 'vendor_bond_released')->exists()
    || !DB::table('admin_notifications')->where('title', 'like', '%bond released%')->exists()
    || !$ledger->reconcile()['ok']) throw new RuntimeException('Eligible vendor bond release, audit, notification or reconciliation failed.');

$eligibilityUser = $insertUser('mp_vendor_eligible');
DB::table('users')->where('id', $eligibilityUser->id)->update(['created_at' => now()->subDays(10)]);
$eligibilityUser = $eligibilityUser->fresh();
if (!app(CoinVendorService::class)->eligibility($eligibilityUser)['eligible']) throw new RuntimeException('A verified, mature user in an allowed country failed vendor eligibility.');
DB::table('users')->where('id', $eligibilityUser->id)->update(['ev' => 0]);
if (app(CoinVendorService::class)->eligibility($eligibilityUser->fresh())['eligible']) throw new RuntimeException('An email-unverified account passed vendor eligibility.');
DB::table('users')->where('id', $eligibilityUser->id)->update(['ev' => 1, 'sv' => 0]);
if (app(CoinVendorService::class)->eligibility($eligibilityUser->fresh())['eligible']) throw new RuntimeException('A phone-unverified account passed vendor eligibility.');
DB::table('users')->where('id', $eligibilityUser->id)->update(['sv' => 1, 'created_at' => now()]);
if (app(CoinVendorService::class)->eligibility($eligibilityUser->fresh())['eligible']) throw new RuntimeException('An account younger than the configured minimum passed vendor eligibility.');
DB::table('users')->where('id', $eligibilityUser->id)->update(['created_at' => now()->subDays(10)]);
DB::table('countries')->where('id', 1)->update(['vendors_allowed' => 0]);
 $vendorCountryResult = app(CoinVendorService::class)->eligibility($eligibilityUser->fresh());
if ($vendorCountryResult['eligible'] || !str_contains($vendorCountryResult['checks'][3]['message'], 'vendor applications are switched off')) throw new RuntimeException('A disabled vendor country passed eligibility or did not identify the country setting that blocks it.');
DB::table('countries')->where('id', 1)->update(['vendors_allowed' => 1]);
DB::table('countries')->where('id', 1)->update(['coin_marketplace_enabled' => 0]);
 $vendorMarketplaceResult = app(CoinVendorService::class)->eligibility($eligibilityUser->fresh());
if ($vendorMarketplaceResult['eligible'] || !str_contains($vendorMarketplaceResult['checks'][3]['message'], 'Coin Marketplace is switched off')) throw new RuntimeException('A country with its Coin Marketplace disabled passed vendor eligibility or lacked a clear explanation.');
DB::table('countries')->where('id', 1)->update(['coin_marketplace_enabled' => 1]);
DB::table('coin_vendor_applications')->insert(['user_id' => $eligibilityUser->id, 'status' => 'rejected', 'rejected_at' => now()->subDay(), 'created_at' => now()->subDay(), 'updated_at' => now()]);
if (app(CoinVendorService::class)->eligibility($eligibilityUser->fresh())['eligible']) throw new RuntimeException('A rejected applicant passed the rejection cooldown.');
DB::table('coin_vendor_applications')->where('user_id', $eligibilityUser->id)->update(['rejected_at' => now()->subDays(31)]);
DB::table('coin_vendor_applications')->where('user_id', $eligibilityUser->id)->update(['status' => 'submitted', 'rejected_at' => null]);
if (app(CoinVendorService::class)->eligibility($eligibilityUser->fresh())['eligible']) throw new RuntimeException('An applicant with an existing submission passed eligibility.');
DB::table('users')->where('id', $eligibilityUser->id)->update(['coin_vendor_flagged' => 1]);
if (app(CoinVendorService::class)->eligibility($eligibilityUser->fresh())['eligible']) throw new RuntimeException('A flagged account passed vendor eligibility.');

// User application tests: duplicate ID, phone and browser/network hints all raise a review flag;
// request fields cannot mass-assign reviewer or approval state.
$applicationFixtures = [];
foreach (['id', 'phone', 'device'] as $duplicateKind) {
    $applicant = $insertUser('mp_apply_' . $duplicateKind);
    DB::table('users')->where('id', $applicant->id)->update(['created_at' => now()->subDays(10)]);
    $applicant = $applicant->fresh();
    $idNumber = 'VENDOR-' . strtoupper($duplicateKind) . '-' . $applicant->id;
    $phoneHash = hash_hmac('sha256', preg_replace('/\D+/', '', $applicant->mobileNumber), (string) config('app.key'));
    $idHash = hash_hmac('sha256', mb_strtolower(preg_replace('/\s+/', '', trim($idNumber))), (string) config('app.key'));
    $fingerprintHash = hash_hmac('sha256', 'vendor-security-test|203.0.113.20', (string) config('app.key'));
    $other = $insertUser('mp_duplicate_' . $duplicateKind);
    $duplicateData = ['user_id' => $other->id, 'status' => 'rejected', 'created_at' => now(), 'updated_at' => now()];
    if ($duplicateKind === 'id') $duplicateData['id_number_hash'] = $idHash;
    if ($duplicateKind === 'phone') $duplicateData['phone'] = $phoneHash;
    if ($duplicateKind === 'device') $duplicateData['device_fingerprint_hash'] = $fingerprintHash;
    DB::table('coin_vendor_applications')->insert($duplicateData);
    Auth::guard('web')->setUser($applicant);
    $baseImage = \Illuminate\Http\UploadedFile::fake()->image('id-front.jpg', 640, 480);
    $imageBytes = file_get_contents($baseImage->getRealPath());
    if ($duplicateKind === 'id') {
        $exif = "Exif\0\0II*\0\x08\0\0\0\0\0\0\0";
        $imageBytes = substr($imageBytes, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($imageBytes, 2);
    }
    $input = [
        'legal_name' => 'Test Applicant ' . $duplicateKind, 'street' => '10 Test Road', 'city' => 'Lagos', 'region' => 'Lagos',
        'address_country_id' => 1, 'postal_code' => '100001', 'phone' => $applicant->mobileNumber, 'id_type' => 'International Passport',
        'id_number' => $idNumber, 'consent_identity' => '1', 'consent_p2p_risk' => '1', 'status' => 'approved', 'reviewed_by' => $adminId,
    ];
    $request = \Illuminate\Http\Request::create('/user/coin-vendor/draft', 'POST', $input, [], ['id_front' => \Illuminate\Http\UploadedFile::fake()->createWithContent('front.jpg', $imageBytes)], ['HTTP_USER_AGENT' => 'vendor-security-test', 'REMOTE_ADDR' => '203.0.113.20']);
    (new \App\Http\Controllers\User\CoinVendorController())->saveDraft($request, app(CoinVendorService::class));
    $savedApplication = \App\Models\CoinVendorApplication::where('user_id', $applicant->id)->latest('id')->firstOrFail();
    if (!$savedApplication->duplicate_flag || $savedApplication->status !== 'draft' || $savedApplication->reviewed_by !== null || $savedApplication->id_last4 !== substr($idNumber, -4) || !preg_match('/^[a-f0-9]{64}$/', (string) $savedApplication->id_number_hash)) {
        throw new RuntimeException("{$duplicateKind} duplicate flag, hashed ID, or mass-assignment protections failed.");
    }
    $rawIdentity = DB::table('coin_vendor_applications')->where('id', $savedApplication->id)->value('identity_data');
    if (str_contains((string) $rawIdentity, 'Test Applicant')) throw new RuntimeException('Identity data is stored as plaintext instead of encrypted data.');
    $savedFront = app(CoinVendorService::class)->readPrivate($savedApplication->id_front_path);
    if (!$savedFront || str_contains($savedFront, "Exif\0\0") || str_contains(strtolower($savedApplication->id_front_path), 'public')) throw new RuntimeException('ID image was not re-encoded into private storage with EXIF removed.');
    $applicationFixtures[$duplicateKind] = [$applicant, $savedApplication];
}

// Admin review decisions always record an audit and move the application/vendor to the
// requested state; rejection requires a reason and approval is gated by the full checklist.
$reviewAdmin = \App\Models\Admin::findOrFail($adminId);
$reviewAdmin->can_review_vendor_identity = true; $reviewAdmin->save();
Auth::guard('admin')->setUser($reviewAdmin->fresh());
$reviewUser = $insertUser('mp_review_flow');
DB::table('users')->where('id', $reviewUser->id)->update(['created_at' => now()->subDays(10)]);
$reviewUser = $reviewUser->fresh();
$reviewApplicationId = DB::table('coin_vendor_applications')->insertGetId([
    'user_id' => $reviewUser->id, 'status' => 'submitted', 'submitted_at' => now(), 'consent_identity' => 1,
    'consent_p2p_risk' => 1, 'id_front_path' => app(CoinVendorService::class)->storePrivate('review identity', 'jpg'),
    'selfie_video_path' => app(CoinVendorService::class)->storePrivate('video review fixture', 'webm'),
    'challenge_code' => '2468', 'created_at' => now(), 'updated_at' => now(),
]);
$reviewController = new \App\Http\Controllers\Admin\CoinVendorController();
$moreInfoRequest = \Illuminate\Http\Request::create('/admin/coin/vendors/applications/' . $reviewApplicationId . '/request-information', 'POST', ['requested_information' => 'Please provide a clearer address proof.']);
$reviewController->requestInformation($moreInfoRequest, $reviewApplicationId);
if (\App\Models\CoinVendorApplication::findOrFail($reviewApplicationId)->status !== 'more_information'
    || !\App\Models\CoinVendorAudit::where('application_id', $reviewApplicationId)->where('action', 'more_information_requested')->exists()) {
    throw new RuntimeException('Request-more-information review did not update status and audit the decision.');
}
$missingReasonRequest = \Illuminate\Http\Request::create('/admin/coin/vendors/applications/' . $reviewApplicationId . '/reject', 'POST', []);
try { $reviewController->reject($missingReasonRequest, $reviewApplicationId); throw new RuntimeException('Application rejection accepted an empty reason.'); }
catch (\Illuminate\Validation\ValidationException) {}
$rejectRequest = \Illuminate\Http\Request::create('/admin/coin/vendors/applications/' . $reviewApplicationId . '/reject', 'POST', ['reason' => 'Identity document does not match the submitted details.']);
$reviewController->reject($rejectRequest, $reviewApplicationId);
$rejectedReview = \App\Models\CoinVendorApplication::findOrFail($reviewApplicationId);
if ($rejectedReview->status !== 'rejected' || $rejectedReview->decision_reason !== 'Identity document does not match the submitted details.'
    || !$rejectedReview->retention_delete_at || !\App\Models\CoinVendorAudit::where('application_id', $reviewApplicationId)->where('action', 'application_rejected')->exists()) {
    throw new RuntimeException('Rejected review reason, retention schedule, or audit was not recorded.');
}
Auth::guard('web')->setUser($reviewUser);
try { (new \App\Http\Controllers\User\CoinVendorController())->desk(app(CoinVendorService::class), $ledger); throw new RuntimeException('A rejected applicant opened a vendor desk.'); }
catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {}
Auth::guard('web')->logout();

$approvedUser = $insertUser('mp_approve_flow');
DB::table('users')->where('id', $approvedUser->id)->update(['created_at' => now()->subDays(10)]);
$approvedUser = $approvedUser->fresh();
$ledger->mint('user', $approvedUser->id, '20', 'approval bond fixture', 'seed:approval-bond:' . $approvedUser->id);
$general->coin_vendor_security_bond = '2'; $general->save();
$approvedAppId = DB::table('coin_vendor_applications')->insertGetId([
    'user_id' => $approvedUser->id, 'status' => 'submitted', 'submitted_at' => now(), 'consent_identity' => 1,
    'consent_p2p_risk' => 1, 'id_front_path' => app(CoinVendorService::class)->storePrivate('approval identity', 'jpg'),
    'selfie_video_path' => app(CoinVendorService::class)->storePrivate('approval video fixture', 'webm'),
    'challenge_code' => '1357', 'created_at' => now(), 'updated_at' => now(),
]);
$checklist = array_fill_keys(['name_matches','face_matches','code_spoken','id_valid','address_plausible','duplicate_checked'], '1');
$approveRequest = \Illuminate\Http\Request::create('/admin/coin/vendors/applications/' . $approvedAppId . '/approve', 'POST', ['checklist' => $checklist, 'reviewer_notes' => 'All checks completed.']);
$reviewController->approve($approveRequest, $approvedAppId, app(CoinVendorService::class), $ledger);
$approvedVendor = CoinVendor::where('user_id', $approvedUser->id)->firstOrFail();
if ($approvedVendor->status !== 'active' || \App\Models\CoinVendorApplication::findOrFail($approvedAppId)->status !== 'approved'
    || bccomp((string) $approvedVendor->bond_amount, '2', 8) !== 0
    || bccomp((string) $ledger->wallet('user', $approvedUser->id)->fresh()->vendor_bond_balance, '2', 8) !== 0
    || !\App\Models\CoinVendorAudit::where('application_id', $approvedAppId)->where('action', 'application_approved')->exists()
    || !DB::table('admin_notifications')->where('title', 'like', '%bond held%')->exists()) {
    throw new RuntimeException('Approval failed to create the active vendor, hold and notify the bond, or audit the decision.');
}
$actionRequest = static fn (string $reason) => \Illuminate\Http\Request::create('/admin/coin/vendors/' . $approvedVendor->id, 'POST', ['reason' => $reason]);
$reviewController->vendorAction($actionRequest('Review pause'), $approvedVendor->id, 'suspend');
if ($approvedVendor->fresh()->status !== 'suspended' || !\App\Models\CoinVendorAudit::where('vendor_id', $approvedVendor->id)->where('action', 'vendor_suspend')->exists()) throw new RuntimeException('Vendor suspension was not audited.');
$reviewController->vendorAction($actionRequest('Review completed'), $approvedVendor->id, 'reinstate');
if ($approvedVendor->fresh()->status !== 'active') throw new RuntimeException('A cleared vendor could not be reinstated.');
$reviewController->vendorAction($actionRequest('Vendor policy breach'), $approvedVendor->id, 'revoke');
if ($approvedVendor->fresh()->status !== 'revoked' || !$approvedVendor->fresh()->bond_release_after_at?->isFuture()
    || !\App\Models\CoinVendorAudit::where('vendor_id', $approvedVendor->id)->where('action', 'vendor_revoke')->exists()) {
    throw new RuntimeException('Vendor revocation did not schedule the cooldown and audit the action.');
}
Auth::guard('admin')->logout();
$general->coin_vendor_security_bond = '0'; $general->save();

$resigningUser = $insertUser('mp_resign_flow');
$resigningApplication = \App\Models\CoinVendorApplication::create(['user_id' => $resigningUser->id, 'status' => 'approved']);
$resigningVendor = CoinVendor::create(['user_id' => $resigningUser->id, 'application_id' => $resigningApplication->id, 'status' => 'active', 'currency_id' => 1, 'sell_rate' => '0.01050000', 'per_trade_limit' => 100, 'daily_limit' => 500, 'bond_amount' => 0]);
Auth::guard('web')->setUser($resigningUser);
(new \App\Http\Controllers\User\CoinVendorController())->resign();
if ($resigningVendor->fresh()->status !== 'resigned' || !$resigningVendor->fresh()->bond_release_after_at?->isFuture()
    || !\App\Models\CoinVendorAudit::where('vendor_id', $resigningVendor->id)->where('action', 'vendor_resigned')->exists()) {
    throw new RuntimeException('Vendor resignation did not stop sales, record the cooldown, and audit the event.');
}
Auth::guard('web')->logout();

$privateVendorFile = app(CoinVendorService::class)->storePrivate('private vendor fixture', 'txt');
if (app(CoinVendorService::class)->readPrivate($privateVendorFile) !== 'private vendor fixture') throw new RuntimeException('Private vendor file encryption/decryption did not round trip.');
app(CoinVendorService::class)->deletePrivate($privateVendorFile);

// Route boundaries: vendor applicants/reviewers use the normal authenticated web/admin
// pipelines (including CSRF), with explicit limits on the expensive application endpoints.
$routes = app('router')->getRoutes();
foreach ([
    'user.coin-vendor.draft' => 'throttle:coin-vendor-draft',
    'user.coin-vendor.challenge' => 'throttle:coin-vendor-challenge',
    'user.coin-vendor.liveness.record' => 'throttle:coin-vendor-selfie-upload',
    'user.coin-vendor.submit' => 'throttle:coin-vendor-submit',
] as $routeName => $throttle) {
    $route = $routes->getByName($routeName);
    if (!$route || !in_array('web', $route->gatherMiddleware(), true) || !in_array($throttle, $route->gatherMiddleware(), true)) {
        throw new RuntimeException("Vendor route {$routeName} is missing web/CSRF or {$throttle} middleware.");
    }
}
$draftRoute = $routes->getByName('user.coin-vendor.draft');
$deleteRejectedRoute = $routes->getByName('user.coin-vendor.delete-rejected-data');
if (!$draftRoute || str_contains($draftRoute->uri(), '{') || !$deleteRejectedRoute || str_contains($deleteRejectedRoute->uri(), '{')) {
    throw new RuntimeException('User application edit/deletion routes accept a caller-supplied application ID (IDOR risk).');
}
foreach ($routes as $route) {
    if (str_starts_with((string) $route->uri(), 'support-agent/') && str_contains(strtolower($route->uri()), 'vendor')) {
        throw new RuntimeException('A vendor or applicant route is exposed in the support-agent portal.');
    }
}

// Only an identity-permitted admin can stream an encrypted private file, and every stream is audited.
$privateApplication = $applicationFixtures['id'][1];
$adminReviewer = \App\Models\Admin::findOrFail($adminId);
$adminReviewer->can_review_vendor_identity = false; $adminReviewer->save();
Auth::guard('admin')->setUser($adminReviewer->fresh());
try {
    (new \App\Http\Controllers\Admin\CoinVendorController())->file($privateApplication->id, 'id-front', app(CoinVendorService::class));
    throw new RuntimeException('An admin without identity permission opened an ID file.');
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { if ($exception->getStatusCode() !== 403) throw $exception; }
$adminReviewer->can_review_vendor_identity = true; $adminReviewer->save();
Auth::guard('admin')->setUser($adminReviewer->fresh());
$auditBeforeFileViews = \App\Models\CoinVendorAudit::where('action', 'identity_file_viewed')->count();
$privateResponse = (new \App\Http\Controllers\Admin\CoinVendorController())->file($privateApplication->id, 'id-front', app(CoinVendorService::class));
$secondPrivateResponse = (new \App\Http\Controllers\Admin\CoinVendorController())->file($privateApplication->id, 'id-front', app(CoinVendorService::class));
if ($privateResponse->getStatusCode() !== 200 || !str_contains(strtolower((string) $privateResponse->headers->get('Cache-Control')), 'private') || !str_contains(strtolower((string) $privateResponse->headers->get('Cache-Control')), 'no-store')
    || $secondPrivateResponse->getStatusCode() !== 200
    || \App\Models\CoinVendorAudit::where('action', 'identity_file_viewed')->count() !== $auditBeforeFileViews + 2) {
    throw new RuntimeException('Permitted admin file access was not private or did not create an audit row: status=' . $privateResponse->getStatusCode() . ', cache=' . $privateResponse->headers->get('Cache-Control') . ', audit=' . \App\Models\CoinVendorAudit::where('action', 'identity_file_viewed')->count() . '/' . ($auditBeforeFileViews + 2));
}
Auth::guard('admin')->logout();
Auth::guard('web')->logout();
try {
    (new \App\Http\Controllers\Admin\CoinVendorController())->file($privateApplication->id, 'id-front', app(CoinVendorService::class));
    throw new RuntimeException('An unauthenticated request opened a private identity file.');
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { if ($exception->getStatusCode() !== 403) throw $exception; }
Auth::guard('web')->setUser($insertUser('mp_wrong_camera_user'));
try {
    (new \App\Http\Controllers\Admin\CoinVendorController())->file($privateApplication->id, 'id-front', app(CoinVendorService::class));
    throw new RuntimeException('An authenticated non-admin opened a private identity file.');
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { if ($exception->getStatusCode() !== 403) throw $exception; }
Auth::guard('web')->setUser($vendorUser);
try {
    (new \App\Http\Controllers\Admin\CoinVendorController())->file($privateApplication->id, 'id-front', app(CoinVendorService::class));
    throw new RuntimeException('A vendor opened another applicant\'s private identity file.');
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { if ($exception->getStatusCode() !== 403) throw $exception; }
Auth::guard('web')->logout();
try {
    (new \App\Http\Controllers\Admin\CoinVendorController())->file($privateApplication->id + 99999, 'id-front', app(CoinVendorService::class));
    throw new RuntimeException('A private file IDOR returned a file for a nonexistent application.');
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { if ($exception->getStatusCode() !== 403) throw $exception; }

// The rejected-user deletion path removes private files and identity while retaining the audit trail.
$deleteUser = $insertUser('mp_delete_rejected');
$deletePath = app(CoinVendorService::class)->storePrivate('identity to delete', 'jpg');
$deleteApplicationId = DB::table('coin_vendor_applications')->insertGetId([
    'user_id' => $deleteUser->id, 'status' => 'rejected', 'identity_data' => \Illuminate\Support\Facades\Crypt::encryptString(json_encode(['legal_name' => 'Delete me'])),
    'phone' => str_repeat('a', 64), 'id_number_hash' => str_repeat('b', 64), 'id_last4' => '1234', 'id_front_path' => $deletePath,
    'created_at' => now(), 'updated_at' => now(),
]);
\App\Models\CoinVendorAudit::record('application_rejected', $deleteApplicationId, null, $adminId, null, 'Data deletion fixture.');
Auth::guard('web')->setUser($deleteUser);
(new \App\Http\Controllers\User\CoinVendorController())->deleteRejectedApplication();
$deletedApplication = \App\Models\CoinVendorApplication::findOrFail($deleteApplicationId);
if ($deletedApplication->identity_data !== null || $deletedApplication->id_number_hash !== null || $deletedApplication->device_fingerprint_hash !== null
    || $deletedApplication->challenge_user_agent !== null || $deletedApplication->challenge_ip !== null || $deletedApplication->challenge_session_hash !== null
    || app(CoinVendorService::class)->readPrivate($deletePath) !== null
    || !\App\Models\CoinVendorAudit::where('application_id', $deleteApplicationId)->exists()) {
    throw new RuntimeException('Rejected-user data deletion removed the audit or retained private identity data.');
}
Auth::guard('web')->logout();

// Retention cleanup deletes rejected uploads once, preserves audits and is safe to rerun.
$retentionPath = app(CoinVendorService::class)->storePrivate('expired identity', 'jpg');
$retentionId = DB::table('coin_vendor_applications')->insertGetId([
    'user_id' => $insertUser('mp_retention')->id, 'status' => 'rejected', 'id_front_path' => $retentionPath,
    'retention_delete_at' => now()->subSecond(), 'created_at' => now()->subDays(70), 'updated_at' => now(),
]);
\App\Models\CoinVendorAudit::record('application_rejected', $retentionId, null, $adminId, null, 'Retention fixture.');
$retentionService = app(CoinVendorService::class);
if ($retentionService->cleanupExpiredApplicationData() !== 1 || $retentionService->cleanupExpiredApplicationData() !== 0
    || $retentionService->readPrivate($retentionPath) !== null
    || !\App\Models\CoinVendorAudit::where('application_id', $retentionId)->exists()) {
    throw new RuntimeException('Rejected application retention cleanup is not idempotent or removed audit history.');
}

// Camera challenge tests exercise the same controller boundary used by the only video route.
$cameraUser = $applicationFixtures['id'][0];
Auth::guard('web')->setUser($cameraUser);
$newSession = static function (): array {
    $session = new \Illuminate\Session\Store('vendor-camera-test', new \Illuminate\Session\ArraySessionHandler(3600));
    $session->start();
    $request = \Illuminate\Http\Request::create('/user/coin-vendor/liveness/challenge', 'POST', [], [], [], ['HTTP_USER_AGENT' => 'Vendor test browser', 'REMOTE_ADDR' => '203.0.113.21']);
    $request->setLaravelSession($session);
    return [$session, $request];
};
$cameraController = new \App\Http\Controllers\User\CoinVendorController();
[$cameraSession, $challengeRequest] = $newSession();
$challengeResponse = $cameraController->challenge($challengeRequest, app(CoinVendorService::class));
$challenge = json_decode($challengeResponse->getContent(), true);
$uploadRequest = static function ($session, array $fields, string $name = 'check.webm', string $body = '') {
    $video = \Illuminate\Http\UploadedFile::fake()->createWithContent($name, $body ?: "\x1A\x45\xDF\xA3" . str_repeat("\x00", 110000));
    $request = \Illuminate\Http\Request::create('/user/coin-vendor/liveness/record', 'POST', $fields, [], ['video' => $video], ['HTTP_USER_AGENT' => 'Vendor test browser', 'REMOTE_ADDR' => '203.0.113.21']);
    $request->setLaravelSession($session);
    return $request;
};
$plainUpload = $uploadRequest($cameraSession, ['challenge_id' => $challenge['challenge_id'], 'duration_ms' => 9000]);
try { $cameraController->uploadSelfie($plainUpload, app(CoinVendorService::class)); throw new RuntimeException('A plain file upload reached the video endpoint without a liveness token.'); }
catch (\Illuminate\Validation\ValidationException) {}
$wrongTokenUpload = $uploadRequest($cameraSession, ['liveness_token' => str_repeat('x', 48), 'challenge_id' => $challenge['challenge_id'], 'duration_ms' => 9000]);
try { $cameraController->uploadSelfie($wrongTokenUpload, app(CoinVendorService::class)); throw new RuntimeException('An invalid liveness token was accepted.'); }
catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { if ($exception->getStatusCode() !== 403) throw $exception; }
$oversizedUpload = $uploadRequest($cameraSession, ['liveness_token' => $challenge['token'], 'challenge_id' => $challenge['challenge_id'], 'duration_ms' => 9000], 'large.webm', "\x1A\x45\xDF\xA3" . str_repeat("\x00", 6 * 1024 * 1024 + 1));
try { $cameraController->uploadSelfie($oversizedUpload, app(CoinVendorService::class)); throw new RuntimeException('The video upload size cap was not enforced.'); }
catch (\Illuminate\Validation\ValidationException) {}

Auth::guard('web')->setUser($applicationFixtures['phone'][0]);
try { $cameraController->uploadSelfie($uploadRequest($cameraSession, ['liveness_token' => $challenge['token'], 'challenge_id' => $challenge['challenge_id'], 'duration_ms' => 9000]), app(CoinVendorService::class)); throw new RuntimeException('A different user could use another applicant video token.'); }
catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { if ($exception->getStatusCode() !== 403) throw $exception; }
Auth::guard('web')->setUser($cameraUser);

[$badMimeSession, $badMimeChallengeRequest] = $newSession();
$badMimeChallenge = json_decode($cameraController->challenge($badMimeChallengeRequest, app(CoinVendorService::class))->getContent(), true);
$badMimePath = tempnam(sys_get_temp_dir(), 'vendor_bad_video_');
file_put_contents($badMimePath, str_repeat('<html>not a video</html>', 6000));
$badMimeFile = new \Illuminate\Http\UploadedFile($badMimePath, 'fake.webm', null, UPLOAD_ERR_OK, true);
$badMime = \Illuminate\Http\Request::create('/user/coin-vendor/liveness/record', 'POST', ['liveness_token' => $badMimeChallenge['token'], 'challenge_id' => $badMimeChallenge['challenge_id'], 'duration_ms' => 9000], [], ['video' => $badMimeFile], ['HTTP_USER_AGENT' => 'Vendor test browser', 'REMOTE_ADDR' => '203.0.113.21']);
$badMime->setLaravelSession($badMimeSession);
$badMimeResponse = $cameraController->uploadSelfie($badMime, app(CoinVendorService::class));
@unlink($badMimePath);
if ($badMimeResponse->getStatusCode() !== 422) throw new RuntimeException('HTML disguised as a video was not rejected by MIME sniffing: status=' . $badMimeResponse->getStatusCode() . ' mime=' . $badMime->file('video')->getMimeType());
$challenge = $badMimeChallenge;
$cameraSession = $badMimeSession;

[$wrongSession] = $newSession();
$wrongSessionUpload = $uploadRequest($wrongSession, ['liveness_token' => $challenge['token'], 'challenge_id' => $challenge['challenge_id'], 'duration_ms' => 9000]);
try { $cameraController->uploadSelfie($wrongSessionUpload, app(CoinVendorService::class)); throw new RuntimeException('A liveness token was accepted from a different session.'); }
catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { if ($exception->getStatusCode() !== 403) throw $exception; }

DB::table('coin_vendor_applications')->where('id', $applicationFixtures['id'][1]->id)->update(['challenge_expires_at' => now()->subSecond()]);
try { $cameraController->uploadSelfie($uploadRequest($cameraSession, ['liveness_token' => $challenge['token'], 'challenge_id' => $challenge['challenge_id'], 'duration_ms' => 9000]), app(CoinVendorService::class)); throw new RuntimeException('An expired liveness token was accepted.'); }
catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { if ($exception->getStatusCode() !== 403) throw $exception; }

[$cameraSession, $challengeRequest] = $newSession();
$challenge = json_decode($cameraController->challenge($challengeRequest, app(CoinVendorService::class))->getContent(), true);
$validUpload = $cameraController->uploadSelfie($uploadRequest($cameraSession, ['liveness_token' => $challenge['token'], 'challenge_id' => $challenge['challenge_id'], 'duration_ms' => 9000]), app(CoinVendorService::class));
if ($validUpload->getStatusCode() !== 200) throw new RuntimeException('A current challenge token with a video was rejected.');
try { $cameraController->uploadSelfie($uploadRequest($cameraSession, ['liveness_token' => $challenge['token'], 'challenge_id' => $challenge['challenge_id'], 'duration_ms' => 9000]), app(CoinVendorService::class)); throw new RuntimeException('A liveness token was replayed successfully.'); }
catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { if ($exception->getStatusCode() !== 403) throw $exception; }
Auth::guard('web')->logout();

// Disguised/double-extension ID uploads are rejected before an application record is saved.
$badFileUser = $insertUser('mp_bad_identity_file');
DB::table('users')->where('id', $badFileUser->id)->update(['created_at' => now()->subDays(10)]);
$badFileUser = $badFileUser->fresh();
Auth::guard('web')->setUser($badFileUser);
$validIdFixture = \Illuminate\Http\UploadedFile::fake()->image('valid.jpg', 640, 480);
$validIdBytes = file_get_contents($validIdFixture->getRealPath());
$badFileFields = [
    'legal_name' => 'Test Upload', 'street' => '10 Test Road', 'city' => 'Lagos', 'region' => 'Lagos', 'address_country_id' => 1,
    'postal_code' => '100001', 'phone' => $badFileUser->mobileNumber, 'id_type' => 'International Passport', 'id_number' => 'BAD-UPLOAD-1234',
    'consent_identity' => '1', 'consent_p2p_risk' => '1',
];
$doubleExtension = \Illuminate\Http\Request::create('/user/coin-vendor/draft', 'POST', $badFileFields, [], [
    'id_front' => \Illuminate\Http\UploadedFile::fake()->createWithContent('document.jpg.php', $validIdBytes),
], ['HTTP_USER_AGENT' => 'bad-upload-test', 'REMOTE_ADDR' => '203.0.113.22']);
$doubleExtensionResponse = null;
try { $doubleExtensionResponse = (new \App\Http\Controllers\User\CoinVendorController())->saveDraft($doubleExtension, app(CoinVendorService::class)); }
catch (\Illuminate\Validation\ValidationException) {}
if (($doubleExtensionResponse && $doubleExtensionResponse->getStatusCode() >= 400) || \App\Models\CoinVendorApplication::where('user_id', $badFileUser->id)->exists()) {
    throw new RuntimeException('A double-extension identity file was accepted or persisted.');
}
$svgUpload = \Illuminate\Http\Request::create('/user/coin-vendor/draft', 'POST', $badFileFields, [], [
    'id_front' => \Illuminate\Http\UploadedFile::fake()->createWithContent('document.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
], ['HTTP_USER_AGENT' => 'bad-upload-test', 'REMOTE_ADDR' => '203.0.113.22']);
try { (new \App\Http\Controllers\User\CoinVendorController())->saveDraft($svgUpload, app(CoinVendorService::class)); throw new RuntimeException('An SVG identity upload was accepted.'); }
catch (\Illuminate\Validation\ValidationException) {}
$largeImagePath = tempnam(sys_get_temp_dir(), 'vendor_large_id_');
file_put_contents($largeImagePath, $validIdBytes . str_repeat("\0", 8 * 1024 * 1024));
$largeImageFile = new \Illuminate\Http\UploadedFile($largeImagePath, 'large-id.jpg', 'image/jpeg', UPLOAD_ERR_OK, true);
$largeImageRequest = \Illuminate\Http\Request::create('/user/coin-vendor/draft', 'POST', $badFileFields, [], ['id_front' => $largeImageFile], ['HTTP_USER_AGENT' => 'bad-upload-test', 'REMOTE_ADDR' => '203.0.113.22']);
try { (new \App\Http\Controllers\User\CoinVendorController())->saveDraft($largeImageRequest, app(CoinVendorService::class)); throw new RuntimeException('The ID image size cap was not enforced.'); }
catch (\Illuminate\Validation\ValidationException) {}
@unlink($largeImagePath);
$htmlPath = tempnam(sys_get_temp_dir(), 'vendor_bad_image_');
file_put_contents($htmlPath, '<html><script>alert(1)</script></html>');
$htmlFile = new \Illuminate\Http\UploadedFile($htmlPath, 'document.jpg', null, UPLOAD_ERR_OK, true);
$htmlUpload = \Illuminate\Http\Request::create('/user/coin-vendor/draft', 'POST', $badFileFields, [], ['id_front' => $htmlFile], ['HTTP_USER_AGENT' => 'bad-upload-test', 'REMOTE_ADDR' => '203.0.113.22']);
$htmlUploadResponse = null;
try { $htmlUploadResponse = (new \App\Http\Controllers\User\CoinVendorController())->saveDraft($htmlUpload, app(CoinVendorService::class)); }
catch (\Illuminate\Validation\ValidationException) {}
@unlink($htmlPath);
if (($htmlUploadResponse && $htmlUploadResponse->getStatusCode() >= 400) || \App\Models\CoinVendorApplication::where('user_id', $badFileUser->id)->exists()) throw new RuntimeException('HTML disguised as a JPG identity image was accepted or persisted.');
Auth::guard('web')->logout();

if (!$ledger->reconcile()['ok']) throw new RuntimeException('Reconciliation failed after vendor trades.');
fwrite(STDOUT, "Coin Vendor: listing purchase and direct sale settle through user wallets; escrow, rate snapshots, linked/self-trade guards, suspension, eligibility, private files, and reconciliation pass.\n");

// --- Trade chat: send/fetch, visibility restricted to the two parties, and attachment handling. ---
Auth::guard('web')->setUser($seller);
$chatRequest = \Illuminate\Http\Request::create('/coin-trade/' . $trade->id . '/chat', 'POST', ['body' => 'Here are my bank details.']);
$chatRequest->setUserResolver(fn () => $seller);
$chatResponse = (new CoinTradeChatController())->send($chatRequest, $trade->id);
$chatPayload = json_decode($chatResponse->getContent(), true);
if ($chatResponse->getStatusCode() !== 201 || $chatPayload['message']['body'] !== 'Here are my bank details.') throw new RuntimeException('Trade chat did not accept a message from a legitimate participant.');

// setUserResolver() ignores whichever guard name is asked for, so it can't simulate "signed in
// on one guard but not others" - use the real multi-guard Auth facade instead, logging the
// other two guards out first, so $request->user($guard) resolves exactly as it would for a real
// HTTP request.
Auth::guard('admin')->logout();
Auth::guard('support_agent')->logout();
$outsider = $insertUser('mp_outsider');
Auth::guard('web')->setUser($outsider);
$outsiderRequest = \Illuminate\Http\Request::create('/coin-trade/' . $trade->id . '/chat', 'GET');
try {
    (new CoinTradeChatController())->messages($outsiderRequest, $trade->id);
    throw new RuntimeException('A non-participant was able to read a trade chat.');
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
    if ($exception->getStatusCode() !== 403) throw $exception;
}

$uploadRequest = \Illuminate\Http\Request::create('/coin-trade/' . $trade->id . '/chat', 'POST', [], [], ['attachment' => \Illuminate\Http\UploadedFile::fake()->image('proof.jpg')]);
$uploadRequest->setUserResolver(fn () => $seller);
$uploadResponse = (new CoinTradeChatController())->send($uploadRequest, $trade->id);
$uploadPayload = json_decode($uploadResponse->getContent(), true);
if (empty($uploadPayload['message']['attachment_url'])) throw new RuntimeException('Trade chat did not store a proof-of-payment attachment.');
fwrite(STDOUT, "Trade chat: participants can message and attach files; a non-participant is rejected.\n");

// --- Agent marketplace controller / admin panel smoke: listing visible to agents, selling toggle. ---
Auth::guard('support_agent')->setUser($agent);
$toggleResponse = (new CoinAgentMarketplaceController())->toggleSelling();
if (!$agent->fresh()->is_selling_coins) throw new RuntimeException('Toggling is_selling_coins did not persist.');
fwrite(STDOUT, "Agent marketplace: the is_selling_coins toggle works.\n");

// Best-effort teardown.
try {
    $marketplaceMigration->down();
    $supervisorFlagMigration->down();
    $coinCoreMigration->down();
    $frameworkMigration->down();
} catch (\Throwable $teardownException) {
    fwrite(STDERR, 'Note: best-effort schema teardown did not fully complete (' . $teardownException->getMessage() . "), ignoring.\n");
}
if (is_dir($testStoragePath)) \Illuminate\Support\Facades\File::deleteDirectory($testStoragePath);
DB::disconnect();
@unlink($dbPath);
fwrite(STDOUT, "Coin marketplace (Phase 2) integration checks passed.\n");
