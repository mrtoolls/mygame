<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\CoinVendor;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\CoinLedger;
use App\Services\CoinMarketplace;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

// Proves that CoinMarketplace's existing notifyVendorParty()/notifyVendorTradeParty() calls -
// which already call the global notify() helper with act 'DEFAULT' and no restricted channel
// list - now ALSO produce a real bell/toast notification for both parties, purely as a side
// effect of adding 'inapp' to Notify::notifyMethods() and backfilling the DEFAULT template row
// (migration 2026_11_07_000001). No CoinMarketplace code changes were needed for this.

$dbPath = __DIR__ . '/coin_marketplace_notification_triggers_integration.sqlite';
if (is_file($dbPath)) unlink($dbPath);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
putenv('CACHE_STORE=database');
putenv('APP_KEY=base64:' . base64_encode(random_bytes(32)));
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec("CREATE TABLE languages (id INTEGER PRIMARY KEY, code VARCHAR(10), name VARCHAR(60), is_default INTEGER DEFAULT 0)");
$pdo->exec("INSERT INTO languages (code, name, is_default) VALUES ('en', 'English', 1)");
$pdo = null;

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
$app->useStoragePath(sys_get_temp_dir() . '/winxpot_coin_marketplace_notif_test_storage');

(require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php')->up();

Schema::create('general_settings', function (Blueprint $table) {
    $table->id();
    $table->string('cur_text', 10)->default('USD');
    $table->string('cur_sym', 5)->default('$');
    $table->string('site_name')->default('Winxpot');
    $table->integer('currency_format')->default(1);
    $table->boolean('force_ssl')->default(0);
    $table->timestamp('last_cron')->nullable();
    $table->timestamps();
});
DB::table('general_settings')->insert(['id' => 1]);
(require dirname(__DIR__) . '/database/migrations/2026_10_24_000011_add_coin_settings_to_general_settings.php')->up();

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('username');
    $table->string('fullname')->nullable();
    $table->string('email')->nullable();
    $table->string('mobile')->nullable();
    $table->string('dial_code', 8)->nullable();
    $table->unsignedBigInteger('country_id')->nullable();
    $table->decimal('balance', 28, 8)->default(0);
    $table->unsignedBigInteger('ref_by')->nullable();
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->tinyInteger('status')->default(1);
    $table->tinyInteger('ev')->default(1);
    $table->tinyInteger('sv')->default(1);
    $table->timestamps();
});
Schema::create('admins', function (Blueprint $table) { $table->id(); $table->string('username')->nullable(); $table->timestamps(); });
Schema::create('admin_notifications', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('user_id')->default(0); $table->string('title'); $table->string('click_url')->nullable(); $table->boolean('is_read')->default(false); $table->timestamps(); });
Schema::create('currencies', function (Blueprint $table) {
    $table->id(); $table->char('code', 3)->unique(); $table->string('name'); $table->string('symbol', 12);
    $table->unsignedTinyInteger('decimals')->default(2); $table->boolean('enabled')->default(true);
    $table->string('symbol_position', 8)->default('left'); $table->decimal('rate_to_usd', 24, 12)->nullable();
    $table->string('rate_source', 16)->default('manual'); $table->boolean('auto_update')->default(false);
    $table->timestamp('last_updated_at')->nullable(); $table->timestamps();
});
DB::table('currencies')->insert(['id' => 1, 'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'enabled' => 1, 'rate_to_usd' => 1, 'created_at' => now(), 'updated_at' => now()]);
Schema::create('countries', function (Blueprint $table) {
    $table->id(); $table->char('iso_code', 2)->unique(); $table->string('name');
    $table->boolean('is_enabled')->default(true); $table->boolean('is_allowed')->default(true);
    $table->unsignedBigInteger('currency_id')->nullable(); $table->boolean('coin_marketplace_enabled')->default(true);
    $table->timestamps();
});
DB::table('countries')->insert(['id' => 1, 'iso_code' => 'US', 'name' => 'United States', 'is_enabled' => 1, 'is_allowed' => 1, 'currency_id' => 1, 'coin_marketplace_enabled' => 1, 'created_at' => now(), 'updated_at' => now()]);
Schema::create('support_agents', function (Blueprint $table) {
    $table->id(); $table->string('name'); $table->string('username'); $table->boolean('is_active')->default(true);
    $table->boolean('is_payment_agent')->default(false); $table->timestamps();
});

(require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_25_000001_add_pause_state_to_coin_rates.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_24_000013_add_is_marketplace_supervisor_to_support_agents.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_24_000020_create_coin_marketplace_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_26_000001_create_coin_vendor_system.php')->up();

// Minimal notification_templates stand-in + the real in-app-channel migration on top (backfills
// the DEFAULT row - see migration docblock).
Schema::create('notification_templates', function (Blueprint $table) {
    $table->id(); $table->string('name'); $table->string('act'); $table->string('subject')->nullable();
    $table->boolean('email_status')->default(0); $table->text('email_body')->nullable();
    $table->boolean('sms_status')->default(0); $table->text('sms_body')->nullable();
    $table->boolean('push_status')->default(0); $table->string('push_title')->nullable(); $table->text('push_body')->nullable();
    $table->text('shortcodes')->nullable(); $table->timestamps();
});
DB::table('notification_templates')->insert(['id' => 1, 'name' => 'Default', 'act' => 'DEFAULT', 'subject' => '{{subject}}', 'push_status' => 1, 'push_body' => '{{message}}', 'created_at' => now(), 'updated_at' => now()]);
(require dirname(__DIR__) . '/database/migrations/2026_11_07_000001_create_in_app_notification_channel.php')->up();

Storage::fake('local');
$marketplace = app(CoinMarketplace::class);

$insertUser = static function (string $username): User {
    $id = DB::table('users')->insertGetId([
        'username' => $username, 'fullname' => $username, 'email' => $username . '@example.test', 'mobile' => (string) random_int(1000000, 9999999),
        'dial_code' => '1', 'country_id' => 1, 'currency_id' => 1, 'ev' => 1, 'sv' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    return User::findOrFail($id);
};

$failures = [];
$pass = 0;
function check(array &$failures, int &$pass, string $label, bool $condition): void
{
    if ($condition) { $pass++; return; }
    $failures[] = $label;
}

$vendorUser = $insertUser('mp_notif_vendor');
app(CoinLedger::class)->mint('user', $vendorUser->id, '100', 'seed-vendor', 'seed:vendor');
$vendor = CoinVendor::create([
    'user_id' => $vendorUser->id, 'application_id' => 1, 'status' => 'active', 'is_selling' => true,
    'currency_id' => 1, 'sell_rate' => '0.01050000', 'per_trade_limit' => 1000, 'daily_limit' => 5000,
]);
$buyer = $insertUser('mp_notif_buyer');

$trade = $marketplace->initiateBuyFromVendor($buyer, $vendor->fresh(), '10');
check($failures, $pass, 'Trade was actually created', (bool) $trade->id);

$vendorNotif = UserNotification::where('user_id', $vendorUser->id)->first();
$buyerNotif = UserNotification::where('user_id', $buyer->id)->first();
check($failures, $pass, 'Vendor received a real in-app notification for the new sale request', (bool) $vendorNotif);
check($failures, $pass, 'Vendor notification mentions the trade', $vendorNotif && str_contains($vendorNotif->message, (string) $trade->id));
check($failures, $pass, 'Buyer received a real in-app notification that the trade started', (bool) $buyerNotif);
check($failures, $pass, 'Buyer notification mentions the trade', $buyerNotif && str_contains($buyerNotif->message, (string) $trade->id));
fwrite(STDOUT, "initiateBuyFromVendor(): vendor and buyer both received real in-app notifications, with zero CoinMarketplace code changes - just the new 'inapp' channel riding the existing notify() calls.\n");

echo "Passed: {$pass}\n";
if ($failures) {
    echo "FAILED (" . count($failures) . "):\n";
    foreach ($failures as $failure) echo " - {$failure}\n";
    exit(1);
}
echo "Coin marketplace notification trigger checks passed.\n";
