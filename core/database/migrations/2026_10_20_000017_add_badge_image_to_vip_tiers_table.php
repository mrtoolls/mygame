<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vip_tiers', function (Blueprint $table) {
            $table->string('badge_image')->nullable()->after('badge');
        });
    }

    public function down(): void
    {
        Schema::table('vip_tiers', function (Blueprint $table) {
            $table->dropColumn('badge_image');
        });
    }
};
