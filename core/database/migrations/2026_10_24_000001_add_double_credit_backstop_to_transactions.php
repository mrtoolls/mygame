<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Row-lock + status-guard already makes every ledger mutation atomic (deposit credit,
// withdrawal reject-refund, balance transfer, referral commission, admin adjustment, stuck-game
// refund). This is the DB-level backstop behind that: every one of those paths writes exactly
// one Transaction row per (trx, remark) pair - a deposit's credit reuses the deposit's own trx
// with remark 'deposit', a withdrawal's reject-refund reuses the withdrawal's trx with remark
// 'withdraw_reject', and so on. A balance transfer's debit and credit legs intentionally SHARE
// one trx but use two different remarks ('balance_transfer' vs 'balance_received'/
// 'receive_transfer'), so this constraint never collides with that legitimate pattern - it only
// ever fires if the exact same (trx, remark) pair is written twice, which should never happen
// and would mean something tried to process the same ledger event more than once.
return new class extends Migration {
    private const INDEX_NAME = 'transactions_trx_remark_unique';

    public function up(): void
    {
        if (!Schema::hasTable('transactions') || $this->indexExists()) {
            return;
        }

        $duplicates = DB::table('transactions')
            ->select('trx', 'remark')
            ->whereNotNull('trx')
            ->groupBy('trx', 'remark')
            ->havingRaw('COUNT(*) > 1')
            ->limit(5)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $sample = $duplicates->map(fn ($row) => "{$row->trx}/{$row->remark}")->implode(', ');
            throw new RuntimeException(
                "Cannot add the transactions(trx, remark) uniqueness backstop: existing duplicate rows found ({$sample}, ...). " .
                'Investigate and resolve these first (they indicate a past double-processing bug), then re-run this migration.'
            );
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->unique(['trx', 'remark'], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('transactions') && $this->indexExists()) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropUnique(self::INDEX_NAME);
            });
        }
    }

    private function indexExists(): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            foreach (DB::select('PRAGMA index_list(transactions)') as $index) {
                if ($index->name === self::INDEX_NAME) {
                    return true;
                }
            }
            return false;
        }

        return count(DB::select('SHOW INDEX FROM `transactions` WHERE Key_name = ?', [self::INDEX_NAME])) > 0;
    }
};
