<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// coin_buy_price_snapshot is deliberately a literal copy of the price used, not just a FK to
// coin_rate_id - so this deposit's record of what the user actually got stays correct forever,
// even if that coin_rates row is edited or deleted later. coin_mode_purchase is an immutable
// marker of which pricing regime priced this deposit, since coin_mode itself can be toggled.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            if (!Schema::hasColumn('deposits', 'coin_amount')) $table->decimal('coin_amount', 28, 8)->nullable()->after('amount');
            if (!Schema::hasColumn('deposits', 'coin_buy_price_snapshot')) $table->decimal('coin_buy_price_snapshot', 28, 8)->nullable()->after('coin_amount');
            if (!Schema::hasColumn('deposits', 'coin_rate_id')) $table->unsignedBigInteger('coin_rate_id')->nullable()->after('coin_buy_price_snapshot');
            if (!Schema::hasColumn('deposits', 'coin_mode_purchase')) $table->boolean('coin_mode_purchase')->default(false)->after('coin_rate_id');
        });
    }

    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            foreach (['coin_amount', 'coin_buy_price_snapshot', 'coin_rate_id', 'coin_mode_purchase'] as $column) {
                if (Schema::hasColumn('deposits', $column)) $table->dropColumn($column);
            }
        });
    }
};
