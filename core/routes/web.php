<?php

use Illuminate\Support\Facades\Route;

Route::get('/clear', function () {
    \Illuminate\Support\Facades\Artisan::call('optimize:clear');
});

Route::get('cron', 'CronController@cron')->name('cron');

Route::get('stats/live', 'StatsController@live')->name('stats.live')->middleware('throttle:30,1');
Route::get('winxpay', 'WinxPayController@index')->name('winxpay');

Route::prefix('support-chat')->name('support-chat.')->controller('SupportChatController')->group(function () {
    Route::get('active', 'active')->name('active')->middleware('throttle:30,1');
    Route::get('agents', 'agents')->name('agents')->middleware('throttle:60,1');
    Route::post('start', 'start')->name('start')->middleware('throttle:15,1');
    Route::get('{uuid}/messages', 'messages')->name('messages')->middleware('throttle:60,1');
    Route::post('{uuid}/messages', 'send')->name('send')->middleware('throttle:20,1');
});

Route::prefix('support-agent')->name('support-agent.')->controller('SupportAgentController')->group(function () {
    Route::get('login', 'loginForm')->name('login');
    Route::post('login', 'login')->middleware('throttle:6,1')->name('login.submit');
    Route::middleware('support.agent')->group(function () {
        Route::post('logout', 'logout')->name('logout');
        Route::get('/', 'dashboard')->name('dashboard');
        Route::get('conversations', 'conversations')->name('conversations')->middleware('throttle:60,1');
        Route::post('conversations/{uuid}/claim', 'claim')->name('claim');
        Route::get('conversations/{uuid}/messages', 'messages')->name('messages')->middleware('throttle:60,1');
        Route::post('conversations/{uuid}/messages', 'reply')->name('reply')->middleware('throttle:20,1');
        Route::post('conversations/{uuid}/close', 'close')->name('close');

        Route::prefix('payments')->name('payments.')->group(function () {
            Route::get('/', 'paymentQueue')->name('queue');
            Route::get('{id}', 'paymentShow')->name('show')->whereNumber('id');
            Route::post('{id}/confirm', 'paymentConfirm')->name('confirm')->whereNumber('id');
            Route::post('{id}/reject', 'paymentReject')->name('reject')->whereNumber('id');
            Route::get('settings/accepted-types', 'paymentSettings')->name('settings');
            Route::post('settings/accepted-types', 'paymentSettingsUpdate')->name('settings.update');
            Route::post('notifications/read', 'paymentNotificationsRead')->name('notifications.read');
        });
    });
});

// User Support Ticket
Route::controller('TicketController')->prefix('ticket')->name('ticket.')->group(function () {
    Route::get('/', 'supportTicket')->name('index');
    Route::get('new', 'openSupportTicket')->name('open');
    Route::post('create', 'storeSupportTicket')->name('store');
    Route::get('view/{ticket}', 'viewTicket')->name('view');
    Route::post('reply/{id}', 'replyTicket')->name('reply');
    Route::post('close/{id}', 'closeTicket')->name('close');
    Route::get('download/{attachment_id}', 'ticketDownload')->name('download');
});

Route::get('app/deposit/confirm/{hash}', 'Gateway\PaymentController@appDepositConfirm')->name('deposit.app.confirm');

Route::controller('SiteController')->group(function () {
    Route::post('feedback', [\App\Http\Controllers\FeedbackController::class, 'store'])->middleware(['auth', 'throttle:5,1'])->name('feedback.store');
    Route::get('feedback', 'pages')->defaults('slug', 'feedback-about-us')->name('feedback');
    Route::get('/pwa/configuration', 'pwaConfiguration')->name('pwa.configuration');
    Route::get('/contact', fn () => redirect()->route('ticket.index'))->name('contact');
    Route::post('/contact', fn () => redirect()->route('ticket.index'));
    Route::get('/change/{lang?}', 'changeLanguage')->name('lang');
    Route::post('/subscribe', 'subscribe')->name('subscribe.post');
    Route::get('cookie-policy', 'cookiePolicy')->name('cookie.policy');
    Route::get('/cookie/accept', 'cookieAccept')->name('cookie.accept');
    Route::get('games', 'games')->name('games');
    Route::get('blog', 'blog')->name('blog');
    Route::get('blog/{slug}', 'blogDetails')->name('blog.details');
    Route::get('policy/{slug}', 'policyPages')->name('policy.pages');
    Route::get('placeholder-image/{size}', 'placeholderImage')->withoutMiddleware('maintenance')->name('placeholder.image');
    Route::get('maintenance-mode', 'maintenance')->withoutMiddleware('maintenance')->name('maintenance');
    Route::get('/{slug}', 'pages')->name('pages');
    Route::get('/', 'index')->name('home');
});
