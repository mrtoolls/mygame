<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\Controllers\Admin\ManageUsersController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\User\BalanceTransferController;
use App\Models\BalanceTransfer;
use App\Models\CommissionLog;
use App\Models\GameLog;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/balance_concurrency_hotfix.sqlite';
if (is_file($dbPath)) unlink($dbPath);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
putenv('CACHE_STORE=database');
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec("CREATE TABLE general_settings (
    id INTEGER PRIMARY KEY, force_ssl INTEGER DEFAULT 0, cur_text VARCHAR(10) DEFAULT 'USD', cur_sym VARCHAR(5) DEFAULT '$',
    currency_format INTEGER DEFAULT 1, balance_transfer INTEGER DEFAULT 1,
    balance_transfer_fixed_charge DECIMAL(28,8) DEFAULT 0, balance_transfer_percent_charge DECIMAL(28,8) DEFAULT 0,
    last_cron DATETIME NULL, created_at DATETIME NULL, updated_at DATETIME NULL
)");
$pdo->exec("INSERT INTO general_settings (id) VALUES (1)");
$pdo->exec("CREATE TABLE languages (id INTEGER PRIMARY KEY, code VARCHAR(10), name VARCHAR(60), is_default INTEGER DEFAULT 0)");
$pdo->exec("INSERT INTO languages (code, name, is_default) VALUES ('en', 'English', 1)");
$pdo = null;

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

$frameworkMigration = require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php';
$frameworkMigration->up();

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('username');
    $table->decimal('balance', 28, 8)->default(0);
    $table->unsignedBigInteger('ref_by')->nullable();
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->tinyInteger('status')->default(1);
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
Schema::create('balance_transfers', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('sender_id');
    $table->unsignedBigInteger('receiver_id');
    $table->decimal('amount', 28, 8);
    $table->decimal('charge', 28, 8)->default(0);
    $table->decimal('final_amount', 28, 8);
    $table->string('trx');
    $table->timestamps();
});
Schema::create('referrals', function (Blueprint $table) {
    $table->id();
    $table->integer('level');
    $table->decimal('percent', 8, 4);
    $table->timestamps();
});
Schema::create('commission_logs', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->unsignedBigInteger('who');
    $table->string('level');
    $table->decimal('amount', 28, 8);
    $table->decimal('main_amo', 28, 8);
    $table->string('title')->nullable();
    $table->string('trx');
    $table->timestamps();
});
Schema::create('game_logs', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->unsignedBigInteger('game_id')->default(1);
    $table->decimal('invest', 28, 8)->default(0);
    $table->decimal('win_amo', 28, 8)->default(0);
    $table->tinyInteger('win_status')->default(0);
    $table->tinyInteger('status')->default(0);
    $table->tinyInteger('demo_play')->default(0);
    $table->string('result')->nullable();
    $table->string('user_select')->nullable();
    $table->integer('mines')->nullable();
    $table->integer('mine_available')->nullable();
    $table->timestamps();
});

// --- Migration safety net check: the new transactions(trx, remark) unique backstop must
// refuse to install itself over pre-existing dirty data (a past double-processing bug) instead
// of silently leaving it or breaking the deploy outright - it should throw a clear, actionable
// error and leave the table untouched. ---
DB::table('transactions')->insert([
    ['user_id' => 1, 'amount' => 10, 'trx_type' => '+', 'details' => 'dup 1', 'remark' => 'deposit', 'trx' => 'DIRTYTRX0001', 'post_balance' => 10, 'created_at' => now(), 'updated_at' => now()],
    ['user_id' => 1, 'amount' => 10, 'trx_type' => '+', 'details' => 'dup 2', 'remark' => 'deposit', 'trx' => 'DIRTYTRX0001', 'post_balance' => 20, 'created_at' => now(), 'updated_at' => now()],
]);
$backstopMigration = require dirname(__DIR__) . '/database/migrations/2026_10_24_000001_add_double_credit_backstop_to_transactions.php';
try {
    $backstopMigration->up();
    throw new RuntimeException('The backstop migration did not detect pre-existing duplicate (trx, remark) rows.');
} catch (RuntimeException $exception) {
    if (!str_contains($exception->getMessage(), 'Cannot add the transactions(trx, remark)')) throw $exception;
}
DB::table('transactions')->truncate();
$backstopMigration->up();
fwrite(STDOUT, "Backstop migration correctly refused to install over dirty data, then installed cleanly once resolved.\n");

// Now that the real unique(trx, remark) constraint is live, confirm it actually rejects a
// genuine duplicate write (the scenario it exists to catch: the same ledger event processed
// twice), while two legitimate rows sharing a trx under different remarks (see the balance
// transfer test below) are unaffected.
DB::table('transactions')->insert(['user_id' => 1, 'amount' => 5, 'trx_type' => '+', 'details' => 'first', 'remark' => 'deposit', 'trx' => 'BACKSTOPCHK1', 'post_balance' => 5, 'created_at' => now(), 'updated_at' => now()]);
try {
    DB::table('transactions')->insert(['user_id' => 1, 'amount' => 5, 'trx_type' => '+', 'details' => 'duplicate', 'remark' => 'deposit', 'trx' => 'BACKSTOPCHK1', 'post_balance' => 10, 'created_at' => now(), 'updated_at' => now()]);
    throw new RuntimeException('The live unique(trx, remark) backstop did not reject a duplicate ledger write.');
} catch (\Illuminate\Database\QueryException) {
    // expected
}
DB::table('transactions')->truncate();
fwrite(STDOUT, "Live unique(trx, remark) backstop correctly rejected a genuine duplicate ledger write.\n");

$assertBalance = static function (User $user, string $expected, string $message): void {
    if (bccomp((string) $user->fresh()->balance, $expected, 8) !== 0) throw new RuntimeException($message . " (expected $expected, got {$user->fresh()->balance})");
};
$insertUser = static function (string $username, string $balance, ?int $refBy = null): User {
    $id = DB::table('users')->insertGetId(['username' => $username, 'balance' => $balance, 'ref_by' => $refBy, 'created_at' => now(), 'updated_at' => now()]);
    return User::findOrFail($id);
};

// --- Test 1: balance-transfer double-spend race, via the previously-unlocked code path. A
// retried/double-submitted transfer must be rejected by a fresh under-lock balance check, not
// double-debit the sender. Also confirms the paired debit+credit rows (same trx, different
// remarks) are unaffected by the new unique(trx, remark) backstop. ---
$sender   = $insertUser('bt_sender', '100.00000000');
$receiver = $insertUser('bt_receiver', '0.00000000');
Auth::setUser($sender);
$makeTransferRequest = function () use ($sender, $receiver) {
    $request = Illuminate\Http\Request::create('/user/transfer', 'POST', ['username' => $receiver->username, 'amount' => '100.00000000']);
    $request->setUserResolver(fn () => $sender->fresh());
    return $request;
};
(new BalanceTransferController())->store($makeTransferRequest());
$assertBalance($sender, '0.00000000', 'First balance transfer did not debit the sender correctly');
$assertBalance($receiver, '100.00000000', 'First balance transfer did not credit the receiver correctly');
(new BalanceTransferController())->store($makeTransferRequest());
$assertBalance($sender, '0.00000000', 'A retried balance transfer double-debited the sender');
$assertBalance($receiver, '100.00000000', 'A retried balance transfer double-credited the receiver');
if (BalanceTransfer::count() !== 1) throw new RuntimeException('A retried balance transfer created a second transfer record instead of being rejected.');
if (Transaction::where('remark', 'balance_transfer')->count() !== 1 || Transaction::where('remark', 'balance_received')->count() !== 1) {
    throw new RuntimeException('The paired debit/credit transaction rows for one transfer were not written correctly.');
}
fwrite(STDOUT, "Balance transfer: retried/double-submitted transfer correctly rejected instead of double-debiting.\n");

// --- Test 2: admin manual balance adjustment, now locked and re-checked under the lock. ---
$target = $insertUser('admin_target', '50.00000000');
$addRequest = Illuminate\Http\Request::create('/admin/users/add-sub-balance', 'POST', ['amount' => '20.00000000', 'act' => 'add', 'remark' => 'Promo credit']);
(new ManageUsersController())->addSubBalance($addRequest, $target->id);
$assertBalance($target, '70.00000000', 'Admin balance addition did not apply correctly');
if (Transaction::where('user_id', $target->id)->where('remark', 'balance_add')->first()?->details !== 'Promo credit') {
    throw new RuntimeException('The admin-supplied reason was not logged on the transaction.');
}
$overSubRequest = Illuminate\Http\Request::create('/admin/users/add-sub-balance', 'POST', ['amount' => '1000.00000000', 'act' => 'sub', 'remark' => 'Too much']);
(new ManageUsersController())->addSubBalance($overSubRequest, $target->id);
$assertBalance($target, '70.00000000', 'An over-large admin subtraction changed the balance instead of being rejected');
fwrite(STDOUT, "Admin manual balance adjustment: locked, reason-logged, and rejects an over-large subtraction.\n");

// --- Test 3: referral commission, now locked and re-read instead of using a stale $referWallet
// object - confirms repeated commissions accumulate correctly rather than racing. ---
$referrer = $insertUser('ref_parent', '0.00000000');
$referred = $insertUser('ref_child', '0.00000000', $referrer->id);
Referral::create(['level' => 1, 'percent' => '10.0000']);
levelCommission($referred->id, '100.00000000', 'Deposit Commission');
$assertBalance($referrer, '10.00000000', 'First referral commission did not credit correctly');
levelCommission($referred->id, '50.00000000', 'Deposit Commission');
$assertBalance($referrer, '15.00000000', 'A second referral commission did not accumulate on top of the first (lost update)');
if (CommissionLog::count() !== 2 || Transaction::where('remark', 'commission')->count() !== 2) {
    throw new RuntimeException('Referral commission did not write a ledger entry for each commission event.');
}
fwrite(STDOUT, "Referral commission: locked re-read correctly accumulates across repeated calls.\n");

// --- Test 4: stuck-game refund (cron sweep), now locked and status-rechecked so an overlapping
// or repeated cron run can never refund the same stuck game twice. ---
$player = $insertUser('stuck_player', '0.00000000');
$stuckGame = GameLog::create(['user_id' => $player->id, 'invest' => '10.00000000', 'status' => 0, 'created_at' => now()->subMinutes(5), 'updated_at' => now()->subMinutes(5)]);
(new CronController())->incompleteGame();
$assertBalance($player, '10.00000000', 'A stuck game was not refunded');
if ((int) $stuckGame->fresh()->status !== 2) throw new RuntimeException('A refunded stuck game did not have its status updated.');
(new CronController())->incompleteGame();
$assertBalance($player, '10.00000000', 'Running the stuck-game sweep again refunded the same game twice');
if (Transaction::where('user_id', $player->id)->where('remark', 'invest_return')->count() !== 1) {
    throw new RuntimeException('A repeated cron sweep created more than one refund ledger entry for the same stuck game.');
}
fwrite(STDOUT, "Stuck-game refund: a repeated/overlapping cron sweep does not double-refund.\n");

// Best-effort teardown only, past this point.
try {
    $backstopMigration->down();
    $frameworkMigration->down();
} catch (\Throwable $teardownException) {
    fwrite(STDERR, 'Note: best-effort schema teardown did not fully complete (' . $teardownException->getMessage() . "), ignoring.\n");
}
DB::disconnect();
@unlink($dbPath);
fwrite(STDOUT, "Balance-mutation concurrency hotfix checks passed.\n");
