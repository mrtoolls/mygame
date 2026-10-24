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
use App\Models\SupportAgent;
use App\Models\User;
use App\Services\CoinLedger;
use App\Services\CoinMarketplace;
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
    $table->decimal('balance', 28, 8)->default(0);
    $table->unsignedBigInteger('ref_by')->nullable();
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->tinyInteger('status')->default(1);
    $table->timestamps();
});
Schema::create('admins', function (Blueprint $table) { $table->id(); $table->string('username')->nullable(); $table->timestamps(); });
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
$supervisorFlagMigration = require dirname(__DIR__) . '/database/migrations/2026_10_24_000013_add_is_marketplace_supervisor_to_support_agents.php';
$supervisorFlagMigration->up();
$marketplaceMigration = require dirname(__DIR__) . '/database/migrations/2026_10_24_000020_create_coin_marketplace_tables.php';
$marketplaceMigration->up();

$ledger = app(CoinLedger::class);
$marketplace = app(CoinMarketplace::class);
Storage::fake('local');

$insertUser = static function (string $username): User {
    $id = DB::table('users')->insertGetId(['username' => $username, 'created_at' => now(), 'updated_at' => now()]);
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
$general->save();

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
DB::disconnect();
@unlink($dbPath);
fwrite(STDOUT, "Coin marketplace (Phase 2) integration checks passed.\n");
