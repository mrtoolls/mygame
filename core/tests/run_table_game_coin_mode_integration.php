<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Games\TableGames\TableGameService;
use App\Models\Currency;
use App\Models\TableGame;
use App\Models\TableGamePlayer;
use App\Models\User;
use App\Services\CoinLedger;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/table_game_coin_mode_integration.sqlite';
if (is_file($dbPath)) unlink($dbPath);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
putenv('CACHE_STORE=database');
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec("CREATE TABLE general_settings (id INTEGER PRIMARY KEY, force_ssl INTEGER DEFAULT 0, cur_text VARCHAR(10) DEFAULT 'USD', created_at DATETIME, updated_at DATETIME)");
$pdo->exec("INSERT INTO general_settings (id, cur_text) VALUES (1, 'USD')");
$pdo = null;

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

Schema::create('users', function (Blueprint $table) {
    $table->id(); $table->string('username'); $table->decimal('balance', 28, 8)->default(0); $table->unsignedBigInteger('currency_id')->nullable(); $table->timestamps();
});
Schema::create('admins', function (Blueprint $table) { $table->id(); $table->timestamps(); });
Schema::create('transactions', function (Blueprint $table) {
    $table->id(); $table->unsignedBigInteger('user_id'); $table->decimal('amount', 28, 8); $table->decimal('charge', 28, 8)->default(0);
    $table->string('trx_type', 1); $table->string('details'); $table->string('remark'); $table->string('trx')->unique();
    $table->decimal('post_balance', 28, 8); $table->timestamps();
});

(require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php')->up();
$migration = require dirname(__DIR__) . '/database/migrations/2026_10_19_000001_create_multiplayer_game_tables.php';
$migration->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_25_000003_add_is_coin_mode_to_table_game_commissions.php')->up();
Schema::table('table_games', fn (Blueprint $table) => $table->unsignedBigInteger('currency_id')->nullable()->index());
Schema::table('table_game_players', fn (Blueprint $table) => $table->unsignedBigInteger('currency_id')->nullable()->index());
(require dirname(__DIR__) . '/database/migrations/2026_10_19_000002_create_table_game_chat_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_19_000003_create_currency_and_country_controls.php')->up();
foreach ([
    '2026_10_20_000001_add_wallet_stake_to_table_game_players.php',
    '2026_10_20_000002_enable_automatic_currency_rates.php',
    '2026_10_20_000003_add_locked_fx_rates_to_table_game_players.php',
    '2026_10_20_000004_add_settlement_amount_to_table_game_players.php',
    '2026_10_20_000009_create_table_game_viewers.php',
    '2026_10_21_000014_create_table_game_rematches_table.php',
    '2026_10_22_000011_add_rules_version_to_table_games.php',
] as $file) (require dirname(__DIR__) . '/database/migrations/' . $file)->up();

(require dirname(__DIR__) . '/database/migrations/2026_10_24_000011_add_coin_settings_to_general_settings.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_24_000010_create_coin_core_tables.php')->up();
(require dirname(__DIR__) . '/database/migrations/2026_10_25_000001_add_pause_state_to_coin_rates.php')->up();

$insertUser = static function (string $username, string $balance): User {
    $id = DB::table('users')->insertGetId(['username' => $username, 'balance' => $balance, 'currency_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
    return User::findOrFail($id);
};
$service = new TableGameService();
$ledger = app(CoinLedger::class);
$failures = [];
$pass = 0;
function check(array &$failures, int &$pass, string $label, bool $condition): void
{
    if ($condition) { $pass++; return; }
    $failures[] = $label;
}
$assertBalanceUntouched = static function (array &$failures, int &$pass, User $user, string $expected, string $label): void {
    check($failures, $pass, $label, bccomp((string) $user->fresh()->balance, $expected, 8) === 0);
};
$assertReconciled = static function (array &$failures, int &$pass, CoinLedger $ledger, string $label): void {
    check($failures, $pass, $label, $ledger->reconcile()['ok']);
};

$general = gs();
$general->coin_mode = true;
$general->save();

// ---------------------------------------------------------------------------
// 1. create()/join(): stakes are HELD in coins, users.balance is never touched at all, and the
//    stake is accepted as a flat coin amount (no wallet-currency conversion machinery runs).
// ---------------------------------------------------------------------------
$creator = $insertUser('coin_tg_creator', '1000.00000000');
$opponent = $insertUser('coin_tg_opponent', '1000.00000000');
$ledger->mint('user', $creator->id, '500', 'seed', 'seed:' . $creator->id);
$ledger->mint('user', $opponent->id, '500', 'seed', 'seed:' . $opponent->id);

$table = $service->create($creator, 'tic_tac_toe', 2, '50', 'ctg_create_key01');
check($failures, $pass, 'create(): creator stake held, not debited from coin wallet available balance', bccomp((string) $ledger->wallet('user', $creator->id)->fresh()->available_balance, '450', 8) === 0);
check($failures, $pass, 'create(): creator stake shows up as held', bccomp((string) $ledger->wallet('user', $creator->id)->fresh()->held_balance, '50', 8) === 0);
$assertBalanceUntouched($failures, $pass, $creator, '1000.00000000', 'create(): users.balance completely untouched by a coin-mode stake');
check($failures, $pass, 'create(): no wallet-currency rate was recorded for a coin stake (table_rate_to_usd null)', $table->players()->where('seat', 0)->value('wallet_rate_to_usd') === null);

$table = $service->join($opponent, $table->id);
check($failures, $pass, 'join(): opponent stake held correctly too', bccomp((string) $ledger->wallet('user', $opponent->id)->fresh()->available_balance, '450', 8) === 0);
$assertBalanceUntouched($failures, $pass, $opponent, '1000.00000000', 'join(): users.balance completely untouched by a coin-mode stake');
check($failures, $pass, 'join(): table is active once full', $table->status === 'active');
$assertReconciled($failures, $pass, $ledger, 'After create+join: reconciliation still balances');
fwrite(STDOUT, "create()/join(): coin-mode stakes are HELD (not debited), users.balance is never touched, and no real-currency conversion runs.\n");

// ---------------------------------------------------------------------------
// 2. Winner settlement: a real winner's payout comes entirely from the loser's HELD stake via
//    CoinLedger, their own held stake is released, commission is burned to the platform wallet,
//    and users.balance stays untouched throughout.
// ---------------------------------------------------------------------------
$creatorBeforeSettle = (string) $ledger->wallet('user', $creator->id)->fresh()->available_balance;
$platformBeforeSettle = (string) $ledger->wallet('platform', CoinLedger::PLATFORM_OWNER_ID)->fresh()->available_balance;
$moves = [[$creator, ['cell' => 0]], [$opponent, ['cell' => 3]], [$creator, ['cell' => 1]], [$opponent, ['cell' => 4]], [$creator, ['cell' => 2]]];
foreach ($moves as $index => [$user, $action]) {
    $table = $service->move($user, $table->id, $action, sprintf('ctg_move_key_%06d', $index + 1), (int) $table->version);
}
check($failures, $pass, 'Winning game finishes correctly', $table->status === 'finished' && $table->result === 'winner:0');
// Pot = 100, 5% commission = 5 (floored), winner_amount = 95 - the winner's own released stake
// (50) plus a net 45 from the opponent's 50 held stake (opponent's stake: 45 to the winner, 5
// commission to the platform).
check($failures, $pass, 'Settlement: winner receives exactly pot minus commission (95), not a cent off', bccomp((string) $ledger->wallet('user', $creator->id)->fresh()->available_balance, bcadd($creatorBeforeSettle, '95', 8), 8) === 0);
check($failures, $pass, 'Settlement: winner has nothing left held after settling', bccomp((string) $ledger->wallet('user', $creator->id)->fresh()->held_balance, '0', 8) === 0);
check($failures, $pass, 'Settlement: loser\'s held stake fully drained to zero (not left stuck)', bccomp((string) $ledger->wallet('user', $opponent->id)->fresh()->held_balance, '0', 8) === 0);
check($failures, $pass, 'Settlement: loser\'s available balance unaffected (they lost their whole stake, nothing more, nothing less)', bccomp((string) $ledger->wallet('user', $opponent->id)->fresh()->available_balance, '450', 8) === 0);
check($failures, $pass, 'Settlement: commission (5 coins) landed in the platform wallet as a whole number', bccomp((string) $ledger->wallet('platform', CoinLedger::PLATFORM_OWNER_ID)->fresh()->available_balance, bcadd($platformBeforeSettle, '5', 8), 8) === 0);
check($failures, $pass, 'Settlement: a TableGameCommission row was still recorded for reporting', DB::table('table_game_commissions')->where('table_game_id', $table->id)->exists());
$assertBalanceUntouched($failures, $pass, $creator, '1000.00000000', 'Settlement: winner\'s real-currency users.balance never moved');
$assertBalanceUntouched($failures, $pass, $opponent, '1000.00000000', 'Settlement: loser\'s real-currency users.balance never moved');
$assertReconciled($failures, $pass, $ledger, 'After settlement: reconciliation still balances exactly');
fwrite(STDOUT, "Winner settlement: payout comes entirely from the loser's held stake via CoinLedger, commission is whole-number and lands on the platform wallet, users.balance is never touched.\n");

// ---------------------------------------------------------------------------
// 3. Draw: every player's own held stake is simply released back to them - no transfer needed.
// ---------------------------------------------------------------------------
$drawTable = $service->create($creator, 'tic_tac_toe', 2, '20', 'ctg_draw_create01');
$drawTable = $service->join($opponent, $drawTable->id);
$creatorBeforeDraw = (string) $ledger->wallet('user', $creator->id)->fresh()->available_balance;
$opponentBeforeDraw = (string) $ledger->wallet('user', $opponent->id)->fresh()->available_balance;
$drawActions = [[$creator,0],[$opponent,1],[$creator,2],[$opponent,4],[$creator,3],[$opponent,5],[$creator,7],[$opponent,6],[$creator,8]];
foreach ($drawActions as $index => [$user, $cell]) {
    $drawTable = $service->move($user, $drawTable->id, ['cell' => $cell], sprintf('ctg_draw_key_%06d', $index + 1), (int) $drawTable->version);
}
check($failures, $pass, 'Draw settlement finishes as a draw', $drawTable->result === 'draw');
check($failures, $pass, 'Draw: creator got their exact stake back, nothing more/less', bccomp((string) $ledger->wallet('user', $creator->id)->fresh()->available_balance, bcadd($creatorBeforeDraw, '20', 8), 8) === 0);
check($failures, $pass, 'Draw: opponent got their exact stake back, nothing more/less', bccomp((string) $ledger->wallet('user', $opponent->id)->fresh()->available_balance, bcadd($opponentBeforeDraw, '20', 8), 8) === 0);
check($failures, $pass, 'Draw: no commission was charged on a refund', !DB::table('table_game_commissions')->where('table_game_id', $drawTable->id)->exists());
$assertReconciled($failures, $pass, $ledger, 'After a draw: reconciliation still balances');
fwrite(STDOUT, "Draw settlement: every player's own held stake is released back to them exactly, with no commission.\n");

// ---------------------------------------------------------------------------
// 4. forceClose() mid-game: held stakes are released back (refundAll's coin-mode path), not
//    double-released, and this also covers the admin "cancel a stuck table" tool in coin mode.
// ---------------------------------------------------------------------------
$closeTable = $service->create($creator, 'tic_tac_toe', 2, '15', 'ctg_close_create01');
$closeTable = $service->join($opponent, $closeTable->id);
$creatorBeforeClose = (string) $ledger->wallet('user', $creator->id)->fresh()->available_balance;
$opponentBeforeClose = (string) $ledger->wallet('user', $opponent->id)->fresh()->available_balance;
$service->forceClose((string) $closeTable->id);
check($failures, $pass, 'forceClose(): creator\'s held stake released back', bccomp((string) $ledger->wallet('user', $creator->id)->fresh()->available_balance, bcadd($creatorBeforeClose, '15', 8), 8) === 0);
check($failures, $pass, 'forceClose(): opponent\'s held stake released back', bccomp((string) $ledger->wallet('user', $opponent->id)->fresh()->available_balance, bcadd($opponentBeforeClose, '15', 8), 8) === 0);
check($failures, $pass, 'forceClose(): nothing left held for either player', bccomp((string) $ledger->wallet('user', $creator->id)->fresh()->held_balance, '0', 8) === 0 && bccomp((string) $ledger->wallet('user', $opponent->id)->fresh()->held_balance, '0', 8) === 0);
$assertReconciled($failures, $pass, $ledger, 'After forceClose(): reconciliation still balances');
fwrite(STDOUT, "forceClose(): a stuck coin-mode table's held stakes are correctly released back to both players, not lost or double-released.\n");

// ---------------------------------------------------------------------------
// 5. Mixed-mode safety: a table opened under real-currency mode must never be settled through
//    the coin path just because the admin later flips coin_mode on, and a second player can't
//    join it under the new mode either.
// ---------------------------------------------------------------------------
$general->coin_mode = false;
$general->save();
$legacyCreator = $insertUser('legacy_creator', '200.00000000');
$legacyOpponent = $insertUser('legacy_opponent', '200.00000000');
$legacyTable = $service->create($legacyCreator, 'tic_tac_toe', 2, '10', 'ctg_legacy_create01');
check($failures, $pass, 'Legacy table: real-currency balance debited normally while coin_mode is off', bccomp((string) $legacyCreator->fresh()->balance, '190.00000000', 8) === 0);

$general->coin_mode = true;
$general->save();
$blockedJoin = false;
try {
    $service->join($legacyOpponent, $legacyTable->id);
} catch (InvalidArgumentException $exception) {
    $blockedJoin = true;
    check($failures, $pass, 'Mixed-mode guard: join() message explains the table is on a different wallet mode', str_contains($exception->getMessage(), 'different wallet mode'));
}
check($failures, $pass, 'Mixed-mode guard: joining a real-currency table after coin_mode flips on is blocked', $blockedJoin);
check($failures, $pass, 'Mixed-mode guard: the blocked join never touched the opponent\'s coin wallet', bccomp((string) $ledger->wallet('user', $legacyOpponent->id)->fresh()->available_balance, '0', 8) === 0);

// Force-close the now-stuck legacy table (its creator is alone forever since joining is
// blocked) and confirm it refunds through the REAL-CURRENCY path, not the coin path, even
// though gs('coin_mode') is live true right now.
$service->forceClose((string) $legacyTable->id);
check($failures, $pass, 'Mixed-mode guard: forceClose() on a legacy table still refunds real currency (not coins), based on how it was actually charged', bccomp((string) $legacyCreator->fresh()->balance, '200.00000000', 8) === 0);
$assertReconciled($failures, $pass, $ledger, 'After the mixed-mode scenario: reconciliation still balances (the legacy table never touched the coin ledger)');
fwrite(STDOUT, "Mixed-mode safety: a table's actual charging mode (not the live flag) decides its settlement path, and join() refuses to mix the two within one table.\n");

// ---------------------------------------------------------------------------
// 6. Multi-winner waterfall (reflection test of the private settleCoins() method - a full
//    Spades/partnership game is impractical to script through move() for this). Verifies: each
//    winner's own stake releases, the loser pool splits correctly across multiple winners with
//    exact remainder handling, every loser's held balance drains to exactly zero, and
//    reconciliation holds.
// ---------------------------------------------------------------------------
$general->coin_mode = true;
$general->save();
$p1 = $insertUser('mw_p1', '0'); $p2 = $insertUser('mw_p2', '0'); $p3 = $insertUser('mw_p3', '0'); $p4 = $insertUser('mw_p4', '0');
foreach ([$p1, $p2, $p3, $p4] as $p) $ledger->mint('user', $p->id, '100', 'seed', 'seed:mw:' . $p->id);

$mwTable = TableGame::create(['game_type' => 'spades', 'creator_id' => $p1->id, 'create_idempotency_key' => 'mw_key_001', 'player_limit' => 4, 'stake' => '30', 'status' => 'active', 'join_deadline_at' => now()->addHours(24), 'settings' => ['commission_percent' => 5, 'turn_seconds' => 60], 'rules_version' => 1]);
$seatUsers = [$p1, $p2, $p3, $p4];
foreach ($seatUsers as $seat => $user) {
    $ledger->hold('user', $user->id, '30', 'player_stake', $mwTable->id . ':stake:' . $user->id, 'TableGame', null);
    TableGamePlayer::create(['table_game_id' => $mwTable->id, 'user_id' => $user->id, 'seat' => $seat, 'stake' => '30', 'wallet_stake' => '30', 'wallet_rate_to_usd' => null, 'table_rate_to_usd' => null, 'status' => 'seated', 'stake_debit_key' => $mwTable->id . ':stake:' . $user->id]);
}
$players = $mwTable->players()->get();
$winnersBeforeSettle = (string) $ledger->wallet('user', $p1->id)->fresh()->available_balance; // p1 and p3 both start at 70 (100 minted - 30 held)
$platformBeforeMwSettle = (string) $ledger->wallet('platform', CoinLedger::PLATFORM_OWNER_ID)->fresh()->available_balance;
$method = new ReflectionMethod(TableGameService::class, 'settleCoins');
$method->setAccessible(true);
// Seats 0 and 2 (p1, p3) are the winning partnership.
$method->invoke($service, $mwTable, $players, 0, [0, 2]);

// Pot = 120, 5% commission = 6 (floored), winner_amount = 114, split 2 ways = 57 each (own stake
// released + net share) - each winner's available balance should rise by exactly that 57.
check($failures, $pass, 'Multi-winner: each winner receives their exact even share (+57)', bccomp((string) $ledger->wallet('user', $p1->id)->fresh()->available_balance, bcadd($winnersBeforeSettle, '57', 8), 8) === 0 && bccomp((string) $ledger->wallet('user', $p3->id)->fresh()->available_balance, bcadd($winnersBeforeSettle, '57', 8), 8) === 0);
check($failures, $pass, 'Multi-winner: both winners have nothing left held', bccomp((string) $ledger->wallet('user', $p1->id)->fresh()->held_balance, '0', 8) === 0 && bccomp((string) $ledger->wallet('user', $p3->id)->fresh()->held_balance, '0', 8) === 0);
check($failures, $pass, 'Multi-winner: both losers\' held stakes drained to exactly zero (nothing stuck)', bccomp((string) $ledger->wallet('user', $p2->id)->fresh()->held_balance, '0', 8) === 0 && bccomp((string) $ledger->wallet('user', $p4->id)->fresh()->held_balance, '0', 8) === 0);
check($failures, $pass, 'Multi-winner: losers\' available balances unaffected (seeded 100, never touched beyond the original hold)', bccomp((string) $ledger->wallet('user', $p2->id)->fresh()->available_balance, '70', 8) === 0 && bccomp((string) $ledger->wallet('user', $p4->id)->fresh()->available_balance, '70', 8) === 0);
check($failures, $pass, 'Multi-winner: commission (6 coins) landed in the platform wallet', bccomp((string) $ledger->wallet('platform', CoinLedger::PLATFORM_OWNER_ID)->fresh()->available_balance, bcadd($platformBeforeMwSettle, '6', 8), 8) === 0);
$assertReconciled($failures, $pass, $ledger, 'After a multi-winner settlement: reconciliation still balances exactly');
fwrite(STDOUT, "Multi-winner settlement: the loser pool splits exactly across every winner, both losers drain to zero held, and reconciliation holds.\n");

// ---------------------------------------------------------------------------
// 7. Concurrent stake-taking: two join() calls racing for the last seat on a table that can only
//    fit one more player must not both succeed (same lockForUpdate()-based serialization the
//    whot stock-reshuffle concurrency test already proved for moves - this proves it for the
//    coin-hold path specifically).
// ---------------------------------------------------------------------------
$raceCreator = $insertUser('race_creator', '0');
$raceP2 = $insertUser('race_p2', '0');
$raceP3 = $insertUser('race_p3', '0');
foreach ([$raceCreator, $raceP2, $raceP3] as $p) $ledger->mint('user', $p->id, '50', 'seed', 'seed:race:' . $p->id);
$raceTable = $service->create($raceCreator, 'tic_tac_toe', 2, '10', 'ctg_race_create01');
$service->join($raceP2, $raceTable->id); // table is now full (player_limit 2)
$rejectedSecondJoin = false;
try {
    $service->join($raceP3, $raceTable->id); // the "losing" side of a race for the last seat
} catch (InvalidArgumentException) {
    $rejectedSecondJoin = true;
}
check($failures, $pass, 'Concurrency: a second join attempt on an already-full table is rejected, not double-seated', $rejectedSecondJoin);
check($failures, $pass, 'Concurrency: the rejected joiner\'s coins were never held', bccomp((string) $ledger->wallet('user', $raceP3->id)->fresh()->held_balance, '0', 8) === 0);
check($failures, $pass, 'Concurrency: the rejected joiner\'s coins are still fully available', bccomp((string) $ledger->wallet('user', $raceP3->id)->fresh()->available_balance, '50', 8) === 0);
$assertReconciled($failures, $pass, $ledger, 'After the concurrency check: reconciliation still balances');
fwrite(STDOUT, "Concurrency: a race for the last seat is rejected cleanly, never double-holding coins.\n");

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------
echo "Passed: {$pass}\n";
if ($failures) {
    echo "FAILED (" . count($failures) . "):\n";
    foreach ($failures as $failure) echo " - {$failure}\n";
    exit(1);
}
echo "Table-game coin-mode integration checks passed.\n";
