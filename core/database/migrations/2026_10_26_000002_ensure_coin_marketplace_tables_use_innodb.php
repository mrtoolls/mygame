<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;

        $tables = [
            'coin_wallets', 'coin_ledger_entries', 'coin_rates', 'coin_listings', 'coin_trades',
            'coin_trade_events', 'coin_trade_messages', 'coin_penalties', 'coin_vendor_applications',
            'coin_vendors', 'coin_vendor_audits',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) continue;
            $engine = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->value('ENGINE');
            if (strtoupper((string) $engine) !== 'INNODB') {
                DB::statement('ALTER TABLE `' . $table . '` ENGINE=InnoDB');
            }
        }
    }

    public function down(): void
    {
        // Storage engine conversion is intentionally irreversible; converting back can break row locking.
    }
};
