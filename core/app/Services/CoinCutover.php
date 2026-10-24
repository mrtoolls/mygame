<?php

namespace App\Services;

use App\Constants\Status;
use App\Games\TableGames\TableGameService;
use App\Models\CoinCutoverLog;
use App\Models\CoinCutoverUserArchive;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\GameLog;
use App\Models\TableGame;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * One-time (but safely re-runnable - everything here is idempotent) cutover from real-currency
 * balances to coins. preview() computes everything execute() would do without changing any
 * data, so the admin tool can show a dry run first. No announcements/staged-rollout machinery
 * here - there are no real players yet, only test accounts, per an explicit instruction for
 * this build.
 */
class CoinCutover
{
    public function __construct(private CoinLedger $ledger, private CoinPricing $pricing)
    {
    }

    public function preview(string $mode, ?string $resetAmount = null): array
    {
        $this->assertValidMode($mode, $resetAmount);

        $runningGames = GameLog::where('status', Status::DISABLE)->count();
        $openTables = \Illuminate\Support\Facades\Schema::hasTable('table_games') ? TableGame::whereIn('status', ['waiting', 'active'])->count() : 0;
        $pendingDeposits = Deposit::whereIn('status', [Status::PAYMENT_INITIATE, Status::PAYMENT_PENDING])->count();
        $pendingWithdrawals = Withdrawal::where('status', Status::PAYMENT_PENDING)->count();

        $userCount = 0;
        $totalRealBalance = '0';
        $totalCoinsToMint = '0';
        $pricingErrors = [];

        User::query()->chunkById(500, function ($users) use (&$userCount, &$totalRealBalance, &$totalCoinsToMint, &$pricingErrors, $mode, $resetAmount) {
            foreach ($users as $user) {
                $userCount++;
                $totalRealBalance = bcadd($totalRealBalance, (string) $user->balance, 8);
                try {
                    $totalCoinsToMint = bcadd($totalCoinsToMint, $this->coinAmountFor($user, $mode, $resetAmount), 8);
                } catch (InvalidArgumentException $exception) {
                    $pricingErrors[$user->id] = $exception->getMessage();
                }
            }
        });

        return [
            'mode' => $mode,
            'reset_amount' => $resetAmount,
            'users_affected' => $userCount,
            'total_real_balance' => $totalRealBalance,
            'total_coins_to_mint' => $totalCoinsToMint,
            'pricing_errors' => $pricingErrors,
            'running_games_to_refund' => $runningGames,
            'open_tables_to_force_close' => $openTables,
            'pending_deposits_to_cancel' => $pendingDeposits,
            'pending_withdrawals_to_cancel' => $pendingWithdrawals,
        ];
    }

    public function execute(string $mode, ?string $resetAmount, int $adminId): CoinCutoverLog
    {
        $this->assertValidMode($mode, $resetAmount);
        $startedAt = now();

        // Created up front (not after the loop) so every per-user archive row below can carry a
        // real run id from the start, instead of a time-window query to backfill it afterwards.
        $log = CoinCutoverLog::create([
            'mode' => $mode,
            'dry_run' => false,
            'admin_id' => $adminId,
            'summary' => [],
            'started_at' => $startedAt,
        ]);

        $gamesRefunded = $this->settleRunningGames();
        $tablesClosed = $this->closeOpenTables();
        $depositsCancelled = $this->cancelPendingDeposits();
        $withdrawalsCancelled = $this->cancelPendingWithdrawals();

        $usersConverted = 0;
        $totalCoinsMinted = '0';
        $rakebackMaterialized = 0;
        $errors = [];
        $vipController = $mode === 'convert' ? app(\App\Http\Controllers\User\VipController::class) : null;

        User::chunkById(500, function ($users) use (&$usersConverted, &$totalCoinsMinted, &$rakebackMaterialized, &$errors, $mode, $resetAmount, $vipController, $adminId, $log) {
            foreach ($users as $user) {
                try {
                    $legacyBalance = (string) $user->balance;
                    $hadBalance = bccomp($legacyBalance, '0', 8) > 0;
                    $rateUsed = null;
                    if ($mode === 'convert' && $hadBalance && $user->currency_id) {
                        $currency = Currency::find($user->currency_id);
                        if ($currency) {
                            try { $rateUsed = $this->pricing->buyPrice($currency); } catch (InvalidArgumentException) {}
                        }
                    }

                    $coinAmount = $this->coinAmountFor($user, $mode, $resetAmount);
                    if (bccomp($coinAmount, '0', 8) > 0) {
                        $this->ledger->mint('user', $user->id, $coinAmount, 'Coin cutover (' . $mode . ')', 'cutover:' . $mode . ':user:' . $user->id);
                    }
                    $this->zeroLegacyBalance($user);
                    // Per the project's decision, unclaimed rakeback is materialized into coins
                    // at cutover (convert mode only - a test-data reset has nothing to do with
                    // real accrued rakeback).
                    if ($vipController && $vipController->materializeRakebackToCoins($user, 'admin', $adminId)) {
                        $rakebackMaterialized++;
                    }
                    // Only a real legacy-balance conversion gets an archive row - a re-run against
                    // an already-zeroed user (idempotency) or a reset_test mint has nothing to
                    // archive here, so nothing new is written on a repeat run.
                    if ($mode === 'convert' && $hadBalance) {
                        CoinCutoverUserArchive::create([
                            'coin_cutover_log_id' => $log->id,
                            'user_id' => $user->id,
                            'legacy_balance' => $legacyBalance,
                            'currency_id' => $user->currency_id,
                            'rate_used' => $rateUsed,
                            'coins_minted' => $coinAmount,
                            'created_at' => now(),
                        ]);
                    }
                    $usersConverted++;
                    $totalCoinsMinted = bcadd($totalCoinsMinted, $coinAmount, 8);
                } catch (InvalidArgumentException $exception) {
                    $errors[$user->id] = $exception->getMessage();
                }
            }
        });

        $summary = [
            'mode' => $mode,
            'reset_amount' => $resetAmount,
            'users_converted' => $usersConverted,
            'total_coins_minted' => $totalCoinsMinted,
            'rakeback_materialized_for_users' => $rakebackMaterialized,
            'pricing_errors' => $errors,
            'running_games_refunded' => $gamesRefunded,
            'open_tables_force_closed' => $tablesClosed,
            'pending_deposits_cancelled' => $depositsCancelled,
            'pending_withdrawals_cancelled' => $withdrawalsCancelled,
            'reconciliation' => $this->ledger->reconcile(),
        ];

        $log->update(['summary' => $summary, 'finished_at' => now()]);
        return $log->fresh();
    }

    private function coinAmountFor(User $user, string $mode, ?string $resetAmount): string
    {
        if ($mode === 'reset_test') {
            return (string) ($resetAmount ?? '0');
        }

        if (bccomp((string) $user->balance, '0', 8) <= 0) return '0';
        if (!$user->currency_id) throw new InvalidArgumentException("User #{$user->id} has no wallet currency set.");

        $currency = \App\Models\Currency::find($user->currency_id);
        if (!$currency) throw new InvalidArgumentException("User #{$user->id}'s currency could not be found.");

        $buyPrice = $this->pricing->buyPrice($currency);
        return bcdiv((string) $user->balance, $buyPrice, 0);
    }

    private function zeroLegacyBalance(User $user): void
    {
        DB::transaction(function () use ($user) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();
            if (bccomp((string) $locked->balance, '0', 8) <= 0) return;

            $oldBalance = $locked->balance;
            $locked->balance = 0;
            $locked->save();

            $transaction = new Transaction();
            $transaction->user_id = $locked->id;
            $transaction->amount = $oldBalance;
            $transaction->charge = 0;
            $transaction->trx_type = '-';
            $transaction->details = 'Coin cutover - legacy balance converted and zeroed';
            $transaction->remark = 'cutover_conversion';
            $transaction->trx = 'CUTOVER' . $locked->id;
            $transaction->post_balance = 0;
            $transaction->save();
        });
    }

    private function settleRunningGames(): int
    {
        $games = GameLog::where('status', Status::DISABLE)->get();
        $count = 0;
        foreach ($games as $game) {
            DB::transaction(function () use ($game, &$count) {
                $locked = GameLog::whereKey($game->id)->lockForUpdate()->first();
                if ((int) $locked->status !== Status::DISABLE) return;

                $user = User::whereKey($locked->user_id)->lockForUpdate()->first();
                if (!$user) return;

                $user->balance = bcadd((string) $user->balance, (string) $locked->invest, 8);
                $user->save();

                $transaction = new Transaction();
                $transaction->user_id = $user->id;
                $transaction->amount = $locked->invest;
                $transaction->charge = 0;
                $transaction->trx_type = '+';
                $transaction->details = 'Coin cutover - in-progress game invest returned';
                $transaction->remark = 'invest_return';
                $transaction->trx = getTrx();
                $transaction->post_balance = $user->balance;
                $transaction->save();

                $locked->status = 2;
                $locked->save();
                $count++;
            });
        }
        return $count;
    }

    private function closeOpenTables(): int
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('table_games')) return 0;
        $service = app(TableGameService::class);
        $tables = TableGame::whereIn('status', ['waiting', 'active'])->get();
        foreach ($tables as $table) {
            try {
                $service->forceClose((string) $table->id);
            } catch (\Throwable) {
                // Already settled by the time we got to it, or otherwise unclosable - skip, not
                // worth failing the whole cutover run over one stuck table.
            }
        }
        return $tables->count();
    }

    private function cancelPendingDeposits(): int
    {
        $deposits = Deposit::whereIn('status', [Status::PAYMENT_INITIATE, Status::PAYMENT_PENDING])->get();
        foreach ($deposits as $deposit) {
            DB::transaction(function () use ($deposit) {
                $locked = Deposit::whereKey($deposit->id)->lockForUpdate()->first();
                if (!in_array((int) $locked->status, [Status::PAYMENT_INITIATE, Status::PAYMENT_PENDING], true)) return;
                $locked->status = Status::PAYMENT_REJECT;
                $locked->admin_feedback = 'Cancelled automatically by the coin cutover - no balance had been credited yet.';
                $locked->save();
            });
        }
        return $deposits->count();
    }

    private function cancelPendingWithdrawals(): int
    {
        $withdrawals = Withdrawal::where('status', Status::PAYMENT_PENDING)->get();
        foreach ($withdrawals as $withdrawal) {
            DB::transaction(function () use ($withdrawal) {
                $locked = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->first();
                if ((int) $locked->status !== Status::PAYMENT_PENDING) return;
                $user = User::whereKey($locked->user_id)->lockForUpdate()->first();

                // The legacy withdrawal flow already deducted the balance at request time - give
                // it back before the cutover zeroes/converts the balance, so nothing is lost.
                $user->balance = bcadd((string) $user->balance, (string) $locked->amount, 8);
                $user->save();

                $locked->status = Status::PAYMENT_REJECT;
                $locked->admin_feedback = 'Cancelled and refunded automatically by the coin cutover.';
                $locked->save();

                $transaction = new Transaction();
                $transaction->user_id = $user->id;
                $transaction->amount = $locked->amount;
                $transaction->charge = 0;
                $transaction->trx_type = '+';
                $transaction->details = 'Coin cutover - pending withdrawal cancelled and refunded';
                $transaction->remark = 'withdraw_reject';
                $transaction->trx = $locked->trx ?? getTrx();
                $transaction->post_balance = $user->balance;
                $transaction->save();
            });
        }
        return $withdrawals->count();
    }

    private function assertValidMode(string $mode, ?string $resetAmount): void
    {
        if (!in_array($mode, ['convert', 'reset_test'], true)) {
            throw new InvalidArgumentException('Cutover mode must be "convert" or "reset_test".');
        }
        if ($mode === 'reset_test' && ($resetAmount === null || bccomp($resetAmount, '0', 8) < 0)) {
            throw new InvalidArgumentException('A non-negative reset amount is required for the reset_test mode.');
        }
    }
}
