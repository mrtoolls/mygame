<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fanning NEW_LIVE_TABLE out to every active user has to happen off the request that creates the
// table - this host has no persistent queue worker (no SSH, deploy is rsync + an admin "Database
// Update" button), so a real ShouldQueue job would just degrade to running inline and block table
// creation. Instead this follows the SAME pattern already proven in routes/console.php (table-
// games:sweep-expired, coin-marketplace:sweep-payment-windows, etc.) - a tiny durable queue row
// processed by a scheduled command in small batches, safe to run every minute via cron on shared
// hosting with no worker process at all. last_user_id is a resume cursor so a run that gets cut
// off (or simply hits its own per-tick batch cap) picks up exactly where it left off next minute,
// rather than restarting from scratch or losing track of who was already notified.
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('table_game_announcement_queue')) return;
        Schema::create('table_game_announcement_queue', function (Blueprint $table) {
            $table->id();
            $table->uuid('table_game_id');
            $table->unsignedBigInteger('last_user_id')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['completed_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_game_announcement_queue');
    }
};
