<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A general-purpose 1:1 messaging system distinct from the three chat systems that already exist
 * (CoinTrade chat - trade-scoped; TableGame chat - in-game; SupportTicket/SupportMessage - inbound
 * support tickets, user-initiated only). This one is specifically for vendor/agent <-> user
 * conversations outside of a trade or a ticket, in either direction - a user can reach out to a
 * vendor/agent, and a vendor/agent can reach out to any user - but two plain users can never start
 * a conversation with each other (enforced in MessagingService, not here).
 *
 * Participants use the same {type, id} polymorphic convention CoinTrade already established
 * (coin_seller_type/coin_seller_id: 'user' or 'agent' - a CoinVendor trades AS a 'user', using
 * their own user_id, same as here). Stored in canonical order (type_a/id_a sorts before
 * type_b/id_b - see Conversation::between()) so the same pair of participants always resolves to
 * exactly one row regardless of who started it or who's asking.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->id();
                $table->string('type_a', 10);
                $table->unsignedBigInteger('id_a');
                $table->string('type_b', 10);
                $table->unsignedBigInteger('id_b');
                $table->timestamp('last_message_at')->nullable();
                $table->string('last_message_preview', 255)->nullable();
                $table->timestamps();

                $table->unique(['type_a', 'id_a', 'type_b', 'id_b']);
                $table->index(['type_a', 'id_a']);
                $table->index(['type_b', 'id_b']);
            });
        }

        if (!Schema::hasTable('conversation_messages')) {
            Schema::create('conversation_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('conversation_id');
                $table->string('sender_type', 10);
                $table->unsignedBigInteger('sender_id');
                $table->text('body');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['conversation_id', 'id']);
                $table->index(['conversation_id', 'read_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
    }
};
