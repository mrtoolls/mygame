<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The messages page's "Ring" button - a lightweight "come online, someone wants to chat" ping
 * (push + in-app only, fire-and-forget, no call/sound), not an actual call. Needs its own act so
 * it goes through the same admin-configurable template pipeline as every other notification type.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('notification_templates')) return;
        if (DB::table('notification_templates')->where('act', 'RING_ONLINE')->exists()) return;

        DB::table('notification_templates')->insert([
            'name' => 'Ring - Come Online', 'act' => 'RING_ONLINE', 'subject' => 'Someone is trying to reach you',
            'email_status' => 0, 'email_body' => '{{ringer}} is hoping to catch you online for a chat.',
            'sms_status' => 0, 'sms_body' => '{{ringer}} is hoping to catch you online for a chat.',
            'push_status' => 1, 'push_title' => "You're being pinged", 'push_body' => '{{ringer}} is hoping to catch you online for a chat.',
            'inapp_status' => 1, 'inapp_title' => "You're being pinged", 'inapp_body' => '{{ringer}} is hoping to catch you online for a chat.',
            'shortcodes' => json_encode(['ringer' => 'Name of the person ringing you']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('notification_templates')) {
            DB::table('notification_templates')->where('act', 'RING_ONLINE')->delete();
        }
    }
};
