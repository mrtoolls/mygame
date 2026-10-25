<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\Middleware\CountryAccess;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/country_access_test.sqlite';
if (is_file($dbPath)) unlink($dbPath);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec('CREATE TABLE general_settings (id INTEGER PRIMARY KEY, force_ssl INTEGER DEFAULT 0)');
$pdo = null;
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
Schema::create('countries', function (Blueprint $table) {
    $table->id();
    $table->char('iso_code', 2)->unique();
    $table->boolean('is_enabled')->default(true);
    $table->boolean('is_allowed')->default(false);
});
Schema::create('country_block_attempts', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('country_id')->nullable();
    $table->unsignedBigInteger('user_id')->nullable();
    $table->string('ip_address', 45)->nullable();
    $table->string('detected_country_code', 2)->nullable();
    $table->string('path', 500)->nullable();
    $table->string('method', 12)->nullable();
    $table->string('reason', 255)->nullable();
    $table->timestamp('created_at')->nullable();
});
$us = DB::table('countries')->insertGetId(['iso_code' => 'US', 'is_enabled' => true, 'is_allowed' => true]);
$fr = DB::table('countries')->insertGetId(['iso_code' => 'FR', 'is_enabled' => true, 'is_allowed' => false]);
$middleware = app(CountryAccess::class);
$next = static fn () => response('allowed', 200);

$allowedRequest = Request::create('/games');
$allowedRequest->headers->set('CF-IPCountry', 'US');
$allowed = $middleware->handle($allowedRequest, $next);
if ($allowed->getStatusCode() !== 200) throw new RuntimeException('An enabled and allowed country was blocked.');

$blockedRequest = Request::create('/games');
$blockedRequest->headers->set('Accept', 'application/json');
$blockedRequest->headers->set('CF-IPCountry', 'FR');
$blocked = $middleware->handle($blockedRequest, $next);
if ($blocked->getStatusCode() !== 403) throw new RuntimeException('A known blocked country was allowed.');

$blockedApiRequest = Request::create('/api/games', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_CF_IPCOUNTRY' => 'FR']);
$blockedApi = $middleware->handle($blockedApiRequest, $next);
if ($blockedApi->getStatusCode() !== 403) throw new RuntimeException('A known blocked country was allowed through the API path.');

$signupFallbackBlockedRequest = Request::create('/games');
$signupFallbackBlockedRequest->setUserResolver(static fn () => (object) ['id' => 45, 'country_id' => $fr]);
$signupFallbackBlocked = $middleware->handle($signupFallbackBlockedRequest, $next);
if ($signupFallbackBlocked->getStatusCode() !== 403) throw new RuntimeException('A blocked signup country was allowed when the country header was missing.');
$unknownAttempt = DB::table('country_block_attempts')->where('user_id', 45)->latest('id')->first();
if ($unknownAttempt?->detected_country_code !== null || !str_contains((string) $unknownAttempt?->reason, 'no usable CF-IPCountry header')) throw new RuntimeException('A signup-country fallback was mislabeled as the detected country instead of Unknown with its missing-signal reason.');

$fallbackRequest = Request::create('/games');
$fallbackRequest->setUserResolver(static fn () => (object) ['id' => 44, 'country_id' => $us]);
$fallback = $middleware->handle($fallbackRequest, $next);
if ($fallback->getStatusCode() !== 200) throw new RuntimeException('A signed-in user from an allowed registration country was blocked when the CDN header was missing.');

$guestRequest = Request::create('/register');
$guest = $middleware->handle($guestRequest, $next);
if ($guest->getStatusCode() !== 200) throw new RuntimeException('A guest was blocked because the country header was unavailable.');

DB::disconnect();
if (is_file($dbPath)) unlink($dbPath);
echo "Country access checks passed for allowed/blocked countries, signup fallback, unknown detected-country logging, and missing-header guests.\n";
