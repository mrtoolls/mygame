<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors support_agents.contact_methods (2026_11_03_000002) - a CoinVendor had no way at all to
 * offer buyers a direct contact channel (WhatsApp/Telegram/phone/email), unlike a payment Support
 * Agent, who already had this. In-app trade chat remains the one channel every vendor always has;
 * this is purely additive on top of it.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('coin_vendors') || Schema::hasColumn('coin_vendors', 'contact_methods')) {
            return;
        }

        Schema::table('coin_vendors', function (Blueprint $table) {
            $table->json('contact_methods')->nullable()->after('encrypted_payment_methods');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('coin_vendors') && Schema::hasColumn('coin_vendors', 'contact_methods')) {
            Schema::table('coin_vendors', function (Blueprint $table) {
                $table->dropColumn('contact_methods');
            });
        }
    }
};
