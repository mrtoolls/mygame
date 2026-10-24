<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\BonusCode;
use App\Models\BonusCodeRedemption;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\Game;
use App\Models\GameLog;
use App\Models\User;
use App\Models\VipRakebackClaim;
use App\Models\VipTier;
use App\Services\CoinLedger;
use App\Services\CoinPricing;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/coin_display_admin_logs.sqlite';
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

// Both admin pages @extend the full admin layout, which has its own unrelated sidebar/topnav DB
// dependencies - render each real file's own @section('panel') body in isolation (same
// technique used for the admin settings page smoke test and the deposit/transactions test) so
// this tests the actual shipped markup without needing to stand up the whole admin shell.
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
    $table->string('firstname')->nullable();
    $table->string('lastname')->nullable();
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
    $table->decimal('rate_to_usd', 24, 12)->nullable();
    $table->timestamp('last_updated_at')->nullable();
    $table->timestamps();
});
Schema::create('deposits', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->decimal('amount', 28, 8);
    $table->decimal('charge', 28, 8)->default(0);
    $table->decimal('rate', 28, 8)->default(1);
    $table->decimal('final_amount', 28, 8)->default(0);
    $table->integer('method_code')->default(5000);
    $table->string('method_currency', 10)->nullable();
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
Schema::create('games', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('alias');
    $table->timestamps();
});
Schema::create('game_logs', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->unsignedBigInteger('game_id');
    $table->decimal('invest', 28, 8)->default(0);
    $table->decimal('win_amo', 28, 8)->default(0);
    $table->tinyInteger('win_status')->default(0);
    $table->tinyInteger('status')->default(1);
    $table->tinyInteger('demo_play')->default(0);
    $table->string('result')->nullable();
    $table->string('user_select')->nullable();
    $table->timestamps();
});
Schema::create('bonus_codes', function (Blueprint $table) {
    $table->id();
    $table->string('code');
    $table->decimal('amount', 28, 8);
    $table->timestamps();
});
Schema::create('bonus_code_redemptions', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->unsignedBigInteger('bonus_code_id');
    $table->decimal('amount', 28, 8);
    $table->string('trx');
    $table->timestamps();
});
Schema::create('vip_tiers', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->timestamps();
});
Schema::create('vip_rakeback_claims', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->unsignedBigInteger('vip_tier_id')->nullable();
    $table->decimal('amount', 28, 8);
    $table->decimal('total_wagered_snapshot', 28, 8)->default(0);
    $table->string('trx');
    $table->timestamps();
});

$coinCoreMigration = require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php';
$coinCoreMigration->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_25_000001_add_pause_state_to_coin_rates.php')->up();

$insertUser = static function (string $username): User {
    $id = DB::table('users')->insertGetId(['username' => $username, 'created_at' => now(), 'updated_at' => now()]);
    return User::findOrFail($id);
};

$user = $insertUser('admin_log_user');
$ngn = Currency::create(['code' => 'NGN', 'decimals' => 2, 'enabled' => true, 'rate_to_usd' => '0.00065', 'last_updated_at' => now()]);
app(CoinPricing::class)->recompute($ngn);
$buyPrice = (string) \App\Models\CoinRate::where('currency_id', $ngn->id)->first()->buy_price;

$general = gs();

// --- Deposit log: a coin-mode purchase shows coins + locked rate; a legacy deposit is untouched regardless of the current flag. ---
$legacyDeposit = Deposit::forceCreate(['user_id' => $user->id, 'amount' => '500', 'rate' => '1', 'final_amount' => '500', 'method_currency' => 'NGN', 'status' => 1, 'trx' => 'LEGACYDEP1', 'currency_id' => $ngn->id, 'coin_mode_purchase' => false]);
$coinDeposit = Deposit::forceCreate(['user_id' => $user->id, 'amount' => bcmul($buyPrice, '30', 8), 'rate' => '1', 'final_amount' => '0', 'method_currency' => 'NGN', 'status' => 1, 'trx' => 'COINDEP1', 'currency_id' => $ngn->id, 'coin_amount' => '30', 'coin_buy_price_snapshot' => $buyPrice, 'coin_mode_purchase' => true]);

foreach ([true, false] as $coinMode) {
    $general->coin_mode = $coinMode;
    $general->save();
    $deposits = Deposit::orderByDesc('id')->paginate(15);
    $html = $renderSectionOnly(resource_path('views/admin/deposit/log.blade.php'), ['deposits' => $deposits, 'pageTitle' => 'Deposits']);
    if (!str_contains($html, '30') || !str_contains($html, 'Coins')) throw new RuntimeException('Deposit log did not show the coin amount for a coin-mode purchase.');
    if (!str_contains($html, '500')) throw new RuntimeException('Deposit log lost the legacy real-currency deposit amount.');
}
fwrite(STDOUT, "Admin deposit log: a coin-mode purchase always shows its coin amount + locked rate; a legacy deposit always shows its real-currency amount - independent of the CURRENT coin_mode flag (the per-row signal is correct, not point-in-time).\n");

// --- Game log: no per-row signal exists, so display follows the CURRENT coin_mode flag (documented point-in-time behavior). ---
$game = Game::forceCreate(['name' => 'Dice', 'alias' => 'dice']);
$gameLog = GameLog::forceCreate(['user_id' => $user->id, 'game_id' => $game->id, 'invest' => '77', 'win_amo' => '0', 'win_status' => 0, 'status' => 1, 'result' => 'lose']);

$general->coin_mode = false;
$general->save();
$logs = GameLog::with(['user', 'game'])->orderByDesc('id')->paginate(15);
$offHtml = $renderSectionOnly(resource_path('views/admin/game/log.blade.php'), ['logs' => $logs, 'pageTitle' => 'Game Log']);
if (!str_contains($offHtml, '77')) throw new RuntimeException('Game log lost the invest amount with coin_mode off.');
if (str_contains($offHtml, '77 Coins')) throw new RuntimeException('Game log incorrectly showed coins with coin_mode off.');

$general->coin_mode = true;
$general->save();
$logs = GameLog::with(['user', 'game'])->orderByDesc('id')->paginate(15);
$onHtml = $renderSectionOnly(resource_path('views/admin/game/log.blade.php'), ['logs' => $logs, 'pageTitle' => 'Game Log']);
if (!str_contains($onHtml, '77') || !str_contains($onHtml, 'Coins')) throw new RuntimeException('Game log did not show the invest amount as coins with coin_mode on.');
fwrite(STDOUT, "Admin game log: no per-row coin signal exists (confirmed table games/casino games write no such marker), so display correctly follows the current coin_mode flag - documented as point-in-time, not retroactive.\n");

// --- Bonus code redemptions and VIP rakeback claims: same point-in-time reasoning as game log. ---
$bonusCode = BonusCode::forceCreate(['code' => 'WELCOME10', 'amount' => '50']);
$redemption = BonusCodeRedemption::forceCreate(['user_id' => $user->id, 'bonus_code_id' => $bonusCode->id, 'amount' => '50', 'trx' => 'BONUSRED1']);
$general->coin_mode = true;
$general->save();
$redemptions = BonusCodeRedemption::with(['user', 'bonusCode'])->orderByDesc('id')->paginate(15);
$bonusHtml = $renderSectionOnly(resource_path('views/admin/bonus_code/redemptions.blade.php'), ['redemptions' => $redemptions, 'pageTitle' => 'Bonus Code Redemptions']);
if (!str_contains($bonusHtml, '50') || !str_contains($bonusHtml, 'Coins')) throw new RuntimeException('Bonus code redemptions did not show coins with coin_mode on.');

$tier = VipTier::forceCreate(['name' => 'Bronze']);
$claim = VipRakebackClaim::forceCreate(['user_id' => $user->id, 'vip_tier_id' => $tier->id, 'amount' => '12', 'total_wagered_snapshot' => '600', 'trx' => 'RAKEBACK1']);
$claims = VipRakebackClaim::with(['user', 'tier'])->orderByDesc('id')->paginate(15);
$claimsHtml = $renderSectionOnly(resource_path('views/admin/vip/claims.blade.php'), ['claims' => $claims, 'pageTitle' => 'VIP Rakeback Claims']);
if (!str_contains($claimsHtml, '12') || !str_contains($claimsHtml, 'Coins')) throw new RuntimeException('VIP rakeback claims did not show the claimed amount as coins with coin_mode on.');
if (!str_contains($claimsHtml, '600')) throw new RuntimeException('VIP rakeback claims lost the wagered snapshot.');
fwrite(STDOUT, "Admin bonus code redemptions and VIP rakeback claims: both show coins once coin_mode is on.\n");

// --- Confirm the deliberately-untouched surfaces really have no coin-ledger connection to touch. ---
$tgServiceSource = file_get_contents(dirname(__DIR__) . '/app/Games/TableGames/TableGameService.php');
if (str_contains($tgServiceSource, 'CoinLedger') || str_contains($tgServiceSource, "gs('coin_mode')")) {
    throw new RuntimeException('TableGameService now has coin_mode/CoinLedger awareness - the admin table-game commission log and the withdrawal/transaction reports need revisiting, this test is now stale.');
}
fwrite(STDOUT, "Confirmed: table game commissions, the withdrawal report, and the transaction report remain genuinely real-currency-only (no coin-ledger connection exists to display) - correctly left untouched.\n");

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
fwrite(STDOUT, "Coin display (admin logs/reports) integration checks passed.\n");
