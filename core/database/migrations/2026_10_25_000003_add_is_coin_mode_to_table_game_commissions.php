<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Marks whether a retained commission row was taken in coins or real currency - needed once
// table games can settle either way (see TableGameService::settleCoins()), so the admin
// Commission Summary never silently sums two different units together. Existing rows default to
// false (they all predate coin-mode table settlement).
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('table_game_commissions') && !Schema::hasColumn('table_game_commissions', 'is_coin_mode')) {
            Schema::table('table_game_commissions', function (Blueprint $table) {
                $table->boolean('is_coin_mode')->default(false)->after('amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('table_game_commissions') && Schema::hasColumn('table_game_commissions', 'is_coin_mode')) {
            Schema::table('table_game_commissions', function (Blueprint $table) {
                $table->dropColumn('is_coin_mode');
            });
        }
    }
};
