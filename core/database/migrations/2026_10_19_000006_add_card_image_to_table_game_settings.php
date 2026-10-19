<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('table_game_settings') && !Schema::hasColumn('table_game_settings', 'card_image')) {
            Schema::table('table_game_settings', function (Blueprint $table) {
                $table->string('card_image')->nullable()->after('enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('table_game_settings') && Schema::hasColumn('table_game_settings', 'card_image')) {
            Schema::table('table_game_settings', function (Blueprint $table) {
                $table->dropColumn('card_image');
            });
        }
    }
};
