<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vip_tiers', function (Blueprint $table) {
            if (!Schema::hasColumn('vip_tiers', 'daily_reward_amount')) {
                $table->decimal('daily_reward_amount', 28, 8)->default(0)->after('bonus_amount');
            }
            if (!Schema::hasColumn('vip_tiers', 'birthday_bonus_amount')) {
                $table->decimal('birthday_bonus_amount', 28, 8)->default(0)->after('daily_reward_amount');
            }
            if (!Schema::hasColumn('vip_tiers', 'withdrawal_processing_hours')) {
                $table->unsignedSmallInteger('withdrawal_processing_hours')->nullable()->after('max_bet');
            }
            if (!Schema::hasColumn('vip_tiers', 'monthly_withdrawal_limit')) {
                $table->decimal('monthly_withdrawal_limit', 28, 8)->nullable()->after('withdrawal_processing_hours');
            }
            if (!Schema::hasColumn('vip_tiers', 'support_level')) {
                $table->string('support_level', 60)->nullable()->after('monthly_withdrawal_limit');
            }
            if (!Schema::hasColumn('vip_tiers', 'exclusive_promotions')) {
                $table->string('exclusive_promotions', 60)->nullable()->after('support_level');
            }
            if (!Schema::hasColumn('vip_tiers', 'referral_bonus_boost_percent')) {
                $table->decimal('referral_bonus_boost_percent', 5, 2)->default(0)->after('exclusive_promotions');
            }
        });

        // Seed/refresh the five VIP packages. Monetary fields are stored in USD and
        // converted into each viewer's wallet currency on display. Keyed on `name` so
        // this is safe to re-run and won't duplicate rows if tiers already exist.
        $now = now();
        $tiers = [
            [
                'name' => 'Bronze', 'badge' => 'Bronze', 'sort_order' => 0,
                'min_wagered' => 0, 'bonus_amount' => 0, 'rakeback_percent' => 1,
                'daily_reward_amount' => 0.10, 'birthday_bonus_amount' => 0,
                'withdrawal_processing_hours' => 24, 'monthly_withdrawal_limit' => 2000,
                'support_level' => 'Standard', 'exclusive_promotions' => null,
                'referral_bonus_boost_percent' => 0,
            ],
            [
                'name' => 'Copper', 'badge' => 'Copper', 'sort_order' => 1,
                'min_wagered' => 500, 'bonus_amount' => 2, 'rakeback_percent' => 2,
                'daily_reward_amount' => 0.20, 'birthday_bonus_amount' => 2,
                'withdrawal_processing_hours' => 24, 'monthly_withdrawal_limit' => 5000,
                'support_level' => 'Standard', 'exclusive_promotions' => 'Monthly',
                'referral_bonus_boost_percent' => 5,
            ],
            [
                'name' => 'Silver', 'badge' => 'Silver', 'sort_order' => 2,
                'min_wagered' => 2500, 'bonus_amount' => 10, 'rakeback_percent' => 3,
                'daily_reward_amount' => 0.40, 'birthday_bonus_amount' => 5,
                'withdrawal_processing_hours' => 12, 'monthly_withdrawal_limit' => 10000,
                'support_level' => 'Priority', 'exclusive_promotions' => 'Bi-weekly',
                'referral_bonus_boost_percent' => 10,
            ],
            [
                'name' => 'Gold', 'badge' => 'Gold', 'sort_order' => 3,
                'min_wagered' => 10000, 'bonus_amount' => 40, 'rakeback_percent' => 5,
                'daily_reward_amount' => 0.80, 'birthday_bonus_amount' => 15,
                'withdrawal_processing_hours' => 6, 'monthly_withdrawal_limit' => 25000,
                'support_level' => 'Priority', 'exclusive_promotions' => 'Weekly',
                'referral_bonus_boost_percent' => 15,
            ],
            [
                'name' => 'Platinum', 'badge' => 'Platinum', 'sort_order' => 4,
                'min_wagered' => 50000, 'bonus_amount' => 150, 'rakeback_percent' => 7,
                'daily_reward_amount' => 1.50, 'birthday_bonus_amount' => 50,
                'withdrawal_processing_hours' => 2, 'monthly_withdrawal_limit' => 50000,
                'support_level' => 'Dedicated manager', 'exclusive_promotions' => 'Personalized',
                'referral_bonus_boost_percent' => 25,
            ],
        ];

        foreach ($tiers as $tier) {
            $existing = DB::table('vip_tiers')->where('name', $tier['name'])->first();
            if ($existing) {
                DB::table('vip_tiers')->where('id', $existing->id)->update($tier + ['updated_at' => $now]);
            } else {
                DB::table('vip_tiers')->insert($tier + ['status' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('vip_tiers', function (Blueprint $table) {
            foreach (['daily_reward_amount', 'birthday_bonus_amount', 'withdrawal_processing_hours', 'monthly_withdrawal_limit', 'support_level', 'exclusive_promotions', 'referral_bonus_boost_percent'] as $column) {
                if (Schema::hasColumn('vip_tiers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
