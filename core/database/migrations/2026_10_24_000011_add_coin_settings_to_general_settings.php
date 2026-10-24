<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const COLUMNS = [
        'coin_mode' => ['boolean', false],
        'coin_mode_confirmed_at' => ['timestamp', null],
        'allow_single_approver' => ['boolean', false],
        'coin_peg_usd' => ['decimal:28,8', '0.01000000'],
        'coin_default_buy_margin_percent' => ['decimal:8,4', '3.0000'],
        'coin_default_sell_margin_percent' => ['decimal:8,4', '3.0000'],
        'coin_rate_stale_hours' => ['integer', 48],
        'coin_rate_change_guard_percent' => ['integer', 15],
        'coin_last_reconciliation_at' => ['timestamp', null],
        'coin_last_reconciliation_ok' => ['boolean', null],
        'coin_marketplace_test_kit_enabled' => ['boolean', false],
    ];

    public function up(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            foreach (self::COLUMNS as $name => [$type, $default]) {
                if (Schema::hasColumn('general_settings', $name)) continue;
                $column = match (true) {
                    $type === 'boolean' => $table->boolean($name),
                    $type === 'timestamp' => $table->timestamp($name),
                    $type === 'integer' => $table->integer($name),
                    str_starts_with($type, 'decimal:') => $table->decimal($name, ...array_map('intval', explode(',', substr($type, 8)))),
                };
                $column->nullable();
                if ($default !== null) $column->default($default);
            }
        });
    }

    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            foreach (array_keys(self::COLUMNS) as $name) {
                if (Schema::hasColumn('general_settings', $name)) $table->dropColumn($name);
            }
        });
    }
};
