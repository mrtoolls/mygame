<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A user's fantasy squad for one season: the 15-player roster, captaincy, chip usage (one per
// season each, enforced by the unique on team_id+chip_type below), per-gameweek point history, a
// log of every individual transfer, and the daily player-price-change history.
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ff_teams')) {
            Schema::create('ff_teams', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('season_id');
                $table->string('name', 60);
                $table->decimal('budget_remaining', 6, 1)->default(100.0);
                $table->unsignedTinyInteger('free_transfers_banked')->default(1);
                $table->integer('total_points')->default(0);
                $table->unsignedInteger('overall_rank')->nullable();
                $table->boolean('fresh_start_active')->default(false); // permanent unlimited-free-transfers chip, once activated stays on
                $table->unsignedBigInteger('one_week_reset_gameweek_id')->nullable(); // set while that one gameweek's unlimited-transfer window is open
                $table->json('one_week_reset_snapshot')->nullable(); // squad snapshot to revert to after that gameweek
                $table->timestamps();

                $table->unique(['user_id', 'season_id']);
                $table->index('season_id');
            });
        }

        if (!Schema::hasTable('ff_team_players')) {
            Schema::create('ff_team_players', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('team_id');
                $table->unsignedBigInteger('player_id');
                $table->decimal('purchase_price', 5, 1);
                $table->string('squad_role', 10)->default('bench'); // starting, bench
                $table->unsignedTinyInteger('bench_order')->nullable(); // 0..3, bench batting order for auto-subs
                $table->boolean('is_captain')->default(false);
                $table->boolean('is_vice_captain')->default(false);
                $table->timestamps();

                $table->unique(['team_id', 'player_id']);
                $table->index('team_id');
            });
        }

        if (!Schema::hasTable('ff_chip_uses')) {
            Schema::create('ff_chip_uses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('team_id');
                $table->unsignedBigInteger('season_id');
                $table->unsignedBigInteger('gameweek_id');
                $table->string('chip_type', 20); // deep_bench, super_captain, fresh_start, one_week_reset
                $table->timestamp('used_at');
                $table->timestamp('reverted_at')->nullable();
                $table->timestamps();

                $table->unique(['team_id', 'chip_type']); // once per season, per chip
                $table->unique(['team_id', 'gameweek_id']); // only one chip active per gameweek
            });
        }

        if (!Schema::hasTable('ff_team_gameweek_history')) {
            Schema::create('ff_team_gameweek_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('team_id');
                $table->unsignedBigInteger('gameweek_id');
                $table->integer('points')->default(0);
                $table->integer('bench_points')->default(0);
                $table->unsignedTinyInteger('transfers_made')->default(0);
                $table->integer('transfer_cost')->default(0);
                $table->string('chip_used', 20)->nullable();
                $table->unsignedInteger('overall_rank_after')->nullable();
                $table->timestamps();

                $table->unique(['team_id', 'gameweek_id']);
            });
        }

        if (!Schema::hasTable('ff_transfers')) {
            Schema::create('ff_transfers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('team_id');
                $table->unsignedBigInteger('gameweek_id');
                $table->unsignedBigInteger('player_out_id')->nullable();
                $table->unsignedBigInteger('player_in_id')->nullable();
                $table->decimal('sell_price', 5, 1)->nullable();
                $table->decimal('buy_price', 5, 1)->nullable();
                $table->boolean('is_free')->default(true);
                $table->unsignedTinyInteger('cost')->default(0); // points cost, 0 or 4
                $table->timestamps();

                $table->index(['team_id', 'gameweek_id']);
            });
        }

        if (!Schema::hasTable('ff_player_price_history')) {
            Schema::create('ff_player_price_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('player_id');
                $table->unsignedBigInteger('gameweek_id');
                $table->decimal('old_price', 5, 1);
                $table->decimal('new_price', 5, 1);
                $table->integer('net_transfers')->default(0);
                $table->timestamps();

                $table->unique(['player_id', 'gameweek_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ff_player_price_history');
        Schema::dropIfExists('ff_transfers');
        Schema::dropIfExists('ff_team_gameweek_history');
        Schema::dropIfExists('ff_chip_uses');
        Schema::dropIfExists('ff_team_players');
        Schema::dropIfExists('ff_teams');
    }
};
