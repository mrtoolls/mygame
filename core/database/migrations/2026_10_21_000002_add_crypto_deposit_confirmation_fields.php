<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('gateway_currencies')) {
            Schema::table('gateway_currencies', function (Blueprint $table) {
                if (!Schema::hasColumn('gateway_currencies', 'wallet_address')) {
                    $table->string('wallet_address')->nullable()->after('gateway_parameter');
                }
                if (!Schema::hasColumn('gateway_currencies', 'network_label')) {
                    $table->string('network_label')->nullable()->after('wallet_address');
                }
                if (!Schema::hasColumn('gateway_currencies', 'qr_image')) {
                    $table->string('qr_image')->nullable()->after('network_label');
                }
            });
        }

        if (Schema::hasTable('deposits')) {
            Schema::table('deposits', function (Blueprint $table) {
                if (!Schema::hasColumn('deposits', 'payment_proof')) {
                    $table->string('payment_proof')->nullable()->after('last_cron');
                }
                if (!Schema::hasColumn('deposits', 'payer_wallet_address')) {
                    $table->string('payer_wallet_address')->nullable()->after('payment_proof');
                }
                if (!Schema::hasColumn('deposits', 'confirmation_started_at')) {
                    $table->timestamp('confirmation_started_at')->nullable()->after('payer_wallet_address');
                }
                if (!Schema::hasColumn('deposits', 'confirmation_sla_minutes')) {
                    $table->unsignedSmallInteger('confirmation_sla_minutes')->nullable()->after('confirmation_started_at');
                }
                if (!Schema::hasColumn('deposits', 'confirmation_deadline_at')) {
                    $table->timestamp('confirmation_deadline_at')->nullable()->after('confirmation_sla_minutes');
                }
                if (!Schema::hasColumn('deposits', 'confirmed_via')) {
                    $table->string('confirmed_via', 10)->nullable()->after('confirmation_deadline_at');
                }
                if (!Schema::hasColumn('deposits', 'disputed_at')) {
                    $table->timestamp('disputed_at')->nullable()->after('confirmed_via');
                }
                if (!Schema::hasColumn('deposits', 'dispute_reason')) {
                    $table->text('dispute_reason')->nullable()->after('disputed_at');
                }
            });
        }

        if (Schema::hasTable('general_settings') && !Schema::hasColumn('general_settings', 'payment_confirmation_sla_minutes')) {
            Schema::table('general_settings', function (Blueprint $table) {
                $table->unsignedSmallInteger('payment_confirmation_sla_minutes')->default(15);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gateway_currencies')) {
            Schema::table('gateway_currencies', function (Blueprint $table) {
                foreach (['wallet_address', 'network_label', 'qr_image'] as $column) {
                    if (Schema::hasColumn('gateway_currencies', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('deposits')) {
            Schema::table('deposits', function (Blueprint $table) {
                foreach (['payment_proof', 'payer_wallet_address', 'confirmation_started_at', 'confirmation_sla_minutes', 'confirmation_deadline_at', 'confirmed_via', 'disputed_at', 'dispute_reason'] as $column) {
                    if (Schema::hasColumn('deposits', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('general_settings') && Schema::hasColumn('general_settings', 'payment_confirmation_sla_minutes')) {
            Schema::table('general_settings', function (Blueprint $table) {
                $table->dropColumn('payment_confirmation_sla_minutes');
            });
        }
    }
};
