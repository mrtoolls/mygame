<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Http\Middleware\EnsureCountryCoinMarketplaceEnabled;
use App\Models\Country;
use App\Models\User;
use App\Services\CoinMarketplace;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

$dbPath = __DIR__ . '/coin_marketplace_country_access_integration.sqlite';
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
$allowed = DB::table('countries')->insertGetId(['iso_code' => 'NG', 'is_enabled' => true, 'is_allowed' => true]);
$blocked = DB::table('countries')->insertGetId(['iso_code' => 'FR', 'is_enabled' => true, 'is_allowed' => false]);

// Backfill only the new setting and keep the existing access values intact.
(require dirname(__DIR__) . '/database/migrations/2026_10_25_000004_add_country_coin_marketplace_control.php')->up();
$ng = DB::table('countries')->where('id', $allowed)->first();
$fr = DB::table('countries')->where('id', $blocked)->first();
if (!(bool) $ng->coin_marketplace_enabled || (bool) $fr->coin_marketplace_enabled
    || !(bool) $ng->is_allowed || (bool) $fr->is_allowed) {
    throw new RuntimeException('Marketplace toggle did not derive from current country access without changing access values.');
}

$middleware = app(EnsureCountryCoinMarketplaceEnabled::class);
$next = static fn () => response('allowed', 200);
$ngCountry = new Country(['iso_code' => 'NG', 'coin_marketplace_enabled' => true]);
$ngUser = new User();
$ngUser->setRelation('country', $ngCountry);
$ngRequest = Request::create('/user/coin');
$ngRequest->setUserResolver(static fn () => $ngUser);
if ($middleware->handle($ngRequest, $next)->getStatusCode() !== 200) throw new RuntimeException('An enabled country could not access the coin marketplace.');

$frCountry = new Country(['iso_code' => 'FR', 'coin_marketplace_enabled' => false]);
$frUser = new User();
$frUser->setRelation('country', $frCountry);
$frRequest = Request::create('/user/coin/listings', 'POST');
$frRequest->setUserResolver(static fn () => $frUser);
try {
    $middleware->handle($frRequest, $next);
    throw new RuntimeException('A disabled country reached a marketplace route.');
} catch (HttpException $exception) {
    if ($exception->getStatusCode() !== 403 || !str_contains($exception->getMessage(), 'not available in your country')) throw $exception;
}

try {
    app(CoinMarketplace::class)->createListing($frUser, '10');
    throw new RuntimeException('Listing creation was not blocked for a disabled country.');
} catch (InvalidArgumentException $exception) {
    if (!str_contains($exception->getMessage(), 'not available in your country')) throw $exception;
}

DB::disconnect();
if (is_file($dbPath)) unlink($dbPath);
echo "Coin marketplace country defaults preserve access and block disabled countries server-side.\n";
