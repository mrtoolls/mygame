<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ff_duels', function (Blueprint $table) {
            $table->id();
            $table->uuid('table_game_id')->unique();
            $table->decimal('budget_tier', 6, 1);
            $table->string('phase', 20)->default('lobby')->index();
            $table->char('seed_commitment', 64);
            $table->text('sealed_seed');
            $table->json('slate');
            $table->json('ready_users')->nullable();
            $table->json('result')->nullable();
            $table->timestamp('ready_started_at')->nullable();
            $table->timestamp('ready_deadline_at')->nullable();
            $table->timestamp('build_started_at')->nullable();
            $table->timestamp('build_deadline_at')->nullable();
            $table->timestamp('live_started_at')->nullable();
            $table->timestamp('live_ends_at')->nullable();
            $table->timestamp('seed_revealed_at')->nullable();
            $table->timestamps();
            $table->foreign('table_game_id')->references('id')->on('table_games')->cascadeOnDelete();
        });

        Schema::create('ff_duel_squads', function (Blueprint $table) {
            $table->id();
            $table->uuid('table_game_id');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->json('player_ids');
            $table->unsignedBigInteger('captain_id');
            $table->boolean('auto_submitted')->default(false);
            $table->timestamp('submitted_at');
            $table->integer('points')->default(0);
            $table->unsignedSmallInteger('goals')->default(0);
            $table->integer('captain_points')->default(0);
            $table->unsignedSmallInteger('place')->nullable();
            $table->timestamps();
            $table->foreign('table_game_id')->references('id')->on('table_games')->cascadeOnDelete();
            $table->unique(['table_game_id', 'user_id']);
            $table->index(['table_game_id', 'place']);
        });

        Schema::create('ff_duel_seats', function (Blueprint $table) {
            $table->id();
            $table->uuid('table_game_id');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('entry_ip', 45)->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamps();
            $table->foreign('table_game_id')->references('id')->on('table_games')->cascadeOnDelete();
            $table->unique(['table_game_id', 'user_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('ff_duel_audits', function (Blueprint $table) {
            $table->id();
            $table->uuid('table_game_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 48)->index();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('table_game_id')->references('id')->on('table_games')->nullOnDelete();
            $table->index(['table_game_id', 'created_at']);
        });

        $settingsColumns = [
            'ff_duel_build_minutes' => fn (Blueprint $table) => $table->unsignedSmallInteger('ff_duel_build_minutes')->default(3),
            'ff_duel_live_minutes' => fn (Blueprint $table) => $table->unsignedSmallInteger('ff_duel_live_minutes')->default(10),
            'ff_duel_fixture_count' => fn (Blueprint $table) => $table->unsignedTinyInteger('ff_duel_fixture_count')->default(4),
            'ff_duel_ready_seconds' => fn (Blueprint $table) => $table->unsignedSmallInteger('ff_duel_ready_seconds')->default(60),
            'ff_duel_lobby_minutes' => fn (Blueprint $table) => $table->unsignedSmallInteger('ff_duel_lobby_minutes')->default(10),
            'ff_duel_fee_percent' => fn (Blueprint $table) => $table->decimal('ff_duel_fee_percent', 5, 2)->default(5),
        ];
        foreach ($settingsColumns as $column => $definition) {
            if (!Schema::hasColumn('general_settings', $column)) Schema::table('general_settings', $definition);
        }

        if (Schema::hasTable('table_game_settings') && !DB::table('table_game_settings')->where('game_type', 'fantasy_duel')->exists()) {
            DB::table('table_game_settings')->insert([
                'game_type' => 'fantasy_duel', 'enabled' => true, 'commission_percent' => 5,
                'min_stake' => 1, 'max_stake' => 1000, 'turn_seconds' => 60,
                'empty_timeout_minutes' => 10, 'max_open_tables_per_user' => 5, 'max_concurrent_tables' => 500,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ff_duel_audits');
        Schema::dropIfExists('ff_duel_seats');
        Schema::dropIfExists('ff_duel_squads');
        Schema::dropIfExists('ff_duels');
        if (Schema::hasTable('general_settings')) {
            foreach (['ff_duel_build_minutes', 'ff_duel_live_minutes', 'ff_duel_fixture_count', 'ff_duel_ready_seconds', 'ff_duel_lobby_minutes', 'ff_duel_fee_percent'] as $column) {
                if (Schema::hasColumn('general_settings', $column)) Schema::table('general_settings', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
        if (Schema::hasTable('table_game_settings')) DB::table('table_game_settings')->where('game_type', 'fantasy_duel')->delete();
    }
};
