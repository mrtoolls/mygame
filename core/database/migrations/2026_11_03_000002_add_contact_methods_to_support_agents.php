<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a payment agent offer WhatsApp/Telegram/phone/email as contact channels on their public
 * payment-agent card, each independently toggled on/off with its own value - in-app chat is a
 * separate built-in channel (gated by is_chat_agent) that's never stored here. Nullable with no
 * default: absence just means only in-app chat is offered, nothing existing is restricted by
 * this column appearing.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('support_agents') || Schema::hasColumn('support_agents', 'contact_methods')) {
            return;
        }

        Schema::table('support_agents', function (Blueprint $table) {
            $table->json('contact_methods')->nullable()->after('payment_details');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('support_agents') && Schema::hasColumn('support_agents', 'contact_methods')) {
            Schema::table('support_agents', function (Blueprint $table) {
                $table->dropColumn('contact_methods');
            });
        }
    }
};
