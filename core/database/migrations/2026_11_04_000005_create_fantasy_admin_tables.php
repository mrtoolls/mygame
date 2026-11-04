<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Editable scoring values (FantasyScoring reads this table, never hardcodes a point value), the
// stat-correction audit trail (admin can re-score a player's fixture stats before contest payout
// only - see FantasyGameweekFinalizer), and a general admin audit log for the rest of the fantasy
// admin surface (league generation, kill switch, settings changes).
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ff_scoring_rules')) {
            Schema::create('ff_scoring_rules', function (Blueprint $table) {
                $table->id();
                $table->string('key', 60)->unique();
                $table->decimal('value', 8, 2);
                $table->timestamps();
            });

            $defaults = [
                'appearance' => 1,
                'appearance_60_plus' => 2,
                'goal_gk' => 6,
                'goal_def' => 6,
                'goal_mid' => 5,
                'goal_fwd' => 4,
                'assist' => 3,
                'clean_sheet_gk' => 4,
                'clean_sheet_def' => 4,
                'clean_sheet_mid' => 1,
                'saves_per_point' => 3, // every N saves = 1 point (GK only)
                'penalty_save' => 5,
                'penalty_miss' => -2,
                'goals_conceded_per_point' => 2, // every N goals conceded = -1 point (GK/DEF only)
                'yellow_card' => -1,
                'red_card' => -3,
                'own_goal' => -2,
                'bonus_max' => 3,
            ];
            $now = now();
            \Illuminate\Support\Facades\DB::table('ff_scoring_rules')->insert(
                collect($defaults)->map(fn ($value, $key) => ['key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now])->values()->all()
            );
        }

        if (!Schema::hasTable('ff_stat_corrections')) {
            Schema::create('ff_stat_corrections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('fixture_id');
                $table->unsignedBigInteger('player_id');
                $table->string('field', 40);
                $table->string('old_value', 40);
                $table->string('new_value', 40);
                $table->unsignedBigInteger('admin_id');
                $table->string('reason', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['fixture_id', 'player_id']);
            });
        }

        if (!Schema::hasTable('ff_audit_log')) {
            Schema::create('ff_audit_log', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->string('action', 60);
                $table->string('subject_type', 40)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['subject_type', 'subject_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ff_audit_log');
        Schema::dropIfExists('ff_stat_corrections');
        Schema::dropIfExists('ff_scoring_rules');
    }
};
