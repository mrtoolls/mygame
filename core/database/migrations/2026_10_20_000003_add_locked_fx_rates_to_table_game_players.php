<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('table_game_players')) return;

        Schema::table('table_game_players', function (Blueprint $table) {
            if (!Schema::hasColumn('table_game_players', 'wallet_rate_to_usd')) {
                $table->decimal('wallet_rate_to_usd', 24, 12)->nullable();
            }
            if (!Schema::hasColumn('table_game_players', 'table_rate_to_usd')) {
                $table->decimal('table_rate_to_usd', 24, 12)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('table_game_players')) return;

        Schema::table('table_game_players', function (Blueprint $table) {
            $columns = array_filter(['wallet_rate_to_usd', 'table_rate_to_usd'], fn ($column) => Schema::hasColumn('table_game_players', $column));
            if ($columns) $table->dropColumn(array_values($columns));
        });
    }
};
