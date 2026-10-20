<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('table_game_players') && !Schema::hasColumn('table_game_players', 'wallet_stake')) {
            Schema::table('table_game_players', function (Blueprint $table) {
                $table->decimal('wallet_stake', 28, 8)->nullable()->after('stake');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('table_game_players') && Schema::hasColumn('table_game_players', 'wallet_stake')) {
            Schema::table('table_game_players', fn (Blueprint $table) => $table->dropColumn('wallet_stake'));
        }
    }
};
