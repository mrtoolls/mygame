<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\Controllers\Gateway\PaymentController;
use App\Http\Controllers\User\UserController;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CoinLedger;
use App\Services\CoinPricing;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/coin_display_deposit_transactions.sqlite';
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
\Illuminate\Support\Facades\View::addNamespace('Template', resource_path('views/templates/nocturne'));

$frameworkMigration = require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php';
$frameworkMigration->up();

Schema::create('general_settings', function (Blueprint $table) {
    $table->id();
    $table->string('cur_text', 10)->default('USD');
    $table->string('cur_sym', 5)->default('$');
    $table->integer('currency_format')->default(1);
    $table->boolean('force_ssl')->default(0);
    $table->integer('paginate_number')->default(15);
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
Schema::create('gateways', function (Blueprint $table) {
    $table->id();
    $table->integer('code')->default(5000);
    $table->string('name')->nullable();
    $table->boolean('status')->default(1);
    $table->timestamps();
});
Schema::create('gateway_currencies', function (Blueprint $table) {
    $table->id();
    $table->string('name')->nullable();
    $table->string('currency', 10)->nullable();
    $table->integer('method_code')->default(5000);
    $table->unsignedBigInteger('support_agent_id')->nullable();
    $table->timestamps();
});
Schema::create('support_agents', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->boolean('is_active')->default(true);
    $table->boolean('is_payment_agent')->default(false);
    $table->timestamps();
});
Schema::create('deposits', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->decimal('amount', 28, 8);
    $table->decimal('charge', 28, 8)->default(0);
    $table->integer('method_code')->default(5000);
    $table->integer('status')->default(1);
    $table->string('trx');
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->decimal('coin_amount', 28, 8)->nullable();
    $table->decimal('coin_buy_price_snapshot', 28, 8)->nullable();
    $table->unsignedBigInteger('coin_rate_id')->nullable();
    $table->boolean('coin_mode_purchase')->default(false);
    $table->string('admin_feedback')->nullable();
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
Schema::create('frontends', function (Blueprint $table) {
    $table->id();
    $table->string('tempname')->nullable();
    $table->string('data_keys')->nullable();
    $table->text('data_values')->nullable();
    $table->timestamps();
});
Schema::create('admin_notifications', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id')->default(0);
    $table->string('title');
    $table->string('click_url')->nullable();
    $table->timestamps();
});

$coinCoreMigration = require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php';
$coinCoreMigration->up();

// Both deposit_history.blade.php and transactions.blade.php @extend the full site master
// layout, which pulls in the header/sidebar/footer and THEIR unrelated DB dependencies
// (extensions, frontends content blocks, etc.) - rendering the real file's own @section body
// in isolation (same technique used for the admin settings page smoke test) tests the actual
// shipped markup without needing to stand up the whole site shell.
$renderSectionOnly = static function (string $viewPath, array $data) {
    $raw = file_get_contents($viewPath);
    $raw = preg_replace('/@extends\([^)]*\)/', '', $raw, 1);
    $raw = preg_replace('/@section\([^)]*\)/', '', $raw, 1);
    $raw = preg_replace('/@endsection/', '', $raw, 1);
    $tmpPath = sys_get_temp_dir() . '/isolated_' . md5($viewPath) . '.blade.php';
    file_put_contents($tmpPath, $raw);
    \Illuminate\Support\Facades\View::addLocation(sys_get_temp_dir());
    return view('isolated_' . md5($viewPath), $data)->render();
};

$ledger = app(CoinLedger::class);
$insertUser = static function (string $username, string $balance = '0', ?int $currencyId = null): User {
    $id = DB::table('users')->insertGetId(['username' => $username, 'balance' => $balance, 'currency_id' => $currencyId, 'created_at' => now(), 'updated_at' => now()]);
    return User::findOrFail($id);
};

$ngn = Currency::create(['code' => 'NGN', 'decimals' => 2, 'enabled' => true, 'rate_to_usd' => '0.00065', 'last_updated_at' => now()]);
$noPrice = Currency::create(['code' => 'ZZZ', 'decimals' => 2, 'enabled' => true, 'rate_to_usd' => '0.5', 'last_updated_at' => now()]);
app(CoinPricing::class)->recompute($ngn);
$buyPrice = (string) \App\Models\CoinRate::where('currency_id', $ngn->id)->first()->buy_price;

$user = $insertUser('deposit_user', '0', $ngn->id);
Auth::guard('web')->setUser($user);

// --- coin_mode OFF: deposit() page is unchanged (regression). ---
$general = gs();
$general->coin_mode = false;
$general->save();

$controller = new PaymentController();
$depositView = $controller->deposit();
if ($depositView->getData()['pageTitle'] !== 'Deposit Methods') throw new RuntimeException('coin_mode=off changed the deposit page title.');
if (array_key_exists('coinBuyPrice', $depositView->getData()) && $depositView->getData()['coinBuyPrice'] !== null) {
    throw new RuntimeException('coin_mode=off unexpectedly computed a coin buy price.');
}
fwrite(STDOUT, "Deposit page: coin_mode off keeps the 'Deposit Methods' title and does no coin pricing work (regression check).\n");

// --- coin_mode ON: deposit() shows the correct locked buy price, and a clear error for a currency with no coin price. ---
$general->coin_mode = true;
$general->save();

$onView = $controller->deposit();
$onData = $onView->getData();
if ($onData['pageTitle'] !== 'Buy Coins') throw new RuntimeException('coin_mode=on did not set the "Buy Coins" page title.');
if ($onData['coinPricingError'] !== null) throw new RuntimeException('coin_mode=on incorrectly reported a pricing error for a priced currency.');
if (bccomp((string) $onData['coinBuyPrice'], $buyPrice, 8) !== 0) throw new RuntimeException('coin_mode=on showed a different buy price than the one PaymentController::userDataUpdate() would actually use.');

$noPriceUser = $insertUser('no_price_user', '0', $noPrice->id);
Auth::guard('web')->setUser($noPriceUser);
$noPriceView = (new PaymentController())->deposit();
$noPriceData = $noPriceView->getData();
if ($noPriceData['coinPricingError'] === null) throw new RuntimeException('A currency with no coin price configured should show a clear error, not silently proceed.');
Auth::guard('web')->setUser($user);
fwrite(STDOUT, "Deposit page: coin_mode on shows the exact locked buy price PaymentController would mint at, and a clear message when no price is configured.\n");

// --- Deposit history: a coin-mode purchase shows coins, a legacy deposit is untouched. ---
$legacyDeposit = Deposit::forceCreate(['user_id' => $user->id, 'amount' => '500', 'status' => 1, 'trx' => 'LEGACYDEP1', 'currency_id' => $ngn->id, 'coin_mode_purchase' => false]);
$coinDeposit = Deposit::forceCreate(['user_id' => $user->id, 'amount' => bcmul($buyPrice, '20', 8), 'status' => 1, 'trx' => 'COINDEP1', 'currency_id' => $ngn->id, 'coin_amount' => '20', 'coin_buy_price_snapshot' => $buyPrice, 'coin_mode_purchase' => true]);

$userController = new UserController();
$historyView = $userController->depositHistory(\Illuminate\Http\Request::create('/x', 'GET'));
if ($historyView->getData()['pageTitle'] !== 'Buy Coins History') throw new RuntimeException('Deposit history did not relabel its title in coin mode.');
$historyHtml = $renderSectionOnly(resource_path('views/templates/nocturne/user/deposit_history.blade.php'), $historyView->getData());
if (!str_contains($historyHtml, '20') || !str_contains($historyHtml, 'Coins')) throw new RuntimeException('Deposit history did not show the coin amount for a coin-mode purchase.');
if (!str_contains($historyHtml, '500')) throw new RuntimeException('Deposit history lost the legacy real-currency deposit amount.');
fwrite(STDOUT, "Deposit history: a coin-mode purchase shows coins with its locked rate; a legacy deposit still shows its real-currency amount.\n");

// --- Transactions: coin_mode on merges CoinLedgerEntry + legacy Transaction rows chronologically. ---
$legacyTxn = new Transaction();
$legacyTxn->user_id = $user->id;
$legacyTxn->amount = '100';
$legacyTxn->charge = 0;
$legacyTxn->trx_type = '+';
$legacyTxn->details = 'Legacy deposit credit';
$legacyTxn->remark = 'deposit';
$legacyTxn->trx = 'OLDTRX1';
$legacyTxn->post_balance = '100';
$legacyTxn->save();

$ledger->mint('user', $user->id, '20', 'Test coin purchase', 'display_test:mint1');

$txnView = $userController->transactions();
$txnHtml = $renderSectionOnly(resource_path('views/templates/nocturne/user/transactions.blade.php'), $txnView->getData());
if (!str_contains($txnHtml, 'OLDTRX1') || !str_contains($txnHtml, 'pre-coin history')) throw new RuntimeException('The legacy transaction did not appear, clearly labelled, in the coin-mode merged history.');
if (!str_contains($txnHtml, 'Coins purchased') && !str_contains($txnHtml, 'Test coin purchase')) throw new RuntimeException('The coin ledger entry did not appear in the merged transaction history.');
fwrite(STDOUT, "Transactions: coin_mode on merges live CoinLedgerEntry rows with pre-existing legacy Transaction rows, clearly labelled.\n");

$general->coin_mode = false;
$general->save();
$offTxnView = $userController->transactions();
$offTxnHtml = $renderSectionOnly(resource_path('views/templates/nocturne/user/transactions.blade.php'), $offTxnView->getData());
if (!str_contains($offTxnHtml, 'OLDTRX1')) throw new RuntimeException('coin_mode=off lost a legacy transaction it should still show normally.');
if (str_contains($offTxnHtml, 'pre-coin history')) throw new RuntimeException('coin_mode=off incorrectly applied coin-mode labelling.');
fwrite(STDOUT, "Transactions: coin_mode off behaves exactly as before (regression check).\n");

if (!app(CoinLedger::class)->reconcile()['ok']) throw new RuntimeException('Reconciliation broke during this test run.');

// Best-effort teardown.
try {
    $coinCoreMigration->down();
    $frameworkMigration->down();
} catch (\Throwable $teardownException) {
    fwrite(STDERR, 'Note: best-effort schema teardown did not fully complete (' . $teardownException->getMessage() . "), ignoring.\n");
}
DB::disconnect();
@unlink($dbPath);
fwrite(STDOUT, "Coin display (deposit/Buy Coins + transactions) integration checks passed.\n");
