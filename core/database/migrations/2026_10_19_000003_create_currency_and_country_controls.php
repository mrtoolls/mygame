<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CURRENCIES = [
        ['USD', 'US Dollar', '$', 2, 'left', '1.000000000000'],
        ["GBP", "Pound Sterling", "\u{00A3}", 2, "left", null],
        ["NGN", "Nigerian Naira", "\u{20A6}", 2, "left", null],
        ["GHS", "Ghanaian Cedi", "GH\u{20B5}", 2, "left", null],
        ['KES', 'Kenyan Shilling', 'KSh', 2, 'left', null],
        ['UGX', 'Ugandan Shilling', 'USh', 0, 'left', null],
        ['TZS', 'Tanzanian Shilling', 'TSh', 0, 'left', null],
        ['ZAR', 'South African Rand', 'R', 2, 'left', null],
        ['XAF', 'Central African CFA Franc', 'FCFA', 0, 'right', null],
        ['ZMW', 'Zambian Kwacha', 'K', 2, 'left', null],
        ['SLE', 'Sierra Leonean Leone', 'Le', 2, 'left', null],
        ['RWF', 'Rwandan Franc', 'RF', 0, 'left', null],
    ];

    private const COUNTRY_CURRENCIES = [
        'US' => 'USD', 'GB' => 'GBP', 'NG' => 'NGN', 'GH' => 'GHS',
        'KE' => 'KES', 'UG' => 'UGX', 'TZ' => 'TZS', 'ZA' => 'ZAR',
        'CM' => 'XAF', 'ZM' => 'ZMW', 'SL' => 'SLE', 'RW' => 'RWF',
    ];

    public function up(): void
    {
        $legacyCode = strtoupper((string) DB::table('general_settings')->value('cur_text'));
        if (!in_array($legacyCode, array_column(self::CURRENCIES, 0), true)) {
            throw new RuntimeException("Legacy site currency '{$legacyCode}' is not seeded. Add it to the currency seed before applying this migration.");
        }

        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique();
            $table->string('name', 80);
            $table->string('symbol', 12);
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->boolean('enabled')->default(false);
            $table->enum('symbol_position', ['left', 'right'])->default('left');
            $table->decimal('rate_to_usd', 24, 12)->nullable();
            $table->enum('rate_source', ['manual', 'api'])->default('manual');
            $table->boolean('auto_update')->default(false);
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('currency_rate_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $table->decimal('old_rate_to_usd', 24, 12)->nullable();
            $table->decimal('new_rate_to_usd', 24, 12)->nullable();
            $table->string('source', 16);
            $table->foreignId('changed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['currency_id', 'created_at']);
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->char('iso_code', 2)->unique();
            $table->string('name', 100);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_allowed')->default(false);
            $table->foreignId('currency_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone_code', 12)->nullable();
            $table->json('allowed_payment_methods')->nullable();
            $table->unsignedBigInteger('min_deposit_minor')->default(0);
            $table->unsignedBigInteger('max_deposit_minor')->default(0);
            $table->unsignedBigInteger('min_withdrawal_minor')->default(0);
            $table->unsignedBigInteger('max_withdrawal_minor')->default(0);
            $table->unsignedBigInteger('min_table_stake_minor')->default(0);
            $table->unsignedBigInteger('max_table_stake_minor')->default(0);
            $table->timestamps();
            $table->index(['is_enabled', 'is_allowed']);
        });

        Schema::create('country_access_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable()->index();
            $table->boolean('is_allowed')->default(true);
            $table->string('reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'is_allowed']);
        });

        Schema::create('country_block_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('detected_country_code', 2)->nullable()->index();
            $table->string('path', 500)->nullable();
            $table->string('method', 12)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
        });

        Schema::create('country_access_mismatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('selected_country_code', 2)->nullable();
            $table->string('detected_country_code', 2);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('user_currency_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('old_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('new_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignId('old_country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('new_country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();
        });

        $now = now();
        foreach (self::CURRENCIES as [$code, $name, $symbol, $decimals, $position, $usdRate]) {
            DB::table('currencies')->updateOrInsert(['code' => $code], [
                'name' => $name, 'symbol' => $symbol, 'decimals' => $decimals,
                'enabled' => true, 'symbol_position' => $position,
                'rate_to_usd' => $usdRate, 'rate_source' => 'manual',
                'last_updated_at' => $usdRate ? $now : null, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $currencyIds = DB::table('currencies')->pluck('id', 'code');
        $countryData = json_decode(file_get_contents(resource_path('views/partials/country.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($countryData as $isoCode => $definition) {
            $isAllowed = array_key_exists($isoCode, self::COUNTRY_CURRENCIES);
            DB::table('countries')->updateOrInsert(['iso_code' => strtoupper($isoCode)], [
                'name' => $definition['country'],
                'is_enabled' => true,
                'is_allowed' => $isAllowed,
                'currency_id' => $currencyIds[self::COUNTRY_CURRENCIES[$isoCode] ?? 'USD'] ?? null,
                'phone_code' => $definition['dial_code'] ?? null,
                'allowed_payment_methods' => json_encode([]),
                'updated_at' => $now,
                'created_at' => $now,
            ]);
        }

        $this->addCurrencyColumns((int) $currencyIds[$legacyCode]);
    }

    // Existing decimal balances and ledger rows retain the site's configured legacy currency.
    // This migration tags records only; it does not convert their stored amounts.
    private function addCurrencyColumns(int $legacyCurrencyId): void
    {
        $columns = [
            'users' => ['country_id' => true, 'currency_id' => true],
            'transactions' => ['currency_id' => false],
            'deposits' => ['currency_id' => false],
            'withdrawals' => ['currency_id' => false],
            'game_logs' => ['currency_id' => false],
            'balance_transfers' => ['currency_id' => false],
            'commission_logs' => ['currency_id' => false],
            'gameplay_bonus_logs' => ['currency_id' => false],
            'vip_rakeback_claims' => ['currency_id' => false],
            'table_games' => ['currency_id' => false],
            'table_game_players' => ['currency_id' => false],
            'table_game_commissions' => ['currency_id' => false],
            'support_agents' => ['country_id' => true, 'currency_id' => false],
        ];

        foreach ($columns as $tableName => $tableColumns) {
            if (!Schema::hasTable($tableName)) continue;
            $missing = [];
            if (isset($tableColumns['country_id']) && !Schema::hasColumn($tableName, 'country_id')) $missing[] = 'country_id';
            if (!Schema::hasColumn($tableName, 'currency_id')) $missing[] = 'currency_id';
            if ($missing) Schema::table($tableName, function (Blueprint $table) use ($missing) {
                if (in_array('country_id', $missing, true)) $table->unsignedBigInteger('country_id')->nullable()->index();
                if (in_array('currency_id', $missing, true)) $table->unsignedBigInteger('currency_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('users')) DB::table('users')->whereNull('currency_id')->update(['currency_id' => $legacyCurrencyId]);
        foreach (['transactions', 'deposits', 'withdrawals', 'game_logs', 'commission_logs', 'gameplay_bonus_logs', 'vip_rakeback_claims'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'currency_id')) {
                $ownerColumn = $tableName === 'support_agents' ? null : 'user_id';
                if ($ownerColumn && Schema::hasColumn($tableName, $ownerColumn)) {
                    DB::table($tableName)->whereNull('currency_id')->update(['currency_id' => $legacyCurrencyId]);
                }
            }
        }
        foreach (['balance_transfers', 'table_games', 'table_game_players', 'table_game_commissions', 'support_agents'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'currency_id')) DB::table($tableName)->whereNull('currency_id')->update(['currency_id' => $legacyCurrencyId]);
        }
    }

    public function down(): void
    {
        foreach (['users', 'transactions', 'deposits', 'withdrawals', 'game_logs', 'balance_transfers', 'commission_logs', 'gameplay_bonus_logs', 'vip_rakeback_claims', 'table_games', 'table_game_players', 'table_game_commissions', 'support_agents'] as $tableName) {
            if (!Schema::hasTable($tableName)) continue;
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['currency_id', 'country_id'] as $column) if (Schema::hasColumn($tableName, $column)) $table->dropColumn($column);
            });
        }
        Schema::dropIfExists('user_currency_change_logs');
        Schema::dropIfExists('country_access_mismatches');
        Schema::dropIfExists('country_block_attempts');
        Schema::dropIfExists('country_access_overrides');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('currency_rate_logs');
        Schema::dropIfExists('currencies');
    }
};
