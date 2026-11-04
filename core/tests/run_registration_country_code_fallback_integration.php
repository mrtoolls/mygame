<?php

// The "Complete Your Profile" page's country_code/mobile_code hidden-field sync depends on JS
// running correctly in the user's browser - when it silently didn't (a blocked/slow script, a
// browser quirk), the page's own submit handler used to hard-block submission with no usable
// error message, which is exactly what was reported as the form getting "stuck" after entering a
// username and mobile number. This proves the fix: userDataSubmit() now derives country_code (and
// mobile_code, if also missing) server-side from the submitted country NAME - which the native
// <select> always submits correctly regardless of any JS - so the request succeeds even when
// country_code/mobile_code never arrive at all. Also confirms a genuinely invalid country is still
# rejected, so the fallback never masks a real validation error.

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\Controllers\User\UserController;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/registration_country_code_fallback_integration.sqlite';
if (is_file($dbPath)) unlink($dbPath);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
putenv('CACHE_STORE=array');
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
    $table->boolean('coin_mode')->default(false);
    $table->boolean('rb')->default(false);
    $table->decimal('register_bonus', 28, 8)->default(0);
    $table->timestamps();
});
DB::table('general_settings')->insert(['id' => 1]);

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('username')->nullable();
    $table->decimal('balance', 28, 8)->default(0);
    $table->tinyInteger('profile_complete')->default(0);
    $table->string('country_code')->nullable();
    $table->string('country_name')->nullable();
    $table->string('dial_code')->nullable();
    $table->string('mobile')->nullable();
    $table->string('state')->nullable();
    $table->string('gender')->nullable();
    $table->string('image')->nullable();
    $table->timestamps();
});

$passed = 0;
function check(bool $cond, string $message): void {
    global $passed;
    if (!$cond) throw new RuntimeException("FAILED: $message");
    $passed++;
}

$makeUser = function (string $username) {
    $id = DB::table('users')->insertGetId(['username' => $username, 'created_at' => now(), 'updated_at' => now()]);
    return User::find($id);
};

// --- The exact failure mode: country_code and mobile_code are both missing (as if the page's own
// JS sync never ran), but a genuinely valid country NAME was submitted by the native <select>. ---
$userA = $makeUser('pending_profile_a');
$request = Request::create('/user/data/submit', 'POST', [
    'country' => 'Nigeria', // no country_code, no mobile_code
    'username' => 'registration_fallback_user',
    'mobile' => '8012345678',
    'gender' => 'male',
]);
Auth::setUser($userA->fresh());
app()->instance('request', $request);
$response = (new UserController())->userDataSubmit($request);

check($response->getTargetUrl() !== null, 'The request did not get stuck/blocked - it redirected (would have returned a validation-error redirect otherwise).');
$userA->refresh();
check($userA->profile_complete == 1, 'Profile completion succeeded even with country_code/mobile_code both missing from the request.');
check($userA->country_code === 'NG', "country_code was correctly derived server-side from the country name 'Nigeria' - got '{$userA->country_code}'.");
check($userA->dial_code === '234', "mobile_code was also derived as Nigeria's own dial code (234) since it too was missing - got '{$userA->dial_code}'.");
check($userA->country_name === 'Nigeria', 'country_name is stored as submitted.');
fwrite(STDOUT, "A request missing country_code/mobile_code entirely now succeeds via the server-side fallback derived from the country name - this is the exact bug that left users stuck.\n");

// --- The user's own typed-in mobile_code (if present) is respected, not overridden by the fallback. ---
$userB = $makeUser('pending_profile_b');
$requestB = Request::create('/user/data/submit', 'POST', [
    'country' => 'Nigeria', // still no country_code
    'mobile_code' => '1', // user manually typed a DIFFERENT code than Nigeria's own (234)
    'username' => 'registration_fallback_user_b',
    'mobile' => '2025550123',
    'gender' => 'female',
]);
Auth::setUser($userB->fresh());
app()->instance('request', $requestB);
(new UserController())->userDataSubmit($requestB);
$userB->refresh();
check($userB->profile_complete == 1, 'Profile completion succeeded with a manually-typed mobile_code.');
check($userB->country_code === 'NG', 'country_code is still correctly derived from the country name.');
check($userB->dial_code === '1', "The user's own manually-typed mobile_code (1) is preserved, not overwritten by Nigeria's own dial code - got '{$userB->dial_code}'.");
fwrite(STDOUT, "A manually-typed dial code (the new editable fallback field) is respected rather than being silently overridden.\n");

// --- A genuinely invalid country must still fail validation - the fallback must never mask a real error. ---
$userC = $makeUser('pending_profile_c');
$requestC = Request::create('/user/data/submit', 'POST', [
    'country' => 'Not A Real Country',
    'username' => 'registration_fallback_user_c',
    'mobile' => '5551234567',
    'gender' => 'male',
]);
Auth::setUser($userC->fresh());
app()->instance('request', $requestC);
$threw = false;
try {
    (new UserController())->userDataSubmit($requestC);
} catch (\Illuminate\Validation\ValidationException $e) {
    $threw = true;
}
check($threw, 'An invalid, unrecognized country name is still correctly rejected by validation - the fallback does not mask real errors.');
$userC->refresh();
check($userC->profile_complete != 1, 'Profile completion did not happen for the invalid-country request.');
fwrite(STDOUT, "An invalid country name still fails validation exactly as before - the fallback only fills in what a valid, recognized country name already implies.\n");

fwrite(STDOUT, "Passed: $passed\n");
fwrite(STDOUT, "Registration country-code fallback integration checks passed.\n");
