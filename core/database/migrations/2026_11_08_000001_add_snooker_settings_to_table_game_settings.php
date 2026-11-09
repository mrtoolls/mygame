<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The existing table-game framework has no per-table lobby picker for a game's own rule variant
 * (every other game - chess, draughts, etc. - also has no such picker, just fixed defaults in
 * TableGameRules::start()). Rather than build a brand-new per-table variant-selection UI/flow
 * just for snooker, these three columns let an admin configure it site-wide from the SAME
 * Admin > Table Games screen every other game's commission/stakes/turn-timer already live on
 * (TableGameAdminController - fully generic, driven by TableGameRules::GAMES, needs no new admin
 * view). See TableGameService::create()'s settings-copy line and TableGameRules::start()'s new
 * optional $customSettings parameter for how these reach a fresh table's initial state.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('table_game_settings') || Schema::hasColumn('table_game_settings', 'snooker_variant')) return;

        Schema::table('table_game_settings', function (Blueprint $table) {
            $table->string('snooker_variant', 10)->default('6_red')->after('turn_seconds'); // '6_red' | '15_red'
            $table->unsignedTinyInteger('snooker_frames_target')->default(1)->after('snooker_variant'); // 1 | 3 | 5
            $table->string('snooker_aid_level', 12)->default('competitive')->after('snooker_frames_target'); // 'competitive' | 'casual'
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('table_game_settings') && Schema::hasColumn('table_game_settings', 'snooker_variant')) {
            Schema::table('table_game_settings', function (Blueprint $table) {
                $table->dropColumn(['snooker_variant', 'snooker_frames_target', 'snooker_aid_level']);
            });
        }
    }
};
