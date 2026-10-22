<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('competitions')) {
            Schema::create('competitions', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('type', 20); // wager_race | biggest_win | lucky_draw | table_league
                $table->text('description')->nullable();
                $table->text('rules')->nullable();
                $table->string('banner_image')->nullable();
                $table->string('status', 20)->default('draft'); // draft|scheduled|live|ended|finalized|paid
                $table->timestamp('start_at');
                $table->timestamp('end_at');
                // Finalization waits for one post-end sweep plus this grace period, so a game
                // still in progress right at end_at isn't unfairly excluded for finishing late.
                $table->unsignedInteger('grace_minutes')->default(30);
                $table->decimal('min_bet_usd', 18, 8)->nullable();
                $table->json('eligible_games')->nullable(); // null = all game types
                $table->unsignedInteger('min_account_age_days')->nullable();
                $table->unsignedInteger('min_games')->nullable();
                $table->unsignedInteger('min_distinct_opponents')->nullable();
                $table->unsignedInteger('max_games_per_opponent_pair')->nullable();
                $table->decimal('ticket_usd_per_ticket', 18, 8)->nullable(); // lucky_draw
                $table->unsignedInteger('max_tickets_per_user')->nullable();  // lucky_draw
                $table->json('type_settings')->nullable(); // table_league's points_config, etc
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('finalized_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('competition_prizes')) {
            Schema::create('competition_prizes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('rank');
                $table->decimal('amount_usd', 18, 8);
                $table->timestamps();
                $table->unique(['competition_id', 'rank']);
            });
        }

        if (!Schema::hasTable('competition_results')) {
            Schema::create('competition_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
                // restrict, not cascade: a paid-prize record must never silently disappear
                // because the user's account was later deleted.
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->unsignedInteger('rank');
                $table->decimal('metric_value', 24, 8); // points / usd / multiplier / tickets
                $table->string('metric_label', 20)->nullable();
                $table->decimal('prize_amount_usd', 18, 8)->nullable();
                $table->unsignedBigInteger('payout_currency_id')->nullable();
                $table->decimal('payout_rate_to_usd', 24, 12)->nullable();
                $table->decimal('payout_amount', 28, 8)->nullable();
                $table->string('source_ref')->nullable(); // idempotency key / transaction reference
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('disqualified_at')->nullable();
                $table->string('disqualified_reason')->nullable();
                $table->unsignedBigInteger('disqualified_by')->nullable();
                $table->timestamps();
                // One result row per user per competition - idempotent payout guard. Re-ranking
                // after any row here has paid_at set is blocked at the service layer, not here.
                $table->unique(['competition_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('competition_flags')) {
            Schema::create('competition_flags', function (Blueprint $table) {
                $table->id();
                $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->unsignedBigInteger('related_user_id')->nullable();
                $table->string('reason');
                $table->json('context')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('competition_audit_log')) {
            Schema::create('competition_audit_log', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('competition_id')->nullable();
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->string('action');
                $table->json('details')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_audit_log');
        Schema::dropIfExists('competition_flags');
        Schema::dropIfExists('competition_results');
        Schema::dropIfExists('competition_prizes');
        Schema::dropIfExists('competitions');
    }
};
