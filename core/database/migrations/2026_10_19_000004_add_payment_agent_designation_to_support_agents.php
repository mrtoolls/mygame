<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('support_agents', 'is_payment_agent')) {
            Schema::table('support_agents', function (Blueprint $table) {
                // Explicit admin opt-in. Existing support agents remain support-only.
                $table->boolean('is_payment_agent')->default(false)->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('support_agents', 'is_payment_agent')) {
            Schema::table('support_agents', function (Blueprint $table) {
                $table->dropColumn('is_payment_agent');
            });
        }
    }
};
