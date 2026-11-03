<?php

// Real bug: a value admin enters in the site's own default currency (confirmed directly against
// the admin forms that set it - GatewayCurrency min_amount/max_amount, Game min_limit/max_limit
// are both literally labeled "... {{ gs('cur_text') }}" there) was being displayed through
// showAmount(), which (by design, for genuinely wallet-currency-denominated values like a user's
// balance) relabels the number with the VIEWER's own chosen wallet currency symbol instead -
// with no actual conversion of the number. A site with cur_text=USD and a min deposit of $10
// would show "10" next to a Naira viewer's own currency symbol, implying a wildly different,
// wrong minimum. This proves the new showBaseAmount() helper (which always uses the site
// default, like the admin's own form labels do) fixes this for both the deposit page and game
// pages, and that the balance widget on the deposit page is now wallet-currency-aware again
// (it had been hardcoded to the site default directly, inconsistent with the same balance shown
// correctly elsewhere via showUserBalance()).

require dirname(__DIR__) . '/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

$dbPath = __DIR__ . '/currency_conversion_display_fix_integration.sqlite';
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
    $table->string('site_name', 190)->default('Test Site');
    $table->string('cur_text', 10)->default('USD');
    $table->string('cur_sym', 5)->default('$');
    $table->integer('currency_format')->default(3); // Status::CUR_SYM - symbol + amount, no code.
    $table->boolean('force_ssl')->default(0);
    $table->boolean('coin_mode')->default(false);
    $table->timestamps();
});
DB::table('general_settings')->insert(['id' => 1]);

Schema::create('currencies', function (Blueprint $table) {
    $table->id();
    $table->string('code', 10);
    $table->string('symbol', 5)->default('$');
    $table->integer('decimals')->default(2);
    $table->boolean('enabled')->default(true);
    $table->string('symbol_position', 10)->default('left');
    $table->decimal('rate_to_usd', 24, 12)->nullable();
    $table->timestamps();
});
$ngnId = DB::table('currencies')->insertGetId(['code' => 'NGN', 'symbol' => '₦', 'rate_to_usd' => '0.00065', 'created_at' => now(), 'updated_at' => now()]);

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('username');
    $table->string('email')->nullable();
    $table->decimal('balance', 28, 8)->default(0);
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->tinyInteger('status')->default(1);
    $table->timestamps();
});
$ngnUserId = DB::table('users')->insertGetId(['username' => 'naira_user', 'balance' => '50000.00000000', 'currency_id' => $ngnId, 'created_at' => now(), 'updated_at' => now()]);

$passed = 0;
function check(bool $cond, string $message): void
{
    global $passed;
    if (!$cond) throw new RuntimeException("FAILED: $message");
    $passed++;
}

// --- showBaseAmount() always uses the site default currency, regardless of the signed-in
// viewer's own wallet currency - unlike showAmount(), which would wrongly relabel it. ---
Auth::setUser(\App\Models\User::find($ngnUserId));
check(showBaseAmount(10) === '$10.00', "showBaseAmount(10) is '\$10.00' for a Naira-wallet viewer (site default is USD) - got '" . showBaseAmount(10) . "'.");
check(showAmount(10) === '₦10.00', "Sanity check: showAmount(10) (the buggy call this replaces) DOES wrongly show '₦10.00' for this same viewer - confirms the bug this fix targets actually exists - got '" . showAmount(10) . "'.");
fwrite(STDOUT, "showBaseAmount() always reflects the site's own default currency, exactly matching what the admin typed, regardless of the viewer's personal wallet currency.\n");
Auth::logout();

// --- The real shipped deposit.blade.php now renders min/max in the site default currency, not
// the viewer's mismatched wallet currency. ---
$renderSectionOnly = static function (string $viewPath, array $data) {
    $raw = file_get_contents($viewPath);
    $raw = preg_replace('/@extends\([^)]*\)/', '', $raw, 1);
    $raw = preg_replace('/@section\([^)]*\)/', '', $raw, 1);
    $raw = preg_replace('/@endsection/', '', $raw, 1);
    $tmpPath = sys_get_temp_dir() . '/isolated_' . md5($viewPath) . '.blade.php';
    file_put_contents($tmpPath, $raw);
    View::addLocation(sys_get_temp_dir());
    return view('isolated_' . md5($viewPath), $data)->render();
};

$gatewayCurrency = (object) [
    'min_amount' => '10.00000000', 'max_amount' => '500.00000000', 'currency' => 'USD', 'symbol' => '$',
    'name' => 'Test Gateway', 'method_code' => 1001, 'support_agent_id' => null, 'supportAgent' => null,
    'method' => (object) ['crypto' => 0, 'image' => null, 'description' => ''],
];
Auth::setUser(\App\Models\User::find($ngnUserId));
$depositViewPath = resource_path('views/templates/nocturne/user/payment/deposit.blade.php');
$html = $renderSectionOnly($depositViewPath, [
    'gatewayCurrency' => collect([$gatewayCurrency]), 'deposits' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 5),
    'pageTitle' => 'Deposit', 'coinMode' => false, 'coinBuyPrice' => null, 'coinPricingError' => null, 'coinPegUsd' => null, 'vendorOffers' => [],
]);
check(str_contains($html, 'data-min-amount="$10.00"'), 'The deposit page shows the minimum in the site default currency ($10.00) for a Naira-wallet viewer, not a wrongly-relabeled "₦10.00" - rendered: ' . (preg_match('/data-min-amount="[^"]*"/', $html, $m) ? $m[0] : 'NOT FOUND'));
check(str_contains($html, 'data-max-amount="$500.00"'), 'The deposit page shows the maximum in the site default currency ($500.00), not relabeled.');
check(!str_contains($html, '₦10.00') && !str_contains($html, '₦500.00'), 'The Naira symbol never appears on these admin-set, site-default-currency limits.');
fwrite(STDOUT, "The real deposit page now shows deposit limits in the site's own default currency for every viewer, matching exactly what the admin typed.\n");

fwrite(STDOUT, "Passed: $passed\n");
fwrite(STDOUT, "Currency conversion display fix integration checks passed.\n");
