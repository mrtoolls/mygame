<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only inventory and precision preflight for the legacy decimal money model. */
final class MoneyConversionAudit
{
    private const AMOUNT_COLUMNS = [
        'users' => ['balance', 'demo_balance', 'rakeback_claimed_total'],
        'transactions' => ['amount', 'charge', 'post_balance'],
        'deposits' => ['amount', 'charge', 'final_amount'],
        'withdrawals' => ['amount', 'charge', 'after_charge', 'final_amount'],
        'game_logs' => ['invest', 'win_amo'],
        'balance_transfers' => ['amount', 'charge', 'final_amount'],
        'commission_logs' => ['amount'],
        'gameplay_bonus_logs' => ['amount'],
        'vip_rakeback_claims' => ['amount', 'total_wagered_snapshot'],
        'table_games' => ['stake'],
        'table_game_players' => ['stake'],
        'table_game_commissions' => ['amount'],
        'bonus_codes' => ['amount'],
        'bonus_code_redemptions' => ['amount'],
    ];

    public function run(): array
    {
        abort_unless(Schema::hasTable('currencies'), 503, 'Run the currency controls database updater first.');
        $fields = [];
        $missingTables = [];

        foreach (self::AMOUNT_COLUMNS as $table => $columns) {
            if (!Schema::hasTable($table)) {
                $missingTables[] = $table;
                continue;
            }
            $hasCurrency = Schema::hasColumn($table, 'currency_id');
            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) continue;
                $query = DB::table($table);
                $fields[] = [
                    'table' => $table,
                    'column' => $column,
                    'database_type' => $this->columnType($table, $column),
                    'rows' => (clone $query)->count(),
                    'nonzero_rows' => (clone $query)->whereNotNull($column)->where($column, '<>', 0)->count(),
                    'currency_unassigned' => $hasCurrency ? (clone $query)->whereNull('currency_id')->whereNotNull($column)->where($column, '<>', 0)->count() : null,
                    'fractional_precision_issues' => $hasCurrency ? $this->precisionIssueCount($table, $column) : null,
                    'precision_issue_examples' => $hasCurrency ? $this->precisionIssueExamples($table, $column) : [],
                ];
            }
        }

        return [
            'fields' => $fields,
            'missing_tables' => $missingTables,
            'checked_at' => now(),
            'total_rows' => array_sum(array_column($fields, 'rows')),
            'unassigned_rows' => array_sum(array_filter(array_column($fields, 'currency_unassigned'), fn ($value) => $value !== null)),
            'precision_issue_rows' => array_sum(array_filter(array_column($fields, 'fractional_precision_issues'), fn ($value) => $value !== null)),
        ];
    }

    private function precisionIssueCount(string $table, string $column): int
    {
        // Table and column names come only from the class allow-list above.
        $wrappedTable = DB::getQueryGrammar()->wrapTable($table);
        $wrappedColumn = DB::getQueryGrammar()->wrap($column);
        return DB::table($table)
            ->whereNotNull($column)
            ->whereRaw("ABS(CAST({$wrappedColumn} AS DECIMAL(38,12)) - ROUND(CAST({$wrappedColumn} AS DECIMAL(38,12)), COALESCE((SELECT decimals FROM currencies WHERE currencies.id = {$wrappedTable}.currency_id), 2))) > 0.000000001")
            ->count();
    }

    private function precisionIssueExamples(string $table, string $column): array
    {
        // Names are selected from AMOUNT_COLUMNS; only a small sample is exposed to admins.
        $query = DB::table($table)
            ->leftJoin('currencies', 'currencies.id', '=', $table . '.currency_id')
            ->whereNotNull($table . '.' . $column)
            ->whereRaw('ABS(CAST(' . DB::getQueryGrammar()->wrap($table . '.' . $column) . ' AS DECIMAL(38,12)) - ROUND(CAST(' . DB::getQueryGrammar()->wrap($table . '.' . $column) . ' AS DECIMAL(38,12)), COALESCE(currencies.decimals, 2))) > 0.000000001');

        $select = [];
        foreach (['id', 'user_id'] as $identifier) {
            if (Schema::hasColumn($table, $identifier)) $select[] = $table . '.' . $identifier;
        }
        $select[] = $table . '.' . $column . ' as amount_value';
        $select[] = 'currencies.code as currency_code';
        $select[] = 'currencies.decimals as currency_decimals';

        return $query->select($select)->limit(10)->get()->map(fn ($row) => (array) $row)->all();
    }

    private function columnType(string $table, string $column): string
    {
        foreach (Schema::getColumns($table) as $definition) {
            if ($definition['name'] === $column) return (string) ($definition['type'] ?? 'unknown');
        }
        return 'unknown';
    }
}
