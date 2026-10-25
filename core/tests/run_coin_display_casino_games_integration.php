<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\Game;
use App\Models\GameLog;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/coin_display_casino_games_integration.sqlite';
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
    $table->timestamps();
});
DB::table('general_settings')->insert(['id' => 1]);
(require dirname(__DIR__) . '/database/migrations/2026_10_24_000011_add_coin_settings_to_general_settings.php')->up();

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('username');
    $table->string('email')->nullable();
    $table->decimal('balance', 28, 8)->default(0);
    $table->decimal('demo_balance', 28, 8)->default(0);
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->tinyInteger('status')->default(1);
    $table->timestamps();
});
Schema::create('currencies', function (Blueprint $table) {
    $table->id();
    $table->string('code', 10);
    $table->integer('decimals')->default(2);
    $table->boolean('enabled')->default(true);
    $table->decimal('rate_to_usd', 24, 12)->nullable();
    $table->timestamp('last_updated_at')->nullable();
    $table->timestamps();
});
Schema::create('games', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('alias');
    $table->string('image')->nullable();
    $table->integer('status')->default(1);
    $table->decimal('min_limit', 28, 8)->default(1);
    $table->decimal('max_limit', 28, 8)->default(1000);
    $table->timestamps();
});
Schema::create('game_logs', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->unsignedBigInteger('game_id')->nullable();
    $table->decimal('invest', 28, 8)->default(0);
    $table->decimal('win_amo', 28, 8)->default(0);
    $table->integer('win_status')->default(0);
    $table->integer('status')->default(0);
    $table->integer('demo_play')->default(0);
    $table->string('user_select')->nullable();
    $table->timestamps();
});
Schema::create('extensions', function (Blueprint $table) {
    $table->id();
    $table->string('act')->nullable();
    $table->integer('status')->default(0);
    $table->timestamps();
});
Schema::create('frontends', function (Blueprint $table) {
    $table->id();
    $table->string('tempname')->nullable();
    $table->string('data_keys')->nullable();
    $table->text('data_values')->nullable();
    $table->timestamps();
});
Schema::create('admin_notifications', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id')->default(0);
    $table->string('title');
    $table->string('click_url')->nullable();
    $table->timestamps();
});

DB::table('frontends')->insert([
    ['tempname' => 'nocturne', 'data_keys' => 'footer.content', 'data_values' => json_encode(['title' => 'Winxpot', 'description' => 'Test']), 'created_at' => now(), 'updated_at' => now()],
    ['tempname' => 'nocturne', 'data_keys' => 'bonus_countdown.content', 'data_values' => json_encode(['title' => 'Bonus', 'expired_at' => null]), 'created_at' => now(), 'updated_at' => now()],
    ['tempname' => null, 'data_keys' => 'cookie.data', 'data_values' => json_encode(['status' => 2, 'short_desc' => 'Test cookie notice']), 'created_at' => now(), 'updated_at' => now()],
]);

(require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_25_000001_add_pause_state_to_coin_rates.php')->up();

$failures = [];
$pass = 0;
function check(array &$failures, int &$pass, string $label, bool $condition): void
{
    if ($condition) { $pass++; return; }
    $failures[] = $label;
}

$userId = DB::table('users')->insertGetId(['username' => 'coin_game_player', 'email' => 'cgp@test.local', 'balance' => '777.00000000', 'currency_id' => null, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
$user = User::findOrFail($userId);
Auth::guard('web')->setUser($user);
app(\App\Services\CoinLedger::class)->mint('user', $user->id, '350', 'test seed', 'seed:' . $user->id);

$game = Game::create(['name' => 'Test Mines', 'alias' => 'mines', 'status' => 1, 'min_limit' => '1.00000000', 'max_limit' => '500.00000000']);
$log = GameLog::create(['user_id' => $user->id, 'game_id' => $game->id, 'invest' => '25.00000000', 'win_amo' => '0', 'win_status' => 0, 'status' => 1]);
$logsPaginator = new \Illuminate\Pagination\LengthAwarePaginator(collect([$log]), 1, 15);

$general = gs();

// ---------------------------------------------------------------------------
// game_log.blade.php: the user's own bet/invest amount shows coins when coin_mode is on,
// unchanged real-currency when off - matching the already-correct admin game log pattern.
// ---------------------------------------------------------------------------
$general->coin_mode = false;
$general->save();
$offHtml = view('Template::user.game_log', ['pageTitle' => 'Game History', 'logs' => $logsPaginator, 'emptyMessage' => 'No logs', 'isDemo' => false])->render();
check($failures, $pass, 'game_log off-mode: unchanged showAmount markup', str_contains($offHtml, '25.00') && !preg_match('/25\s*Coins/', $offHtml));

$general->coin_mode = true;
$general->save();
$onHtml = view('Template::user.game_log', ['pageTitle' => 'Game History', 'logs' => $logsPaginator, 'emptyMessage' => 'No logs', 'isDemo' => false])->render();
check($failures, $pass, 'game_log coin_mode on: shows the bet as coins', (bool) preg_match('/25\s*Coins/', $onHtml));
fwrite(STDOUT, "game_log.blade.php: the user's own game history shows coins once coin_mode is on, unchanged off.\n");

// ---------------------------------------------------------------------------
// A representative casino gameplay page (mines.blade.php): balance, bet-input symbol, and
// min/max limits all become coin-mode-aware without changing anything when it's off.
// ---------------------------------------------------------------------------
$general->coin_mode = false;
$general->save();
$offGameHtml = view('Template::user.games.mines', ['pageTitle' => 'Mines', 'game' => $game, 'balance' => (string) $user->balance, 'isDemo' => false])->render();
check($failures, $pass, 'mines.blade.php off-mode: shows the real-currency balance unchanged', str_contains($offGameHtml, '777.00'));
check($failures, $pass, 'mines.blade.php off-mode: min/max limits show real currency, not coins', !str_contains($offGameHtml, 'Coins'));

$general->coin_mode = true;
$general->save();
$onGameHtml = view('Template::user.games.mines', ['pageTitle' => 'Mines', 'game' => $game, 'balance' => (string) $user->balance, 'isDemo' => false])->render();
check($failures, $pass, 'mines.blade.php coin_mode on: shows the live coin wallet balance (350), not the stale real-currency balance (777)', str_contains($onGameHtml, '350') && !str_contains($onGameHtml, '777.00'));
check($failures, $pass, 'mines.blade.php coin_mode on: min/max limits now show Coins', str_contains($onGameHtml, 'Coins'));

// Demo play must never be affected by coin mode either way.
$offDemoHtml = view('Template::user.games.mines', ['pageTitle' => 'Mines', 'game' => $game, 'balance' => '0.00000000', 'isDemo' => true])->render();
// The site header separately (and correctly) always shows the real coin wallet balance (350)
// regardless of which page or mode is being viewed - that's not what's under test here. Only the
// game card's own "Demo Balance" line (class="text balance") must stay the plain demo figure.
preg_match('/<span class="text balance">([^<]*)<\/span>/', $offDemoHtml, $demoBalanceMatch);
check($failures, $pass, 'mines.blade.php demo play: the game card\'s own balance line stays the plain demo balance (0.00), not the coin wallet', trim($demoBalanceMatch[1] ?? '') === '0.00');
fwrite(STDOUT, "mines.blade.php: balance, bet-input symbol, and min/max limits all become coin-mode-aware; demo play is untouched either way.\n");

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------
echo "Passed: {$pass}\n";
if ($failures) {
    echo "FAILED (" . count($failures) . "):\n";
    foreach ($failures as $failure) echo " - {$failure}\n";
    exit(1);
}
echo "Coin display (casino games) integration checks passed.\n";
