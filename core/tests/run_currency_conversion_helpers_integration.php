<?php

// Foundation for the real currency-conversion fix: GatewayCurrency min/max and Game bet limits
// are always admin-entered in the site's own default currency (confirmed against the admin
// forms' own labels), but a user's wallet balance is tracked in their own chosen currency. These
// new helpers bridge a site-default amount to/from any wallet currency via Currency.rate_to_usd,
// reusing the same USD-bridge math formatUsdInWalletCurrency() already uses correctly elsewhere.
// This proves: a currency matching the site default is a pure no-op (no rounding drift for the
// common case), a genuinely different currency converts correctly and round-trips, the formatted
// display helper produces the target currency's own symbol (not the site default's), and an
// unusable/disabled target currency falls back to the plain site-default figure instead of
// throwing.

require dirname(__DIR__) . '/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/currency_conversion_helpers_integration.sqlite';
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

(require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php')->up();
Schema::create('general_settings', function (Blueprint $table) {
    $table->id();
    $table->string('cur_text', 10)->default('USD');
    $table->string('cur_sym', 5)->default('$');
    $table->integer('currency_format')->default(3);
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
DB::table('currencies')->insert(['code' => 'USD', 'symbol' => '$', 'rate_to_usd' => '1', 'created_at' => now(), 'updated_at' => now()]);
// A clean, exactly-reversible rate (10 USD / 0.0005 = 20000.00000000 exactly) avoids bcdiv
// truncation noise in the round-trip assertion below.
$ngnId = DB::table('currencies')->insertGetId(['code' => 'NGN', 'symbol' => '₦', 'rate_to_usd' => '0.0005', 'created_at' => now(), 'updated_at' => now()]);
$disabledId = DB::table('currencies')->insertGetId(['code' => 'ZZZ', 'symbol' => 'Z', 'enabled' => false, 'rate_to_usd' => '0.1', 'created_at' => now(), 'updated_at' => now()]);
// The real, live production rate for NGN - deliberately NOT a clean/reversible one (10 / this
// rate does not terminate at 2 decimal places), to reproduce the "more decimal places than this
// currency supports" InvalidArgumentException that convertFromSiteDefault() threw in production.
$messyNgnId = DB::table('currencies')->insertGetId(['code' => 'NGM', 'symbol' => '₦', 'rate_to_usd' => '0.000750966924', 'created_at' => now(), 'updated_at' => now()]);

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('username');
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->timestamps();
});
$ngnUserId = DB::table('users')->insertGetId(['username' => 'naira_user', 'currency_id' => $ngnId, 'created_at' => now(), 'updated_at' => now()]);
$disabledUserId = DB::table('users')->insertGetId(['username' => 'disabled_currency_user', 'currency_id' => $disabledId, 'created_at' => now(), 'updated_at' => now()]);

$passed = 0;
function check(bool $cond, string $message): void
{
    global $passed;
    if (!$cond) throw new RuntimeException("FAILED: $message");
    $passed++;
}

// --- Same currency as site default: pure no-op, no rounding drift. ---
check(convertAmountFromSiteDefault('10', 'USD') === '10', "Converting to the site's own default currency (USD) is a no-op - got '" . convertAmountFromSiteDefault('10', 'USD') . "'.");
check(convertAmountToSiteDefault('10', 'USD') === '10', 'The inverse is also a no-op for the same currency.');
fwrite(STDOUT, "A target currency matching the site default is a pure no-op - no USD-bridge rounding drift for the common case.\n");

// --- A genuinely different currency converts correctly and round-trips exactly (clean rate). ---
check(convertAmountFromSiteDefault('10', 'NGN') === '20000.00000000', "10 USD -> NGN at rate 0.0005 should be exactly 20000.00000000 - got '" . convertAmountFromSiteDefault('10', 'NGN') . "'.");
check(convertAmountToSiteDefault('20000', 'NGN') === '10.00000000', "20000 NGN -> USD should round-trip to exactly 10.00000000 - got '" . convertAmountToSiteDefault('20000', 'NGN') . "'.");
fwrite(STDOUT, "A genuinely different wallet currency converts correctly via the USD bridge and round-trips exactly for a clean rate.\n");

// --- Defaults to the authenticated viewer's own wallet currency when none is given. ---
Auth::setUser(\App\Models\User::find($ngnUserId));
check(convertAmountFromSiteDefault('10') === '20000.00000000', 'With no explicit target, the helper converts into the authenticated viewer\'s own wallet currency.');
Auth::logout();

// --- Formatted display uses the TARGET currency's own symbol, not the site default's. ---
$formatted = convertFromSiteDefault('10', 'NGN');
check(str_contains($formatted, '₦'), "The formatted conversion shows the Naira symbol, not the site default's \$ - got '$formatted'.");
check(!str_contains($formatted, '$'), "The formatted conversion does not show the site default's \$ symbol at all - got '$formatted'.");
fwrite(STDOUT, "The formatted display helper shows the target currency's own symbol, genuinely converted, not the site default's.\n");

// --- A disabled/unusable target currency falls back to the plain site-default figure instead of throwing or dividing by a bad rate. ---
Auth::setUser(\App\Models\User::find($disabledUserId));
check(convertAmountFromSiteDefault('10') === '10', 'A disabled wallet currency falls back to the site-default amount unchanged (no conversion attempted against an unusable currency).');
$fallbackFormatted = convertFromSiteDefault('10');
check(str_contains($fallbackFormatted, '$'), "The formatted fallback for a disabled currency shows the site default's own \$ symbol - got '$fallbackFormatted'.");
Auth::logout();
fwrite(STDOUT, "A disabled/unusable wallet currency degrades to the plain site-default figure instead of converting against a bad rate.\n");

// --- A real-world rate whose bcdiv result doesn't terminate within the target currency's own
// decimals must round to that currency's decimals, not throw - this is the exact production bug
// (Money::toMinor() rejecting an 8-decimal-place conversion result for a 2-decimal currency). ---
$formattedMessy = convertFromSiteDefault('10', 'NGM');
check($formattedMessy === '₦13,316.17', "A non-terminating conversion (10 / 0.000750966924 = 13316.16570638...) rounds to the currency's own 2 decimals instead of throwing - got '$formattedMessy'.");
fwrite(STDOUT, "A conversion result with more decimal places than the target currency supports rounds correctly instead of throwing (regression check for the live Money::toMinor() crash).\n");

fwrite(STDOUT, "Passed: $passed\n");
fwrite(STDOUT, "Currency conversion helpers integration checks passed.\n");
