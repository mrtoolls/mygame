<?php

// DB-backed coverage for the two parts of the "market runs empty" bug fix that only show up
// through the real TableGameService::move() path (not the pure TableGameRules unit tests in
// run_whot_stock_reshuffle_integration.php): a racing/stale second draw request must not be
// able to double-reshuffle or double-draw the same card, and a round that ends because the
// market is truly empty must settle (pay out / refund) exactly like any other finished game.

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Games\TableGames\TableGameService;
use App\Models\TableGame;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dbPath = __DIR__ . '/whot_stock_concurrency_integration.sqlite';
if (is_file($dbPath)) unlink($dbPath);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
putenv('CACHE_STORE=database');
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec("CREATE TABLE general_settings (id INTEGER PRIMARY KEY, force_ssl INTEGER DEFAULT 0, cur_text VARCHAR(10) DEFAULT 'USD')");
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
(require dirname(__DIR__) . '/database/migrations/2026_10_19_000001_create_multiplayer_game_tables.php')->up();
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

$insertUser = static function (string $username, string $balance): User {
    $id = DB::table('users')->insertGetId(['username' => $username, 'balance' => $balance, 'currency_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
    return User::findOrFail($id);
};
$service = new TableGameService();
$failures = [];
$pass = 0;
function check(array &$failures, int &$pass, string $label, bool $condition): void
{
    if ($condition) { $pass++; return; }
    $failures[] = $label;
}
$assertBalance = static function (User $user, string $expected, string $message): void {
    if (bccomp((string) $user->fresh()->balance, $expected, 8) !== 0) throw new RuntimeException($message);
};

// ---------------------------------------------------------------------------
// 1. A stale/racing second draw request (same version the first request read,
//    sent before the client would have seen the first one's version bump)
//    must be rejected outright, never allowed to reshuffle or draw again.
// ---------------------------------------------------------------------------
$creator = $insertUser('whot_race_creator', '100.00000000');
$opponent = $insertUser('whot_race_opponent', '100.00000000');
$table = $service->create($creator, 'whot', 2, '0.00000000', 'race_create_key01');
$table = $service->join($opponent, $table->id);
check($failures, $pass, 'race setup: table is active', $table->status === 'active');

$top = ['shape' => 'Circle', 'number' => 9];
$behind = ['shape' => 'Cross', 'number' => 3];
$creatorSeat = (int) $table->players()->where('user_id', $creator->id)->value('seat');
$unplayable = $creatorSeat === 0 ? ['shape' => 'Star', 'number' => 4] : ['shape' => 'Star', 'number' => 4];
$craftedState = [
    'hands' => [0 => [], 1 => []],
    'stock' => [],
    'discard' => [$behind, $top],
    'active_shape' => 'Circle',
    'pending_penalty' => null,
    'turn' => $creatorSeat,
    'moves' => 0,
];
$craftedState['hands'][$creatorSeat] = [$unplayable];
$craftedState['hands'][1 - $creatorSeat] = [['shape' => 'Star', 'number' => 5]];
TableGame::whereKey($table->id)->update([
    'state' => json_encode($craftedState),
    'current_user_id' => $creator->id,
]);
$table = $table->fresh();
$staleVersion = (int) $table->version;

$afterFirst = $service->move($creator, $table->id, ['type' => 'draw'], 'race_move_key_001', $staleVersion);
check($failures, $pass, 'race: first draw reshuffled and succeeded', count($afterFirst->state['hands'][$creatorSeat]) === 2);
check($failures, $pass, 'race: version advanced past the stale snapshot', (int) $afterFirst->version === $staleVersion + 1);

$rejected = false;
try {
    $service->move($creator, $table->id, ['type' => 'draw'], 'race_move_key_002', $staleVersion);
} catch (InvalidArgumentException $e) {
    $rejected = true;
    check($failures, $pass, 'race: the stale second request is rejected, not silently replayed', str_contains($e->getMessage(), 'changed'));
}
check($failures, $pass, 'race: the stale second request actually threw', $rejected);
$finalTable = TableGame::findOrFail($table->id);
check($failures, $pass, 'race: state was not touched a second time (still exactly what the first draw produced)', count($finalTable->state['hands'][$creatorSeat]) === 2);
check($failures, $pass, 'race: no card was double-drawn into the hand that never got a turn', count($finalTable->state['hands'][1 - $creatorSeat]) === 1);

// ---------------------------------------------------------------------------
// 2. A round that ends because the market is truly empty (lowest-hand-total
//    settlement) must still pay out / refund stakes exactly like a normal win.
// ---------------------------------------------------------------------------
$lowCreator = $insertUser('whot_end_creator', '100.00000000');
$lowOpponent = $insertUser('whot_end_opponent', '100.00000000');
$endTable = $service->create($lowCreator, 'whot', 2, '10.00000000', 'end_create_key01');
$endTable = $service->join($lowOpponent, $endTable->id);
$assertBalance($lowCreator, '90.00000000', 'Creating the staked whot table did not debit the creator.');
$assertBalance($lowOpponent, '90.00000000', 'Joining the staked whot table did not debit the opponent.');

$endCreatorSeat = (int) $endTable->players()->where('user_id', $lowCreator->id)->value('seat');
$endOpponentSeat = 1 - $endCreatorSeat;
// Creator holds a single low card (unplayable, forcing a draw); opponent holds a heavier hand,
// so when the market comes up completely empty the lowest-total settlement must favor the
// creator - proving the "no card can be drawn and no legal play" ending actually runs the
// normal settlement path (pot, commission, payout) rather than a bespoke/forgotten one.
$endState = [
    'hands' => [0 => [], 1 => []],
    'stock' => [],
    'discard' => [['shape' => 'Circle', 'number' => 9]], // only the top card - nothing to reshuffle
    'active_shape' => 'Circle',
    'pending_penalty' => null,
    'turn' => $endCreatorSeat,
    'moves' => 0,
];
$endState['hands'][$endCreatorSeat] = [['shape' => 'Star', 'number' => 3]];
$endState['hands'][$endOpponentSeat] = [['shape' => 'Cross', 'number' => 10], ['shape' => 'Square', 'number' => 11]];
TableGame::whereKey($endTable->id)->update(['state' => json_encode($endState), 'current_user_id' => $lowCreator->id]);
$endTable = $endTable->fresh();

$finished = $service->move($lowCreator, $endTable->id, ['type' => 'draw'], 'end_move_key_001', (int) $endTable->version);
check($failures, $pass, 'empty-market ending: table finished instead of erroring or stalling', $finished->status === 'finished');
check($failures, $pass, 'empty-market ending: lowest hand total (creator, 3) is recorded as the winner', $finished->result === 'winner:' . $endCreatorSeat);
$assertBalance($lowCreator, '109.00000000', 'The lowest-hand-total winner was not paid out through the normal settlement path.');
$assertBalance($lowOpponent, '90.00000000', 'The losing stake was not left debited after the empty-market ending.');
if (DB::table('table_game_commissions')->where('table_game_id', $endTable->id)->count() !== 1) $failures[] = 'empty-market ending: no commission ledger row was created, unlike every other settled table.';
else $pass++;

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------
echo "Passed: {$pass}\n";
if ($failures) {
    echo "FAILED (" . count($failures) . "):\n";
    foreach ($failures as $failure) echo " - {$failure}\n";
    exit(1);
}
echo "All whot concurrency/settlement checks passed.\n";
