<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;

class SystemController extends Controller
{
    public function systemInfo()
    {
        $laravelVersion = app()->version();
        $timeZone       = config('app.timezone');
        $pageTitle      = 'Application Information';

        return view('admin.system.info', compact('pageTitle', 'laravelVersion', 'timeZone'));
    }

    public function optimize()
    {
        $pageTitle = 'Clear System Cache';

        return view('admin.system.optimize', compact('pageTitle'));
    }

    public function optimizeClear()
    {
        Artisan::call('optimize:clear');
        $notify[] = ['success', 'Cache cleared successfully'];

        return back()->withNotify($notify);
    }

    public function systemServerInfo()
    {
        $currentPHP    = phpversion();
        $pageTitle     = 'Server Information';
        $serverDetails = $_SERVER;

        return view('admin.system.server', compact('pageTitle', 'serverDetails', 'currentPHP'));
    }

    public function databaseUpdate()
    {
        $pageTitle = 'Database Update';
        $pending   = [];
        try {
            $pending = Artisan::call('migrate:status') === 0
                ? collect(explode("\n", Artisan::output()))
                    ->filter(fn($line) => str_contains($line, 'Pending'))
                    ->values()
                : [];
        } catch (\Throwable $e) {
            $pending = [];
        }
        return view('admin.system.database_update', compact('pageTitle', 'pending'));
    }

    public function databaseUpdateRun()
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $output   = Artisan::output();
            $notify[] = ['success', 'Database update finished'];
        } catch (\Throwable $e) {
            $output   = $e->getMessage();
            $notify[] = ['error', 'Database update failed, see the output below'];
        }
        session()->flash('migrate_output', $output);
        return back()->withNotify($notify);
    }
}
