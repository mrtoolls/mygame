<?php

// Admin\ManageUsersController::addSubBalance() ("Add/Sub Balance" on a user's admin detail page)
// directly mutated $user->balance and wrote a plain Transaction row unconditionally - even once
// coin mode is on, when $user->balance is frozen and the real, live number everyone (player and
// admin) actually looks at lives in the CoinLedger wallet instead. An admin "adding" balance
// would silently change a number nobody sees any more, while the player's real coin wallet never
// moved. This test proves the fix: coin mode on routes the adjustment through CoinLedger
// (mint for add, burn for subtract, with the insufficient-balance check against the live wallet,
// not the frozen column), and coin_mode off is byte-for-byte the original behavior.

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\Controllers\Admin\ManageUsersController;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CoinLedger;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/admin_balance_adjust_coin_mode_integration.sqlite';
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
    $table->string('site_name', 190)->default('Test Site');
    $table->string('cur_text', 10)->default('USD');
    $table->string('cur_sym', 5)->default('$');
    $table->integer('currency_format')->default(1);
    $table->boolean('force_ssl')->default(0);
    $table->timestamps();
});
DB::table('general_settings')->insert(['id' => 1]);
(require dirname(__DIR__) . '/database/migrations/2026_10_24_000011_add_coin_settings_to_general_settings.php')->up();

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('username');
    $table->decimal('balance', 28, 8)->default(0);
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->tinyInteger('status')->default(1);
    $table->timestamps();
});
Schema::create('admins', function (Blueprint $table) {
    $table->id();
    $table->string('username')->nullable();
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
(require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php')->up();

$general = \App\Models\GeneralSetting::first();

$adminId = DB::table('admins')->insertGetId(['username' => 'root', 'created_at' => now(), 'updated_at' => now()]);
Auth::guard('admin')->loginUsingId($adminId);

$passed = 0;
function check(bool $cond, string $message): void {
    global $passed;
    if (!$cond) throw new RuntimeException("FAILED: $message");
    $passed++;
}

$controller = new ManageUsersController();
$ledger = app(CoinLedger::class);

// --- coin_mode OFF: unchanged original behavior (regression check). ---
$userId = DB::table('users')->insertGetId(['username' => 'offuser', 'balance' => '100.00000000', 'created_at' => now(), 'updated_at' => now()]);
$addRequest = Request::create('/x', 'POST', ['amount' => '25', 'act' => 'add', 'remark' => 'test add']);
$controller->addSubBalance($addRequest, $userId);
$user = User::find($userId);
check(bccomp((string) $user->balance, '125', 8) === 0, 'coin_mode off: add credits real balance exactly as before.');
check(Transaction::where('user_id', $userId)->where('remark', 'balance_add')->exists(), 'coin_mode off: legacy Transaction row is still written.');
check((float) $ledger->wallet('user', $userId)->available_balance === 0.0, 'coin_mode off: the coin wallet must stay untouched.');

$subRequest = Request::create('/x', 'POST', ['amount' => '500', 'act' => 'sub', 'remark' => 'too much']);
$controller->addSubBalance($subRequest, $userId);
$user = User::find($userId);
check(bccomp((string) $user->balance, '125', 8) === 0, 'coin_mode off: an over-large subtract is still rejected against the real balance.');
fwrite(STDOUT, "Admin balance adjustment, coin_mode off: unchanged real-currency behavior (regression check).\n");

// --- coin_mode ON: routes through CoinLedger instead of the frozen balance column. ---
$general->coin_mode = true;
$general->save();

$coinUserId = DB::table('users')->insertGetId(['username' => 'coinuser', 'balance' => '999.00000000', 'created_at' => now(), 'updated_at' => now()]);
$ledger->mint('user', $coinUserId, '50', 'seed', 'admin_test_seed:' . $coinUserId);

$addRequest = Request::create('/x', 'POST', ['amount' => '30', 'act' => 'add', 'remark' => 'admin grant']);
$controller->addSubBalance($addRequest, $coinUserId);
$user = User::find($coinUserId);
check(bccomp((string) $user->balance, '999', 8) === 0, 'coin_mode on: the frozen real-currency balance must never move.');
check((float) $ledger->wallet('user', $coinUserId)->fresh()->available_balance === 80.0, 'coin_mode on: add mints into the coin wallet (50 + 30 = 80).');
check(!Transaction::where('user_id', $coinUserId)->where('remark', 'balance_add')->exists(), 'coin_mode on: no legacy Transaction row - the ledger is the single source of truth.');

$subRequest = Request::create('/x', 'POST', ['amount' => '20', 'act' => 'sub', 'remark' => 'admin correction']);
$controller->addSubBalance($subRequest, $coinUserId);
check((float) $ledger->wallet('user', $coinUserId)->fresh()->available_balance === 60.0, 'coin_mode on: subtract burns from the coin wallet (80 - 20 = 60).');

// Insufficient coins must be rejected against the LIVE wallet, not the frozen balance column
// (which still shows 999 and would wrongly look "sufficient" if checked instead).
$overRequest = Request::create('/x', 'POST', ['amount' => '1000', 'act' => 'sub', 'remark' => 'too many coins']);
$controller->addSubBalance($overRequest, $coinUserId);
check((float) $ledger->wallet('user', $coinUserId)->fresh()->available_balance === 60.0, 'coin_mode on: an over-large subtract is rejected against the live coin wallet, not the frozen balance.');

$reconcile = $ledger->reconcile();
check($reconcile['ok'], 'coin_mode on: the ledger still reconciles after admin mint/burn adjustments.');
fwrite(STDOUT, "Admin balance adjustment, coin_mode on: mints/burns through CoinLedger, never touches the frozen users.balance, and the insufficient-balance check reads the live wallet.\n");

fwrite(STDOUT, "Passed: $passed\n");
fwrite(STDOUT, "Admin balance adjustment coin-mode integration checks passed.\n");
