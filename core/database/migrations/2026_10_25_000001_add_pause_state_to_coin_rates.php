<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lets a currency with no usable exchange rate be marked 'paused' (with a human-readable reason)
// instead of simply having no coin_rates row at all - so the admin Coin Settings page can list
// every unpriced currency in one place, and the Buy Coins page can show *why* a currency has no
// live coin price instead of a bare dead-end error.
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('coin_rates') && !Schema::hasColumn('coin_rates', 'status')) {
            Schema::table('coin_rates', function (Blueprint $table) {
                $table->string('status', 20)->default('active')->after('flagged_for_review');
                $table->string('pause_reason', 255)->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('coin_rates') && Schema::hasColumn('coin_rates', 'status')) {
            Schema::table('coin_rates', function (Blueprint $table) {
                $table->dropColumn(['status', 'pause_reason']);
            });
        }
    }
};
