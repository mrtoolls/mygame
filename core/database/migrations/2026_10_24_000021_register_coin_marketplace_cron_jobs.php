<?php

use App\Constants\Status;
use App\Http\Controllers\CronHandlers\CoinConfirmationWindowSweep;
use App\Http\Controllers\CronHandlers\CoinPaymentWindowSweep;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Trade payment-window expiry and confirmation-window escalation both need to run every minute
// (the brief's own "no auto-release, but don't leave a trade stuck forever either" requirement) -
// same DB-driven /cron mechanism as the reconciliation job registered in 2026_10_24_000015.
return new class extends Migration {
    private const JOBS = [
        'coin-payment-window-sweep' => ['Coin Payment Window Sweep', CoinPaymentWindowSweep::class],
        'coin-confirmation-window-sweep' => ['Coin Confirmation Window Sweep', CoinConfirmationWindowSweep::class],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('cron_schedules') || !Schema::hasTable('cron_jobs')) return;

        $schedule = DB::table('cron_schedules')->where('interval', 60)->first();
        $scheduleId = $schedule?->id;
        if (!$scheduleId) {
            $scheduleId = DB::table('cron_schedules')->insertGetId([
                'name' => 'Every Minute',
                'interval' => 60,
                'status' => Status::ENABLE,
            ]);
        }

        foreach (self::JOBS as $alias => [$name, $class]) {
            if (DB::table('cron_jobs')->where('alias', $alias)->exists()) continue;
            DB::table('cron_jobs')->insert([
                'name' => $name,
                'alias' => $alias,
                'next_run' => now(),
                'cron_schedule_id' => $scheduleId,
                'url' => null,
                'is_default' => Status::YES,
                'is_running' => Status::YES,
                'action' => json_encode([$class, 'run']),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cron_jobs')) {
            DB::table('cron_jobs')->whereIn('alias', array_keys(self::JOBS))->delete();
        }
    }
};
