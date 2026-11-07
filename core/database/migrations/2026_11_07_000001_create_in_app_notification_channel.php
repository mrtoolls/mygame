<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A generalized in-app notification feed (the bell icon), built on the SAME admin-configurable
// template architecture email/sms/push already use (see App\Notify\NotifyProcess/Email/Push) -
// an 'inapp' channel slots into App\Notify\Notify::notifyMethods() exactly like the other three,
// reading inapp_status/inapp_title/inapp_body off the same notification_templates row instead of
// a brand-new config surface. user_notifications is the equivalent of notification_logs for this
// channel, except it IS the user-visible feed item (not just a delivery audit row), so it carries
// real display fields (icon/url/actor) a bell dropdown needs - ArenaNotification already proved
// this exact shape works well for a dropdown feed, this generalizes it site-wide instead of only
// for the Arena feature.
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('notification_templates', 'inapp_status')) {
            Schema::table('notification_templates', function (Blueprint $table) {
                $table->boolean('inapp_status')->default(1)->after('push_status');
                $table->string('inapp_title')->nullable()->after('inapp_status');
                $table->text('inapp_body')->nullable()->after('inapp_title');
            });
        }

        // The shared NotifyProcess::getMessage() always substitutes the per-template body into a
        // site-wide "global template" wrapper (see push_template/email_template) before applying
        // the template's own short-codes - for in-app that wrapper should be a pure passthrough
        // (no extra boilerplate belongs in a bell dropdown row), so this is just "{{message}}"
        // rather than a second admin-configurable global template nobody asked for.
        if (!Schema::hasColumn('general_settings', 'inapp_template')) {
            Schema::table('general_settings', function (Blueprint $table) {
                $table->text('inapp_template')->nullable();
            });
            DB::table('general_settings')->update(['inapp_template' => '{{message}}']);
        }

        if (!Schema::hasTable('user_notifications')) {
            Schema::create('user_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('type', 40); // matches notification_templates.act
                $table->string('title')->nullable();
                $table->text('message');
                $table->string('icon', 40)->nullable(); // a Phosphor icon class suffix, e.g. "trophy" for ph-fill ph-trophy
                $table->string('url')->nullable(); // where the notification links on click
                $table->string('actor_name')->nullable(); // "who"/"what" this is about, e.g. an opponent's username
                $table->string('actor_avatar')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'read_at']);
                $table->index(['user_id', 'created_at']);
            });
        }

        $this->seedTemplates();
        $this->backfillDefaultTemplate();
    }

    /**
     * The admin "send notification to all/selected users" broadcast tool (ManageUsersController::
     * sendNotificationAll/Single, via App\Lib\UserNotificationSender) is the real announcement
     * feature - it always targets the single 'DEFAULT' act row and supplies the actual
     * subject/message as runtime short-codes, the same way it already does for email/sms/push.
     * Giving that row inapp_title/inapp_body passthroughs (mirroring whatever convention its
     * existing push_body already uses) is what lets ticking the new "Send In-App" option there
     * actually produce a bell notification, instead of silently no-oping like any other act with
     * no enabled template row.
     */
    private function backfillDefaultTemplate(): void
    {
        DB::table('notification_templates')->where('act', 'DEFAULT')->where(function ($q) {
            $q->whereNull('inapp_body')->orWhere('inapp_body', '');
        })->update([
            'inapp_title' => '{{subject}}',
            'inapp_body'  => '{{message}}',
            'updated_at'  => now(),
        ]);
    }

    /**
     * New event types the existing notification_templates table has no row for at all - without
     * one, App\Notify\NotifyProcess::getMessage() returns false and silently sends nothing on
     * every channel, per its own documented behavior. Defaults: in-app + push ON for everything
     * (the bell/popup is the point of this feature), email ON only for the one event explicitly
     * asked to reach email (a new live table opening) - a win/loss or a chat message firing an
     * email per event would be exactly the kind of inbox spam nobody wants, and is better left as
     * an explicit admin opt-in than a default this migration forces onto every fresh install.
     * Admin announcements are NOT a new act here - that feature already exists (ManageUsersController
     * ::sendNotificationAll/Single) and always targets the pre-existing 'DEFAULT' act row; see
     * backfillDefaultTemplate() below for how that row gains in-app support instead.
     */
    private function seedTemplates(): void
    {
        $now = now();
        $rows = [
            [
                'name' => 'Game Win', 'act' => 'GAME_WIN', 'subject' => 'You won!',
                'email_status' => 0, 'email_body' => 'Congratulations {{fullname}}, you won {{amount}} in {{game}} against {{opponent}}.',
                'sms_status' => 0, 'sms_body' => 'You won {{amount}} in {{game}}.',
                'push_status' => 1, 'push_title' => 'You won! 🏆', 'push_body' => 'You won {{amount}} in {{game}} against {{opponent}}.',
                'inapp_status' => 1, 'inapp_title' => 'You won!', 'inapp_body' => 'You won {{amount}} in {{game}} against {{opponent}}.',
                'shortcodes' => json_encode(['game' => 'Game name', 'amount' => 'Amount won', 'opponent' => 'Opponent name']),
            ],
            [
                'name' => 'Game Loss', 'act' => 'GAME_LOSS', 'subject' => 'Better luck next time',
                'email_status' => 0, 'email_body' => '{{fullname}}, you lost {{amount}} in {{game}} against {{opponent}}.',
                'sms_status' => 0, 'sms_body' => 'You lost {{amount}} in {{game}}.',
                'push_status' => 1, 'push_title' => 'Game over', 'push_body' => 'You lost {{amount}} in {{game}} against {{opponent}}.',
                'inapp_status' => 1, 'inapp_title' => 'Game over', 'inapp_body' => 'You lost {{amount}} in {{game}} against {{opponent}}.',
                'shortcodes' => json_encode(['game' => 'Game name', 'amount' => 'Amount lost', 'opponent' => 'Opponent name']),
            ],
            [
                'name' => 'New Chat Message', 'act' => 'NEW_CHAT_MESSAGE', 'subject' => 'New message',
                'email_status' => 0, 'email_body' => '{{sender}} sent you a message: {{preview}}',
                'sms_status' => 0, 'sms_body' => 'New message from {{sender}}.',
                'push_status' => 1, 'push_title' => 'New message', 'push_body' => '{{sender}}: {{preview}}',
                'inapp_status' => 1, 'inapp_title' => 'New message', 'inapp_body' => '{{sender}}: {{preview}}',
                'shortcodes' => json_encode(['sender' => 'Sender name', 'preview' => 'Message preview']),
            ],
            [
                'name' => 'Coin Purchase Request', 'act' => 'COIN_BUY_REQUEST', 'subject' => 'New coin purchase request',
                'email_status' => 0, 'email_body' => '{{buyer}} wants to buy {{amount}} coins from you.',
                'sms_status' => 0, 'sms_body' => '{{buyer}} wants to buy {{amount}} coins.',
                'push_status' => 1, 'push_title' => 'New purchase request', 'push_body' => '{{buyer}} wants to buy {{amount}} coins from you.',
                'inapp_status' => 1, 'inapp_title' => 'New purchase request', 'inapp_body' => '{{buyer}} wants to buy {{amount}} coins from you.',
                'shortcodes' => json_encode(['buyer' => 'Buyer username', 'amount' => 'Coin amount']),
            ],
            [
                'name' => 'Coin Purchase Response', 'act' => 'COIN_BUY_RESPONSE', 'subject' => 'Update on your coin purchase',
                'email_status' => 0, 'email_body' => '{{seller}} {{status}} your request to buy {{amount}} coins.',
                'sms_status' => 0, 'sms_body' => '{{seller}} {{status}} your coin purchase.',
                'push_status' => 1, 'push_title' => 'Purchase update', 'push_body' => '{{seller}} {{status}} your request to buy {{amount}} coins.',
                'inapp_status' => 1, 'inapp_title' => 'Purchase update', 'inapp_body' => '{{seller}} {{status}} your request to buy {{amount}} coins.',
                'shortcodes' => json_encode(['seller' => 'Seller name', 'amount' => 'Coin amount', 'status' => 'accepted/declined/completed']),
            ],
            [
                'name' => 'New Live Table', 'act' => 'NEW_LIVE_TABLE', 'subject' => 'A new live table just opened',
                'email_status' => 1, 'email_body' => '{{creator}} just opened a {{game}} table. Join now at {{site_name}}.',
                'sms_status' => 0, 'sms_body' => 'A new {{game}} table just opened.',
                'push_status' => 1, 'push_title' => 'New live table', 'push_body' => '{{creator}} just opened a {{game}} table.',
                'inapp_status' => 1, 'inapp_title' => 'New live table', 'inapp_body' => '{{creator}} just opened a {{game}} table.',
                'shortcodes' => json_encode(['creator' => 'Table creator', 'game' => 'Game name']),
            ],
        ];
        foreach ($rows as $row) {
            if (DB::table('notification_templates')->where('act', $row['act'])->exists()) continue;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
            DB::table('notification_templates')->insert($row);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
        if (Schema::hasColumn('general_settings', 'inapp_template')) {
            Schema::table('general_settings', fn (Blueprint $table) => $table->dropColumn('inapp_template'));
        }
        if (Schema::hasColumn('notification_templates', 'inapp_status')) {
            Schema::table('notification_templates', function (Blueprint $table) {
                $table->dropColumn(['inapp_status', 'inapp_title', 'inapp_body']);
            });
        }
        DB::table('notification_templates')->whereIn('act', ['GAME_WIN', 'GAME_LOSS', 'NEW_CHAT_MESSAGE', 'COIN_BUY_REQUEST', 'COIN_BUY_RESPONSE', 'NEW_LIVE_TABLE'])->delete();
    }
};
