<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fantasy Football has no row in the games table to hang an image column off of (see
// FantasyAdminController::thumbnailUpdate()), so its card thumbnail - uploaded through the admin
// panel exactly like any other game's image - lives as a single settings value instead.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('general_settings', 'ff_card_image')) {
                $table->string('ff_card_image')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            if (Schema::hasColumn('general_settings', 'ff_card_image')) $table->dropColumn('ff_card_image');
        });
    }
};
