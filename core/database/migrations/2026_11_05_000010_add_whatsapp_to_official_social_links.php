<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('social_links')) {
            return;
        }

        if (!DB::table('social_links')->where('platform', 'whatsapp')->exists()) {
            DB::table('social_links')->insert([
                'platform' => 'whatsapp',
                'label' => 'WhatsApp',
                'url' => null,
                'is_active' => true,
                'sort_order' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('social_links')) {
            DB::table('social_links')->where('platform', 'whatsapp')->delete();
        }
    }
};
