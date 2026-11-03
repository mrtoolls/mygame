<?php

// Becoming a Coin Vendor normally requires a full identity-verification application (selfie
// video, ID upload, admin review) via CoinVendorController::approve(). The admin asked for a
// direct shortcut: grant vendor status to any user without that application, for someone they
// already trust. This test proves CoinVendorController::promoteUser() creates a working vendor
// profile (with the required but otherwise-unused application_id satisfied by a synthetic,
// already-"approved" application row), refuses to duplicate an existing vendor profile, and
// never requires a security bond the user may not have coins to cover.

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\Controllers\Admin\CoinVendorController;
use App\Models\CoinVendor;
use App\Models\CoinVendorApplication;
use App\Models\CoinVendorAudit;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/admin_promote_vendor_integration.sqlite';
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

(require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php')->up();

Schema::create('general_settings', function (Blueprint $table) {
    $table->id();
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
    $table->string('email')->nullable();
    $table->decimal('balance', 28, 8)->default(0);
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->tinyInteger('status')->default(1);
    $table->tinyInteger('ev')->default(0);
    $table->tinyInteger('sv')->default(0);
    $table->timestamps();
});
Schema::create('admins', function (Blueprint $table) { $table->id(); $table->string('username')->nullable(); $table->timestamps(); });
Schema::create('admin_notifications', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('user_id')->default(0); $table->string('title'); $table->string('click_url')->nullable(); $table->timestamps(); });
Schema::create('currencies', function (Blueprint $table) {
    $table->id(); $table->char('code', 3)->unique(); $table->string('name'); $table->string('symbol', 12);
    $table->unsignedTinyInteger('decimals')->default(2); $table->boolean('enabled')->default(true);
    $table->string('symbol_position', 8)->default('left'); $table->decimal('rate_to_usd', 24, 12)->nullable();
    $table->timestamps();
});
Schema::create('countries', function (Blueprint $table) {
    $table->id(); $table->char('iso_code', 2)->unique(); $table->string('name');
    $table->boolean('is_enabled')->default(true); $table->boolean('is_allowed')->default(true);
    $table->unsignedBigInteger('currency_id')->nullable(); $table->boolean('coin_marketplace_enabled')->default(true);
    $table->timestamps();
});

(require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_25_000001_add_pause_state_to_coin_rates.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_26_000001_create_coin_vendor_system.php')->up();

$adminId = DB::table('admins')->insertGetId(['username' => 'root', 'can_review_vendor_identity' => true, 'created_at' => now(), 'updated_at' => now()]);
Auth::guard('admin')->loginUsingId($adminId);

$passed = 0;
function check(bool $cond, string $message): void {
    global $passed;
    if (!$cond) throw new RuntimeException("FAILED: $message");
    $passed++;
}

$controller = new CoinVendorController();

// A brand-new, completely unverified user (ev=0, sv=0, no KYC, no coins) - exactly the kind of
// account the normal application flow would reject outright, proving this really does work for
// ANY user, not just ones that would already qualify.
$userId = DB::table('users')->insertGetId(['username' => 'trusted_partner', 'email' => 'tp@test.com', 'created_at' => now(), 'updated_at' => now()]);
$user = User::find($userId);

$response = $controller->promoteUser(Request::create('/x', 'POST'), $userId);

$vendor = CoinVendor::where('user_id', $userId)->first();
check($vendor !== null, 'A CoinVendor row was created for the user.');
check($vendor->status === 'active', 'The new vendor is active immediately, with no application review step.');
check((float) $vendor->bond_amount === 0.0, 'No security bond is required for an admin-granted vendor (the user may have no coins at all).');
check($vendor->application_id !== null, 'The vendor has a non-null application_id, satisfying the schema requirement.');

$application = CoinVendorApplication::find($vendor->application_id);
check($application !== null && $application->status === 'approved', 'A synthetic application row exists and is marked approved.');
check(str_contains((string) $application->reviewer_notes, 'granted directly'), "The synthetic application's notes make clear it bypassed the normal flow.");

check(CoinVendorAudit::where('action', 'vendor_admin_granted')->where('vendor_id', $vendor->id)->exists(), 'An audit entry records the admin grant.');

// A second attempt for the same user must be refused, not create a duplicate/conflicting profile.
$second = $controller->promoteUser(Request::create('/x', 'POST'), $userId);
check(CoinVendor::where('user_id', $userId)->count() === 1, 'A second promotion attempt does not create a duplicate vendor profile.');

fwrite(STDOUT, "Admin can promote any user (even one that would never pass the normal identity-verification application) directly to an active vendor, with no bond required and a full audit trail, and the action refuses to duplicate an existing vendor profile.\n");
fwrite(STDOUT, "Passed: $passed\n");
fwrite(STDOUT, "Admin promote-to-vendor integration checks passed.\n");
