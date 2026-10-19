<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('currencies') || !Schema::hasTable('general_settings')) return;
        $legacyCode = strtoupper((string) DB::table('general_settings')->value('cur_text'));
        $legacyCurrencyId = DB::table('currencies')->where('code', $legacyCode)->value('id');
        if (!$legacyCurrencyId) throw new RuntimeException('The configured legacy currency is not present in currencies.');

        foreach (['bonus_codes', 'bonus_code_redemptions'] as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'currency_id')) {
                Schema::table($tableName, fn (Blueprint $table) => $table->unsignedBigInteger('currency_id')->nullable()->index());
            }
        }

        if (Schema::hasTable('bonus_codes')) {
            DB::table('bonus_codes')->whereNull('currency_id')->update(['currency_id' => $legacyCurrencyId]);
        }
        if (Schema::hasTable('bonus_code_redemptions') && Schema::hasTable('users') && Schema::hasColumn('users', 'currency_id')) {
            DB::table('bonus_code_redemptions as redemption')
                ->join('users', 'users.id', '=', 'redemption.user_id')
                ->whereNull('redemption.currency_id')
                ->update(['redemption.currency_id' => DB::raw('users.currency_id')]);
        }
        if (Schema::hasTable('bonus_code_redemptions')) {
            DB::table('bonus_code_redemptions')->whereNull('currency_id')->update(['currency_id' => $legacyCurrencyId]);
        }
    }

    public function down(): void
    {
        foreach (['bonus_codes', 'bonus_code_redemptions'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'currency_id')) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('currency_id'));
            }
        }
    }
};
