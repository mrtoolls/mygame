<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\Controllers\Api\WithdrawController as ApiWithdrawController;
use App\Http\Controllers\User\WithdrawController;
use App\Models\Currency;
use App\Models\User;
use App\Models\Withdrawal;
use App\Models\WithdrawMethod;
use App\Services\CoinLedger;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/coin_mode_display_integration.sqlite';
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
// ActiveTemplateMiddleware normally registers this namespace per-request - this script never
// goes through HTTP middleware, so it needs registering by hand to render real theme views.
\Illuminate\Support\Facades\View::addNamespace('Template', resource_path('views/templates/nocturne'));

$frameworkMigration = require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php';
$frameworkMigration->up();

Schema::create('general_settings', function (Blueprint $table) {
    $table->id();
    $table->string('cur_text', 10)->default('USD');
    $table->string('cur_sym', 5)->default('$');
    $table->integer('currency_format')->default(1);
    $table->boolean('force_ssl')->default(0);
    $table->boolean('min_balance')->default(0);
    $table->timestamps();
});
DB::table('general_settings')->insert(['id' => 1]);
(require dirname(__DIR__) . '/database/migrations/2026_10_24_000011_add_coin_settings_to_general_settings.php')->up();

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('username');
    $table->string('email')->nullable();
    $table->decimal('balance', 28, 8)->default(0);
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->tinyInteger('status')->default(1);
    $table->boolean('ts')->default(0);
    $table->timestamps();
});
Schema::create('currencies', function (Blueprint $table) {
    $table->id();
    $table->string('code', 10);
    $table->integer('decimals')->default(2);
    $table->boolean('enabled')->default(true);
    $table->string('symbol', 5)->default('$');
    $table->string('symbol_position', 10)->default('left');
    $table->decimal('rate_to_usd', 24, 12)->nullable();
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
    $table->timestamps();
});
Schema::create('withdraw_methods', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('currency', 10);
    $table->decimal('min_limit', 28, 8)->default(1);
    $table->decimal('max_limit', 28, 8)->default(100000);
    $table->decimal('fixed_charge', 28, 8)->default(0);
    $table->decimal('percent_charge', 28, 8)->default(0);
    $table->decimal('rate', 28, 8)->default(1);
    $table->unsignedBigInteger('form_id')->nullable();
    $table->unsignedBigInteger('support_agent_id')->nullable();
    $table->boolean('status')->default(1);
    $table->timestamps();
});
Schema::create('withdrawals', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->unsignedBigInteger('method_id')->nullable();
    $table->decimal('amount', 28, 8);
    $table->string('currency', 10)->nullable();
    $table->decimal('rate', 28, 8)->default(1);
    $table->decimal('charge', 28, 8)->default(0);
    $table->decimal('final_amount', 28, 8)->default(0);
    $table->decimal('after_charge', 28, 8)->default(0);
    $table->json('withdraw_information')->nullable();
    $table->integer('status')->default(0);
    $table->string('trx')->nullable();
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->string('admin_feedback')->nullable();
    $table->timestamps();
});

Schema::create('support_agents', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->boolean('is_active')->default(true);
    $table->boolean('is_payment_agent')->default(false);
    $table->timestamps();
});

$coinCoreMigration = require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php';
$coinCoreMigration->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_25_000001_add_pause_state_to_coin_rates.php')->up();

$ledger = app(CoinLedger::class);
$insertUser = static function (string $username, string $balance = '0', ?int $currencyId = null): User {
    $id = DB::table('users')->insertGetId(['username' => $username, 'balance' => $balance, 'currency_id' => $currencyId, 'created_at' => now(), 'updated_at' => now()]);
    return User::findOrFail($id);
};

$usd = Currency::create(['code' => 'USD', 'decimals' => 2, 'enabled' => true, 'rate_to_usd' => '1', 'last_updated_at' => now()]);
$user = $insertUser('display_user', '500', $usd->id);
Auth::guard('web')->setUser($user);

// --- coin_mode OFF: withdraw is completely unaffected (regression). ---
$general = gs();
$general->coin_mode = false;
$general->save();

$method = WithdrawMethod::forceCreate(['name' => 'Bank Transfer', 'currency' => 'USD', 'min_limit' => '1', 'max_limit' => '1000', 'fixed_charge' => '0', 'percent_charge' => '0', 'rate' => '1', 'status' => 1]);

$webController = new WithdrawController();
$response = $webController->withdrawMoney();
if (method_exists($response, 'getTargetUrl') && str_contains((string) $response->getTargetUrl(), 'coin')) {
    throw new RuntimeException('coin_mode=off incorrectly blocked withdrawMoney().');
}
if (!($response instanceof \Illuminate\View\View)) {
    throw new RuntimeException('coin_mode=off withdrawMoney() did not return the normal view.');
}

$storeRequest = \Illuminate\Http\Request::create('/user/withdraw', 'POST', ['method_code' => $method->id, 'amount' => '50']);
$storeRequest->setUserResolver(fn () => $user);
$storeResponse = $webController->withdrawStore($storeRequest);
if ($storeResponse->getTargetUrl() && str_contains($storeResponse->getTargetUrl(), 'coin')) {
    throw new RuntimeException('coin_mode=off incorrectly blocked withdrawStore().');
}
if (Withdrawal::count() !== 1) throw new RuntimeException('coin_mode=off withdrawStore() did not create a withdrawal as before.');
fwrite(STDOUT, "Withdraw: coin_mode off behaves exactly as before (regression check) - a withdrawal request is created normally.\n");

// --- coin_mode ON: every mutating withdraw action is blocked, server-side, with a clear message. ---
$general->coin_mode = true;
$general->save();

$blockedMoney = $webController->withdrawMoney();
if (!str_contains($blockedMoney->getTargetUrl(), route('user.coin.marketplace'))) throw new RuntimeException('withdrawMoney() was not blocked in coin mode.');

$blockedStore = $webController->withdrawStore($storeRequest);
if (!str_contains($blockedStore->getTargetUrl(), route('user.coin.marketplace'))) throw new RuntimeException('withdrawStore() was not blocked in coin mode.');
if (Withdrawal::count() !== 1) throw new RuntimeException('withdrawStore() created a second withdrawal despite coin mode being on.');

$blockedPreview = $webController->withdrawPreview();
if (!str_contains($blockedPreview->getTargetUrl(), route('user.coin.marketplace'))) throw new RuntimeException('withdrawPreview() was not blocked in coin mode.');

$blockedSubmit = $webController->withdrawSubmit(\Illuminate\Http\Request::create('/x', 'POST'));
if (!str_contains($blockedSubmit->getTargetUrl(), route('user.coin.marketplace'))) throw new RuntimeException('withdrawSubmit() was not blocked in coin mode.');

// A direct POST to the submit endpoint with a forged/stale session must also be blocked - not
// just the normal navigation flow.
session(['wtrx' => 'SOMETRX']);
$forgedSubmit = $webController->withdrawSubmit(\Illuminate\Http\Request::create('/x', 'POST'));
if (!str_contains($forgedSubmit->getTargetUrl(), route('user.coin.marketplace'))) throw new RuntimeException('A direct request to withdrawSubmit() bypassed the coin-mode block.');

// History must still work, read-only - the one existing withdrawal stays visible.
$logResponse = $webController->withdrawLog(\Illuminate\Http\Request::create('/x', 'GET'));
if (!($logResponse instanceof \Illuminate\View\View)) throw new RuntimeException('withdrawLog() was incorrectly blocked in coin mode - history must stay visible.');
fwrite(STDOUT, "Withdraw: every mutating action (money/store/preview/submit, including a forged direct request) is blocked server-side in coin mode; history remains visible.\n");

// --- Same for the API controller. ---
$apiController = new ApiWithdrawController();
$apiBlocked = $apiController->withdrawMethod();
$apiPayload = json_decode($apiBlocked->getContent(), true);
if (($apiPayload['status'] ?? null) !== 'error') throw new RuntimeException('API withdrawMethod() was not blocked in coin mode.');

$apiStoreBlocked = $apiController->withdrawStore(\Illuminate\Http\Request::create('/x', 'POST', ['method_code' => $method->id, 'amount' => '10']));
$apiStorePayload = json_decode($apiStoreBlocked->getContent(), true);
if (($apiStorePayload['status'] ?? null) !== 'error') throw new RuntimeException('API withdrawStore() was not blocked in coin mode.');
if (Withdrawal::count() !== 1) throw new RuntimeException('API withdrawStore() created a withdrawal despite coin mode being on.');

$apiSubmitBlocked = $apiController->withdrawSubmit(\Illuminate\Http\Request::create('/x', 'POST', ['trx' => 'SOMETRX']));
$apiSubmitPayload = json_decode($apiSubmitBlocked->getContent(), true);
if (($apiSubmitPayload['status'] ?? null) !== 'error') throw new RuntimeException('API withdrawSubmit() was not blocked in coin mode.');
fwrite(STDOUT, "Withdraw: the API controller is blocked identically to the web controller.\n");

// --- Balance display: showUserBalance() and the coin-amount component. ---
$general->coin_mode = false;
$general->save();
$offDisplay = showUserBalance($user);
if ($offDisplay !== showAmount($user->balance)) throw new RuntimeException('showUserBalance() changed behavior when coin_mode is off.');

$general->coin_mode = true;
$general->save();
$ledger->mint('user', $user->id, '42', 'test', 'display_test:mint');
$ledger->hold('user', $user->id, '10', 'test hold', 'display_test:hold');
$onDisplay = showUserBalance($user, approx: false, showHeld: true);
if (!str_contains($onDisplay, '32') || !str_contains($onDisplay, 'Coins')) throw new RuntimeException("showUserBalance() did not render the correct coin amount: {$onDisplay}");
if (!str_contains($onDisplay, '10')) throw new RuntimeException('showUserBalance() did not render the held amount when requested.');
if (str_contains($onDisplay, '$') || str_contains($onDisplay, 'USD')) throw new RuntimeException('showUserBalance() rendered a currency symbol instead of coins.');
fwrite(STDOUT, "Balance display: showUserBalance() shows the real coin wallet (available + held) in coin mode, and is unchanged off.\n");

// --- Nav: render the real account_nav partial and confirm Withdraw disappears / Coin Marketplace appears. ---
$general->coin_mode = false;
$general->save();
$navOff = view('Template::partials.account_nav')->render();
if (!str_contains($navOff, route('user.withdraw'))) throw new RuntimeException('The Withdraw nav link is missing when coin_mode is off.');
if (str_contains($navOff, route('user.coin.marketplace'))) throw new RuntimeException('The Coin Marketplace nav link incorrectly appears when coin_mode is off.');

$general->coin_mode = true;
$general->save();
$navOn = view('Template::partials.account_nav')->render();
if (str_contains($navOn, 'href="' . route('user.withdraw') . '"')) throw new RuntimeException('The Withdraw (create) nav link is still present when coin_mode is on.');
if (!str_contains($navOn, route('user.coin.marketplace'))) throw new RuntimeException('The Coin Marketplace nav link is missing when coin_mode is on.');
if (!str_contains($navOn, route('user.withdraw.history'))) throw new RuntimeException('The read-only Withdrawal History nav link is missing when coin_mode is on.');
fwrite(STDOUT, "Nav: the real account_nav partial correctly swaps Withdraw for Coin Marketplace (keeping read-only history reachable) based on coin_mode.\n");

// Best-effort teardown.
try {
    $coinCoreMigration->down();
    $frameworkMigration->down();
} catch (\Throwable $teardownException) {
    fwrite(STDERR, 'Note: best-effort schema teardown did not fully complete (' . $teardownException->getMessage() . "), ignoring.\n");
}
DB::disconnect();
@unlink($dbPath);
fwrite(STDOUT, "Coin-mode display and withdraw-block integration checks passed.\n");
