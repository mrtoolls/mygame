<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('table_game_messages') && !Schema::hasColumn('table_game_messages', 'reply_to_id')) {
            Schema::table('table_game_messages', function (Blueprint $table) {
                $table->foreignId('reply_to_id')->nullable()->after('user_id')->constrained('table_game_messages')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('table_game_messages') && Schema::hasColumn('table_game_messages', 'reply_to_id')) {
            Schema::table('table_game_messages', function (Blueprint $table) {
                $table->dropConstrainedForeignId('reply_to_id');
            });
        }
    }
};
