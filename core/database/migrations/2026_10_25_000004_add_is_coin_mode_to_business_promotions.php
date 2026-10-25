<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Marks whether a business promotion was paid for in coins or real currency, so amount_minor is
// always read the right way regardless of the LIVE coin_mode setting - the same per-row-signal
// pattern already used for deposits.coin_mode_purchase and table_game_commissions.is_coin_mode.
// Existing rows default to false (they all predate coin-mode business promotions).
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('business_promotions') && !Schema::hasColumn('business_promotions', 'is_coin_mode')) {
            Schema::table('business_promotions', function (Blueprint $table) {
                $table->boolean('is_coin_mode')->default(false)->after('amount_minor');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('business_promotions') && Schema::hasColumn('business_promotions', 'is_coin_mode')) {
            Schema::table('business_promotions', function (Blueprint $table) {
                $table->dropColumn('is_coin_mode');
            });
        }
    }
};
