<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('table_game_messages') && !Schema::hasColumn('table_game_messages', 'reported_by')) {
            Schema::table('table_game_messages', function (Blueprint $table) {
                $table->foreignId('reported_by')->nullable()->after('reply_to_id')->constrained('users')->nullOnDelete();
                $table->string('reported_reason', 255)->nullable()->after('reported_by');
                $table->timestamp('reported_at')->nullable()->after('reported_reason');
            });
        }

        if (!Schema::hasTable('user_blocks')) {
            Schema::create('user_blocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['blocker_id', 'blocked_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
        if (Schema::hasTable('table_game_messages') && Schema::hasColumn('table_game_messages', 'reported_by')) {
            Schema::table('table_game_messages', function (Blueprint $table) {
                $table->dropConstrainedForeignId('reported_by');
                $table->dropColumn(['reported_reason', 'reported_at']);
            });
        }
    }
};
