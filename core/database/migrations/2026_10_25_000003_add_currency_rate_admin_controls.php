<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            foreach (['currency_rate_api_key' => 'text', 'currency_rates_last_success_at' => 'timestamp', 'currency_rates_next_at' => 'timestamp', 'currency_rates_last_error' => 'text'] as $name => $type) {
                if (!Schema::hasColumn('general_settings', $name)) $table->{$type}($name)->nullable();
            }
            if (!Schema::hasColumn('general_settings', 'coin_default_buy_margin_percent')) $table->decimal('coin_default_buy_margin_percent', 8, 4)->default(5);
            if (!Schema::hasColumn('general_settings', 'coin_default_sell_margin_percent')) $table->decimal('coin_default_sell_margin_percent', 8, 4)->default(2);
        });

        if (Schema::hasTable('currency_rate_logs') && !Schema::hasColumn('currency_rate_logs', 'reason')) {
            Schema::table('currency_rate_logs', fn (Blueprint $table) => $table->string('reason', 500)->nullable());
        }
        if (Schema::hasTable('country_block_attempts') && !Schema::hasColumn('country_block_attempts', 'reason')) {
            Schema::table('country_block_attempts', fn (Blueprint $table) => $table->string('reason', 255)->nullable());
        }
        if (Schema::hasTable('coin_rates')) {
            Schema::table('coin_rates', function (Blueprint $table) {
                if (!Schema::hasColumn('coin_rates', 'buy_margin_override')) $table->decimal('buy_margin_override', 8, 4)->nullable();
                if (!Schema::hasColumn('coin_rates', 'sell_margin_override')) $table->decimal('sell_margin_override', 8, 4)->nullable();
            });
            // Preserve per-currency values that differ from the former 3%/3% defaults.
            DB::table('coin_rates')->where('buy_margin_percent', '<>', 3)->update(['buy_margin_override' => DB::raw('buy_margin_percent')]);
            DB::table('coin_rates')->where('sell_margin_percent', '<>', 3)->update(['sell_margin_override' => DB::raw('sell_margin_percent')]);
        }

        // Installed defaults were 3/3. Move only untouched defaults to the requested platform defaults.
        if (Schema::hasColumn('general_settings', 'coin_default_buy_margin_percent')) {
            DB::table('general_settings')->where('coin_default_buy_margin_percent', 3)->where('coin_default_sell_margin_percent', 3)->update([
                'coin_default_buy_margin_percent' => 5, 'coin_default_sell_margin_percent' => 2,
            ]);
        }
    }

    public function down(): void
    {
        foreach ([['general_settings', ['currency_rate_api_key', 'currency_rates_last_success_at', 'currency_rates_next_at', 'currency_rates_last_error']], ['currency_rate_logs', ['reason']], ['country_block_attempts', ['reason']], ['coin_rates', ['buy_margin_override', 'sell_margin_override']]] as [$tableName, $columns]) {
            if (!Schema::hasTable($tableName)) continue;
            foreach ($columns as $column) if (Schema::hasColumn($tableName, $column)) Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($column));
        }
    }
};
