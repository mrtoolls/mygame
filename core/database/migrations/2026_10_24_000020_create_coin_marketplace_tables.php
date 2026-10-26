<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The P2P agent marketplace. A coin_listings row only ever exists for the sell-to-agent
// direction (a user listing coins for sale); buy-from-agent starts a coin_trades row directly,
// with no listing. coin_trades is the one state machine for both directions - coin_seller_* is
// always whoever's coins are held and eventually transferred away (and who gets paid real money
// outside the platform), coin_buyer_* is always whoever receives the coins (and pays real money
// outside the platform) - this holds regardless of which direction the trade is.
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('coin_listings')) {
            Schema::create('coin_listings', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('seller_user_id');
                $table->decimal('amount', 28, 8);
                $table->string('status', 20)->default('on_sale'); // on_sale, pending, cancelled, expired
                $table->unsignedBigInteger('claimed_by_agent_id')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
            });
        }

        if (!Schema::hasTable('coin_trades')) {
            Schema::create('coin_trades', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('listing_id')->nullable();
                $table->string('direction', 20); // sell_to_agent, buy_from_agent
                $table->string('coin_seller_type', 20);
                $table->unsignedBigInteger('coin_seller_id');
                $table->string('coin_buyer_type', 20);
                $table->unsignedBigInteger('coin_buyer_id');
                $table->decimal('amount', 28, 8);
                $table->decimal('fee_amount', 28, 8)->default(0);
                $table->string('fee_payer', 10)->default('seller'); // seller, buyer
                $table->string('status', 20)->default('pending'); // pending, paid_claimed, completed, disputed, cancelled, expired
                $table->timestamp('payment_deadline_at')->nullable();
                $table->timestamp('payment_claimed_at')->nullable();
                $table->timestamp('confirmation_deadline_at')->nullable();
                $table->string('proof_attachment_path')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancelled_reason')->nullable();
                $table->timestamp('dispute_opened_at')->nullable();
                $table->string('dispute_opened_by_type', 20)->nullable();
                $table->unsignedBigInteger('dispute_opened_by_id')->nullable();
                $table->timestamp('no_payment_ruled_at')->nullable();
                $table->string('resolved_by_type', 20)->nullable(); // admin, agent (a marketplace supervisor)
                $table->unsignedBigInteger('resolved_by_id')->nullable();
                $table->timestamps();

                $table->index(['coin_seller_type', 'coin_seller_id']);
                $table->index(['coin_buyer_type', 'coin_buyer_id']);
                $table->index('status');
            });
        }

        if (!Schema::hasTable('coin_trade_events')) {
            Schema::create('coin_trade_events', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('trade_id');
                $table->string('actor_type', 20);
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action', 60);
                $table->text('reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('trade_id');
            });
        }

        if (!Schema::hasTable('coin_trade_messages')) {
            Schema::create('coin_trade_messages', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('trade_id');
                $table->string('sender_type', 20);
                $table->unsignedBigInteger('sender_id');
                $table->text('body')->nullable();
                $table->string('attachment_path')->nullable();
                $table->string('attachment_name')->nullable();
                $table->string('attachment_mime')->nullable();
                $table->unsignedInteger('attachment_size')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('trade_id');
            });
        }

        if (!Schema::hasTable('coin_penalties')) {
            Schema::create('coin_penalties', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('trade_id');
                $table->string('offender_type', 20);
                $table->unsignedBigInteger('offender_id');
                $table->unsignedTinyInteger('tier');
                $table->decimal('percent', 8, 4);
                $table->decimal('coin_amount', 28, 8);
                $table->decimal('split_to_wronged_party', 28, 8);
                $table->decimal('split_to_platform', 28, 8);
                $table->text('reason');
                $table->string('issued_by_type', 20); // admin, agent (a marketplace supervisor)
                $table->unsignedBigInteger('issued_by_id');
                $table->string('second_approver_type', 20)->nullable();
                $table->unsignedBigInteger('second_approver_id')->nullable();
                $table->timestamps();

                $table->index(['offender_type', 'offender_id']);
            });
        }

        if (!Schema::hasColumn('support_agents', 'is_selling_coins')) {
            Schema::table('support_agents', function (Blueprint $table) {
                $table->boolean('is_selling_coins')->default(false)->after('is_marketplace_supervisor');
            });
        }

        $marketplaceSettings = [
            'coin_payment_window_minutes' => 30,
            'coin_confirmation_window_minutes' => 60,
            'coin_min_listing_amount' => 10,
            'coin_max_listing_amount' => 100000,
            'coin_min_trade_amount' => 10,
            'coin_max_trade_amount' => 100000,
            'coin_agent_daily_limit' => 1000000,
            'coin_max_open_trades_per_user' => 3,
            'coin_max_open_trades_per_agent' => 10,
            'coin_fee_percent' => 2,
            'coin_fee_payer' => 'seller',
            'coin_penalty_tier_1_percent' => 10,
            'coin_penalty_tier_2_percent' => 25,
            'coin_penalty_tier_3_percent' => 50,
            'coin_penalty_split_wronged_percent' => 50,
            'coin_penalty_second_approval_threshold' => 500,
        ];
        Schema::table('general_settings', function (Blueprint $table) use ($marketplaceSettings) {
            foreach ($marketplaceSettings as $name => $default) {
                if (Schema::hasColumn('general_settings', $name)) continue;
                if (is_string($default)) $table->string($name, 20)->nullable()->default($default);
                else $table->decimal($name, 28, 8)->nullable()->default($default);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_penalties');
        Schema::dropIfExists('coin_trade_messages');
        Schema::dropIfExists('coin_trade_events');
        Schema::dropIfExists('coin_trades');
        Schema::dropIfExists('coin_listings');
        if (Schema::hasColumn('support_agents', 'is_selling_coins')) {
            Schema::table('support_agents', fn (Blueprint $table) => $table->dropColumn('is_selling_coins'));
        }
    }
};
