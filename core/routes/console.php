<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('currencies:update-rates')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('table-games:sweep-expired')->everyMinute()->withoutOverlapping();
Schedule::command('competitions:sweep')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('coin-vendors:cleanup-application-data')->dailyAt('03:30')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();
