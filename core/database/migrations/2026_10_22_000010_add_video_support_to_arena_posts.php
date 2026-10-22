<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('arena_posts') && !Schema::hasColumn('arena_posts', 'video')) {
            Schema::table('arena_posts', function (Blueprint $table) {
                $table->string('video')->nullable()->after('image');
            });
        }

        if (Schema::hasTable('general_settings') && !Schema::hasColumn('general_settings', 'arena_video_max_mb')) {
            Schema::table('general_settings', function (Blueprint $table) {
                $table->unsignedInteger('arena_video_max_mb')->default(25);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('arena_posts') && Schema::hasColumn('arena_posts', 'video')) {
            Schema::table('arena_posts', function (Blueprint $table) {
                $table->dropColumn('video');
            });
        }

        if (Schema::hasTable('general_settings') && Schema::hasColumn('general_settings', 'arena_video_max_mb')) {
            Schema::table('general_settings', function (Blueprint $table) {
                $table->dropColumn('arena_video_max_mb');
            });
        }
    }
};
