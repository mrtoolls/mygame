<?php

namespace App\Providers;

use App\Constants\Status;
use App\Lib\Searchable;
use App\Models\AdminNotification;
use App\Models\Deposit;
use App\Models\Frontend;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider {
    /**
     * Register any application services.
     */
    public function register(): void {
        Builder::mixin(new Searchable);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {
        // Keep vendor application actions in separate buckets. Numeric throttle
        // middleware shares a user/IP key, which let repeated camera retries
        // exhaust the draft, challenge, and upload limits together.
        foreach ([
            'coin-vendor-draft' => 10,
            'coin-vendor-challenge' => 8,
            'coin-vendor-selfie-upload' => 5,
            'coin-vendor-submit' => 5,
        ] as $name => $attempts) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute($attempts)
                ->by($name . '|' . ($request->user()?->getAuthIdentifier() ?? 'guest') . '|' . $request->ip()));
        }

        // Never query the configured cache store during application boot: a fresh
        // install may not have database cache tables yet, and Artisan must still
        // be able to run the migration that creates them.
        $envFilePath = base_path('.env');
        $isInstalled = is_file($envFilePath) && filesize($envFilePath) > 0;
        if (!$isInstalled && !$this->app->runningInConsole()) {
            header('Location: install');
            exit;
        }
        if (!$this->app->runningInConsole() && config('session.driver') === 'database') {
            try {
                if (!Schema::hasTable(config('session.table', 'sessions'))) config(['session.driver' => 'file']);
            } catch (Throwable) {
                config(['session.driver' => 'file']);
            }
        }
        $viewShare['emptyMessage'] = 'Data not found';
        view()->share($viewShare);

        view()->composer('admin.partials.sidenav', function ($view) {
            $view->with([
                'bannedUsersCount'           => User::banned()->count(),
                'emailUnverifiedUsersCount'  => User::emailUnverified()->count(),
                'mobileUnverifiedUsersCount' => User::mobileUnverified()->count(),
                'kycUnverifiedUsersCount'    => User::kycUnverified()->count(),
                'kycPendingUsersCount'       => User::kycPending()->count(),
                'pendingTicketCount'         => SupportTicket::whereIN('status', [Status::TICKET_OPEN, Status::TICKET_REPLY])->count(),
                'pendingDepositsCount'       => Deposit::pending()->count(),
                'pendingWithdrawCount'       => Withdrawal::pending()->count(),
            ]);
        });

        view()->composer('admin.partials.topnav', function ($view) {
            $view->with([
                'adminNotifications'     => AdminNotification::where('is_read', Status::NO)->with('user')->orderBy('id', 'desc')->take(10)->get(),
                'adminNotificationCount' => AdminNotification::where('is_read', Status::NO)->count(),
            ]);
        });

        view()->composer('partials.seo', function ($view) {
            $seo = Frontend::where('data_keys', 'seo.data')->first();
            $view->with([
                'seo' => $seo ? $seo->data_values : $seo,
            ]);
        });

        if (!$this->app->runningInConsole() && gs('force_ssl')) {
            \URL::forceScheme('https');
        }

        Paginator::useBootstrapFive();
    }
}
