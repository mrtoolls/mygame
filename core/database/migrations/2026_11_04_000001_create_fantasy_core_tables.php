<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The virtual football league's own world: fictional clubs and players, seasons/gameweeks,
// fixtures, and the per-fixture event log + box score that both the match simulation and the
// fantasy scoring engine read from. No real league/club/player names ever appear here - club and
// player identities are entirely generated (see FantasyLeagueGenerator).
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ff_clubs')) {
            Schema::create('ff_clubs', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->string('name', 60);
                $table->string('short_code', 3);
                $table->string('color_primary', 7);
                $table->string('color_secondary', 7);
                $table->string('pattern', 20)->default('solid'); // solid, stripes, hoops, halves
                $table->decimal('strength_rating', 5, 2)->default(70);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ff_players')) {
            Schema::create('ff_players', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('club_id');
                $table->string('name', 80);
                $table->string('position', 3); // GK, DEF, MID, FWD
                $table->decimal('rating', 5, 2)->default(65);
                $table->decimal('price', 5, 1)->default(4.5);
                $table->string('status', 15)->default('fit'); // fit, injured, suspended
                $table->date('status_until')->nullable();
                $table->decimal('minutes_tendency', 4, 3)->default(0.700); // 0..1, likelihood of starting a given fixture
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('club_id');
                $table->index(['position', 'is_active']);
            });
        }

        if (!Schema::hasTable('ff_seasons')) {
            Schema::create('ff_seasons', function (Blueprint $table) {
                $table->id();
                $table->string('name', 60);
                $table->string('status', 15)->default('draft'); // draft, active, completed
                $table->unsignedBigInteger('current_gameweek_id')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ff_gameweeks')) {
            Schema::create('ff_gameweeks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('season_id');
                $table->unsignedInteger('number');
                $table->timestamp('deadline_at');
                $table->timestamp('kickoff_start_at');
                $table->timestamp('kickoff_end_at');
                $table->string('status', 15)->default('upcoming'); // upcoming, active, finalizing, final
                $table->timestamp('finalized_at')->nullable();
                $table->timestamps();

                $table->unique(['season_id', 'number']);
                $table->index('status');
            });
        }

        if (!Schema::hasTable('ff_fixtures')) {
            Schema::create('ff_fixtures', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('gameweek_id');
                $table->unsignedBigInteger('home_club_id');
                $table->unsignedBigInteger('away_club_id');
                $table->timestamp('kickoff_at');
                $table->string('status', 15)->default('scheduled'); // scheduled, live, final
                $table->char('commitment_hash', 64)->nullable();
                $table->string('seed_reveal', 191)->nullable();
                $table->unsignedSmallInteger('home_score')->nullable();
                $table->unsignedSmallInteger('away_score')->nullable();
                $table->timestamp('simulated_at')->nullable();
                $table->timestamps();

                $table->index('gameweek_id');
                $table->index(['status', 'kickoff_at']);
            });
        }

        if (!Schema::hasTable('ff_fixture_events')) {
            Schema::create('ff_fixture_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('fixture_id');
                $table->unsignedTinyInteger('minute');
                $table->string('type', 20); // goal, own_goal, penalty_scored, penalty_missed, yellow, red, save, substitution, injury
                $table->unsignedBigInteger('player_id')->nullable();
                $table->unsignedBigInteger('secondary_player_id')->nullable(); // assist provider, or sub-on player
                $table->unsignedBigInteger('club_id')->nullable();
                $table->timestamps();

                $table->index(['fixture_id', 'minute']);
            });
        }

        if (!Schema::hasTable('ff_player_fixture_stats')) {
            Schema::create('ff_player_fixture_stats', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('fixture_id');
                $table->unsignedBigInteger('player_id');
                $table->unsignedBigInteger('club_id');
                $table->unsignedTinyInteger('minutes')->default(0);
                $table->unsignedTinyInteger('goals')->default(0);
                $table->unsignedTinyInteger('assists')->default(0);
                $table->unsignedTinyInteger('yellow_cards')->default(0);
                $table->unsignedTinyInteger('red_cards')->default(0);
                $table->unsignedTinyInteger('own_goals')->default(0);
                $table->unsignedTinyInteger('penalties_saved')->default(0);
                $table->unsignedTinyInteger('penalties_missed')->default(0);
                $table->unsignedTinyInteger('saves')->default(0);
                $table->unsignedTinyInteger('goals_conceded')->default(0);
                $table->boolean('clean_sheet')->default(false);
                $table->unsignedTinyInteger('bonus')->default(0);
                $table->integer('total_points')->default(0);
                $table->boolean('started')->default(false);
                $table->timestamps();

                $table->unique(['fixture_id', 'player_id']);
                $table->index('player_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ff_player_fixture_stats');
        Schema::dropIfExists('ff_fixture_events');
        Schema::dropIfExists('ff_fixtures');
        Schema::dropIfExists('ff_gameweeks');
        Schema::dropIfExists('ff_seasons');
        Schema::dropIfExists('ff_players');
        Schema::dropIfExists('ff_clubs');
    }
};
