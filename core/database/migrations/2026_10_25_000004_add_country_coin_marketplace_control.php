<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('countries')) return;
        if (!Schema::hasColumn('countries', 'coin_marketplace_enabled')) {
            Schema::table('countries', function (Blueprint $table) {
                $table->boolean('coin_marketplace_enabled')->nullable()->default(null);
            });
        }

        // Preserve each country's existing access decision. Marketplace/cash-out defaults
        // on only for countries that are currently enabled and allowed; blocked countries
        // stay blocked and can be enabled separately by an administrator.
        DB::table('countries')->whereNull('coin_marketplace_enabled')->update([
            'coin_marketplace_enabled' => DB::raw('CASE WHEN is_enabled = 1 AND is_allowed = 1 THEN 1 ELSE 0 END'),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('countries') && Schema::hasColumn('countries', 'coin_marketplace_enabled')) {
            Schema::table('countries', fn (Blueprint $table) => $table->dropColumn('coin_marketplace_enabled'));
        }
    }
};
