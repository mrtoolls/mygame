<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('support_agents') && !Schema::hasColumn('support_agents', 'is_moderator')) {
            Schema::table('support_agents', function (Blueprint $table) {
                $table->boolean('is_moderator')->default(false)->after('is_verified');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('support_agents') && Schema::hasColumn('support_agents', 'is_moderator')) {
            Schema::table('support_agents', function (Blueprint $table) {
                $table->dropColumn('is_moderator');
            });
        }
    }
};
