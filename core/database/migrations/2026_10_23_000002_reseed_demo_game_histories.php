<?php

use App\Models\User;
use App\Support\Concerns\SeedsDemoGameHistory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use SeedsDemoGameHistory;

    /**
     * Corrective re-run of 2026_10_23_000001_seed_demo_game_histories: that migration ran
     * (and is recorded as run, so it will never run again on its own) but seeded nothing on
     * winxpot.com, because its idempotency check - "does any demo user already have any
     * game_logs row at all" - false-positived the moment the site owner logged into a demo
     * account (@demo.winxpot.test, password documented in
     * 2026_10_20_000012_seed_demo_users_and_feedback.php) to test a real feature, which wrote
     * a game_logs row for that user and made the whole migration look "already done" to that
     * check. This migration performs the exact same seed, but checks for this trait's own
     * marker (user_select / create_idempotency_key prefix) instead of "any activity at all" -
     * safe to ship even to an install where 2026_10_23_000001 already succeeded normally,
     * since the precise check means it simply does nothing there.
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
