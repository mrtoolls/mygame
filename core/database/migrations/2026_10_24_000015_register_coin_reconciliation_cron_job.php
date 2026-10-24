<?php

use App\Constants\Status;
use App\Http\Controllers\CronHandlers\CoinReconciliationCron;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// This app has no OS-level cron access - everything scheduled runs through the DB-driven
// cron_jobs table, triggered whenever cPanel's own cron GUI hits the /cron route (no shell
// needed to set that up). This registers coin reconciliation as a default job on a daily
// schedule, exactly like every other scheduled job here, so turning it on needs nothing beyond
// running this migration from the admin Database Update button.
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('cron_schedules') || !Schema::hasTable('cron_jobs')) return;

        $schedule = DB::table('cron_schedules')->where('interval', 86400)->first();
        $scheduleId = $schedule?->id;
        if (!$scheduleId) {
            $scheduleId = DB::table('cron_schedules')->insertGetId([
                'name' => 'Daily',
                'interval' => 86400,
                'status' => Status::ENABLE,
            ]);
        }

        $exists = DB::table('cron_jobs')->where('alias', 'coin-reconciliation')->exists();
        if (!$exists) {
            DB::table('cron_jobs')->insert([
                'name' => 'Coin Reconciliation',
                'alias' => 'coin-reconciliation',
                'next_run' => now(),
                'cron_schedule_id' => $scheduleId,
                'url' => null,
                'is_default' => Status::YES,
                'is_running' => Status::YES,
                'action' => json_encode([CoinReconciliationCron::class, 'run']),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cron_jobs')) {
            DB::table('cron_jobs')->where('alias', 'coin-reconciliation')->delete();
        }
    }
};
