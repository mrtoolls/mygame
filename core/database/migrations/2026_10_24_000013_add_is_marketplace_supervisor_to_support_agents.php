<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A separate flag from is_moderator (which means "Arena content moderator" and is unrelated).
// A marketplace supervisor reviews coin-trade disputes and approves penalties/float grants.
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('support_agents') && !Schema::hasColumn('support_agents', 'is_marketplace_supervisor')) {
            Schema::table('support_agents', function (Blueprint $table) {
                $table->boolean('is_marketplace_supervisor')->default(false)->after('is_moderator');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('support_agents') && Schema::hasColumn('support_agents', 'is_marketplace_supervisor')) {
            Schema::table('support_agents', function (Blueprint $table) {
                $table->dropColumn('is_marketplace_supervisor');
            });
        }
    }
};
