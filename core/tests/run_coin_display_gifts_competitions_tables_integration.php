<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/coin_display_gifts_competitions_tables_integration.sqlite';
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
    $table->timestamps();
});
DB::table('general_settings')->insert(['id' => 1]);
$coinSettingsMigration = require dirname(__DIR__) . '/database/migrations/2026_10_24_000011_add_coin_settings_to_general_settings.php';
$coinSettingsMigration->up();

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

/**
 * Pulls the exact, currently-published line out of a real theme blade file by a unique
 * substring, then compiles/renders JUST that line through Blade - this proves the real file
 * on disk behaves correctly for both coin_mode states, without needing to stand up the full
 * page layout (header/sidebar/auth/session partials) that @extends('Template::layouts.master')
 * would otherwise pull in.
 */
function extractBladeLine(string $file, string $needle): string {
    $lines = file($file);
    if ($lines === false) throw new RuntimeException("Could not read {$file}");
    foreach ($lines as $line) {
        if (str_contains($line, $needle)) return trim($line);
    }
    throw new RuntimeException("Could not find a line containing '{$needle}' in {$file}");
}

$giftsIndexFile = resource_path('views/templates/nocturne/user/gifts/index.blade.php');
$tableShowFile = resource_path('views/templates/nocturne/user/table_games/show.blade.php');
$compIndexFile = resource_path('views/templates/nocturne/user/competitions/index.blade.php');
$compShowFile = resource_path('views/templates/nocturne/user/competitions/show.blade.php');

$general = gs();
$general->coin_mode = false;
$general->save();

// =====================================================================================
// Gift shop: buy price (shop grid + in-game gift drawer) and cashout value (inventory).
// =====================================================================================

$cashoutLine = extractBladeLine($giftsIndexFile, 'formatCoinOrUsd($item->gift->cashout_value)');
$buyPriceLineShop = extractBladeLine($giftsIndexFile, 'formatCoinOrUsd($gift->buy_price)');
$buyPriceLineDrawer = extractBladeLine($tableShowFile, 'formatCoinOrUsd($gift->buy_price)');

$item = (object) ['gift' => (object) ['cashout_value' => '12.50']];
$gift = (object) ['buy_price' => '5.00'];

$cashoutOff = Blade::render($cashoutLine, ['item' => $item]);
if (!str_contains($cashoutOff, '$12.50')) throw new RuntimeException("Gift cashout display (coin_mode off) did not show the raw USD value: {$cashoutOff}");
if (str_contains($cashoutOff, 'Coins')) throw new RuntimeException('Gift cashout display showed Coins while coin_mode is off.');

$buyShopOff = Blade::render($buyPriceLineShop, ['gift' => $gift]);
if (!str_contains($buyShopOff, '$5.00') && !str_contains($buyShopOff, '$5')) throw new RuntimeException("Gift shop buy-price display (coin_mode off) did not show the raw USD value: {$buyShopOff}");
if (str_contains($buyShopOff, 'Coins')) throw new RuntimeException('Gift shop buy-price display showed Coins while coin_mode is off.');

$buyDrawerOff = Blade::render($buyPriceLineDrawer, ['gift' => $gift]);
if (!str_contains($buyDrawerOff, '$5.00') && !str_contains($buyDrawerOff, '$5')) throw new RuntimeException("In-game gift drawer buy-price display (coin_mode off) did not show the raw USD value: {$buyDrawerOff}");
if (str_contains($buyDrawerOff, 'Coins')) throw new RuntimeException('In-game gift drawer buy-price display showed Coins while coin_mode is off.');
fwrite(STDOUT, "Gift shop/drawer/inventory display: coin_mode off shows the unchanged literal \$USD markup (regression check).\n");

$general = gs();
$general->coin_mode = true;
$general->coin_peg_usd = '0.01';
$general->save();

$cashoutOn = Blade::render($cashoutLine, ['item' => $item]);
if (!str_contains($cashoutOn, '1,250') || !str_contains($cashoutOn, 'Coins')) throw new RuntimeException("Gift cashout display (coin_mode on) did not show the correct coin amount: {$cashoutOn}");
if (str_contains($cashoutOn, '$')) throw new RuntimeException('Gift cashout display showed a $ sign while coin_mode is on.');

$buyShopOn = Blade::render($buyPriceLineShop, ['gift' => $gift]);
if (!str_contains($buyShopOn, '500') || !str_contains($buyShopOn, 'Coins')) throw new RuntimeException("Gift shop buy-price display (coin_mode on) did not show the correct coin amount: {$buyShopOn}");
if (str_contains($buyShopOn, '$')) throw new RuntimeException('Gift shop buy-price display showed a $ sign while coin_mode is on.');

$buyDrawerOn = Blade::render($buyPriceLineDrawer, ['gift' => $gift]);
if (!str_contains($buyDrawerOn, '500') || !str_contains($buyDrawerOn, 'Coins')) throw new RuntimeException("In-game gift drawer buy-price display (coin_mode on) did not show the correct coin amount: {$buyDrawerOn}");
if (str_contains($buyDrawerOn, '$')) throw new RuntimeException('In-game gift drawer buy-price display showed a $ sign while coin_mode is on.');
fwrite(STDOUT, "Gift shop/drawer/inventory display: coin_mode on converts the USD price/cashout value through the coin peg and shows Coins, with no \$ sign.\n");

// =====================================================================================
// Competitions: hero prize-pool total, per-card prize pool, per-rank prize, and "your result".
// =====================================================================================

$heroPoolLine = extractBladeLine($compIndexFile, 'formatCoinOrUsd($allPool)');
$cardPoolLine = extractBladeLine($compIndexFile, 'formatCoinOrUsd($prizePool)');
$prizeRowLine = extractBladeLine($compShowFile, 'formatCoinOrUsd($prize->amount_usd)');
$myResultLine = extractBladeLine($compShowFile, 'formatCoinOrUsd($myResult->prize_amount_usd)');

$medals = ['🥇', '🥈', '🥉'];
$prize = (object) ['rank' => 1, 'amount_usd' => '100.00'];
$myResult = (object) ['prize_amount_usd' => '25.00'];

$general = gs();
$general->coin_mode = false;
$general->save();

$heroOff = Blade::render($heroPoolLine, ['allPool' => 300.0]);
if (!str_contains($heroOff, '$300')) throw new RuntimeException("Competition hero prize-pool display (coin_mode off) did not show the raw USD value: {$heroOff}");
if (str_contains($heroOff, 'Coins')) throw new RuntimeException('Competition hero prize-pool display showed Coins while coin_mode is off.');

$cardOff = Blade::render($cardPoolLine, ['prizePool' => 150.0]);
if (!str_contains($cardOff, '$150')) throw new RuntimeException("Competition card prize-pool display (coin_mode off) did not show the raw USD value: {$cardOff}");

$prizeRowOff = Blade::render($prizeRowLine, ['prize' => $prize, 'medals' => $medals]);
if (!str_contains($prizeRowOff, '$100.00')) throw new RuntimeException("Competition prize-row display (coin_mode off) did not show the raw USD value: {$prizeRowOff}");

$myResultOff = Blade::render($myResultLine, ['myResult' => $myResult]);
if (!str_contains($myResultOff, '$25.00')) throw new RuntimeException("Competition 'your result' prize display (coin_mode off) did not show the raw USD value: {$myResultOff}");
fwrite(STDOUT, "Competition prize display: coin_mode off shows the unchanged literal \$USD markup (regression check).\n");

$general = gs();
$general->coin_mode = true;
$general->coin_peg_usd = '0.01';
$general->save();

$heroOn = Blade::render($heroPoolLine, ['allPool' => 300.0]);
if (!str_contains($heroOn, '30,000') || !str_contains($heroOn, 'Coins')) throw new RuntimeException("Competition hero prize-pool display (coin_mode on) did not show the correct coin amount: {$heroOn}");
if (str_contains($heroOn, '$')) throw new RuntimeException('Competition hero prize-pool display showed a $ sign while coin_mode is on.');

$cardOn = Blade::render($cardPoolLine, ['prizePool' => 150.0]);
if (!str_contains($cardOn, '15,000') || !str_contains($cardOn, 'Coins')) throw new RuntimeException("Competition card prize-pool display (coin_mode on) did not show the correct coin amount: {$cardOn}");
if (str_contains($cardOn, '$')) throw new RuntimeException('Competition card prize-pool display showed a $ sign while coin_mode is on.');

$prizeRowOn = Blade::render($prizeRowLine, ['prize' => $prize, 'medals' => $medals]);
if (!str_contains($prizeRowOn, '10,000') || !str_contains($prizeRowOn, 'Coins')) throw new RuntimeException("Competition prize-row display (coin_mode on) did not show the correct coin amount: {$prizeRowOn}");
if (str_contains($prizeRowOn, '$')) throw new RuntimeException('Competition prize-row display showed a $ sign while coin_mode is on.');

$myResultOn = Blade::render($myResultLine, ['myResult' => $myResult]);
if (!str_contains($myResultOn, '2,500') || !str_contains($myResultOn, 'Coins')) throw new RuntimeException("Competition 'your result' prize display (coin_mode on) did not show the correct coin amount: {$myResultOn}");
if (str_contains($myResultOn, '$')) throw new RuntimeException('Competition \'your result\' prize display showed a $ sign while coin_mode is on.');
fwrite(STDOUT, "Competition prize display: coin_mode on converts every USD prize amount through the coin peg and shows Coins, with no \$ sign.\n");

// =====================================================================================
// Table games: stakes are NOT backend coin-aware (see TableGameService - no gs('coin_mode')
// branch anywhere, stakes still move through the legacy $user->balance + wallet-currency FX
// path regardless of the flag). Per the task's own instruction, that means the display layer
// must NOT be changed to show "Coins" for stakes - doing so would show a unit the backend
// never actually moves. This is a regression guard: it proves (a) that fact still holds (so a
// future change to TableGameService doesn't silently invalidate this decision) and (b) that
// the real stake-formatting call site used by the lobby/live-table views - Currency::
// formatMinor(), via TableGameService::liveTables()'s wallet_stake_formatted - renders
// byte-for-byte identically whether coin_mode is on or off, exactly as coin_mode=off things
// are supposed to behave everywhere.
// =====================================================================================

$tableServiceSource = file_get_contents(dirname(__DIR__) . '/app/Games/TableGames/TableGameService.php');
if ($tableServiceSource === false) throw new RuntimeException('Could not read TableGameService.php for the coin-mode-awareness check.');
if (str_contains($tableServiceSource, "gs('coin_mode')") || str_contains($tableServiceSource, 'CoinLedger')) {
    throw new RuntimeException('TableGameService now references coin_mode/CoinLedger - table stake display should be revisited to show coins (see formatVipThreshold()/coinAmountText() pattern), not left on the legacy USD/FX path.');
}

$usd = \App\Models\Currency::create(['code' => 'USD', 'decimals' => 2, 'enabled' => true, 'symbol' => '$', 'symbol_position' => 'left', 'rate_to_usd' => '1', 'last_updated_at' => now()]);
$money = app(\App\Support\Money::class);
$stakeMinor = $money->toMinor('25.00', (int) $usd->decimals);

$general = gs();
$general->coin_mode = false;
$general->save();
$stakeDisplayOff = $usd->formatMinor($stakeMinor, true);

$general = gs();
$general->coin_mode = true;
$general->save();
$stakeDisplayOn = $usd->formatMinor($stakeMinor, true);

if ($stakeDisplayOff !== $stakeDisplayOn) throw new RuntimeException("Table stake display changed between coin_mode off ({$stakeDisplayOff}) and on ({$stakeDisplayOn}) - it should be completely unaffected since TableGameService has no coin-mode logic to back a coin display with.");
if (!str_contains($stakeDisplayOff, '$25.00')) throw new RuntimeException("Table stake display did not render the expected USD amount: {$stakeDisplayOff}");
if (str_contains($stakeDisplayOff, 'Coins')) throw new RuntimeException('Table stake display unexpectedly showed Coins - this would be incorrect since table stakes are not yet coin-aware on the backend.');
fwrite(STDOUT, "Table game stakes: confirmed TableGameService has no coin_mode/CoinLedger awareness, and its stake display is identical regardless of coin_mode (correctly left untouched).\n");

$general = gs();
$general->coin_mode = false;
$general->save();

// Best-effort teardown.
try {
    $coinSettingsMigration->down();
    $frameworkMigration->down();
} catch (\Throwable $teardownException) {
    fwrite(STDERR, 'Note: best-effort schema teardown did not fully complete (' . $teardownException->getMessage() . "), ignoring.\n");
}
DB::disconnect();
@unlink($dbPath);
fwrite(STDOUT, "Coin display (gifts/competitions/table games) integration checks passed.\n");
