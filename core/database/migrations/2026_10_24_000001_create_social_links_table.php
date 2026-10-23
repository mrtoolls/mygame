<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Powers the "Official" button (landing page footer + the sidebar after News) - a small,
     *  fixed set of platforms rather than a general CRUD, since the list itself never changes,
     *  only each platform's URL and whether it's shown. */
    private const PLATFORMS = [
        ['platform' => 'x', 'label' => 'X (Twitter)', 'sort_order' => 1],
        ['platform' => 'facebook', 'label' => 'Facebook', 'sort_order' => 2],
        ['platform' => 'instagram', 'label' => 'Instagram', 'sort_order' => 3],
        ['platform' => 'discord', 'label' => 'Discord', 'sort_order' => 4],
        ['platform' => 'tiktok', 'label' => 'TikTok', 'sort_order' => 5],
    ];

    public function up(): void
    {
        if (Schema::hasTable('social_links')) return;

        Schema::create('social_links', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 30)->unique();
            $table->string('label', 60);
            $table->string('url')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        foreach (self::PLATFORMS as $row) {
            \Illuminate\Support\Facades\DB::table('social_links')->insert($row + [
                'is_active' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_links');
    }
};
