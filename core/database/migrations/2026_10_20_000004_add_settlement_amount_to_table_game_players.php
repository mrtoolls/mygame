<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('table_game_players') && !Schema::hasColumn('table_game_players', 'settlement_amount')) {
            Schema::table('table_game_players', function (Blueprint $table) {
                $table->decimal('settlement_amount', 28, 8)->nullable()->after('settlement_key');
                $table->string('settlement_type', 20)->nullable()->after('settlement_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('table_game_players') && Schema::hasColumn('table_game_players', 'settlement_amount')) {
            Schema::table('table_game_players', function (Blueprint $table) {
                $table->dropColumn(['settlement_amount', 'settlement_type']);
            });
        }
    }
};
