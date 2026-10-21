<?php

use App\Models\UserFeedback;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * user_feedback.display_name is a masked snapshot of the submitter's username taken at
     * submission time (see FeedbackController@store) - it never updates on its own when the
     * underlying account's username changes later. The demo feedback seed (
     * 2026_10_20_000012_seed_demo_users_and_feedback.php) ran before the demo_* usernames were
     * renamed to real-name-derived handles, so every one of those reviews was still showing the
     * same generic "dem***" snapshot. Regenerate every feedback row's display_name from the
     * account's current username so this stays correct for everyone, not just the demo rows.
     */
    public function up(): void
    {
        if (!Schema::hasTable('user_feedback') || !Schema::hasColumn('user_feedback', 'display_name')) {
            return;
        }

        UserFeedback::with('user')->whereNotNull('user_id')->get()->each(function (UserFeedback $feedback) {
            if ($feedback->user && $feedback->user->username) {
                $feedback->forceFill(['display_name' => substr($feedback->user->username, 0, 3) . '***'])->save();
            }
        });
    }

    public function down(): void
    {
        // Intentionally not reversible - the stale snapshots are not worth restoring.
    }
};
