<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const COLUMNS = [
        'ff_enabled' => ['boolean', true], // kill switch - admin can flip off to stop all new entries/transfers instantly
        'ff_match_duration_minutes' => ['integer', 10],
        'ff_gameweek_cadence_days' => ['integer', 1],
        'ff_contest_fee_percent' => ['decimal:8,4', '5.0000'],
        'ff_settlement_review_minutes' => ['integer', 15],
        'ff_price_change_enabled' => ['boolean', false],
        'ff_price_change_step' => ['decimal:5,1', '0.1'],
        'ff_price_min' => ['decimal:5,1', '3.5'],
        'ff_price_max' => ['decimal:5,1', '15.0'],
        'ff_max_entry_stake' => ['decimal:28,8', '10000.00000000'],
        'ff_daily_stake_cap' => ['decimal:28,8', '50000.00000000'],
    ];

    public function up(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            foreach (self::COLUMNS as $name => [$type, $default]) {
                if (Schema::hasColumn('general_settings', $name)) continue;
                $column = match (true) {
                    $type === 'boolean' => $table->boolean($name),
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
