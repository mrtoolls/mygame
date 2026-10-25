<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Constants\Status;
use App\Models\BonusCode;
use App\Models\BonusCodeRedemption;
use App\Models\CommissionLog;
use App\Models\Deposit;
use App\Models\GameLog;
use App\Models\Referral;
use App\Models\SupportAgent;
use App\Models\User;
use App\Models\VipTier;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/coin_display_referrals_agent.sqlite';
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
// goes through HTTP middleware, so it needs registering by hand to render real theme views
// (same workaround used in tests/run_coin_mode_display_integration.php).
\Illuminate\Support\Facades\View::addNamespace('Template', resource_path('views/templates/nocturne'));

// Both admin/bonus_code/index.blade.php, commission_log.blade.php and referrals.blade.php
// @extend their respective full layouts, which have their own unrelated sidebar/topnav DB
// dependencies - render each real file's own @section body in isolation (same technique used in
// tests/run_coin_display_admin_logs_integration.php) so this tests the actual shipped markup
// without needing to stand up the whole admin/user theme shell.
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
    $table->string('email')->nullable();
    $table->string('image')->nullable();
    $table->decimal('balance', 28, 8)->default(0);
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->unsignedBigInteger('ref_by')->nullable();
    $table->tinyInteger('status')->default(1);
    $table->decimal('rakeback_claimed_total', 28, 8)->default(0);
    $table->timestamp('last_rakeback_claim_at')->nullable();
    $table->timestamps();
});
Schema::create('vip_tiers', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->decimal('min_wagered', 28, 8)->default(0);
    $table->decimal('rakeback_percent', 8, 2)->default(0);
    $table->string('badge_image')->nullable();
    $table->tinyInteger('status')->default(1);
    $table->timestamps();
});
Schema::create('game_logs', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->unsignedBigInteger('game_id')->nullable();
    $table->decimal('invest', 28, 8)->default(0);
    $table->decimal('win_amo', 28, 8)->default(0);
    $table->tinyInteger('win_status')->default(0);
    $table->tinyInteger('status')->default(1);
    $table->tinyInteger('demo_play')->default(0);
    $table->string('result')->nullable();
    $table->string('user_select')->nullable();
    $table->timestamps();
});
// Only queried via Referral::max('level') on the referrals page - its row shape is irrelevant
// here (guarded = ['id'] on the model), just needs to exist and hold a 'level' column.
Schema::create('referrals', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id')->nullable();
    $table->integer('level')->default(0);
    $table->timestamps();
});
Schema::create('bonus_codes', function (Blueprint $table) {
    $table->id();
    $table->string('code');
    $table->decimal('amount', 28, 8);
    $table->unsignedInteger('used_count')->default(0);
    $table->unsignedInteger('max_uses')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->tinyInteger('status')->default(1);
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
Schema::create('commission_logs', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->unsignedBigInteger('who');
    $table->integer('level')->default(1);
    $table->decimal('amount', 28, 8);
    $table->string('title')->nullable();
    $table->string('trx');
    $table->timestamps();
});
Schema::create('deposits', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->unsignedBigInteger('support_agent_id')->nullable();
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
    $table->timestamp('confirmation_deadline_at')->nullable();
    $table->timestamp('disputed_at')->nullable();
    $table->string('dispute_reason')->nullable();
    $table->string('payer_wallet_address')->nullable();
    $table->string('payment_proof')->nullable();
    $table->json('detail')->nullable();
    $table->string('admin_feedback')->nullable();
    $table->timestamps();
});
Schema::create('support_agents', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->nullable();
    $table->string('username')->nullable();
    $table->string('password')->nullable();
    $table->string('photo')->nullable();
    $table->string('title')->nullable();
    $table->boolean('is_active')->default(true);
    $table->boolean('is_payment_agent')->default(false);
    $table->timestamps();
});

$insertUser = static function (string $username, array $extra = []): User {
    $id = DB::table('users')->insertGetId(array_merge([
        'username' => $username,
        'firstname' => 'Test',
        'lastname' => 'User',
        'created_at' => now(),
        'updated_at' => now(),
    ], $extra));
    return User::findOrFail($id);
};

$general = gs();
$user = $insertUser('referrals_user');
Auth::guard('web')->setUser($user);

// A VIP tier with a nonzero rakeback percent so $claimable and $wagered24h are both nonzero
// real numbers, not just a zero-amount passthrough.
$tier = VipTier::forceCreate(['name' => 'Bronze', 'min_wagered' => '0', 'rakeback_percent' => '10', 'status' => Status::ENABLE]);
GameLog::forceCreate(['user_id' => $user->id, 'invest' => '100', 'win_amo' => '0', 'win_status' => 0, 'status' => 1, 'demo_play' => 0, 'result' => 'lose']);
$bonusCode = BonusCode::forceCreate(['code' => 'WELCOME10', 'amount' => '50']);
$redemption = BonusCodeRedemption::forceCreate(['user_id' => $user->id, 'bonus_code_id' => $bonusCode->id, 'amount' => '25', 'trx' => 'BONUSRED1']);

$renderReferrals = function () use ($user, $redemption, $renderSectionOnly) {
    $maxLevel = Referral::max('level');
    $currentTier = $user->vipTier();
    $wagered24h = $user->wageredLast24Hours();
    $claimable = $user->rakebackClaimable();
    $rakebackTiers = VipTier::active()->orderBy('min_wagered')->get();
    $activeBonuses = collect([$redemption]);
    return $renderSectionOnly(resource_path('views/templates/nocturne/user/referrals.blade.php'), [
        'pageTitle' => 'Bonuses',
        'user' => $user,
        'maxLevel' => $maxLevel,
        'currentTier' => $currentTier,
        'wagered24h' => $wagered24h,
        'claimable' => $claimable,
        'rakebackTiers' => $rakebackTiers,
        'activeBonuses' => $activeBonuses,
    ]);
};

// --- referrals.blade.php: wagered-24h, claimable rakeback, and bonus redemption amount all show
// coins when coin_mode is on, real currency unchanged off. ---
$general->coin_mode = false;
$general->save();
$offHtml = $renderReferrals();
if (!str_contains($offHtml, '100') || str_contains($offHtml, '100 Coins')) throw new RuntimeException('referrals.blade.php: wagered-24h incorrectly shown as coins with coin_mode off.');
if (str_contains($offHtml, '25 Coins')) throw new RuntimeException('referrals.blade.php: bonus redemption amount incorrectly shown as coins with coin_mode off.');
if (!str_contains($offHtml, '10.00%')) throw new RuntimeException('referrals.blade.php: the rakeback percent line lost its expected "10.00%" formatting with coin_mode off.');

$general->coin_mode = true;
$general->save();
$onHtml = $renderReferrals();
if (!str_contains($onHtml, '100 Coins')) throw new RuntimeException("referrals.blade.php: wagered-24h did not render as coins with coin_mode on: {$onHtml}");
if (!str_contains($onHtml, '10 Coins')) throw new RuntimeException('referrals.blade.php: claimable rakeback did not render as coins with coin_mode on.');
if (!str_contains($onHtml, '25 Coins')) throw new RuntimeException('referrals.blade.php: bonus redemption amount did not render as coins with coin_mode on.');
if (!str_contains($onHtml, '10.00%')) throw new RuntimeException('referrals.blade.php: the rakeback PERCENT line was incorrectly converted - percentages must never go through coinAmountText, and must keep their "10.00%" formatting regardless of coin_mode.');
fwrite(STDOUT, "User referrals/bonuses page: wagered-24h, claimable rakeback, and bonus redemption amount all show coins once coin_mode is on (unchanged off); the rakeback PERCENT line is correctly left untouched in both modes.\n");

// --- commission_log.blade.php: referral commission amount shows coins when coin_mode is on. ---
$commissionFromUser = $insertUser('commission_from_user');
$commissionLog = CommissionLog::forceCreate(['user_id' => $user->id, 'who' => $commissionFromUser->id, 'level' => 1, 'amount' => '33', 'title' => 'Level 1 commission', 'trx' => 'COMM1']);

$general->coin_mode = false;
$general->save();
$logs = CommissionLog::where('user_id', $user->id)->with('userFrom')->latest()->paginate(15);
$commissionOffHtml = $renderSectionOnly(resource_path('views/templates/nocturne/user/commission_log.blade.php'), ['pageTitle' => 'Commission Logs', 'logs' => $logs]);
if (!str_contains($commissionOffHtml, '33') || str_contains($commissionOffHtml, '33 Coins')) throw new RuntimeException('commission_log.blade.php: commission amount incorrectly shown as coins with coin_mode off.');

$general->coin_mode = true;
$general->save();
$logs = CommissionLog::where('user_id', $user->id)->with('userFrom')->latest()->paginate(15);
$commissionOnHtml = $renderSectionOnly(resource_path('views/templates/nocturne/user/commission_log.blade.php'), ['pageTitle' => 'Commission Logs', 'logs' => $logs]);
if (!str_contains($commissionOnHtml, '33 Coins')) throw new RuntimeException("commission_log.blade.php: commission amount did not render as coins with coin_mode on: {$commissionOnHtml}");
fwrite(STDOUT, "User commission log: referral commission amount shows coins once coin_mode is on, unchanged off.\n");

// --- admin/bonus_code/index.blade.php: bonus code face value shows coins when coin_mode is on. ---
$general->coin_mode = false;
$general->save();
$codes = BonusCode::orderByDesc('id')->paginate(15);
$bonusIndexOffHtml = $renderSectionOnly(resource_path('views/admin/bonus_code/index.blade.php'), ['pageTitle' => 'Bonus Codes', 'codes' => $codes]);
if (!str_contains($bonusIndexOffHtml, '50') || str_contains($bonusIndexOffHtml, '50 Coins')) throw new RuntimeException('admin/bonus_code/index.blade.php: face value incorrectly shown as coins with coin_mode off.');

$general->coin_mode = true;
$general->save();
$codes = BonusCode::orderByDesc('id')->paginate(15);
$bonusIndexOnHtml = $renderSectionOnly(resource_path('views/admin/bonus_code/index.blade.php'), ['pageTitle' => 'Bonus Codes', 'codes' => $codes]);
if (!str_contains($bonusIndexOnHtml, '50 Coins')) throw new RuntimeException("admin/bonus_code/index.blade.php: face value did not render as coins with coin_mode on: {$bonusIndexOnHtml}");
fwrite(STDOUT, "Admin bonus code index: bonus code face value shows coins once coin_mode is on, unchanged off.\n");

// --- support-agent/payments/queue.blade.php: a per-row coin_mode_purchase flag decides the
// display, independent of the CURRENT coin_mode flag - exactly like admin/deposit/log.blade.php.
$agentId = DB::table('support_agents')->insertGetId(['name' => 'Agent Smith', 'is_active' => true, 'is_payment_agent' => true, 'created_at' => now(), 'updated_at' => now()]);
$agent = SupportAgent::findOrFail($agentId);

$legacyDeposit = Deposit::forceCreate(['user_id' => $user->id, 'support_agent_id' => $agent->id, 'amount' => '500', 'trx' => 'LEGACYDEP1', 'coin_mode_purchase' => false]);
$coinDeposit = Deposit::forceCreate(['user_id' => $user->id, 'support_agent_id' => $agent->id, 'amount' => '195', 'charge' => '5', 'coin_amount' => '30', 'coin_buy_price_snapshot' => '6.5', 'trx' => 'COINDEP1', 'coin_mode_purchase' => true]);

$renderQueue = function () use ($agent) {
    $deposits = Deposit::with('user')->orderByDesc('id')->get();
    return view('support-agent.payments.queue', [
        'agent' => $agent,
        'deposits' => $deposits,
        'speedScore' => 90,
        'fastStreakCount' => 5,
        'notifications' => collect(),
    ])->render();
};
$renderShow = function (Deposit $deposit) use ($agent) {
    return view('support-agent.payments.show', [
        'agent' => $agent,
        'deposit' => $deposit->load('user'),
    ])->render();
};

foreach ([true, false] as $coinMode) {
    $general->coin_mode = $coinMode;
    $general->save();

    $queueHtml = $renderQueue();
    if (!str_contains($queueHtml, '30 Coins')) throw new RuntimeException("support-agent payments queue: coin-mode purchase row did not show its coin amount (coin_mode flag currently {$coinMode}).");
    if (!str_contains($queueHtml, '500')) throw new RuntimeException("support-agent payments queue: legacy deposit lost its real-currency amount (coin_mode flag currently {$coinMode}).");

    $coinShowHtml = $renderShow($coinDeposit);
    if (!str_contains($coinShowHtml, '30 Coins')) throw new RuntimeException("support-agent payments show: coin-mode purchase deposit did not show its coin amount (coin_mode flag currently {$coinMode}).");

    $legacyShowHtml = $renderShow($legacyDeposit);
    if (!str_contains($legacyShowHtml, '500')) throw new RuntimeException("support-agent payments show: legacy deposit lost its real-currency amount (coin_mode flag currently {$coinMode}).");
    if (str_contains($legacyShowHtml, 'Coins')) throw new RuntimeException("support-agent payments show: legacy deposit incorrectly shown as coins (coin_mode flag currently {$coinMode}).");
}
fwrite(STDOUT, "Support-agent payments queue/show: a coin-mode-purchase deposit always shows its coin amount and a legacy deposit always shows its real-currency amount - independent of the CURRENT coin_mode flag (the per-row signal is correct, not point-in-time), matching admin/deposit/log.blade.php's established pattern.\n");

// Best-effort teardown.
try {
    $frameworkMigration->down();
} catch (\Throwable $teardownException) {
    fwrite(STDERR, 'Note: best-effort schema teardown did not fully complete (' . $teardownException->getMessage() . "), ignoring.\n");
}
DB::disconnect();
@unlink($dbPath);
fwrite(STDOUT, "Coin display (referrals/commission log/admin bonus codes/support-agent payments) integration checks passed.\n");
