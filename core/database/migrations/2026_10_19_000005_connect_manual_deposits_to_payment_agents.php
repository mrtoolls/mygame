<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('support_agents') && !Schema::hasColumn('support_agents', 'payment_details')) {
            Schema::table('support_agents', function (Blueprint $table) {
                $table->text('payment_details')->nullable();
            });
        }

        if (Schema::hasTable('support_agents') && Schema::hasTable('gateway_currencies') && !Schema::hasColumn('gateway_currencies', 'support_agent_id')) {
            Schema::table('gateway_currencies', function (Blueprint $table) {
                $table->foreignId('support_agent_id')->nullable()->after('method_code')
                    ->constrained('support_agents')->nullOnDelete();
            });
        }

        if (Schema::hasTable('support_agents') && Schema::hasTable('withdraw_methods') && !Schema::hasColumn('withdraw_methods', 'support_agent_id')) {
            Schema::table('withdraw_methods', function (Blueprint $table) {
                $table->foreignId('support_agent_id')->nullable()->after('id')
                    ->constrained('support_agents')->nullOnDelete();
            });
        }

        if (Schema::hasTable('deposits') && !Schema::hasColumn('deposits', 'payment_agent_snapshot')) {
            Schema::table('deposits', function (Blueprint $table) {
                $table->json('payment_agent_snapshot')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gateway_currencies') && Schema::hasColumn('gateway_currencies', 'support_agent_id')) {
            Schema::table('gateway_currencies', function (Blueprint $table) {
                $table->dropConstrainedForeignId('support_agent_id');
            });
        }
        if (Schema::hasTable('withdraw_methods') && Schema::hasColumn('withdraw_methods', 'support_agent_id')) {
            Schema::table('withdraw_methods', function (Blueprint $table) {
                $table->dropConstrainedForeignId('support_agent_id');
            });
        }
        if (Schema::hasTable('support_agents') && Schema::hasColumn('support_agents', 'payment_details')) {
            Schema::table('support_agents', function (Blueprint $table) {
                $table->dropColumn('payment_details');
            });
        }
        if (Schema::hasTable('deposits') && Schema::hasColumn('deposits', 'payment_agent_snapshot')) {
            Schema::table('deposits', function (Blueprint $table) {
                $table->dropColumn('payment_agent_snapshot');
            });
        }
    }
};
