<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Today almost nothing an agent can do is actually gated server-side - any active agent can
 * already hit live chat, payments, and news regardless of what the admin intended, only Arena
 * moderation is enforced. is_chat_agent/is_news_editor default TRUE for existing rows (both are
 * universal/ungated today, so no currently-active agent loses access they already have the
 * moment this runs) but default to the admin form's own sensible per-capability choice for any
 * NEW agent created after this ships. Also links a support agent to an optional player (User)
 * account, so that player can reach their agent dashboard from their own account nav instead of
 * a separate login.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('support_agents')) {
            return;
        }

        Schema::table('support_agents', function (Blueprint $table) {
            if (!Schema::hasColumn('support_agents', 'is_chat_agent')) {
                $table->boolean('is_chat_agent')->default(true)->after('is_moderator');
            }
            if (!Schema::hasColumn('support_agents', 'is_news_editor')) {
                $table->boolean('is_news_editor')->default(true)->after('is_chat_agent');
            }
            if (!Schema::hasColumn('support_agents', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
            }
        });

        if (Schema::hasTable('users') && Schema::hasColumn('support_agents', 'user_id')) {
            Schema::table('support_agents', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('support_agents')) {
            return;
        }

        Schema::table('support_agents', function (Blueprint $table) {
            if (Schema::hasColumn('support_agents', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            foreach (['is_chat_agent', 'is_news_editor'] as $column) {
                if (Schema::hasColumn('support_agents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
