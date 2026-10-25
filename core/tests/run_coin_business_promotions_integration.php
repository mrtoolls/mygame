<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\Controllers\Admin\BusinessPromotionController as AdminBusinessPromotionController;
use App\Http\Controllers\User\BusinessPromotionController;
use App\Models\BusinessPromotion;
use App\Models\BusinessPromotionRate;
use App\Models\Currency;
use App\Models\User;
use App\Services\CoinLedger;
use App\Services\CoinPricing;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

$dbPath = __DIR__ . '/coin_business_promotions_integration.sqlite';
if (is_file($dbPath)) unlink($dbPath);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
putenv('CACHE_STORE=database');
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec("CREATE TABLE general_settings (id INTEGER PRIMARY KEY, force_ssl INTEGER DEFAULT 0, cur_text VARCHAR(10) DEFAULT 'USD', created_at DATETIME, updated_at DATETIME)");
$pdo->exec("INSERT INTO general_settings (id, cur_text) VALUES (1, 'USD')");
$pdo = null;

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
Storage::fake('local');

(require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php')->up();

Schema::create('users', function (Blueprint $table) {
    $table->id(); $table->string('username'); $table->decimal('balance', 28, 8)->default(0);
    $table->unsignedBigInteger('currency_id')->nullable(); $table->unsignedBigInteger('country_id')->nullable(); $table->timestamps();
});
Schema::create('admins', function (Blueprint $table) { $table->id(); $table->timestamps(); });
Schema::create('transactions', function (Blueprint $table) {
    $table->id(); $table->unsignedBigInteger('user_id'); $table->decimal('amount', 28, 8); $table->decimal('charge', 28, 8)->default(0);
    $table->string('trx_type', 1); $table->string('details'); $table->string('remark'); $table->string('trx')->unique();
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->decimal('post_balance', 28, 8); $table->timestamps();
});
Schema::create('currencies', function (Blueprint $table) {
    $table->id(); $table->string('code', 10); $table->integer('decimals')->default(2); $table->boolean('enabled')->default(true);
    $table->string('symbol', 5)->default('$'); $table->string('symbol_position', 10)->default('left');
    $table->decimal('rate_to_usd', 24, 12)->nullable(); $table->timestamp('last_updated_at')->nullable(); $table->timestamps();
});

(require dirname(__DIR__) . '/database/migrations/2026_10_24_000011_add_coin_settings_to_general_settings.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_25_000001_add_pause_state_to_coin_rates.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_19_000006_create_business_promotions.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_25_000004_add_is_coin_mode_to_business_promotions.php')->up();

$ledger = app(CoinLedger::class);
$failures = [];
$pass = 0;
function check(array &$failures, int &$pass, string $label, bool $condition): void
{
    if ($condition) { $pass++; return; }
    $failures[] = $label;
}

$insertUser = static function (string $username, string $balance, int $currencyId): User {
    $id = DB::table('users')->insertGetId(['username' => $username, 'balance' => $balance, 'currency_id' => $currencyId, 'created_at' => now(), 'updated_at' => now()]);
    return User::findOrFail($id);
};

$ngn = Currency::create(['code' => 'NGN', 'decimals' => 2, 'enabled' => true, 'rate_to_usd' => '0.00065', 'last_updated_at' => now()]);
app(CoinPricing::class)->recompute($ngn);
BusinessPromotionRate::create(['currency_id' => $ngn->id, 'daily_price_minor' => 500000, 'weekly_price_minor' => 2500000, 'monthly_price_minor' => 8000000]); // 5000.00 NGN/day

$general = gs();
$general->coin_mode = true;
$general->save();

// ---------------------------------------------------------------------------
// 1. store(): charges coins (via CoinLedger::debit), never touches users.balance, stores the
//    coin amount on the row with is_coin_mode=true, matching the same currency->USD->coin
//    conversion CoinPricing itself uses.
// ---------------------------------------------------------------------------
$user = $insertUser('promo_user', '1000.00000000', $ngn->id);
$ledger->mint('user', $user->id, '500', 'seed', 'seed:' . $user->id);
Auth::login($user);

$request = Request::create('/business-promotions', 'POST', [
    'idempotency_key' => (string) Str::uuid(),
    'business_name' => 'Test Biz',
    'title' => 'Grand Opening',
    'description' => 'Come check us out, we have great offers all week long for everyone.',
    'destination_url' => 'https://example.com',
    'duration' => 'day',
], [], ['image' => UploadedFile::fake()->image('promo.jpg', 200, 200)]);
$request->setUserResolver(fn () => $user);

(new BusinessPromotionController())->store($request);

$promotion = BusinessPromotion::where('user_id', $user->id)->first();
check($failures, $pass, 'store(): a promotion row was created', $promotion !== null);
check($failures, $pass, 'store(): is_coin_mode flag is set on the row', $promotion?->is_coin_mode === true);
// 5000.00 NGN * 0.00065 rate_to_usd = 3.25 USD -> / 0.01 peg = 325 coins.
check($failures, $pass, 'store(): the coin amount matches the currency->USD->coin conversion (325)', (int) $promotion?->amount_minor === 325);
check($failures, $pass, 'store(): the coin wallet was debited by exactly that amount', bccomp((string) $ledger->wallet('user', $user->id)->fresh()->available_balance, '175', 8) === 0);
check($failures, $pass, 'store(): users.balance was never touched', bccomp((string) $user->fresh()->balance, '1000.00000000', 8) === 0);
check($failures, $pass, 'store(): reconciliation still balances', $ledger->reconcile()['ok']);
fwrite(STDOUT, "BusinessPromotionController::store() in coin mode: charges coins via CoinLedger, never touches users.balance, records the exact coin amount converted through the currency's own rate.\n");

// ---------------------------------------------------------------------------
// 2. Admin reject(): refunds coins via CoinLedger::credit() (not a real-currency balance credit),
//    based on the promotion's own is_coin_mode flag.
// ---------------------------------------------------------------------------
$admin = DB::table('admins')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
$rejectRequest = Request::create('', 'POST', ['admin_note' => 'Does not meet guidelines.']);
(new AdminBusinessPromotionController())->reject($rejectRequest, $promotion->id);

$promotion->refresh();
check($failures, $pass, 'reject(): promotion marked rejected and refunded', $promotion->status === 'rejected' && $promotion->refunded_at !== null);
check($failures, $pass, 'reject(): the coin wallet was credited back the exact amount (325)', bccomp((string) $ledger->wallet('user', $user->id)->fresh()->available_balance, '500', 8) === 0);
check($failures, $pass, 'reject(): users.balance still untouched after the refund', bccomp((string) $user->fresh()->balance, '1000.00000000', 8) === 0);
check($failures, $pass, 'reject(): reconciliation still balances after the refund', $ledger->reconcile()['ok']);

// Re-rejecting (already rejected) must not double-refund.
try {
    (new AdminBusinessPromotionController())->reject($rejectRequest, $promotion->id);
    $failures[] = 'reject(): rejecting an already-rejected promotion should have aborted (422), not succeeded silently.';
} catch (\Throwable) {
    $pass++;
}
check($failures, $pass, 'reject(): no double-refund occurred', bccomp((string) $ledger->wallet('user', $user->id)->fresh()->available_balance, '500', 8) === 0);
fwrite(STDOUT, "Admin\\BusinessPromotionController::reject(): refunds via CoinLedger for a coin-mode promotion, based on its own is_coin_mode flag, with no double-refund.\n");

// ---------------------------------------------------------------------------
// 3. Mixed-mode safety: a promotion paid for in REAL CURRENCY before coin_mode was on must still
//    refund in real currency even if rejected after the flag flips on - never trusting the live
//    flag for a historical row.
// ---------------------------------------------------------------------------
$general->coin_mode = false;
$general->save();
$legacyUser = $insertUser('legacy_promo_user', '10000.00000000', $ngn->id);
Auth::login($legacyUser);
$legacyRequest = Request::create('/business-promotions', 'POST', [
    'idempotency_key' => (string) Str::uuid(),
    'business_name' => 'Legacy Biz', 'title' => 'Legacy Promo',
    'description' => 'A perfectly normal real-money promotion submitted before coin mode existed.',
    'destination_url' => 'https://example.com', 'duration' => 'day',
], [], ['image' => UploadedFile::fake()->image('legacy.jpg', 200, 200)]);
$legacyRequest->setUserResolver(fn () => $legacyUser);
(new BusinessPromotionController())->store($legacyRequest);
$legacyPromotion = BusinessPromotion::where('user_id', $legacyUser->id)->first();
check($failures, $pass, 'Mixed-mode: a real-currency promotion is NOT flagged is_coin_mode', $legacyPromotion?->is_coin_mode === false);
check($failures, $pass, 'Mixed-mode: real-currency charge debited the real balance (5000.00 NGN)', bccomp((string) $legacyUser->fresh()->balance, '5000.00000000', 8) === 0);

$general->coin_mode = true;
$general->save();
$legacyWalletBefore = (string) $ledger->wallet('user', $legacyUser->id)->fresh()->available_balance;
(new AdminBusinessPromotionController())->reject(Request::create('', 'POST', ['admin_note' => 'test']), $legacyPromotion->id);
check($failures, $pass, 'Mixed-mode: rejecting it after coin_mode flipped on still refunds REAL currency, not coins', bccomp((string) $legacyUser->fresh()->balance, '10000.00000000', 8) === 0);
check($failures, $pass, 'Mixed-mode: the coin wallet was never touched by this real-currency refund', bccomp((string) $ledger->wallet('user', $legacyUser->id)->fresh()->available_balance, $legacyWalletBefore, 8) === 0);
Auth::logout();
fwrite(STDOUT, "Mixed-mode safety: a promotion's own is_coin_mode flag (not the live setting) decides whether its refund moves coins or real currency.\n");

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------
echo "Passed: {$pass}\n";
if ($failures) {
    echo "FAILED (" . count($failures) . "):\n";
    foreach ($failures as $failure) echo " - {$failure}\n";
    exit(1);
}
echo "Coin-mode business promotions integration checks passed.\n";
