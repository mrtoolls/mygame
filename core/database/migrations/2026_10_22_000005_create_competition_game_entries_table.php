<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('competition_game_entries')) {
            return;
        }

        Schema::create('competition_game_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->uuid('table_game_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('game_type', 40);
            $table->string('result', 20)->nullable(); // win | draw | loss (table_league)
            $table->json('opponent_ids');
            $table->decimal('stake_usd', 24, 8)->nullable();
            $table->decimal('counted_usd', 24, 8)->nullable(); // wager_race / lucky_draw metric
            $table->decimal('base_points', 10, 4)->nullable(); // table_league, pre pair-cap scaling
            // Fraction of this game's opponents NOT already pair-capped out. 1-vs-1 games are
            // binary (0 or 1); multiplayer games scale proportionally rather than all-or-nothing.
            $table->decimal('uncapped_fraction', 6, 4)->default(1.0);
            $table->decimal('points_earned', 10, 4)->nullable(); // base_points * uncapped_fraction
            // True only when uncapped_fraction == 1. Win-rate / distinct-opponent tie-breaks
            // read only fully_counted rows so a partially-capped game can't inflate them.
            $table->boolean('fully_counted')->default(true);
            $table->timestamp('finished_at'); // table_games.finished_at - authoritative for window + ordering
            $table->timestamp('counted_at');
            $table->timestamps();

            $table->unique(['competition_id', 'table_game_id', 'user_id'], 'comp_game_entries_unique');
            $table->index(['competition_id', 'user_id'], 'comp_game_entries_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_game_entries');
    }
};
