<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Budget tiers for Quick Contests and Live Duels: every entrant at a given tier drafts under the
// exact same budget (never purchasable with coins - see ff_budget_tiers below), so a higher entry
// fee buys entry into a higher-stakes contest, never a bigger budget than anyone else at that
// tier. A "quick" contest type is added alongside the existing h2h/gameweek/sponsored ones; unlike
// those, which score the entrant's one season-long ff_teams squad, a quick contest is scored from
// a squad built just for it (ff_contest_squads) so a user can enter several tiers without having
// to touch their main team.
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('general_settings', 'ff_budget_tiers')) {
            Schema::table('general_settings', function (Blueprint $table) {
                $table->json('ff_budget_tiers')->nullable(); // admin-editable list, e.g. [100, 120, 150]
            });
        }
        if (!Schema::hasColumn('general_settings', 'ff_tier_min_fees')) {
            Schema::table('general_settings', fn (Blueprint $table) => $table->json('ff_tier_min_fees')->nullable());
        }

        if (!Schema::hasColumn('ff_contests', 'budget_tier')) {
            Schema::table('ff_contests', function (Blueprint $table) {
                $table->decimal('budget_tier', 6, 1)->nullable()->after('type'); // only meaningful for type=quick
            });
        }

        if (!Schema::hasTable('ff_contest_squads')) {
            Schema::create('ff_contest_squads', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('contest_id');
                $table->unsignedBigInteger('user_id');
                $table->string('name', 60);
                $table->decimal('budget_tier', 6, 1);
                $table->decimal('budget_remaining', 6, 1);
                $table->timestamps();

                $table->unique(['contest_id', 'user_id']);
                $table->index('user_id');
            });
        }

        if (!Schema::hasColumn('ff_contest_entries', 'contest_squad_id')) {
            Schema::table('ff_contest_entries', function (Blueprint $table) {
                $table->unsignedBigInteger('contest_squad_id')->nullable()->after('team_id'); // set instead of team_id for type=quick entries
            });
        }

        if (!Schema::hasTable('ff_contest_squad_players')) {
            Schema::create('ff_contest_squad_players', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('contest_squad_id');
                $table->unsignedBigInteger('player_id');
                $table->decimal('purchase_price', 5, 1);
                $table->string('squad_role', 10)->default('bench'); // starting, bench
                $table->unsignedTinyInteger('bench_order')->nullable();
                $table->boolean('is_captain')->default(false);
                $table->boolean('is_vice_captain')->default(false);
                $table->timestamps();

                $table->unique(['contest_squad_id', 'player_id']);
                $table->index('contest_squad_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ff_contest_squad_players');
        Schema::dropIfExists('ff_contest_squads');
        if (Schema::hasColumn('ff_contest_entries', 'contest_squad_id')) {
            Schema::table('ff_contest_entries', fn (Blueprint $table) => $table->dropColumn('contest_squad_id'));
        }
        if (Schema::hasColumn('ff_contests', 'budget_tier')) {
            Schema::table('ff_contests', fn (Blueprint $table) => $table->dropColumn('budget_tier'));
        }
        if (Schema::hasColumn('general_settings', 'ff_budget_tiers')) {
            Schema::table('general_settings', fn (Blueprint $table) => $table->dropColumn('ff_budget_tiers'));
        }
        if (Schema::hasColumn('general_settings', 'ff_tier_min_fees')) {
            Schema::table('general_settings', fn (Blueprint $table) => $table->dropColumn('ff_tier_min_fees'));
        }
    }
};
