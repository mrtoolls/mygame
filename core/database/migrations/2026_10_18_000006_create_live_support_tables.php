<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->unique();
            $table->string('password');
            $table->string('photo')->nullable();
            $table->string('title')->nullable();
            $table->text('bio')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->uuid('chat_uuid')->nullable()->unique();
            $table->char('visitor_token', 64)->nullable()->index();
            $table->foreignId('support_agent_id')->nullable()->constrained('support_agents')->nullOnDelete();
            $table->string('channel', 20)->default('ticket')->index();
        });

        Schema::table('support_messages', function (Blueprint $table) {
            $table->string('sender_type', 20)->nullable()->index();
            $table->foreignId('support_agent_id')->nullable()->constrained('support_agents')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->boolean('is_read')->default(true);
        });

        Schema::create('support_chat_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_type', 20);
            $table->unsignedBigInteger('recipient_id');
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('support_message_id')->nullable()->constrained('support_messages')->cascadeOnDelete();
            $table->string('title');
            $table->string('body', 500);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['recipient_type', 'recipient_id', 'read_at'], 'support_chat_notice_recipient_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_chat_notifications');
        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropForeign(['support_agent_id']);
            $table->dropIndex(['sender_type']);
            $table->dropColumn(['sender_type', 'support_agent_id', 'metadata', 'is_read']);
        });
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropForeign(['support_agent_id']);
            $table->dropIndex(['channel']);
            $table->dropIndex(['visitor_token']);
            $table->dropUnique(['chat_uuid']);
            $table->dropColumn(['chat_uuid', 'visitor_token', 'support_agent_id', 'channel']);
        });
        Schema::dropIfExists('support_agents');
    }
};
