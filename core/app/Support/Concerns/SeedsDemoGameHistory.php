<?php

namespace App\Support\Concerns;

use App\Constants\Status;
use App\Games\TableGames\TableGameRules;
use App\Games\TableGames\TableGameSettlementMath;
use App\Models\Game;
use App\Models\GameLog;
use App\Models\TableGame;
use App\Models\TableGamePlayer;
use App\Models\TableGameSetting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Shared by the demo-game-history seed migrations. Pulled out as a trait rather than kept
 * inline in one migration because a first attempt at this (2026_10_23_000001) used too broad
 * an idempotency check - "does any demo user have any game_logs row at all" - which false-
 * positived the moment the site owner logged into a demo account themselves to test a real
 * feature, silently skipping the whole seed with no error. The fix (2026_10_23_000002) needs
 * the exact same seeding logic with a precise marker-based check instead, so it lives here
 * once and both migrations call it.
 */
trait SeedsDemoGameHistory
{
    /**
     * The @demo.winxpot.test email filter (2026_10_20_000012_seed_demo_users_and_feedback)
     * stopped working on winxpot.com once the site owner manually edited every demo account's
     * email and username to look realistic (2026_10_21_000016_rename_demo_usernames only
     * touched usernames; the email change was a separate manual edit). What didn't change is
     * their password - all 50 were created with the exact same Hash::make('Demo@12345') call,
     * and nothing since has had reason to touch it. Hash::check() (not a direct string/hash
     * comparison - bcrypt salts differently per hash) finds them by that shared password
     * regardless of what their email/username now look like, in addition to the original
     * email-pattern match for any install where it still applies.
     */
    protected function demoUsers()
    {
        $byEmail = User::where('email', 'like', '%@demo.winxpot.test')->pluck('id');
        $byPassword = User::whereNotIn('id', $byEmail)->get()
            ->filter(fn (User $user) => $user->password && Hash::check('Demo@12345', $user->password))
            ->pluck('id');

        return User::whereIn('id', $byEmail->merge($byPassword)->unique())->get();
    }

    /** A game_logs row this trait created always has this in user_select - never a real value. */
    protected function demoGameLogMarker(): string
    {
        return 'seed_demo_history_v1';
    }

    /** A table_games row this trait created always has its create_idempotency_key start with this. */
    protected function demoTableGamePrefix(): string
    {
        return 'seed_demo_history_v1_';
    }

    protected function demoNormalHistoryAlreadySeeded(): bool
    {
        return Schema::hasTable('game_logs') && GameLog::where('user_select', $this->demoGameLogMarker())->exists();
    }

    protected function demoTableHistoryAlreadySeeded(): bool
    {
        return Schema::hasTable('table_games') && TableGame::where('create_idempotency_key', 'like', $this->demoTableGamePrefix() . '%')->exists();
    }

    protected function seedNormalGameHistory($demoUsers): void
    {
        $games = Game::active()->get();
        if ($games->isEmpty()) return;

        $hasCurrencyCol = Schema::hasColumn('game_logs', 'currency_id');
        $now = now();
        $marker = $this->demoGameLogMarker();

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
            $log->user_select = $marker;
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

    protected function seedTableGameHistory($demoUsers): void
    {
        $hasTableCurrency = Schema::hasColumn('table_games', 'currency_id');
        $hasPlayerCurrency = Schema::hasColumn('table_game_players', 'currency_id');
        $hasRulesVersion = Schema::hasColumn('table_games', 'rules_version');
        $now = now();
        $prefix = $this->demoTableGamePrefix();

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
                    'create_idempotency_key' => $prefix . Str::random(20),
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
                        'stake_debit_key' => $prefix . 'stake_' . Str::random(14),
                        'settlement_key' => $prefix . 'settle_' . Str::random(14),
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

    protected function removeSeededNormalGameHistory(): void
    {
        if (Schema::hasTable('game_logs')) GameLog::where('user_select', $this->demoGameLogMarker())->delete();
    }

    protected function removeSeededTableGameHistory(): void
    {
        if (!Schema::hasTable('table_games')) return;
        $tableIds = TableGame::where('create_idempotency_key', 'like', $this->demoTableGamePrefix() . '%')->pluck('id');
        if ($tableIds->isEmpty()) return;
        if (Schema::hasTable('table_game_players')) TableGamePlayer::whereIn('table_game_id', $tableIds)->delete();
        TableGame::whereIn('id', $tableIds)->delete();
    }
}
