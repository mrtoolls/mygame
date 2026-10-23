<?php

use App\Models\User;
use App\Support\Concerns\SeedsDemoGameHistory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use SeedsDemoGameHistory;

    /**
     * Backfills "Last Games" / "Recent Big Wins" / the table-games Hall of Fame and weekly
     * leaderboard with realistic activity so those sections aren't empty before real play
     * happens, using the 50 @demo.winxpot.test users already seeded by
     * 2026_10_20_000012_seed_demo_users_and_feedback.php. Purely historical/display data: it
     * writes finished game_logs and already-settled table_games/table_game_players rows
     * directly, without touching any user's real balance or the wallet transaction ledger -
     * settlement_amount on a seeded table_game_players row is a record of what the demo
     * "would have" won, not a live credit.
     *
     * NOTE: on winxpot.com this migration ran but seeded nothing, because its idempotency
     * check ("does any demo user have any game_logs row at all") false-positived the moment
     * the site owner logged into a demo account to test a real feature - see
     * 2026_10_23_000002_reseed_demo_game_histories.php, which uses a precise marker-based
     * check instead and actually carries the data on this install. This file is left using the
     * same precise check (via the shared trait) for correctness on any fresh install, even
     * though Laravel's migrations table means it won't re-run here.
     */
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasTable('game_logs')) return;

        $demoUsers = User::where('email', 'like', '%@demo.winxpot.test')->get();
        if ($demoUsers->isEmpty()) return;

        if (!$this->demoNormalHistoryAlreadySeeded()) $this->seedNormalGameHistory($demoUsers);

        if (Schema::hasTable('table_games') && Schema::hasTable('table_game_players') && !$this->demoTableHistoryAlreadySeeded()) {
            $this->seedTableGameHistory($demoUsers);
        }
    }

    public function down(): void
    {
        $this->removeSeededNormalGameHistory();
        $this->removeSeededTableGameHistory();
    }
};
