<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Constants\Status;
use App\Http\Controllers\Api\PlayController;
use App\Http\Controllers\SupportAgentController;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\Game;
use App\Models\SupportAgent;
use App\Models\User;
use App\Services\CoinLedger;
use App\Services\CoinPricing;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/coin_display_balance_sweep_integration.sqlite';
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

(require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php')->up();

Schema::create('general_settings', function (Blueprint $table) {
    $table->id();
    $table->string('cur_text', 10)->default('USD');
    $table->string('cur_sym', 5)->default('$');
    $table->integer('currency_format')->default(1);
    $table->boolean('force_ssl')->default(0);
    $table->integer('paginate_number')->default(15);
    $table->timestamp('last_cron')->nullable();
    $table->timestamps();
});
DB::table('general_settings')->insert(['id' => 1]);
(require dirname(__DIR__) . '/database/migrations/2026_10_24_000011_add_coin_settings_to_general_settings.php')->up();

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('username');
    $table->string('email')->nullable();
    $table->string('password')->nullable();
    $table->decimal('balance', 28, 8)->default(0);
    $table->decimal('demo_balance', 28, 8)->default(0);
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->tinyInteger('status')->default(1);
    $table->timestamps();
});
Schema::create('admins', function (Blueprint $table) { $table->id(); $table->string('username')->nullable(); $table->timestamps(); });
Schema::create('support_agents', function (Blueprint $table) {
    $table->id(); $table->string('name'); $table->string('email')->nullable(); $table->string('username');
    $table->string('password')->nullable(); $table->boolean('is_active')->default(true); $table->boolean('is_payment_agent')->default(false);
    $table->timestamps();
});
Schema::create('currencies', function (Blueprint $table) {
    $table->id();
    $table->string('code', 10);
    $table->integer('decimals')->default(2);
    $table->boolean('enabled')->default(true);
    $table->decimal('rate_to_usd', 24, 12)->nullable();
    $table->string('rate_source', 10)->default('manual');
    $table->boolean('auto_update')->default(false);
    $table->timestamp('last_updated_at')->nullable();
    $table->timestamps();
});
Schema::create('games', function (Blueprint $table) {
    $table->id(); $table->string('name'); $table->string('alias'); $table->string('image')->nullable();
    $table->integer('status')->default(1); $table->integer('trending')->default(0); $table->integer('featured')->default(0);
    $table->decimal('min_limit', 28, 8)->default(1); $table->decimal('max_limit', 28, 8)->default(1000);
    $table->timestamps();
});
Schema::create('guess_bonuses', function (Blueprint $table) { $table->id(); $table->string('alias'); $table->decimal('percent', 8, 2); $table->integer('chance')->default(0); $table->timestamps(); });
Schema::create('deposits', function (Blueprint $table) {
    $table->id(); $table->unsignedBigInteger('user_id'); $table->unsignedBigInteger('support_agent_id')->nullable();
    $table->decimal('amount', 28, 8); $table->decimal('charge', 28, 8)->default(0); $table->decimal('final_amount', 28, 8)->default(0);
    $table->integer('method_code')->default(5000); $table->integer('status')->default(0); $table->string('trx');
    $table->unsignedBigInteger('currency_id')->nullable(); $table->boolean('coin_mode_purchase')->default(false);
    $table->decimal('coin_amount', 28, 8)->nullable(); $table->decimal('coin_buy_price_snapshot', 28, 8)->nullable();
    $table->unsignedBigInteger('coin_rate_id')->nullable(); $table->string('method_currency')->nullable();
    $table->decimal('rate', 28, 8)->default(1); $table->string('confirmed_via')->nullable(); $table->string('admin_feedback')->nullable();
    $table->timestamps();
});
Schema::create('transactions', function (Blueprint $table) {
    $table->id(); $table->unsignedBigInteger('user_id'); $table->decimal('amount', 28, 8); $table->decimal('charge', 28, 8)->default(0);
    $table->string('trx_type', 1); $table->string('details'); $table->string('remark'); $table->string('trx')->unique();
    $table->decimal('post_balance', 28, 8); $table->timestamps();
});
Schema::create('admin_notifications', function (Blueprint $table) {
    $table->id(); $table->unsignedBigInteger('user_id')->nullable(); $table->string('title'); $table->string('click_url')->nullable();
    $table->timestamp('read_at')->nullable(); $table->timestamps();
});
Schema::create('agent_transaction_speed_logs', function (Blueprint $table) {
    $table->id(); $table->unsignedBigInteger('support_agent_id'); $table->unsignedBigInteger('deposit_id'); $table->unsignedBigInteger('user_id');
    $table->timestamp('confirmed_at')->nullable(); $table->integer('sla_minutes_snapshot')->default(15); $table->integer('elapsed_seconds')->default(0);
    $table->boolean('met_fast_threshold')->default(false); $table->timestamps();
});
(require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_25_000001_add_pause_state_to_coin_rates.php')->up();

$failures = [];
$pass = 0;
function check(array &$failures, int &$pass, string $label, bool $condition): void
{
    if ($condition) { $pass++; return; }
    $failures[] = $label;
}

$insertUser = static function (string $username, string $balance = '0', ?int $currencyId = null): User {
    $id = DB::table('users')->insertGetId(['username' => $username, 'email' => $username . '@test.local', 'balance' => $balance, 'currency_id' => $currencyId, 'created_at' => now(), 'updated_at' => now()]);
    return User::findOrFail($id);
};

$general = gs();
$ngn = Currency::create(['code' => 'NGN', 'decimals' => 2, 'enabled' => true, 'rate_to_usd' => '0.00065', 'last_updated_at' => now()]);
app(CoinPricing::class)->recompute($ngn);

// ---------------------------------------------------------------------------
// formatHistoricalAmountAsCoins(): row rate > currency fallback > rate-1 fallback, floors, and
// leaves off-mode behavior untouched.
// ---------------------------------------------------------------------------
$general->coin_mode = false;
$general->save();
check($failures, $pass, 'formatHistoricalAmountAsCoins off-mode: unchanged showAmount passthrough', formatHistoricalAmountAsCoins('15.50', $ngn->id, null) === showAmount('15.50'));

$general->coin_mode = true;
$general->save();
// 1000 NGN * 0.00065 (NGN rate_to_usd) = 0.65 USD -> 0.65 / 0.01 peg = 65 coins, using the ROW's
// own rate (0.00065) directly - never a currency lookup when one is given.
check($failures, $pass, 'formatHistoricalAmountAsCoins: uses the row-level rate first', formatHistoricalAmountAsCoins('1000', $ngn->id, '0.00065') === '65 Coins');
// No row-level rate given - falls back to the currency's current rate_to_usd (same math).
check($failures, $pass, 'formatHistoricalAmountAsCoins: falls back to the currency\'s current rate', formatHistoricalAmountAsCoins('1000', $ngn->id, null) === '65 Coins');
// No currency and no rate at all - falls back to treating the amount as already USD (rate 1).
check($failures, $pass, 'formatHistoricalAmountAsCoins: falls back to rate=1 (already-USD) with no currency at all', formatHistoricalAmountAsCoins('0.50', null, null) === '50 Coins');
// Floors, never rounds up: 999 NGN * 0.00065 = 0.64935 USD -> 64.935 coins -> floors to 64.
check($failures, $pass, 'formatHistoricalAmountAsCoins: floors to whole coins, never rounds up', formatHistoricalAmountAsCoins('999', $ngn->id, '0.00065') === '64 Coins');
fwrite(STDOUT, "formatHistoricalAmountAsCoins(): row-rate/currency-fallback/rate-1-fallback resolution and whole-coin flooring all behave correctly, and coin_mode off is an exact passthrough.\n");

// ---------------------------------------------------------------------------
// Admin gift catalog (index + preview) shows coins once coin_mode is on, unchanged off.
// ---------------------------------------------------------------------------
$gift = new class {
    public $id = 1;
    public $sort_order = 1;
    public $name = 'Rose';
    public $buy_price = '5.00';
    public $cashout_value = '3.50';
    public $status = 1;
    public $slug = 'rose';
    public $statusBadge = '<span>Active</span>';
    public function imageUrl() { return ''; }
    public function webpImageUrl() { return null; }
};

$general->coin_mode = false;
$general->save();
$offHtml = $renderSectionOnly(resource_path('views/admin/gift/index.blade.php'), ['gifts' => collect([$gift])]);
check($failures, $pass, 'Admin gift list off-mode: unchanged literal $ markup', str_contains($offHtml, '$5') && !str_contains($offHtml, 'Coins'));

$general->coin_mode = true;
$general->save();
$onHtml = $renderSectionOnly(resource_path('views/admin/gift/index.blade.php'), ['gifts' => collect([$gift])]);
check($failures, $pass, 'Admin gift list coin_mode on: shows Coins, no $ sign', str_contains($onHtml, 'Coins') && !str_contains($onHtml, '$5'));
fwrite(STDOUT, "Admin gift catalog (list): shows coins once coin_mode is on, with the original \$-prefixed markup unchanged off.\n");

// ---------------------------------------------------------------------------
// SupportAgentController::paymentConfirm - an agent's own confirmation never mints coins while
// coin mode is on; the deposit is left untouched (so an admin can still approve it directly) and
// the agent gets a clear explanation, not a raw server error.
// ---------------------------------------------------------------------------
$agentUser = $insertUser('agent_test_user', '0', $ngn->id);
$agentId = DB::table('support_agents')->insertGetId(['name' => 'Agent Smith', 'username' => 'agent_smith', 'is_active' => true, 'is_payment_agent' => true, 'created_at' => now(), 'updated_at' => now()]);
$agent = SupportAgent::findOrFail($agentId);
$deposit = Deposit::forceCreate(['user_id' => $agentUser->id, 'support_agent_id' => $agentId, 'amount' => '1000', 'final_amount' => '1000', 'status' => Status::PAYMENT_PENDING, 'trx' => 'AGENTDEP001', 'currency_id' => $ngn->id, 'method_code' => 5000]);

Auth::guard('support_agent')->setUser($agent);
$response = (new SupportAgentController())->paymentConfirm($deposit->id);
$deposit->refresh();
check($failures, $pass, 'Agent confirm in coin mode: deposit left pending, not silently lost', (int) $deposit->status === Status::PAYMENT_PENDING);
check($failures, $pass, 'Agent confirm in coin mode: no coins were minted off the agent\'s own attestation', bccomp((string) app(CoinLedger::class)->wallet('user', $agentUser->id)->fresh()->available_balance, '0', 8) === 0);
check($failures, $pass, 'Agent confirm in coin mode: redirects with a clear explanation, not a crash', $response->getSession()->get('error') !== null || str_contains(json_encode($response->getSession()->all()), 'admin'));
check($failures, $pass, 'Agent confirm in coin mode: flags an admin notification so the deposit actually gets finished', DB::table('admin_notifications')->where('title', 'like', '%coin mode requires an admin%')->exists());

// The admin's own approval path is NOT blocked - it must still work end to end.
app(\App\Http\Controllers\Admin\DepositController::class);
\App\Http\Controllers\Gateway\PaymentController::userDataUpdate($deposit, true, 'admin', 'Reviewed manually.');
$deposit->refresh();
check($failures, $pass, 'Admin approval in coin mode still works after an agent could not confirm', (int) $deposit->status === Status::PAYMENT_SUCCESS);
check($failures, $pass, 'Admin approval in coin mode actually minted coins', bccomp((string) app(CoinLedger::class)->wallet('user', $agentUser->id)->fresh()->available_balance, '0', 8) > 0);
Auth::guard('support_agent')->logout();
fwrite(STDOUT, "SupportAgentController::paymentConfirm: an agent cannot mint coins alone in coin mode, gets a clear message instead of a crash, flags an admin, and the admin's own approval path still works.\n");

// ---------------------------------------------------------------------------
// Api\PlayController::playGame - the balance returned to the game client is the live coin
// wallet in coin mode (not the frozen $user->balance), while demo play is untouched either way.
// ---------------------------------------------------------------------------
$playUser = $insertUser('play_test_user', '777', $ngn->id);
app(CoinLedger::class)->mint('user', $playUser->id, '250', 'test seed', 'seed:' . $playUser->id);
Game::create(['name' => 'Test Mines', 'alias' => 'mines', 'image' => null, 'status' => 1]);
Auth::login($playUser);
$playResponse = json_decode((new PlayController())->playGame('mines')->getContent(), true);
check($failures, $pass, 'PlayController real balance in coin mode: reflects the live coin wallet (250), not the stale $user->balance (777)', $playResponse['data']['balance'] === '250');

$demoResponse = json_decode((new PlayController())->playGame('mines', 'demo')->getContent(), true);
check($failures, $pass, 'PlayController demo balance is untouched by coin mode', (float) $demoResponse['data']['balance'] === 0.0);
Auth::logout();
fwrite(STDOUT, "Api\\PlayController::playGame: real balance reflects the live coin wallet in coin mode; demo balance stays separate either way.\n");

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------
echo "Passed: {$pass}\n";
if ($failures) {
    echo "FAILED (" . count($failures) . "):\n";
    foreach ($failures as $failure) echo " - {$failure}\n";
    exit(1);
}
echo "Coin display balance-sweep integration checks passed.\n";
