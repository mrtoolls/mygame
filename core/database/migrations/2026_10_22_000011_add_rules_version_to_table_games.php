<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('table_games') && !Schema::hasColumn('table_games', 'rules_version')) {
            Schema::table('table_games', function (Blueprint $table) {
                // Existing tables receive version 1 and retain their current rules.
                $table->unsignedSmallInteger('rules_version')->default(1);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('table_games') && Schema::hasColumn('table_games', 'rules_version')) {
            Schema::table('table_games', function (Blueprint $table) {
                $table->dropColumn('rules_version');
            });
        }
    }
};
