<?php

// $game->min_limit/max_limit and GameLog.invest/win_amo are always admin-entered/stored in the
// site's default currency (confirmed against the admin form's own "{{ gs('cur_text') }}" label),
// but a user's wallet balance is tracked in their own chosen currency. This proves the real-money
// fix end to end for the game bet flow, mirroring the deposit flow fix: a Naira-wallet viewer's
// bet is validated against the genuinely converted min/max (not the raw site-default figure),
// the stake debit/invest-back credit/win credit all move the correctly converted Naira-equivalent
// amount on balance (the severe half of the original bug - real money, not just display), while
// GameLog.invest/win_amo stay in site-default terms throughout (preserving VIP min_wagered's
// existing unconverted comparison). Demo play is proven to stay completely untouched by any of
// this - adjustDemoBalance() explicitly documents demo_balance as always staying in the site's
// own default currency, never converted to the player's wallet currency.

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Games\CasinoDice;
use App\Models\Currency;
use App\Models\GameLog;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

$dbPath = __DIR__ . '/currency_conversion_game_flow_integration.sqlite';
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
View::addNamespace('Template', resource_path('views/templates/nocturne'));

(require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php')->up();

Schema::create('general_settings', function (Blueprint $table) {
    $table->id();
    $table->string('cur_text', 10)->default('USD');
    $table->string('cur_sym', 5)->default('$');
    $table->integer('currency_format')->default(1);
    $table->boolean('coin_mode')->default(false);
    $table->boolean('game_play')->default(0);
    $table->decimal('gameplay_number_for_bonus', 28, 8)->default(0);
    $table->decimal('gameplay_bonus', 28, 8)->default(0);
    $table->timestamps();
});
DB::table('general_settings')->insert(['id' => 1]);

Schema::create('currencies', function (Blueprint $table) {
    $table->id();
    $table->string('code', 10);
    $table->integer('decimals')->default(2);
    $table->boolean('enabled')->default(true);
    $table->string('symbol', 8)->default('$');
    $table->string('symbol_position', 10)->default('left');
    $table->decimal('rate_to_usd', 24, 12)->nullable();
    $table->timestamp('last_updated_at')->nullable();
    $table->timestamps();
});
Currency::create(['code' => 'USD', 'decimals' => 2, 'enabled' => true, 'symbol' => '$', 'rate_to_usd' => '1']);
// Clean, exactly-reversible rate: 1 USD = 1 / 0.0005 = 2,000 NGN.
$ngn = Currency::create(['code' => 'NGN', 'decimals' => 2, 'enabled' => true, 'symbol' => '₦', 'rate_to_usd' => '0.0005']);

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('username');
    $table->decimal('balance', 28, 8)->default(0);
    $table->decimal('demo_balance', 28, 8)->default(0);
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
Schema::create('games', function (Blueprint $table) {
    $table->id();
    $table->string('name')->nullable();
    $table->string('alias', 40)->nullable();
    $table->tinyInteger('status')->default(1);
    $table->decimal('win', 28, 8)->default(0);
    $table->decimal('max_limit', 28, 8)->default(0);
    $table->decimal('min_limit', 28, 8)->default(0);
    $table->tinyInteger('invest_back')->default(0);
    $table->string('probable_win')->nullable();
    $table->string('probable_win_demo')->nullable();
    $table->text('instruction')->nullable();
    $table->timestamps();
});
Schema::create('game_logs', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id')->default(0);
    $table->unsignedBigInteger('game_id')->default(0);
    $table->text('user_select')->nullable();
    $table->tinyInteger('demo_play')->default(0);
    $table->text('result')->nullable();
    $table->tinyInteger('status')->default(0);
    $table->decimal('invest', 28, 8)->default(0);
    $table->decimal('win_amo', 28, 8)->default(0);
    $table->tinyInteger('win_status')->default(0);
    $table->tinyInteger('mines')->default(0);
    $table->integer('mine_available')->default(0);
    $table->timestamps();
});

$passed = 0;
function check(bool $cond, string $message): void {
    global $passed;
    if (!$cond) throw new RuntimeException("FAILED: $message");
    $passed++;
}

// $10-$1,000 (USD, site default), 20% win bonus, invest-back on - so both the invest-back credit
// and the win credit touch balance and need converting.
$gameId = DB::table('games')->insertGetId([
    'name' => 'Casino Dice', 'alias' => 'casino_dice', 'status' => 1,
    'win' => '20.00000000', 'max_limit' => '1000.00000000', 'min_limit' => '10.00000000',
    'invest_back' => 1, 'instruction' => 'Test', 'created_at' => now(), 'updated_at' => now(),
]);

$playRound = static function (User $user, string $invest, bool $demo = false) use ($gameId) {
    Auth::setUser($user->fresh());
    app()->instance('request', Request::create('/x', 'POST', ['invest' => $invest, 'percent' => '100', 'choose' => 'low']));
    $game = new CasinoDice();
    $game->demoPlay = $demo;
    $playData = $game->play()->getData(true);
    if (!isset($playData['game_log_id'])) {
        throw new RuntimeException('Game play did not return a game_log_id: ' . json_encode($playData));
    }
    $gameLogId = $playData['game_log_id'];

    Auth::setUser($user->fresh());
    app()->instance('request', Request::create('/x', 'POST', ['game_log_id' => $gameLogId]));
    $completeGame = new CasinoDice();
    $completeGame->demoPlay = $demo;
    $completeResult = $completeGame->complete();

    return ['game_log_id' => $gameLogId, 'complete' => $completeResult];
};

// --- Display: the game page shows the minimum/maximum genuinely converted into Naira, not the
// raw site-default figure, for a Naira-wallet real-money viewer. ---
$ngnUserId = DB::table('users')->insertGetId(['username' => 'naira_player', 'balance' => '100000.00000000', 'currency_id' => $ngn->id, 'created_at' => now(), 'updated_at' => now()]);
$ngnUser = User::find($ngnUserId);
Auth::setUser($ngnUser);

$viewPath = resource_path('views/templates/nocturne/user/games/dice_rolling.blade.php');
$raw = file_get_contents($viewPath);
$raw = preg_replace('/@extends\([^)]*\)/', '', $raw, 1);
$raw = preg_replace('/@section\([^)]*\)/', '', $raw, 1);
$raw = preg_replace('/@endsection/', '', $raw, 1);
// The quick-fill min/max JS lives in @push('script') blocks, which only render when captured by
// a layout's @stack - stripping the push/endpush directives (but keeping their content) lets
// that script render inline here too, so this isolated render can assert on it directly.
$raw = preg_replace('/@push\([^)]*\)/', '', $raw);
$raw = preg_replace('/@endpush/', '', $raw);
$tmpPath = sys_get_temp_dir() . '/isolated_' . md5($viewPath) . '.blade.php';
file_put_contents($tmpPath, $raw);
View::addLocation(sys_get_temp_dir());
$game = DB::table('games')->where('id', $gameId)->first();
$html = view('isolated_' . md5($viewPath), ['game' => $game, 'isDemo' => false, 'pageTitle' => 'Dice Rolling', 'balance' => $ngnUser->balance])->render();
// $10 min -> NGN at 0.0005 = ₦20,000.00; $1,000 max -> ₦2,000,000.00.
check(str_contains($html, '₦20,000.00'), 'The rendered game page shows the minimum genuinely converted to Naira ($10 -> ₦20,000.00), not the raw "$10" site-default figure.');
check(str_contains($html, '₦2,000,000.00'), 'The rendered game page shows the maximum genuinely converted to Naira ($1,000 -> ₦2,000,000.00).');
check(str_contains($html, 'let minLimit = Number("20000.00000000")'), 'The quick-fill-minimum JS variable uses the converted Naira figure (20000), not the raw site-default 10, so clicking "min" fills the bet field with a currency-consistent amount.');
check(str_contains($html, 'let maxLimit = Number("2000000.00000000")'), 'The quick-fill-maximum JS variable uses the converted Naira figure (2000000), not the raw site-default 1000.');
fwrite(STDOUT, "The game page shows bet min/max and quick-fill values genuinely converted into the viewer's own wallet currency.\n");

// --- Demo play on the same page shows the RAW, unconverted site-default limit - adjustDemoBalance()
// explicitly keeps demo_balance in site-default terms throughout, so what's displayed/quick-filled
// for demo must match that, not the wallet-converted figure. ---
$demoHtml = view('isolated_' . md5($viewPath), ['game' => $game, 'isDemo' => true, 'pageTitle' => 'Dice Rolling', 'balance' => $ngnUser->demo_balance])->render();
check(str_contains($demoHtml, 'let minLimit = Number("10")'), 'Demo play\'s quick-fill-minimum JS variable stays the raw site-default figure (10), never converted to the viewer\'s wallet currency.');
check(str_contains($demoHtml, 'let maxLimit = Number("1000")'), 'Demo play\'s quick-fill-maximum JS variable stays the raw site-default figure (1000).');
check(!str_contains($demoHtml, '₦20,000.00') && !str_contains($demoHtml, '₦2,000,000.00'), 'Demo play never shows the wallet-converted Naira limit text.');
fwrite(STDOUT, "Demo play's displayed/quick-fill bet limits stay in the site's own default currency, matching what adjustDemoBalance() keeps demo_balance in - never converted.\n");

// --- Submitting a bet below the converted Naira minimum is rejected; at/within it is accepted. ---
Auth::setUser($ngnUser->fresh());
app()->instance('request', Request::create('/x', 'POST', ['invest' => '15000', 'percent' => '100', 'choose' => 'low']));
$belowMinPlay = (new CasinoDice())->play()->getData(true);
check(isset($belowMinPlay['error']) && str_contains($belowMinPlay['error'], 'minimum'), 'A Naira bet below the converted minimum (15,000 < 20,000) is correctly rejected - got ' . json_encode($belowMinPlay));
fwrite(STDOUT, "Game bet validation correctly compares the submitted Naira amount against the converted Naira-equivalent minimum.\n");

// --- Full round: invest 50,000 NGN (within the converted [20,000, 2,000,000] range), forced win
// (percent=100 on CasinoDice always wins). Proves the stake debit, the invest-back credit, and
// the win credit all move the correctly converted Naira-equivalent amount on balance - not the
// raw site-default number silently added to a Naira balance (the severe half of the original bug) -
// while GameLog.invest/win_amo stay in site-default (USD) terms throughout. ---
$round = $playRound($ngnUser, '50000');
check($round['complete']['win_status'] == 1, 'The forced-win game round actually won - fixture is broken otherwise.');

$gameLog = GameLog::find($round['game_log_id']);
// 50,000 NGN submitted -> stored as its USD-equivalent: 50000 * 0.0005 = 25.
check(bccomp((string) $gameLog->invest, '25', 6) === 0, "GameLog.invest stays in site-default (USD) terms (25), not the raw submitted Naira figure (50000) - got '{$gameLog->invest}'.");
// win_amo = invest(25) * win%(20) / 100 = 5, also site-default, unconverted.
check(bccomp((string) $gameLog->win_amo, '5', 6) === 0, "GameLog.win_amo stays in site-default terms (5) - got '{$gameLog->win_amo}'.");

// Balance: 100,000 (start) - 50,000 (stake, already wallet-currency, no conversion needed)
// + 50,000 (invest-back: GameLog.invest=25 USD converted to NGN = 25 / 0.0005 = 50,000)
// + 10,000 (win: win_amo=5 USD converted to NGN = 5 / 0.0005 = 10,000) = 110,000.
$finalUser = $ngnUser->fresh();
check(bccomp((string) $finalUser->balance, '110000', 6) === 0, "The user's Naira balance reflects the correctly converted stake/invest-back/win amounts (expected 110000), not the raw site-default figures silently applied to a Naira balance - got '{$finalUser->balance}'.");
fwrite(STDOUT, "The game round debits/credits the correct wallet-currency-converted amounts to balance for the stake, invest-back, and win - the actual money-correctness bug is fixed, not just the display.\n");

// Transaction rows reflect what actually moved on balance, in Naira terms.
check((float) Transaction::where('user_id', $ngnUser->id)->where('remark', 'invest')->value('amount') === 50000.0, 'The invest Transaction amount is the original Naira stake (50000), unconverted - it already matched balance\'s own unit.');
check((float) Transaction::where('user_id', $ngnUser->id)->where('remark', 'invest_back')->value('amount') === 50000.0, 'The invest_back Transaction amount is the converted Naira-equivalent (50000).');
check((float) Transaction::where('user_id', $ngnUser->id)->where('remark', 'Win_Bonus')->value('amount') === 10000.0, 'The win-bonus Transaction amount is the converted Naira-equivalent (10000).');
fwrite(STDOUT, "Transaction rows reflect the actual wallet-currency amounts that moved on balance.\n");

// --- Demo play: completely unaffected by any of this - stays in site-default terms throughout,
// exactly like pre-fix behavior, regardless of the player's own wallet currency. ---
$demoUserId = DB::table('users')->insertGetId(['username' => 'naira_demo_player', 'balance' => '0', 'demo_balance' => '1000.00000000', 'currency_id' => $ngn->id, 'created_at' => now(), 'updated_at' => now()]);
$demoUser = User::find($demoUserId);

// 5,000 NGN would be below the converted 20,000 minimum, but demo play checks the RAW site-default
// limit (10-1000), so a raw bet of 50 (within [10, 1000]) is what's actually submitted/enforced.
$demoRound = $playRound($demoUser, '50', true);
check($demoRound['complete']['win_status'] == 1, 'The forced-win demo round actually won - fixture is broken otherwise.');

$demoGameLog = GameLog::find($demoRound['game_log_id']);
// Demo play's GameLog.invest is the raw submitted figure, never converted (coin_mode/demoPlay
// branch in Game::invest() stores $request->invest directly).
check(bccomp((string) $demoGameLog->invest, '50', 6) === 0, "Demo play's GameLog.invest stays the raw submitted figure (50), never converted - got '{$demoGameLog->invest}'.");
check(bccomp((string) $demoGameLog->win_amo, '10', 6) === 0, "Demo play's GameLog.win_amo (50 * 20% = 10) stays unconverted - got '{$demoGameLog->win_amo}'.");

$finalDemoUser = $demoUser->fresh();
// demo_balance: 1000 - 50 (stake) + 50 (invest-back) + 10 (win) = 1010 - entirely in site-default
// terms, the NGN rate (0.0005) never applied anywhere in this arithmetic.
check(bccomp((string) $finalDemoUser->demo_balance, '1010', 6) === 0, "Demo play's demo_balance moves entirely in site-default terms (expected 1010, no NGN conversion applied) - got '{$finalDemoUser->demo_balance}'.");
check(bccomp((string) $finalDemoUser->balance, '0', 6) === 0, "Demo play never touches the real, wallet-currency balance column.");
check(!Transaction::where('user_id', $demoUser->id)->exists(), 'Demo play never writes a Transaction row.');
fwrite(STDOUT, "Demo play stays completely unaffected by the currency-conversion fix - demo_balance moves in site-default terms exactly as before, regardless of the player's own wallet currency.\n");

fwrite(STDOUT, "Passed: $passed\n");
fwrite(STDOUT, "Currency conversion game bet flow integration checks passed.\n");
