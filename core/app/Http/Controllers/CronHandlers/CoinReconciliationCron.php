<?php

namespace App\Http\Controllers\CronHandlers;

use App\Models\AdminNotification;
use App\Services\CoinLedger;

/**
 * Registered as a default cron_jobs row (see the 2026_10_24_000015 migration) on a daily
 * schedule, run through the same /cron endpoint every other scheduled job already uses - this
 * app has no real OS-level cron access, only cPanel hitting that one URL periodically.
 */
class CoinReconciliationCron
{
    public function run(): void
    {
        $general = gs();
        if (!$general->coin_mode) return;

        $result = app(CoinLedger::class)->reconcile();

        $general->coin_last_reconciliation_at = now();
        $general->coin_last_reconciliation_ok = $result['ok'];
        $general->save();

        if (!$result['ok']) {
            $adminNotification = new AdminNotification();
            $adminNotification->user_id = 0;
            $adminNotification->title = "Coin ledger reconciliation FAILED - circulating {$result['circulating']} + platform {$result['platform_balance']} = {$result['difference']} (should be 0)";
            $adminNotification->click_url = urlPath('admin.coin.settings');
            $adminNotification->save();
        }
    }
}
