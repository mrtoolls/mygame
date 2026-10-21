<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('arena_posts')) {
            Schema::create('arena_posts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->text('body')->nullable();
                $table->string('image')->nullable();
                $table->string('background_style', 20)->nullable();
                $table->string('type', 20)->default('general'); // general|win|challenge|question
                // Not a DB-level foreign key: game_logs predates this project's migrations
                // and isn't something we can verify the exact column type of. Validated
                // in the application layer instead (must belong to the posting user).
                $table->unsignedBigInteger('linked_game_log_id')->nullable();
                $table->string('status', 10)->default('approved'); // pending|approved|rejected
                $table->string('moderated_by_type', 10)->nullable(); // admin|agent
                $table->unsignedBigInteger('moderated_by_id')->nullable();
                $table->string('rejection_reason', 255)->nullable();
                $table->unsignedInteger('upvotes_count')->default(0);
                $table->unsignedInteger('downvotes_count')->default(0);
                $table->unsignedInteger('comments_count')->default(0);
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->index(['status', 'created_at']);
            });
        }

        if (Schema::hasTable('general_settings') && !Schema::hasColumn('general_settings', 'arena_post_approval_required')) {
            Schema::table('general_settings', function (Blueprint $table) {
                $table->boolean('arena_post_approval_required')->default(true);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('arena_posts');

        if (Schema::hasTable('general_settings') && Schema::hasColumn('general_settings', 'arena_post_approval_required')) {
            Schema::table('general_settings', function (Blueprint $table) {
                $table->dropColumn('arena_post_approval_required');
            });
        }
    }
};
