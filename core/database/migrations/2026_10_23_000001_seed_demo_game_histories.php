<?php

use App\Constants\Status;
use App\Games\TableGames\TableGameRules;
use App\Games\TableGames\TableGameSettlementMath;
use App\Models\Game;
use App\Models\GameLog;
use App\Models\TableGame;
use App\Models\TableGamePlayer;
use App\Models\TableGameSetting;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Backfills "Last Games" / "Recent Big Wins" / the table-games Hall of Fame and weekly
     * leaderboard with realistic activity so those sections aren't empty before real play
     * happens, using the 50 @demo.winxpot.test users already seeded by
     * 2026_10_20_000012_seed_demo_users_and_feedback.php. Purely historical/display data: it
     * writes finished game_logs and already-settled table_games/table_game_players rows
     * directly, without touching any user's real balance or the wallet transaction ledger -
     * settlement_amount on a seeded table_game_players row is a record of what the demo
     * "would have" won, not a live credit.
     */
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasTable('game_logs')) return;

        $demoUsers = User::where('email', 'like', '%@demo.winxpot.test')->get();
        if ($demoUsers->isEmpty()) return;
        if (GameLog::whereIn('user_id', $demoUsers->pluck('id'))->exists()) return;

        $this->seedNormalGameHistory($demoUsers);

        if (Schema::hasTable('table_games') && Schema::hasTable('table_game_players')) {
            $this->seedTableGameHistory($demoUsers);
        }
    }

    private function seedNormalGameHistory($demoUsers): void
    {
        $games = Game::active()->get();
        if ($games->isEmpty()) return;

        $hasCurrencyCol = Schema::hasColumn('game_logs', 'currency_id');
        $now = now();

        // Oldest first so auto-increment id order (what "Last Games" actually sorts by) lines
        // up with created_at order, same as organic play would produce.
        $offsets = [];
        for ($i = 0; $i < 220; $i++) $offsets[] = random_int(1, 10080); // spread across the last 7 days
        rsort($offsets);

        foreach ($offsets as $minutesAgo) {
            $user = $demoUsers->random();
            $game = $games->random();
            $isWin = random_int(1, 100) <= 40;
            $invest = number_format(random_int(50, 5000) / 100, 2, '.', '');
            $winAmo = $isWin ? number_format(((float) $invest) * (random_int(150, 400) / 100), 8, '.', '') : '0.00000000';

            $log = new GameLog();
            $log->user_id = $user->id;
            if ($hasCurrencyCol) $log->currency_id = $user->currency_id;
            $log->game_id = $game->id;
            $log->demo_play = Status::NO;
            $log->user_select = 'seed';
            $log->result = 'seed';
            $log->status = Status::GAME_FINISHED;
            $log->win_status = $isWin ? Status::WIN : Status::LOSS;
            $log->invest = $invest;
            $log->win_amo = $winAmo;
            $log->mines = 0;
            $log->mine_available = 0;
            $log->created_at = $now->copy()->subMinutes($minutesAgo);
            $log->updated_at = $log->created_at;
            $log->save();
        }
    }

    private function seedTableGameHistory($demoUsers): void
    {
        $hasTableCurrency = Schema::hasColumn('table_games', 'currency_id');
        $hasPlayerCurrency = Schema::hasColumn('table_game_players', 'currency_id');
        $hasRulesVersion = Schema::hasColumn('table_games', 'rules_version');
        $now = now();

        $minutesAgoPool = [];
        for ($i = 0; $i < 90; $i++) {
            // Keep roughly a third inside the current week so the weekly leaderboard has data,
            // and the rest within the same few days as the seeded normal-game history (not a
            // long tail back to 30 days) so "Last Games" naturally interleaves both game types
            // instead of the far more numerous normal-game rows crowding every table game out
            // of the latest-10 window.
            $minutesAgoPool[] = $i % 3 === 0
                ? random_int(1, max(1, (int) now()->diffInMinutes(now()->startOfWeek())))
                : random_int(1, 4 * 1440);
        }
        rsort($minutesAgoPool);

        foreach (TableGameRules::AVAILABLE as $gameType) {
            $definition = TableGameRules::GAMES[$gameType];
            $setting = TableGameSetting::where('game_type', $gameType)->first();
            $minStake = (float) ($setting->min_stake ?? 1);
            $maxStake = min((float) ($setting->max_stake ?? 1000), max($minStake * 20, $minStake + 50));
            $commissionPercent = (string) ($setting->commission_percent ?? 5);

            $gamesForType = (int) floor(count($minutesAgoPool) / count(TableGameRules::AVAILABLE)) + 1;

            for ($g = 0; $g < $gamesForType && !empty($minutesAgoPool); $g++) {
                $minutesAgo = array_pop($minutesAgoPool);
                $seatCount = $definition['min'] === $definition['max']
                    ? $definition['max']
                    : random_int($definition['min'], $definition['max']);

                $players = $demoUsers->random(min($seatCount, $demoUsers->count()))->values();
                if ($players->count() < 2) continue;

                $stake = number_format(random_int((int) ($minStake * 100), (int) ($maxStake * 100)) / 100, 8, '.', '');
                $finishedAt = $now->copy()->subMinutes($minutesAgo);
                $startedAt = $finishedAt->copy()->subMinutes(random_int(2, 25));

                $tableData = [
                    'id' => (string) Str::uuid(),
                    'game_type' => $gameType,
                    'creator_id' => $players->first()->id,
                    'create_idempotency_key' => 'seed_' . Str::random(24),
                    'player_limit' => $players->count(),
                    'stake' => $stake,
                    'status' => 'finished',
                    'version' => $players->count() * 3,
                    'result' => null, // set below once the winner seat is known
                    'settings' => ['commission_percent' => (float) $commissionPercent, 'turn_seconds' => (int) ($setting->turn_seconds ?? 60)],
                    'started_at' => $startedAt,
                    'finished_at' => $finishedAt,
                    'created_at' => $startedAt,
                    'updated_at' => $finishedAt,
                ];
                if ($hasRulesVersion) $tableData['rules_version'] = in_array($gameType, ['ludo', 'whot'], true) ? 2 : 1;
                if ($hasTableCurrency) $tableData['currency_id'] = $players->first()->currency_id;

                $winnerSeat = random_int(0, $players->count() - 1);
                $tableData['result'] = 'winner:' . $winnerSeat;

                $table = TableGame::create($tableData);

                $payout = TableGameSettlementMath::payout(
                    $players->map(fn () => $stake)->all(),
                    $commissionPercent
                );

                foreach ($players as $seat => $user) {
                    $isWinner = $seat === $winnerSeat;
                    $playerData = [
                        'table_game_id' => $table->id,
                        'user_id' => $user->id,
                        'seat' => $seat,
                        'stake' => $stake,
                        'wallet_stake' => $stake,
                        'wallet_rate_to_usd' => '1.000000000000',
                        'table_rate_to_usd' => '1.000000000000',
                        'status' => 'seated',
                        'missed_turns' => 0,
                        'last_seen_at' => $finishedAt,
                        'stake_debit_key' => 'seed_stake_' . Str::random(20),
                        'settlement_key' => 'seed_settle_' . Str::random(20),
                        'settlement_amount' => $isWinner ? $payout['winner_amount'] : '0.00000000',
                        'settlement_type' => $isWinner ? 'payout' : 'loss',
                        'created_at' => $startedAt,
                        'updated_at' => $finishedAt,
                    ];
                    if ($hasPlayerCurrency) $playerData['currency_id'] = $user->currency_id;
                    TableGamePlayer::create($playerData);
                }
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) return;

        $demoUserIds = User::where('email', 'like', '%@demo.winxpot.test')->pluck('id');
        if ($demoUserIds->isEmpty()) return;

        if (Schema::hasTable('game_logs')) GameLog::whereIn('user_id', $demoUserIds)->delete();

        if (Schema::hasTable('table_game_players')) {
            $tableIds = TableGamePlayer::whereIn('user_id', $demoUserIds)->pluck('table_game_id')->unique();
            TableGamePlayer::whereIn('table_game_id', $tableIds)->delete();
            if (Schema::hasTable('table_games')) TableGame::whereIn('id', $tableIds)->delete();
        }
    }
};
