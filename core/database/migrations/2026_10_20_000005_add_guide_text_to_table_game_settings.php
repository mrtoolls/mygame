<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('table_game_settings') && !Schema::hasColumn('table_game_settings', 'guide_json')) {
            Schema::table('table_game_settings', function (Blueprint $table) {
                $table->json('guide_json')->nullable()->after('turn_seconds');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('table_game_settings') && Schema::hasColumn('table_game_settings', 'guide_json')) {
            Schema::table('table_game_settings', fn (Blueprint $table) => $table->dropColumn('guide_json'));
        }
    }
};
