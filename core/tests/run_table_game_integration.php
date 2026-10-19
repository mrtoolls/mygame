<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Games\TableGames\TableGameService;
use App\Models\TableGame;
use App\Models\TableGameMessage;
use App\Models\TableGameMessageReaction;
use App\Models\User;
use App\Http\Controllers\User\TableGameController;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

$dbPath = __DIR__ . '/table_games_integration.sqlite';
if (is_file($dbPath)) unlink($dbPath);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);
putenv('CACHE_STORE=database');
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec('CREATE TABLE general_settings (id INTEGER PRIMARY KEY, force_ssl INTEGER DEFAULT 0)');
$pdo = null;

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

Schema::create('users', function (Blueprint $table) {
    $table->id(); $table->string('username'); $table->decimal('balance', 28, 8)->default(0); $table->timestamps();
});
Schema::create('admins', function (Blueprint $table) { $table->id(); $table->timestamps(); });
Schema::create('transactions', function (Blueprint $table) {
    $table->id(); $table->unsignedBigInteger('user_id'); $table->decimal('amount', 28, 8); $table->decimal('charge', 28, 8)->default(0);
    $table->string('trx_type', 1); $table->string('details'); $table->string('remark'); $table->string('trx')->unique();
    $table->decimal('post_balance', 28, 8); $table->timestamps();
});

$frameworkMigration = require dirname(__DIR__) . '/database/migrations/2026_10_19_000000_create_framework_cache_and_sessions_tables.php';
$frameworkMigration->up();
if (!Schema::hasTable('cache') || !Schema::hasTable('cache_locks') || !Schema::hasTable('sessions')) throw new RuntimeException('Framework cache/session tables were not created.');
$migration = require dirname(__DIR__) . '/database/migrations/2026_10_19_000001_create_multiplayer_game_tables.php';
$migration->up();
$chatMigration = require dirname(__DIR__) . '/database/migrations/2026_10_19_000002_create_table_game_chat_tables.php';
$chatMigration->up();

$insertUser = static function (string $username, string $balance): User {
    $id = DB::table('users')->insertGetId(['username' => $username, 'balance' => $balance, 'created_at' => now(), 'updated_at' => now()]);
    return User::findOrFail($id);
};
$creator = $insertUser('table_creator', '100.00000000');
$opponent = $insertUser('table_opponent', '100.00000000');
$service = new TableGameService();
$assertBalance = static function (User $user, string $expected, string $message): void {
    if (bccomp((string) $user->fresh()->balance, $expected, 8) !== 0) throw new RuntimeException($message);
};
$table = $service->create($creator, 'tic_tac_toe', 2, '10.00000000', 'create_key_000001');
$assertBalance($creator, '90.00000000', 'Creating a table did not atomically debit the creator.');
$replayedCreate = $service->create($creator, 'tic_tac_toe', 2, '10.00000000', 'create_key_000001');
if ($replayedCreate->id !== $table->id) throw new RuntimeException('A retried table creation did not return the original table.');
$assertBalance($creator, '90.00000000', 'Retrying table creation charged the creator twice.');
$table = $service->join($opponent, $table->id);
if ($table->status !== 'active') throw new RuntimeException('A full table did not start.');
$assertBalance($opponent, '90.00000000', 'Joining a full table did not atomically debit the opponent.');
$service->join($opponent, $table->id);
$assertBalance($opponent, '90.00000000', 'Retrying a table join charged the opponent twice.');

$moves = [[$creator, ['cell' => 0]], [$opponent, ['cell' => 3]], [$creator, ['cell' => 1]], [$opponent, ['cell' => 4]], [$creator, ['cell' => 2]]];
foreach ($moves as $index => [$user, $action]) {
    $table = $service->move($user, $table->id, $action, sprintf('move_key_%06d', $index + 1), (int) $table->version);
}
if ($table->status !== 'finished' || $table->result !== 'winner:0') throw new RuntimeException('A legal winning game did not finish correctly.');
$assertBalance($creator, '109.00000000', 'Winner payout or pot commission is incorrect.');
$assertBalance($opponent, '90.00000000', 'The losing stake balance is incorrect.');
$service->move($creator, $table->id, ['cell' => 2], 'move_key_000005', (int) $table->version);
$assertBalance($creator, '109.00000000', 'Replaying a completed move paid twice.');
if (DB::table('transactions')->count() !== 3 || DB::table('table_game_commissions')->count() !== 1) throw new RuntimeException('Stake, payout, and commission ledger records are incomplete.');

$friendlyTable = $service->create($creator, 'tic_tac_toe', 2, '0.00000000', 'friendly_key_0001');
$friendlyTable = $service->join($opponent, $friendlyTable->id);
$friendlyMoves = [[$creator, ['cell' => 0]], [$opponent, ['cell' => 3]], [$creator, ['cell' => 1]], [$opponent, ['cell' => 4]], [$creator, ['cell' => 2]]];
foreach ($friendlyMoves as $index => [$user, $action]) {
    $friendlyTable = $service->move($user, $friendlyTable->id, $action, sprintf('friendly_move_%06d', $index + 1), (int) $friendlyTable->version);
}
if ($friendlyTable->status !== 'finished' || $friendlyTable->result !== 'winner:0') throw new RuntimeException('A friendly game did not finish normally.');
$assertBalance($creator, '109.00000000', 'A friendly game changed the winner balance.');
$assertBalance($opponent, '90.00000000', 'A friendly game changed the other player balance.');
if (DB::table('transactions')->count() !== 3 || DB::table('table_game_commissions')->count() !== 1) throw new RuntimeException('A friendly game created a wallet transaction or commission.');

$refundUser = $insertUser('table_refund', '20.00000000');
$waiting = $service->create($refundUser, 'tic_tac_toe', 2, '5.00000000', 'create_key_000002');
$service->forceClose($waiting->id);
$assertBalance($refundUser, '20.00000000', 'Force-close did not return the creator stake.');
$refunds = DB::table('transactions')->where('user_id', $refundUser->id)->where('remark', 'table_refund')->count();
if ($refunds !== 1) throw new RuntimeException('Force-close refund is missing its ledger entry.');
$expired = $service->create($refundUser, 'tic_tac_toe', 2, '5.00000000', 'create_key_000004');
TableGame::whereKey($expired->id)->update(['join_deadline_at' => now()->subSecond()]);
$service->sweepExpired();
$service->sweepExpired();
if ($expired->fresh()->status !== 'cancelled') throw new RuntimeException('An empty table did not expire.');
$assertBalance($refundUser, '20.00000000', 'An expired table did not safely refund its stake.');
if (DB::table('transactions')->where('user_id', $refundUser->id)->where('remark', 'table_refund')->count() !== 2) throw new RuntimeException('Repeated expiration caused a duplicate or missing refund.');

$drawTable = $service->create($creator, 'tic_tac_toe', 2, '1.00000000', 'create_key_000003');
$drawTable = $service->join($opponent, $drawTable->id);
$drawActions = [[$creator,0],[$opponent,1],[$creator,2],[$opponent,4],[$creator,3],[$opponent,5],[$creator,7],[$opponent,6],[$creator,8]];
foreach ($drawActions as $index => [$user, $cell]) {
    $drawTable = $service->move($user, $drawTable->id, ['cell' => $cell], sprintf('draw_key_%06d', $index + 1), (int) $drawTable->version);
}
if ($drawTable->result !== 'draw') throw new RuntimeException('A full tie game was not settled as a draw.');
$assertBalance($creator, '109.00000000', 'Draw settlement did not return the creator stake.');
$assertBalance($opponent, '90.00000000', 'Draw settlement did not return the opponent stake.');

$activeClose = $service->create($creator, 'tic_tac_toe', 2, '1.00000000', 'create_key_000005');
$activeClose = $service->join($opponent, $activeClose->id);
$service->forceClose($activeClose->id);
$assertBalance($creator, '109.00000000', 'Force-closing an active table did not refund the creator.');
$assertBalance($opponent, '90.00000000', 'Force-closing an active table did not refund the opponent.');
$failedTable = $service->create($creator, 'tic_tac_toe', 2, '1.00000000', 'create_key_000007');
$failedTable = $service->join($opponent, $failedTable->id);
$brokenRules = new class extends \App\Games\TableGames\TableGameRules {
    public function move(string $game, array $state, int $seat, array $action, array $activeSeats = []): array
    {
        throw new RuntimeException('Simulated server rules failure.');
    }
};
try {
    (new TableGameService($brokenRules))->move($creator, $failedTable->id, ['cell' => 0], 'error_key_000001', (int) $failedTable->version);
    throw new RuntimeException('A server-side game failure was not surfaced.');
} catch (RuntimeException $exception) {
    if (!str_contains($exception->getMessage(), 'server error')) throw $exception;
}
if ($failedTable->fresh()->status !== 'force_closed') throw new RuntimeException('A failed table was not safely closed.');
$assertBalance($creator, '109.00000000', 'A game-server failure did not refund the creator.');
$assertBalance($opponent, '90.00000000', 'A game-server failure did not refund the opponent.');
$invitee = $insertUser('table_invitee', '20.00000000');
$inviteTable = $service->create($creator, 'tic_tac_toe', 2, '1.00000000', 'create_key_000008');
$invite = $service->invite($creator, $inviteTable->id, 'table_invitee');
$pendingInvites = $service->pendingInvites($invitee);
if (!$pendingInvites instanceof Illuminate\Support\Collection || $pendingInvites->count() !== 1) throw new RuntimeException('The table invitation inbox did not return a collection with the expected invite.');
$joinedByInvite = $service->acceptInvite($invitee, $invite->id);
if ($joinedByInvite->status !== 'active') throw new RuntimeException('Accepting a table invitation did not join and start the table.');
$assertBalance($invitee, '19.00000000', 'Accepting an invitation did not debit the agreed stake.');
$service->forceClose($inviteTable->id);
$assertBalance($creator, '109.00000000', 'Force-closing an invited table did not refund the creator.');
$assertBalance($invitee, '20.00000000', 'Force-closing an invited table did not refund the invitee.');
$timed = $service->create($creator, 'tic_tac_toe', 2, '1.00000000', 'create_key_000009');
$timed = $service->join($opponent, $timed->id);
for ($turn = 0; $turn < 3; $turn++) {
    TableGame::whereKey($timed->id)->update(['turn_deadline_at' => now()->subSecond()]);
    $service->sweepExpired();
    $timed->refresh();
}
if ($timed->status !== 'finished' || $timed->result !== 'winner:1') throw new RuntimeException('Two missed turns did not forfeit the inactive player.');
$assertBalance($creator, '108.00000000', 'The forfeited stake was incorrectly refunded.');
$assertBalance($opponent, '90.90000000', 'The remaining player did not receive the net pot.');
$dominoTable = $service->create($creator, 'dominoes', 2, '1.00000000', 'create_key_000010');
$dominoTable = $service->join($opponent, $dominoTable->id);
$privateSnapshot = $service->snapshot($creator, $dominoTable->id);
if (count($privateSnapshot['state']['hands'][0]) !== 7 || $privateSnapshot['state']['hands'][1] !== [] || $privateSnapshot['state']['hand_counts'][1] !== 7) throw new RuntimeException('A domino snapshot exposed another player\'s hidden hand.');
$unchanged = $service->snapshot($creator, $dominoTable->id, (int) $privateSnapshot['version']);
if (empty($unchanged['unchanged'])) throw new RuntimeException('A version-matched game snapshot did not use the lightweight response path.');
$snapshotStarted = hrtime(true);
for ($request = 0; $request < 200; $request++) $service->snapshot($creator, $dominoTable->id, (int) $privateSnapshot['version']);
$snapshotMs = (hrtime(true) - $snapshotStarted) / 1_000_000;
fwrite(STDOUT, sprintf("200 unchanged table snapshots completed in %.1f ms (local SQLite).\n", $snapshotMs));
$message = TableGameMessage::create(['table_game_id' => $dominoTable->id, 'user_id' => $creator->id, 'kind' => 'text', 'body' => 'Good game!']);
TableGameMessageReaction::create(['message_id' => $message->id, 'user_id' => $opponent->id, 'emoji' => '👏']);
$chatRequest = Illuminate\Http\Request::create('/user/tables/' . $dominoTable->id . '/chat', 'GET');
$chatRequest->setUserResolver(fn () => $opponent);
$chatResponse = (new TableGameController())->messages($chatRequest, $dominoTable->id);
$chatPayload = json_decode($chatResponse->getContent(), true);
if (($chatPayload['messages'][0]['body'] ?? null) !== 'Good game!' || ($chatPayload['messages'][0]['reactions'][0]['emoji'] ?? null) !== '👏') throw new RuntimeException('Table chat did not return ordered text and emoji reactions.');
$reactionRequest = Illuminate\Http\Request::create('/user/tables/' . $dominoTable->id . '/chat/' . $message->id . '/reaction', 'POST', ['emoji' => '❤️']);
$reactionRequest->setUserResolver(fn () => $creator);
$reactionResponse = (new TableGameController())->react($reactionRequest, $dominoTable->id, (int) $message->id);
$reactionPayload = json_decode($reactionResponse->getContent(), true);
if (!in_array('❤️', array_column($reactionPayload['reactions'] ?? [], 'emoji'), true)) throw new RuntimeException('A player could not add an emoji reaction to table chat.');
Storage::fake('local');
$uploadRequest = Illuminate\Http\Request::create('/user/tables/' . $dominoTable->id . '/chat', 'POST', ['kind' => 'file'], [], ['attachment' => Illuminate\Http\UploadedFile::fake()->createWithContent('table-notes.txt', 'Share this table note.')]);
$uploadRequest->setUserResolver(fn () => $creator);
$uploadResponse = (new TableGameController())->sendMessage($uploadRequest, $dominoTable->id);
$uploadPayload = json_decode($uploadResponse->getContent(), true);
if ($uploadResponse->getStatusCode() !== 201 || empty($uploadPayload['message']['attachment_url'])) throw new RuntimeException('Table chat file sharing did not store a private attachment.');
$downloadRequest = Illuminate\Http\Request::create('/user/tables/' . $dominoTable->id . '/chat/' . $uploadPayload['message']['id'] . '/attachment', 'GET');
$downloadRequest->setUserResolver(fn () => $opponent);
$downloadResponse = (new TableGameController())->attachment($downloadRequest, $dominoTable->id, (int) $uploadPayload['message']['id']);
if ($downloadResponse->getStatusCode() !== 200) throw new RuntimeException('A table participant could not retrieve a shared attachment.');
Storage::disk('local')->deleteDirectory('table-game-chat/' . $dominoTable->id);
$guest = $insertUser('table_chat_guest', '5.00000000');
$guestRequest = Illuminate\Http\Request::create('/user/tables/' . $dominoTable->id . '/chat', 'GET');
$guestRequest->setUserResolver(fn () => $guest);
try { (new TableGameController())->messages($guestRequest, $dominoTable->id); throw new RuntimeException('A nonparticipant read private table chat.'); }
catch (Symfony\Component\HttpKernel\Exception\HttpException) {}
$service->forceClose($dominoTable->id);
$seaTable = $service->create($creator, 'sea_battle', 2, '1.00000000', 'create_key_000011');
$seaTable = $service->join($opponent, $seaTable->id);
$seaSnapshot = $service->snapshot($creator, $seaTable->id);
if (isset($seaSnapshot['state']['boards']) || count($seaSnapshot['state']['own_board']) !== 10) throw new RuntimeException('Sea Battle did not keep the opponent fleet server-private.');
$service->forceClose($seaTable->id);
$poorUser = $insertUser('table_low_balance', '0.50000000');
try {
    $service->create($poorUser, 'tic_tac_toe', 2, '1.00000000', 'create_key_000006');
    throw new RuntimeException('A user with insufficient balance created a table.');
} catch (InvalidArgumentException) {
    $assertBalance($poorUser, '0.50000000', 'A failed table creation changed the user balance.');
}

$chatMigration->down();
$migration->down();
$frameworkMigration->down();
DB::disconnect();
@unlink($dbPath);
fwrite(STDOUT, "Table-game migration, wallet, payout, idempotency, and refund integration checks passed.\n");
