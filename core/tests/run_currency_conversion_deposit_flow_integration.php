<?php

// A deposit method's min_amount/max_amount/fixed_charge are always admin-entered in the site's
// default currency (confirmed against the admin forms that set them), but a user's wallet
// balance is tracked in their own chosen currency. This proves the full real-money fix end to
// end: the deposit page shows a genuinely converted minimum/maximum to a Naira-wallet viewer
// (not the raw site-default figure), a deposit submitted in that converted Naira amount is
// correctly accepted/rejected against the converted threshold, the stored Deposit.amount stays
// in site-default terms (so Deposit History keeps showing what was actually configured, not a
// relabeled figure), and - the most severe half of the original bug - the amount actually
// credited to the user's balance is the correct Naira-equivalent, not the raw site-default
// number silently added to a Naira balance.

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\Controllers\Gateway\PaymentController;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

$dbPath = __DIR__ . '/currency_conversion_deposit_flow_integration.sqlite';
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
View::addNamespace('Template', resource_path('views/templates/nocturne'));

(require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php')->up();
Schema::create('general_settings', function (Blueprint $table) {
    $table->id();
    $table->string('cur_text', 10)->default('USD');
    $table->string('cur_sym', 5)->default('$');
    $table->integer('currency_format')->default(3);
    $table->boolean('coin_mode')->default(false);
    $table->integer('dc')->default(0);
    $table->timestamps();
});
DB::table('general_settings')->insert(['id' => 1]);

Schema::create('currencies', function (Blueprint $table) {
    $table->id();
    $table->string('code', 10);
    $table->integer('decimals')->default(2);
    $table->boolean('enabled')->default(true);
    $table->string('symbol', 8)->default('$');
    $table->string('symbol_position', 10)->default('left');
    $table->decimal('rate_to_usd', 24, 12)->nullable();
    $table->timestamp('last_updated_at')->nullable();
    $table->timestamps();
});
Currency::create(['code' => 'USD', 'decimals' => 2, 'enabled' => true, 'symbol' => '$', 'rate_to_usd' => '1']);
// Clean, exactly-reversible rate: $10 / 0.0005 = 20000.00000000 exactly.
$ngn = Currency::create(['code' => 'NGN', 'decimals' => 2, 'enabled' => true, 'symbol' => '₦', 'rate_to_usd' => '0.0005']);

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('username');
    $table->decimal('balance', 28, 8)->default(0);
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->unsignedBigInteger('country_id')->nullable();
    $table->tinyInteger('status')->default(1);
    $table->timestamps();
});
Schema::create('gateways', function (Blueprint $table) {
    $table->id();
    $table->integer('code')->default(5000);
    $table->string('name')->nullable();
    $table->string('alias')->nullable();
    $table->boolean('crypto')->default(false);
    $table->boolean('status')->default(1);
    $table->timestamps();
});
Schema::create('gateway_currencies', function (Blueprint $table) {
    $table->id();
    $table->string('name')->nullable();
    $table->string('currency', 10)->nullable();
    $table->integer('method_code')->default(5000);
    $table->unsignedBigInteger('support_agent_id')->nullable();
    $table->decimal('min_amount', 20, 8)->default(1);
    $table->decimal('max_amount', 20, 8)->default(1000000);
    $table->decimal('fixed_charge', 20, 8)->default(0);
    $table->decimal('percent_charge', 8, 4)->default(0);
    $table->decimal('rate', 20, 8)->default(1);
    $table->string('symbol', 8)->default('$');
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
    $table->integer('status')->default(0); // PAYMENT_INITIATE - a freshly-submitted deposit, awaiting confirmation.
    $table->string('trx');
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->unsignedBigInteger('support_agent_id')->nullable();
    $table->json('payment_agent_snapshot')->nullable();
    $table->string('method_currency', 10)->nullable();
    $table->decimal('rate', 20, 8)->default(1);
    $table->decimal('final_amount', 20, 8)->default(0);
    $table->decimal('btc_amount', 20, 8)->default(0);
    $table->string('btc_wallet')->nullable();
    $table->text('success_url')->nullable();
    $table->text('failed_url')->nullable();
    $table->decimal('coin_amount', 28, 8)->nullable();
    $table->decimal('coin_buy_price_snapshot', 28, 8)->nullable();
    $table->unsignedBigInteger('coin_rate_id')->nullable();
    $table->boolean('coin_mode_purchase')->default(false);
    $table->string('admin_feedback')->nullable();
    $table->string('confirmed_via')->nullable();
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
Schema::create('notification_logs', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id')->nullable();
    $table->string('subject')->nullable();
    $table->text('message')->nullable();
    $table->timestamps();
});

$passed = 0;
function check(bool $cond, string $message): void
{
    global $passed;
    if (!$cond) throw new RuntimeException("FAILED: $message");
    $passed++;
}

// A manual (non-crypto) gateway, configured in USD - admin typed "10" / "500" meaning USD,
// matching the site default, exactly as the admin form's own "{{ gs('cur_text') }}" label says.
DB::table('gateways')->insert(['code' => 1001, 'name' => 'Manual USD Agent', 'alias' => 'ManualUsd', 'crypto' => false, 'status' => true, 'created_at' => now(), 'updated_at' => now()]);
DB::table('gateway_currencies')->insert([
    'name' => 'Manual USD Agent', 'currency' => 'USD', 'method_code' => 1001,
    'min_amount' => '10', 'max_amount' => '500', 'fixed_charge' => '0.5', 'percent_charge' => '0', 'rate' => '1', 'symbol' => '$',
    'created_at' => now(), 'updated_at' => now(),
]);

$ngnUserId = DB::table('users')->insertGetId(['username' => 'naira_depositor', 'balance' => '0', 'currency_id' => $ngn->id, 'created_at' => now(), 'updated_at' => now()]);
Auth::guard('web')->setUser(User::find($ngnUserId));

$controller = new PaymentController();

// --- Display: the deposit page shows the minimum genuinely converted into Naira, not the raw
// site-default figure. ---
$depositView = $controller->deposit();
$gateway = $depositView->getData()['gatewayCurrency']->firstWhere('method_code', 1001);
check($gateway !== null, 'The manual USD gateway is shown to the Naira-wallet viewer (manual gateways are not currency-filtered by design).');
check(bccomp((string) $gateway->min_amount, '10', 6) === 0, "The underlying model attribute stays the raw site-default figure (10) - the conversion happens at display time, not in the data - got '{$gateway->min_amount}'.");

$viewPath = resource_path('views/templates/nocturne/user/payment/deposit.blade.php');
$raw = file_get_contents($viewPath);
$raw = preg_replace('/@extends\([^)]*\)/', '', $raw, 1);
$raw = preg_replace('/@section\([^)]*\)/', '', $raw, 1);
$raw = preg_replace('/@endsection/', '', $raw, 1);
$tmpPath = sys_get_temp_dir() . '/isolated_' . md5($viewPath) . '.blade.php';
file_put_contents($tmpPath, $raw);
View::addLocation(sys_get_temp_dir());
$html = view('isolated_' . md5($viewPath), $depositView->getData())->render();
check(str_contains($html, 'data-min-amount="₦20,000.00"'), 'The rendered deposit page shows the minimum genuinely converted to Naira ($10 -> ₦20,000.00 at this rate), not "$10" or a wrongly-relabeled "₦10" - rendered data-min-amount: ' . (preg_match('/data-min-amount="[^"]*"/', $html, $m) ? $m[0] : 'NOT FOUND'));
check(str_contains($html, 'data-min-threshold="20000.00000000"'), 'The raw numeric threshold used for client-side validation is also the converted Naira figure.');
fwrite(STDOUT, "The deposit page shows the minimum/maximum genuinely converted into the viewer's own wallet currency, not the raw site-default figure or a wrongly-relabeled one.\n");

// --- Submitting the converted Naira minimum is accepted; below it is rejected. ---
$belowMin = Request::create('/user/deposit/insert', 'POST', ['amount' => '19000', 'gateway' => '1001', 'currency' => 'USD']);
$belowMin->headers->set('Accept', 'application/json');
$belowMinResponse = $controller->depositInsert($belowMin);
check($belowMinResponse->getStatusCode() === 422, 'A Naira amount below the converted minimum (19,000 < 20,000) is correctly rejected.');

$atMin = Request::create('/user/deposit/insert', 'POST', ['amount' => '20000', 'gateway' => '1001', 'currency' => 'USD']);
$atMin->headers->set('Accept', 'application/json');
$atMinResponse = $controller->depositInsert($atMin);
check($atMinResponse->getStatusCode() === 200 && $atMinResponse->getData(true)['ok'], 'A Naira amount exactly at the converted minimum (20,000) is accepted.');
fwrite(STDOUT, "Deposit validation correctly compares the submitted Naira amount against the converted Naira-equivalent minimum.\n");

// --- The stored Deposit.amount stays in site-default (USD) terms - not the raw Naira figure
// the user typed - so Deposit History keeps showing what was actually configured. ---
$deposit = Deposit::where('user_id', $ngnUserId)->latest('id')->first();
check(bccomp((string) $deposit->amount, '10', 6) === 0, "Deposit.amount is stored in site-default (USD) terms (10), not the raw submitted Naira figure (20000) - got '{$deposit->amount}'.");
fwrite(STDOUT, "Deposit.amount stays in site-default terms regardless of what currency the user actually typed in, preserving History's existing meaning.\n");

// --- Crediting: userDataUpdate() credits the correct Naira-equivalent to balance, not the raw
// site-default number - this is the severe half of the original bug. ---
PaymentController::userDataUpdate($deposit, true, 'admin');
$creditedUser = User::find($ngnUserId);
check(bccomp((string) $creditedUser->balance, '20000', 6) === 0, "The user's Naira balance increases by the correctly converted amount (20000), not the raw site-default figure (10) - got '{$creditedUser->balance}'.");
fwrite(STDOUT, "The deposit credits the correct wallet-currency-converted amount to balance - the actual money-correctness bug is fixed, not just the display.\n");

// --- History still shows the historical, site-default amount after the fact, unconverted -
// a $10 deposit should still read as "$10.00", not be relabeled with the viewer's own symbol. ---
$historyViewPath = resource_path('views/templates/nocturne/user/deposit_history.blade.php');
$historyRaw = file_get_contents($historyViewPath);
$historyRaw = preg_replace('/@extends\([^)]*\)/', '', $historyRaw, 1);
$historyRaw = preg_replace('/@section\([^)]*\)/', '', $historyRaw, 1);
$historyRaw = preg_replace('/@endsection/', '', $historyRaw, 1);
$historyTmpPath = sys_get_temp_dir() . '/isolated_' . md5($historyViewPath) . '.blade.php';
file_put_contents($historyTmpPath, $historyRaw);
$deposits = new \Illuminate\Pagination\LengthAwarePaginator(Deposit::where('user_id', $ngnUserId)->get(), 1, 10);
$txnStatusMeta = fn ($status) => ['cls' => 'success', 'icon' => 'ph-check', 'label' => 'Successful'];
$historyHtml = view('isolated_' . md5($historyViewPath), ['deposits' => $deposits, 'pageTitle' => 'History', 'txnStatusMeta' => $txnStatusMeta])->render();
check(str_contains($historyHtml, '$10.00'), "Deposit History shows the historical amount in its real, site-default terms (\$10.00) even though the viewer's wallet is Naira - not a relabeled '₦10.00' or the raw Naira amount actually submitted.");
check(!str_contains($historyHtml, '₦10.00') && !str_contains($historyHtml, '₦20,000'), 'Deposit History never shows the Naira symbol on this historical record.');
fwrite(STDOUT, "Deposit History shows the historical, site-default amount as it actually was, not relabeled or re-converted for the current viewer.\n");

fwrite(STDOUT, "Passed: $passed\n");
fwrite(STDOUT, "Currency conversion deposit flow integration checks passed.\n");
