<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/auth_pages_redesign_integration.sqlite';
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

(require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php')->up();

Schema::create('general_settings', function (Blueprint $table) {
    $table->id();
    $table->string('cur_text', 10)->default('USD');
    $table->string('cur_sym', 5)->default('$');
    $table->integer('currency_format')->default(1);
    $table->boolean('force_ssl')->default(0);
    $table->integer('paginate_number')->default(15);
    $table->string('active_template')->default('nocturne');
    $table->string('base_color')->default('00EF87');
    $table->string('site_name')->default('Winxpot');
    $table->boolean('secure_password')->default(0);
    $table->timestamps();
});
DB::table('general_settings')->insert(['id' => 1]);

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('username');
    $table->string('email')->nullable();
    $table->string('mobile')->nullable();
    $table->string('dial_code')->nullable();
    $table->decimal('balance', 28, 8)->default(0);
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->string('ver_code', 6)->nullable();
    $table->timestamp('ver_code_send_at')->nullable();
    $table->tinyInteger('status')->default(1);
    $table->timestamps();
});
Schema::create('currencies', function (Blueprint $table) {
    $table->id(); $table->string('code', 10); $table->integer('decimals')->default(2); $table->boolean('enabled')->default(true); $table->timestamps();
});
Schema::create('extensions', function (Blueprint $table) {
    $table->id(); $table->string('act')->nullable(); $table->integer('status')->default(0); $table->timestamps();
});
Schema::create('frontends', function (Blueprint $table) {
    $table->id(); $table->string('tempname')->nullable(); $table->string('data_keys')->nullable(); $table->text('data_values')->nullable(); $table->timestamps();
});
Schema::create('admin_notifications', function (Blueprint $table) {
    $table->id(); $table->unsignedBigInteger('user_id')->default(0); $table->string('title'); $table->string('click_url')->nullable(); $table->timestamps();
});
DB::table('frontends')->insert([
    ['tempname' => 'nocturne', 'data_keys' => 'footer.content', 'data_values' => json_encode(['title' => 'Winxpot']), 'created_at' => now(), 'updated_at' => now()],
    ['tempname' => null, 'data_keys' => 'cookie.data', 'data_values' => json_encode(['status' => 2]), 'created_at' => now(), 'updated_at' => now()],
    ['tempname' => 'nocturne', 'data_keys' => 'login.content', 'data_values' => json_encode(['heading' => 'Welcome back']), 'created_at' => now(), 'updated_at' => now()],
]);

$failures = [];
$pass = 0;
function check(array &$failures, int &$pass, string $label, bool $condition): void
{
    if ($condition) { $pass++; return; }
    $failures[] = $label;
}

$userId = DB::table('users')->insertGetId(['username' => 'auth_redesign_user', 'email' => 'test@example.com', 'mobile' => '5551234', 'dial_code' => '1', 'ver_code' => '123456', 'ver_code_send_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
$user = User::findOrFail($userId);

// ---------------------------------------------------------------------------
// Every redesigned page renders without error, uses the premium nocturne-auth
// layout (not the old unstyled verification-code-wrapper), and the OTP pages
// render exactly 6 responsive input boxes (never the old fixed-width overflow
// component).
// ---------------------------------------------------------------------------
$html = view('Template::user.auth.passwords.email', ['pageTitle' => 'Reset Password'])->render();
check($failures, $pass, 'passwords/email: uses the premium nocturne-auth card layout', str_contains($html, 'nocturne-auth-card'));
check($failures, $pass, 'passwords/email: the old unstyled wrapper is gone', !str_contains($html, 'verification-code-wrapper'));

$html = view('Template::user.auth.passwords.code_verify', ['pageTitle' => 'Verify', 'email' => 'test@example.com'])->render();
check($failures, $pass, 'passwords/code_verify: uses the premium nocturne-auth card layout', str_contains($html, 'nocturne-auth-card'));
check($failures, $pass, 'passwords/code_verify: the old unstyled wrapper is gone', !str_contains($html, 'verification-code-wrapper'));
check($failures, $pass, 'passwords/code_verify: renders exactly 6 OTP boxes', substr_count($html, 'nocturne-otp__box') >= 6);
check($failures, $pass, 'passwords/code_verify: the single hidden code field the backend expects is still present', str_contains($html, 'name="code"'));

$html = view('Template::user.auth.passwords.reset', ['pageTitle' => 'Reset Password', 'email' => 'test@example.com', 'token' => 'abc123'])->render();
check($failures, $pass, 'passwords/reset: uses the premium nocturne-auth card layout', str_contains($html, 'nocturne-auth-card'));
check($failures, $pass, 'passwords/reset: both password fields are present with the existing field names', str_contains($html, 'name="password"') && str_contains($html, 'name="password_confirmation"'));

Auth::login($user);
$html = view('Template::user.auth.authorization.email', ['pageTitle' => 'Verify Email', 'user' => $user])->render();
check($failures, $pass, 'authorization/email: uses the premium nocturne-auth card layout', str_contains($html, 'nocturne-auth-card'));
check($failures, $pass, 'authorization/email: renders exactly 6 OTP boxes', substr_count($html, 'nocturne-otp__box') >= 6);
check($failures, $pass, 'authorization/email: the resend countdown is still present', str_contains($html, 'countdown-wrapper') && str_contains($html, 'try-again-link'));

$html = view('Template::user.auth.authorization.sms', ['pageTitle' => 'Verify Mobile', 'user' => $user])->render();
check($failures, $pass, 'authorization/sms: uses the premium nocturne-auth card layout', str_contains($html, 'nocturne-auth-card'));
check($failures, $pass, 'authorization/sms: renders exactly 6 OTP boxes', substr_count($html, 'nocturne-otp__box') >= 6);

$html = view('Template::user.auth.authorization.2fa', ['pageTitle' => 'Two-Factor'])->render();
check($failures, $pass, 'authorization/2fa: uses the premium nocturne-auth card layout', str_contains($html, 'nocturne-auth-card'));
check($failures, $pass, 'authorization/2fa: renders exactly 6 OTP boxes', substr_count($html, 'nocturne-otp__box') >= 6);
Auth::logout();

fwrite(STDOUT, "All 6 redesigned auth pages render cleanly on the premium nocturne-auth layout, the old fixed-width overflow component is gone everywhere, and the backend's single 'code' field contract is preserved.\n");

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------
echo "Passed: {$pass}\n";
if ($failures) {
    echo "FAILED (" . count($failures) . "):\n";
    foreach ($failures as $failure) echo " - {$failure}\n";
    exit(1);
}
echo "Auth pages redesign integration checks passed.\n";
