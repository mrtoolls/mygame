<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\Controllers\User\CoinMarketplaceController;
use App\Models\CoinTrade;
use App\Models\CoinVendor;
use App\Models\SupportAgent;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Proves two things end to end: (1) a CoinVendor can now actually set contact_methods (the
// column/cast/controller/route/form did not exist before), and (2) CoinMarketplaceController::
// show() resolves the trade's counterparty into a display-ready contact card for both a
// CoinVendor and a SupportAgent, and correctly resolves to null when the counterparty is a plain
// member with no contact channels set up (falls back to trade chat only, same as before).

$dbPath = __DIR__ . '/coin_vendor_contact_methods_integration.sqlite';
if (is_file($dbPath)) unlink($dbPath);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
putenv('CACHE_STORE=database');
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec("CREATE TABLE general_settings (id INTEGER PRIMARY KEY, cur_text VARCHAR(10) DEFAULT 'USD', created_at DATETIME, updated_at DATETIME)");
$pdo->exec("INSERT INTO general_settings (id, cur_text) VALUES (1, 'USD')");
$pdo = null;

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

Schema::create('users', function (Blueprint $table) {
    $table->id(); $table->string('username'); $table->string('fullname')->nullable(); $table->decimal('balance', 28, 8)->default(0); $table->unsignedBigInteger('currency_id')->nullable(); $table->timestamps();
});
Schema::create('currencies', function (Blueprint $table) {
    $table->id(); $table->char('code', 3)->unique(); $table->string('name'); $table->string('symbol', 12);
    $table->unsignedTinyInteger('decimals')->default(2); $table->boolean('enabled')->default(true);
    $table->decimal('rate_to_usd', 24, 12)->nullable(); $table->timestamps();
});
DB::table('currencies')->insert(['id' => 1, 'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'enabled' => 1, 'rate_to_usd' => 1, 'created_at' => now(), 'updated_at' => now()]);
Schema::create('support_agents', function (Blueprint $table) {
    $table->id(); $table->string('name'); $table->string('username'); $table->string('photo')->nullable();
    $table->boolean('is_active')->default(true); $table->boolean('is_verified')->default(false);
    $table->boolean('is_chat_agent')->default(true); $table->json('contact_methods')->nullable();
    $table->unsignedBigInteger('user_id')->nullable(); $table->timestamps();
});

(require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_25_000001_add_pause_state_to_coin_rates.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_24_000020_create_coin_marketplace_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_26_000001_create_coin_vendor_system.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_11_07_000003_add_contact_methods_to_coin_vendors.php')->up();

$insertUser = static function (string $username): User {
    $id = DB::table('users')->insertGetId(['username' => $username, 'fullname' => $username, 'currency_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
    return User::findOrFail($id);
};

$failures = [];
$pass = 0;
function check(array &$failures, int &$pass, string $label, bool $condition): void
{
    if ($condition) { $pass++; return; }
    $failures[] = $label;
}

$controller = new CoinMarketplaceController();
$reflection = new ReflectionMethod(CoinMarketplaceController::class, 'counterpartyContactCard');
$reflection->setAccessible(true);

// 1. CoinVendor with contact_methods set -> resolves to a real card.
$vendorUser = $insertUser('contact_vendor');
$buyer = $insertUser('contact_buyer');
$vendor = CoinVendor::create([
    'user_id' => $vendorUser->id, 'application_id' => 1, 'status' => 'active', 'is_selling' => true,
    'currency_id' => 1, 'sell_rate' => '0.01', 'per_trade_limit' => 100, 'daily_limit' => 500,
]);
check($failures, $pass, 'CoinVendor starts with no contact_methods (column exists, defaults null)', $vendor->fresh()->contact_methods === null);
$vendor->contact_methods = ['whatsapp' => ['enabled' => true, 'value' => '+15551234567'], 'email' => ['enabled' => false, 'value' => 'old@example.test']];
$vendor->save();

$trade = CoinTrade::create([
    'direction' => 'buy_from_agent', 'coin_seller_type' => 'user', 'coin_seller_id' => $vendorUser->id,
    'coin_buyer_type' => 'user', 'coin_buyer_id' => $buyer->id, 'amount' => '10', 'status' => 'pending',
    'fee_amount' => '0', 'fee_payer' => 'seller',
]);
$card = $reflection->invoke($controller, $trade, $buyer->id);
check($failures, $pass, 'Buyer sees a contact card for the vendor', (bool) $card);
check($failures, $pass, 'Card shows the vendor role', $card && $card['role'] === 'Coin Vendor');
check($failures, $pass, 'Card is verified (every active CoinVendor is)', $card && $card['verified'] === true);
check($failures, $pass, 'Card includes the enabled WhatsApp channel', $card && $card['contact_methods']['whatsapp']['value'] === '+15551234567');

$cardFromVendorSide = $reflection->invoke($controller, $trade, $vendorUser->id);
check($failures, $pass, 'The vendor, viewing their own trade, does NOT see a "contact card" for themselves', $cardFromVendorSide === null);
fwrite(STDOUT, "CoinVendor contact card: resolves correctly for the counterparty, not for the vendor's own view of their trade.\n");

// 2. CoinVendor with NO contact_methods enabled -> no card (falls back to trade chat only).
$silentVendorUser = $insertUser('silent_vendor');
$silentBuyer = $insertUser('silent_buyer');
CoinVendor::create(['user_id' => $silentVendorUser->id, 'application_id' => 1, 'status' => 'active', 'is_selling' => true, 'currency_id' => 1, 'sell_rate' => '0.01', 'per_trade_limit' => 100, 'daily_limit' => 500]);
$silentTrade = CoinTrade::create([
    'direction' => 'buy_from_agent', 'coin_seller_type' => 'user', 'coin_seller_id' => $silentVendorUser->id,
    'coin_buyer_type' => 'user', 'coin_buyer_id' => $silentBuyer->id, 'amount' => '5', 'status' => 'pending',
    'fee_amount' => '0', 'fee_payer' => 'seller',
]);
check($failures, $pass, 'A vendor with no contact methods set produces no card', $reflection->invoke($controller, $silentTrade, $silentBuyer->id) === null);
fwrite(STDOUT, "No contact methods set: correctly falls back to trade chat only, no empty/broken card shown.\n");

// 3. SupportAgent counterparty with contact_methods -> resolves to a real card too.
$agentUser = $insertUser('contact_agent_user');
$agentBuyer = $insertUser('contact_agent_buyer');
$agentId = DB::table('support_agents')->insertGetId([
    'name' => 'Jane Agent', 'username' => 'janeagent', 'is_active' => 1, 'is_verified' => 1,
    'contact_methods' => json_encode(['telegram' => ['enabled' => true, 'value' => '@janeagent']]),
    'user_id' => $agentUser->id, 'created_at' => now(), 'updated_at' => now(),
]);
$agentTrade = CoinTrade::create([
    'direction' => 'buy_from_agent', 'coin_seller_type' => 'user', 'coin_seller_id' => $agentUser->id,
    'coin_buyer_type' => 'user', 'coin_buyer_id' => $agentBuyer->id, 'amount' => '8', 'status' => 'pending',
    'fee_amount' => '0', 'fee_payer' => 'seller',
]);
$agentCard = $reflection->invoke($controller, $agentTrade, $agentBuyer->id);
check($failures, $pass, 'Buyer sees a contact card for the support agent', (bool) $agentCard);
check($failures, $pass, 'Agent card shows the correct role', $agentCard && $agentCard['role'] === 'Support Agent');
check($failures, $pass, 'Agent card includes the Telegram channel', $agentCard && $agentCard['contact_methods']['telegram']['value'] === '@janeagent');
fwrite(STDOUT, "SupportAgent contact card: resolves correctly too, reusing the same mechanism as a CoinVendor.\n");

// 4. Plain member-to-member trade (neither party is a vendor or agent) -> no card at all.
$memberSeller = $insertUser('plain_seller');
$memberBuyer = $insertUser('plain_buyer');
$memberTrade = CoinTrade::create([
    'direction' => 'listing', 'coin_seller_type' => 'user', 'coin_seller_id' => $memberSeller->id,
    'coin_buyer_type' => 'user', 'coin_buyer_id' => $memberBuyer->id, 'amount' => '3', 'status' => 'pending',
    'fee_amount' => '0', 'fee_payer' => 'seller',
]);
check($failures, $pass, 'A plain member-to-member trade has no contact card', $reflection->invoke($controller, $memberTrade, $memberBuyer->id) === null);
fwrite(STDOUT, "Member-to-member trade: no contact card, same as before this feature existed.\n");

echo "Passed: {$pass}\n";
if ($failures) {
    echo "FAILED (" . count($failures) . "):\n";
    foreach ($failures as $failure) echo " - {$failure}\n";
    exit(1);
}
echo "Coin vendor/agent contact methods checks passed.\n";
