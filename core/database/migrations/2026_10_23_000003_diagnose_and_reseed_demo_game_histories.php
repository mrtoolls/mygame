<?php

use App\Models\Game;
use App\Models\GameLog;
use App\Models\TableGame;
use App\Models\User;
use App\Support\Concerns\SeedsDemoGameHistory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use SeedsDemoGameHistory;

    /**
     * 2026_10_23_000002_reseed_demo_game_histories reported "Database update finished" (no
     * exception) but inserted nothing on winxpot.com - a second silent failure, different from
     * the one it was written to fix. Since Laravel only ever runs a given migration file once
     * and that one is now permanently recorded as "ran" regardless of what it actually did,
     * no amount of re-clicking Database Update will make it try again, bug fixed or not. This
     * migration is a fresh file (so it's guaranteed to actually execute) that does the same
     * seed but throws a specific, descriptive exception instead of silently returning whenever
     * a precondition isn't met - so if it still can't seed, "Database Update" will show
     * exactly why instead of reporting false success.
     */
    public function up(): void
    {
        if (!Schema::hasTable('users')) throw new RuntimeException('Cannot seed: users table does not exist.');
        if (!Schema::hasTable('game_logs')) throw new RuntimeException('Cannot seed: game_logs table does not exist.');

        $demoUsers = User::where('email', 'like', '%@demo.winxpot.test')->get();
        if ($demoUsers->isEmpty()) {
            throw new RuntimeException('Cannot seed: no users found with an email ending in @demo.winxpot.test. Has 2026_10_20_000012_seed_demo_users_and_feedback run?');
        }

        if ($this->demoNormalHistoryAlreadySeeded()) {
            $existing = GameLog::where('user_select', $this->demoGameLogMarker())->count();
            if ($existing === 0) {
                throw new RuntimeException('demoNormalHistoryAlreadySeeded() returned true but 0 matching rows exist - marker check logic is broken.');
            }
        } else {
            $activeGames = Game::active()->count();
            if ($activeGames === 0) {
                throw new RuntimeException('Cannot seed normal-game history: Game::active() returned 0 rows. Check the games table has at least one enabled game.');
            }
            $this->seedNormalGameHistory($demoUsers);
            $seeded = GameLog::where('user_select', $this->demoGameLogMarker())->count();
            if ($seeded === 0) {
                throw new RuntimeException("seedNormalGameHistory() ran against {$activeGames} active game(s) but inserted 0 game_logs rows.");
            }
        }

        if (!Schema::hasTable('table_games') || !Schema::hasTable('table_game_players')) {
            throw new RuntimeException('Cannot seed table-game history: table_games or table_game_players does not exist.');
        }

        if ($this->demoTableHistoryAlreadySeeded()) {
            $existing = TableGame::where('create_idempotency_key', 'like', $this->demoTableGamePrefix() . '%')->count();
            if ($existing === 0) {
                throw new RuntimeException('demoTableHistoryAlreadySeeded() returned true but 0 matching rows exist - marker check logic is broken.');
            }
            return;
        }

        $this->seedTableGameHistory($demoUsers);
        $seededTables = TableGame::where('create_idempotency_key', 'like', $this->demoTableGamePrefix() . '%')->count();
        if ($seededTables === 0) {
            throw new RuntimeException('seedTableGameHistory() ran but inserted 0 table_games rows.');
        }
    }

    public function down(): void
    {
        $this->removeSeededNormalGameHistory();
        $this->removeSeededTableGameHistory();
    }
};
