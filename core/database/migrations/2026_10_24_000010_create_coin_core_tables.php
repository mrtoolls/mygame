<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Core coin ledger. coin_wallets holds one row per owner (a user, a support agent acting as a
// marketplace agent, or the single 'platform' row that mints/burns coins into and out of
// circulation). coin_ledger_entries is the append-only, immutable record every balance mutation
// writes to - CoinLedger is the only code allowed to write to either table.
//
// The platform wallet is intentionally allowed a negative available_balance: it represents how
// many coins are currently in circulation (owed to real wallets), not an asset. Every other
// wallet must stay non-negative - enforced by CoinLedger under a row lock, the same way every
// other balance in this codebase is protected (there is no DB CHECK constraint for this anywhere
// else in the schema either, so this follows the house convention rather than introducing one).
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('coin_wallets')) {
            Schema::create('coin_wallets', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->string('owner_type', 20);
                $table->unsignedBigInteger('owner_id');
                $table->decimal('available_balance', 28, 8)->default(0);
                $table->decimal('held_balance', 28, 8)->default(0);
                $table->timestamps();
                $table->unique(['owner_type', 'owner_id']);
            });
        }

        if (!Schema::hasTable('coin_ledger_entries')) {
            Schema::create('coin_ledger_entries', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('wallet_id');
                $table->string('type', 20); // mint, burn, credit, debit, hold, release, transfer_out, transfer_in
                $table->decimal('amount', 28, 8);
                $table->decimal('available_balance_after', 28, 8);
                $table->decimal('held_balance_after', 28, 8);
                $table->string('reason', 190);
                $table->string('reference_type', 60)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('idempotency_key', 190)->unique();
                $table->string('actor_type', 20); // system, user, admin, agent
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['wallet_id', 'created_at']);
                $table->index(['reference_type', 'reference_id']);
            });
        }

        if (!Schema::hasTable('coin_rates')) {
            Schema::create('coin_rates', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('currency_id')->unique();
                $table->decimal('buy_margin_percent', 8, 4)->default(3);
                $table->decimal('sell_margin_percent', 8, 4)->default(3);
                $table->decimal('buy_price', 28, 8)->nullable();
                $table->decimal('sell_price', 28, 8)->nullable();
                $table->timestamp('last_computed_at')->nullable();
                $table->boolean('flagged_for_review')->default(false);
                $table->unsignedBigInteger('updated_by_admin_id')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('coin_rate_logs')) {
            Schema::create('coin_rate_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('currency_id');
                $table->decimal('old_buy_price', 28, 8)->nullable();
                $table->decimal('new_buy_price', 28, 8)->nullable();
                $table->decimal('old_sell_price', 28, 8)->nullable();
                $table->decimal('new_sell_price', 28, 8)->nullable();
                $table->decimal('change_percent', 8, 4)->nullable();
                $table->unsignedBigInteger('changed_by_admin_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // The platform mint/burn counterparty wallet. owner_id=1 is a plain convention (there is
        // only ever one), created here so CoinLedger never has to special-case "create it on
        // first use" under its own lock.
        if (!\Illuminate\Support\Facades\DB::table('coin_wallets')->where('owner_type', 'platform')->exists()) {
            \Illuminate\Support\Facades\DB::table('coin_wallets')->insert([
                'owner_type' => 'platform',
                'owner_id' => 1,
                'available_balance' => 0,
                'held_balance' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_rate_logs');
        Schema::dropIfExists('coin_rates');
        Schema::dropIfExists('coin_ledger_entries');
        Schema::dropIfExists('coin_wallets');
    }
};
