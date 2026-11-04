<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Coin-staked contests built on a fantasy gameweek's score. Entry holds the entrant's stake via
// CoinLedger::hold() (same pattern TableGameService already uses for pooled stakes - see
// FantasyContestService), settled after the gameweek is final and a short review window has
// passed. An H2H contest is simply a gameweek contest capped at exactly 2 entrants with an equal
// stake - no separate pairing table needed.
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ff_contests')) {
            Schema::create('ff_contests', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->string('type', 12); // h2h, gameweek, sponsored
                $table->unsignedBigInteger('gameweek_id');
                $table->string('name', 100);
                $table->decimal('entry_fee', 28, 8)->default(0); // 0 for sponsored contests
                $table->decimal('fee_percent', 8, 4)->nullable(); // overrides the admin default when set
                $table->decimal('sponsored_pool', 28, 8)->nullable(); // admin-funded fixed pool for sponsored contests
                $table->unsignedInteger('min_entrants')->default(2);
                $table->unsignedInteger('max_entrants')->nullable();
                $table->string('status', 12)->default('open'); // open, locked, settling, settled, cancelled, refunded
                $table->json('prize_structure')->nullable(); // [{from_percent, to_percent, share_percent}, ...] tiers over the distributable pool
                $table->timestamp('deadline_at'); // no entry after this - matches the gameweek deadline
                $table->timestamp('review_until_at')->nullable(); // set once the gameweek is final; settlement waits until this passes
                $table->decimal('final_pool', 28, 8)->nullable();
                $table->decimal('final_fee_burned', 28, 8)->nullable();
                $table->timestamp('settled_at')->nullable();
                $table->unsignedBigInteger('created_by_admin_id')->nullable();
                $table->timestamps();

                $table->index(['gameweek_id', 'type', 'status']);
            });
        }

        if (!Schema::hasTable('ff_contest_entries')) {
            Schema::create('ff_contest_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('contest_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('team_id');
                $table->decimal('stake', 28, 8);
                $table->string('hold_idempotency_key', 191)->unique();
                $table->string('status', 10)->default('held'); // held, settled, refunded
                $table->string('entry_ip', 45)->nullable(); // best-effort same-IP self-matching guard - see FantasyContestService::assertNoLinkedEntry()
                $table->unsignedInteger('rank')->nullable();
                $table->integer('score')->nullable();
                $table->decimal('payout', 28, 8)->nullable();
                $table->timestamp('entered_at');
                $table->timestamps();

                $table->unique(['contest_id', 'user_id']);
                $table->unique(['contest_id', 'team_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ff_contest_entries');
        Schema::dropIfExists('ff_contests');
    }
};
