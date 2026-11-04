<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Classic mini-leagues (join code), head-to-head leagues (round-robin fixtures between members),
// and the global leaderboard (a singleton league every team auto-joins - see
// FantasyLeagueSeeder/FantasyTeamService::createTeam()).
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ff_leagues')) {
            Schema::create('ff_leagues', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('season_id');
                $table->string('name', 80);
                $table->string('type', 10); // classic, h2h, global
                $table->string('join_code', 10)->nullable()->unique();
                $table->unsignedBigInteger('creator_team_id')->nullable();
                $table->unsignedInteger('max_members')->nullable();
                $table->timestamps();

                $table->index(['season_id', 'type']);
            });
        }

        if (!Schema::hasTable('ff_league_members')) {
            Schema::create('ff_league_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('league_id');
                $table->unsignedBigInteger('team_id');
                $table->timestamp('joined_at');
                $table->timestamps();

                $table->unique(['league_id', 'team_id']);
            });
        }

        if (!Schema::hasTable('ff_h2h_fixtures')) {
            Schema::create('ff_h2h_fixtures', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('league_id');
                $table->unsignedBigInteger('gameweek_id');
                $table->unsignedBigInteger('team_a_id');
                $table->unsignedBigInteger('team_b_id');
                $table->integer('team_a_points')->nullable();
                $table->integer('team_b_points')->nullable();
                $table->string('result', 10)->default('pending'); // pending, a_win, b_win, draw
                $table->timestamps();

                $table->unique(['league_id', 'gameweek_id', 'team_a_id']);
                $table->index(['league_id', 'gameweek_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ff_h2h_fixtures');
        Schema::dropIfExists('ff_league_members');
        Schema::dropIfExists('ff_leagues');
    }
};
