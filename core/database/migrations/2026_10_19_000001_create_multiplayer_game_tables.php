<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_games', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('game_type', 40)->index();
            $table->foreignId('creator_id')->constrained('users')->restrictOnDelete();
            $table->string('create_idempotency_key', 64)->unique();
            $table->unsignedTinyInteger('player_limit');
            $table->decimal('stake', 28, 8)->default(0);
            $table->string('status', 20)->default('waiting')->index();
            $table->json('state')->nullable();
            $table->foreignId('current_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(0);
            $table->timestamp('turn_started_at')->nullable();
            $table->timestamp('turn_deadline_at')->nullable();
            $table->timestamp('join_deadline_at')->nullable();
            $table->string('result', 40)->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'join_deadline_at']);
            $table->index(['status', 'turn_deadline_at']);
        });

        Schema::create('table_game_players', function (Blueprint $table) {
            $table->id();
            $table->uuid('table_game_id');
            // Preserve an auditable seat and settlement recipient for every stake movement.
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('seat');
            $table->decimal('stake', 28, 8)->default(0);
            $table->string('status', 20)->default('seated');
            $table->unsignedTinyInteger('missed_turns')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->json('private_state')->nullable();
            $table->string('stake_debit_key', 64)->nullable()->unique();
            $table->string('settlement_key', 64)->nullable()->unique();
            $table->timestamps();
            $table->foreign('table_game_id')->references('id')->on('table_games')->cascadeOnDelete();
            $table->unique(['table_game_id', 'user_id']);
            $table->index(['table_game_id', 'seat', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('table_game_invites', function (Blueprint $table) {
            $table->id();
            $table->uuid('table_game_id');
            $table->foreignId('inviter_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('invitee_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->foreign('table_game_id')->references('id')->on('table_games')->cascadeOnDelete();
            $table->unique(['table_game_id', 'invitee_id']);
            $table->index(['invitee_id', 'status', 'expires_at']);
        });

        Schema::create('table_game_moves', function (Blueprint $table) {
            $table->id();
            $table->uuid('table_game_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('action', 40);
            $table->json('public_data')->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('table_game_id')->references('id')->on('table_games')->cascadeOnDelete();
            $table->index(['table_game_id', 'version']);
        });

        Schema::create('table_game_settings', function (Blueprint $table) {
            $table->id();
            $table->string('game_type', 40)->nullable()->unique();
            $table->boolean('enabled')->default(true);
            $table->decimal('commission_percent', 5, 2)->default(5.00);
            $table->decimal('min_stake', 28, 8)->default(1);
            $table->decimal('max_stake', 28, 8)->default(1000);
            $table->unsignedSmallInteger('turn_seconds')->default(60);
            $table->unsignedSmallInteger('empty_timeout_minutes')->default(10);
            $table->unsignedSmallInteger('max_open_tables_per_user')->default(5);
            $table->unsignedSmallInteger('max_concurrent_tables')->default(500);
            $table->timestamps();
        });

        Schema::create('table_game_commissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('table_game_id')->unique();
            $table->string('game_type', 40)->index();
            $table->decimal('amount', 28, 8);
            $table->decimal('rate_percent', 5, 2);
            $table->string('status', 20)->default('retained');
            $table->timestamps();
            $table->foreign('table_game_id')->references('id')->on('table_games')->cascadeOnDelete();
        });

        Schema::create('table_game_user_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('banned')->default(false);
            $table->unsignedSmallInteger('max_open_tables')->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_game_moves');
        Schema::dropIfExists('table_game_invites');
        Schema::dropIfExists('table_game_players');
        Schema::dropIfExists('table_game_settings');
        Schema::dropIfExists('table_game_commissions');
        Schema::dropIfExists('table_game_user_limits');
        Schema::dropIfExists('table_games');
    }
};
