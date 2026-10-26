<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('coin_wallets') && !Schema::hasColumn('coin_wallets', 'vendor_bond_balance')) {
            Schema::table('coin_wallets', fn (Blueprint $table) => $table->decimal('vendor_bond_balance', 28, 8)->default(0));
        }
        if (Schema::hasTable('countries') && !Schema::hasColumn('countries', 'vendors_allowed')) {
            Schema::table('countries', fn (Blueprint $table) => $table->boolean('vendors_allowed')->nullable()->default(null));
        }
        if (Schema::hasTable('countries') && Schema::hasColumn('countries', 'vendors_allowed')) {
            DB::table('countries')->whereNull('vendors_allowed')->update([
                'vendors_allowed' => DB::raw('CASE WHEN is_enabled = 1 AND is_allowed = 1 THEN 1 ELSE 0 END'),
            ]);
        }

        if (Schema::hasTable('admins') && !Schema::hasColumn('admins', 'can_review_vendor_identity')) {
            Schema::table('admins', fn (Blueprint $table) => $table->boolean('can_review_vendor_identity')->default(true));
        }
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'coin_vendor_flagged')) {
            Schema::table('users', fn (Blueprint $table) => $table->boolean('coin_vendor_flagged')->default(false));
        }
        if (Schema::hasTable('coin_listings') && !Schema::hasColumn('coin_listings', 'claimed_by_user_id')) {
            Schema::table('coin_listings', fn (Blueprint $table) => $table->unsignedBigInteger('claimed_by_user_id')->nullable()->index());
        }
        if (Schema::hasTable('coin_trades')) {
            Schema::table('coin_trades', function (Blueprint $table) {
                foreach (['fiat_currency' => 12, 'fiat_total' => 28, 'price_per_coin' => 28] as $column => $precision) {
                    if (!Schema::hasColumn('coin_trades', $column)) {
                        if ($column === 'fiat_currency') $table->string($column, 12)->nullable();
                        else $table->decimal($column, $precision, 8)->nullable();
                    }
                }
            });
        }

        if (!Schema::hasTable('coin_vendor_applications')) {
            Schema::create('coin_vendor_applications', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('status', 24)->default('draft')->index();
                $table->text('identity_data')->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('device_fingerprint_hash', 64)->nullable()->index();
                $table->string('id_type', 40)->nullable();
                $table->string('id_number_hash', 64)->nullable()->index();
                $table->string('id_last4', 4)->nullable();
                $table->string('id_front_path', 255)->nullable();
                $table->string('id_back_path', 255)->nullable();
                $table->string('selfie_video_path', 255)->nullable();
                $table->string('challenge_code', 4)->nullable();
                $table->string('challenge_token_id', 36)->nullable();
                $table->string('challenge_token_hash', 64)->nullable();
                $table->timestamp('challenge_expires_at')->nullable();
                $table->timestamp('challenge_used_at')->nullable();
                $table->string('challenge_session_hash', 64)->nullable();
                $table->string('challenge_user_agent', 500)->nullable();
                $table->string('challenge_ip', 45)->nullable();
                $table->unsignedInteger('video_duration_ms')->nullable();
                $table->timestamp('video_recorded_at')->nullable();
                $table->boolean('consent_identity')->default(false);
                $table->boolean('consent_p2p_risk')->default(false);
                $table->json('review_checklist')->nullable();
                $table->text('reviewer_notes')->nullable();
                $table->text('decision_reason')->nullable();
                $table->text('requested_information')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('retention_delete_at')->nullable();
                $table->string('duplicate_face_note', 500)->nullable();
                $table->boolean('duplicate_flag')->default(false);
                $table->timestamps();
                $table->index(['user_id', 'status']);
            });
        }
        if (!Schema::hasTable('coin_vendors')) {
            Schema::create('coin_vendors', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->unsignedBigInteger('application_id')->index();
                $table->string('status', 20)->default('active')->index();
                $table->boolean('is_selling')->default(false);
                $table->unsignedBigInteger('currency_id')->nullable();
                $table->decimal('sell_rate', 28, 8)->nullable();
                $table->decimal('per_trade_limit', 28, 8)->default(1000);
                $table->decimal('daily_limit', 28, 8)->default(5000);
                $table->decimal('bond_amount', 28, 8)->default(0);
                $table->timestamp('bond_release_after_at')->nullable();
                $table->timestamp('resigned_at')->nullable();
                $table->text('encrypted_payment_methods')->nullable();
                $table->text('linked_user_ids')->nullable();
                $table->decimal('reliability_score', 8, 2)->default(50);
                $table->unsignedInteger('completed_trades')->default(0);
                $table->unsignedInteger('average_release_minutes')->default(0);
                $table->unsignedInteger('penalty_count')->default(0);
                $table->text('status_reason')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('coin_vendor_audits')) {
            Schema::create('coin_vendor_audits', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('application_id')->nullable()->index();
                $table->unsignedBigInteger('vendor_id')->nullable()->index();
                $table->unsignedBigInteger('admin_id')->nullable()->index();
                $table->string('action', 60);
                $table->string('subject', 80)->nullable();
                $table->text('details')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (Schema::hasTable('general_settings')) {
            $settings = [
                'coin_vendor_min_account_age_days' => ['integer', 7],
                'coin_vendor_rejection_cooldown_days' => ['integer', 30],
                'coin_vendor_rejected_retention_days' => ['integer', 60],
                'coin_vendor_revoked_retention_days' => ['integer', 365],
                'coin_vendor_security_bond' => ['decimal', 0],
                'coin_vendor_bond_cooldown_days' => ['integer', 14],
                'coin_vendor_new_per_trade_limit' => ['decimal', 1000],
                'coin_vendor_new_daily_limit' => ['decimal', 5000],
                'coin_vendor_max_open_trades' => ['integer', 5],
                'coin_vendor_rate_band_below_percent' => ['decimal', 3],
                'coin_vendor_rate_band_above_percent' => ['decimal', 3],
                'coin_vendor_fee_percent' => ['decimal', null],
                'coin_vendor_auto_suspend_penalties' => ['integer', 3],
                'coin_vendor_legal_hold_days' => ['integer', 365],
                'coin_vendor_id_types' => ['text', null],
                'coin_vendor_enabled' => ['boolean', true],
            ];
            foreach ($settings as $name => [$type, $default]) {
                if (Schema::hasColumn('general_settings', $name)) continue;
                Schema::table('general_settings', function (Blueprint $table) use ($name, $type, $default) {
                    if ($type === 'integer') $table->unsignedInteger($name)->nullable()->default($default);
                    elseif ($type === 'decimal') $table->decimal($name, 28, 8)->nullable()->default($default);
                    elseif ($type === 'boolean') $table->boolean($name)->nullable()->default($default);
                    else $table->longText($name)->nullable();
                });
            }
            if (Schema::hasColumn('general_settings', 'coin_vendor_id_types')) {
                DB::table('general_settings')->whereNull('coin_vendor_id_types')->update(['coin_vendor_id_types' => json_encode(['Driver\'s License', 'Voter\'s Card', 'International Passport'])]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_vendor_audits');
        Schema::dropIfExists('coin_vendors');
        Schema::dropIfExists('coin_vendor_applications');
        if (Schema::hasTable('coin_wallets') && Schema::hasColumn('coin_wallets', 'vendor_bond_balance')) Schema::table('coin_wallets', fn (Blueprint $table) => $table->dropColumn('vendor_bond_balance'));
        foreach (['coin_vendor_min_account_age_days','coin_vendor_rejection_cooldown_days','coin_vendor_rejected_retention_days','coin_vendor_revoked_retention_days','coin_vendor_security_bond','coin_vendor_bond_cooldown_days','coin_vendor_new_per_trade_limit','coin_vendor_new_daily_limit','coin_vendor_max_open_trades','coin_vendor_rate_band_below_percent','coin_vendor_rate_band_above_percent','coin_vendor_fee_percent','coin_vendor_auto_suspend_penalties','coin_vendor_legal_hold_days','coin_vendor_id_types','coin_vendor_enabled'] as $column) {
            if (Schema::hasTable('general_settings') && Schema::hasColumn('general_settings', $column)) Schema::table('general_settings', fn (Blueprint $table) => $table->dropColumn($column));
        }
        if (Schema::hasTable('coin_listings') && Schema::hasColumn('coin_listings', 'claimed_by_user_id')) {
            // Drop the index separately so SQLite and MySQL both remove it before
            // compiling the column drop.
            Schema::table('coin_listings', fn (Blueprint $table) => $table->dropIndex(['claimed_by_user_id']));
            Schema::table('coin_listings', fn (Blueprint $table) => $table->dropColumn('claimed_by_user_id'));
        }
        foreach (['fiat_currency','fiat_total','price_per_coin'] as $column) if (Schema::hasTable('coin_trades') && Schema::hasColumn('coin_trades', $column)) Schema::table('coin_trades', fn (Blueprint $table) => $table->dropColumn($column));
        if (Schema::hasTable('countries') && Schema::hasColumn('countries', 'vendors_allowed')) Schema::table('countries', fn (Blueprint $table) => $table->dropColumn('vendors_allowed'));
        if (Schema::hasTable('admins') && Schema::hasColumn('admins', 'can_review_vendor_identity')) Schema::table('admins', fn (Blueprint $table) => $table->dropColumn('can_review_vendor_identity'));
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'coin_vendor_flagged')) Schema::table('users', fn (Blueprint $table) => $table->dropColumn('coin_vendor_flagged'));
    }
};
