<?php

use Illuminate\Support\Facades\Route;

Route::namespace('Auth')->group(function () {
    Route::middleware('admin.guest')->group(function () {
        Route::controller('LoginController')->group(function () {
            Route::get('/', 'showLoginForm')->name('login');
            // Per-account/IP lockout already comes from AuthenticatesUsers (ThrottlesLogins) in
            // the controller itself - this route-level limit is defense in depth against an
            // attacker rotating through many different admin usernames from one IP, which a
            // per-account lock alone wouldn't catch.
            Route::post('/', 'login')->middleware('throttle:10,1')->name('login');
            Route::get('logout', 'logout')->middleware('admin')->withoutMiddleware('admin.guest')->name('logout');
        });

        // Admin Password Reset
        Route::controller('ForgotPasswordController')->prefix('password')->name('password.')->middleware('throttle:10,1')->group(function () {
            Route::get('reset', 'showLinkRequestForm')->name('reset');
            Route::post('reset', 'sendResetCodeEmail');
            Route::get('code-verify', 'codeVerify')->name('code.verify');
            Route::post('verify-code', 'verifyCode')->name('verify.code');
        });

        Route::controller('ResetPasswordController')->group(function () {
            Route::get('password/reset/{token}', 'showResetForm')->name('password.reset.form');
            Route::post('password/reset/change', 'reset')->name('password.change');
        });
    });
});

// Reachable with no admin/user auth requirement by design: it only ever ends the impersonated
// user's web-guard session and sends the browser back to the admin side (or admin login if that
// guard's session has somehow also gone), never grants any access itself.
Route::post('impersonate/return', 'ManageUsersController@returnToAdmin')->name('impersonate.return');

Route::middleware('admin')->group(function () {
    Route::controller('FeedbackAdminController')->prefix('feedback')->name('feedback.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('{id}/approve', 'approve')->name('approve');
        Route::post('{id}/reject', 'reject')->name('reject');
        Route::post('{id}/update', 'update')->name('update');
        Route::post('{id}/delete', 'destroy')->name('destroy');
    });
    Route::controller('BusinessPromotionController')->prefix('business-promotions')->name('business-promotions.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('rates', 'rates')->name('rates');
        Route::post('rates', 'saveRates')->name('rates.save');
        Route::post('{id}/approve', 'approve')->name('approve');
        Route::post('{id}/reject', 'reject')->name('reject');
    });
    Route::controller('SponsorshipSubmissionController')->prefix('sponsorship-submissions')->name('sponsorship-submissions.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{id}', 'show')->name('show');
        Route::post('{id}/approve', 'approve')->name('approve');
        Route::post('{id}/reject', 'reject')->name('reject');
        Route::post('{id}/delete', 'destroy')->name('destroy');
    });

    Route::controller('CurrencyCountryController')->prefix('currency-country')->name('currency-country.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('refresh-rates', 'refreshRates')->name('rates.refresh');
        Route::post('rate-source', 'saveRateSource')->name('rate-source.save');
        Route::post('currency/{currency}/manual-rate', 'manualRateOverride')->name('currency.manual-rate');
        Route::post('countries/bulk', 'bulkCountries')->name('countries.bulk');
        Route::get('money-audit', 'moneyAudit')->name('money-audit');
        Route::post('currency/{currency}', 'updateCurrency')->name('currency.update');
        Route::post('country/{country}', 'updateCountry')->name('country.update');
        Route::post('override', 'addOverride')->name('override.create');
        Route::post('user/{user}/currency', 'changeUserCurrency')->name('user.currency');
    });

    Route::controller('CoinSettingsController')->prefix('coin')->name('coin.')->group(function () {
        Route::get('/', 'index')->name('settings');
        Route::get('pricing', 'pricing')->name('pricing');
        Route::post('pricing/margins', 'saveGlobalMargins')->name('pricing.margins');
        Route::post('pricing/refresh', 'refreshPricing')->name('pricing.refresh');
        Route::post('pricing/currency/{currencyId}/margins', 'saveCurrencyMargins')->name('pricing.currency.margins');
        Route::post('mode', 'toggleCoinMode')->name('mode.toggle');
        Route::post('single-approver', 'toggleSingleApprover')->name('single-approver.toggle');
        Route::post('test-kit', 'toggleTestKit')->name('test-kit.toggle');
        Route::post('margins/{currencyId}', 'saveMargins')->name('margins.save');
        Route::post('recompute', 'recomputeAll')->name('recompute');
    });

    Route::controller('CoinVendorController')->prefix('coin/vendors')->name('coin.vendors.')->group(function () {
        Route::get('applications', 'applications')->name('applications');
        Route::get('applications/{id}', 'show')->name('applications.show')->whereNumber('id');
        Route::get('applications/{id}/files/{kind}', 'file')->name('applications.file')->whereNumber('id');
        Route::post('applications/{id}/approve', 'approve')->name('applications.approve')->whereNumber('id');
        Route::post('applications/{id}/reject', 'reject')->name('applications.reject')->whereNumber('id');
        Route::post('applications/{id}/request-information', 'requestInformation')->name('applications.request-information')->whereNumber('id');
        Route::get('/', 'vendors')->name('index');
        Route::post('{id}/{action}', 'vendorAction')->name('action')->whereNumber('id');
        Route::get('settings', 'settings')->name('settings');
        Route::post('settings', 'saveSettings')->name('settings.save');
        Route::post('reviewer-permission/{admin}', 'reviewerPermission')->name('reviewer-permission')->whereNumber('admin');
    });

    Route::controller('AgentCoinFloatController')->prefix('coin/float')->name('coin.float.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('{id}/approve', 'approve')->name('approve');
        Route::post('{id}/reject', 'reject')->name('reject');
    });

    Route::controller('CoinDisputeController')->prefix('coin/disputes')->name('coin.disputes.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('{tradeId}/resolve', 'resolve')->name('resolve')->whereNumber('tradeId');
        Route::post('{tradeId}/penalize', 'penalize')->name('penalize')->whereNumber('tradeId');
    });

    Route::controller('CoinCutoverController')->prefix('coin/cutover')->name('coin.cutover.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('preview', 'preview')->name('preview');
        Route::post('execute', 'execute')->name('execute');
    });

    Route::controller('CoinTestKitController')->prefix('coin/test-kit')->name('coin.test-kit.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('create-agent', 'createTestAgent')->name('create-agent');
        Route::post('create-user', 'createTestUser')->name('create-user');
        Route::post('grant-float', 'grantTestFloat')->name('grant-float');
        Route::post('grant-coins', 'grantTestCoins')->name('grant-coins');
        Route::post('demo-rates', 'setDemoRates')->name('demo-rates');
        Route::post('sample-listing', 'createSampleListing')->name('sample-listing');
        Route::post('simulate-timeout', 'simulateTimeout')->name('simulate-timeout');
        Route::post('concurrency-self-test', 'concurrencySelfTest')->name('concurrency-self-test');
    });

    Route::controller('AdminController')->group(function () {
        Route::get('dashboard', 'dashboard')->name('dashboard');
        Route::get('chart/deposit-withdraw', 'depositAndWithdrawReport')->name('chart.deposit.withdraw');
        Route::get('chart/transaction', 'transactionReport')->name('chart.transaction');
        Route::get('profile', 'profile')->name('profile');
        Route::post('profile', 'profileUpdate')->name('profile.update');
        Route::get('password', 'password')->name('password');
        Route::post('password', 'passwordUpdate')->name('password.update');

        //Notification
        Route::get('notifications', 'notifications')->name('notifications');
        Route::get('notification/read/{id}', 'notificationRead')->name('notification.read');
        Route::get('notifications/read-all', 'readAllNotification')->name('notifications.read.all');
        Route::post('notifications/delete-all', 'deleteAllNotification')->name('notifications.delete.all');
        Route::post('notifications/delete-single/{id}', 'deleteSingleNotification')->name('notifications.delete.single');

        Route::get('download-attachments/{file_hash}', 'downloadAttachment')->name('download.attachment');
        Route::get('preview-attachments/{file_hash}', 'previewAttachment')->name('preview.attachment');
    });

    // Referral
    Route::controller('ReferralController')->name('referral.')->prefix('referral')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('store', 'Store')->name('store');
        Route::get('status/{key}', 'status')->name('status');
    });

    // Game
    Route::controller('GameController')->name('game.')->prefix('game')->group(function () {
        Route::get('index', 'index')->name('index');
        Route::get('edit/{id}', 'edit')->name('edit');
        Route::post('update/{id}', 'update')->name('update');
        Route::post('status/{id}', 'status')->name('status');
        Route::post('chance-create/{alias?}', 'chanceCreate')->name('chance.create');
        Route::get('game-log', 'gameLog')->name('log');
        Route::post('keno-update/{id}', 'kenoUpdate')->name('keno.update');
        Route::post('crazy-times/{id}', 'crazyTimesUpdate')->name('crazy.times.update');
        Route::post('dream-catcher/{id}', 'dreamCatcherUpdate')->name('dream.catcher.update');
        Route::post('optimize-images', 'optimizeImages')->name('optimize.images');
    });

    Route::controller('TableGameAdminController')->name('table-games.')->prefix('table-games')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('settings', 'settings')->name('settings');
        Route::post('{id}/force-close', 'forceClose')->whereUuid('id')->name('force-close');
        Route::post('reports/{id}/dismiss', 'dismissReport')->whereNumber('id')->name('reports.dismiss');
        Route::post('user-limit', 'userLimit')->name('user-limit');
    });

    // VIP Tiers
    Route::controller('VipTierController')->name('vip.')->prefix('vip')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('create', 'store')->name('store');
        Route::get('edit/{id}', 'edit')->name('edit');
        Route::post('edit/{id}', 'update')->name('update');
        Route::post('status/{id}', 'status')->name('status');
        Route::post('delete/{id}', 'destroy')->name('destroy');
        Route::get('claims', 'claims')->name('claims');
    });

    // Arena
    Route::controller('ArenaController')->name('arena.')->prefix('arena')->group(function () {
        Route::get('pending', 'pending')->name('pending');
        Route::post('approve/{id}', 'approve')->name('approve');
        Route::post('reject', 'reject')->name('reject');
        Route::get('settings', 'settings')->name('settings');
        Route::post('settings', 'settingsUpdate')->name('settings.update');
    });
    Route::controller('ArenaAnnouncementController')->name('arena.announcements.')->prefix('arena/announcements')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('create', 'store')->name('store');
        Route::get('edit/{id}', 'edit')->name('edit');
        Route::post('edit/{id}', 'update')->name('update');
        Route::post('status/{id}', 'status')->name('status');
        Route::post('delete/{id}', 'destroy')->name('destroy');
    });

    // Competitions
    Route::controller('CompetitionController')->name('competitions.')->prefix('competitions')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('seed-launch', 'seedLaunch')->name('seed-launch');
        Route::get('create', 'create')->name('create');
        Route::post('create', 'store')->name('store');
        Route::get('edit/{id}', 'edit')->name('edit');
        Route::post('edit/{id}', 'update')->name('update');
        Route::post('delete/{id}', 'destroy')->name('destroy');
        Route::get('{id}/results', 'results')->name('results');
        Route::post('{id}/finalize-now', 'finalizeNow')->name('finalize-now');
        Route::post('results/{resultId}/pay', 'payResult')->name('results.pay');
        Route::post('results/{resultId}/disqualify', 'disqualify')->name('results.disqualify');
        Route::get('flags', 'flags')->name('flags');
        Route::post('flags/{id}/review', 'reviewFlag')->name('flags.review');
    });

    // News
    Route::controller('NewsController')->name('news.')->prefix('news')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('create', 'store')->name('store');
        Route::get('edit/{id}', 'edit')->name('edit');
        Route::post('edit/{id}', 'update')->name('update');
        Route::post('status/{id}', 'status')->name('status');
        Route::post('delete/{id}', 'destroy')->name('destroy');
    });

    // Gifts
    Route::controller('GiftController')->name('gift.')->prefix('gift')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('preview-effects', 'previewEffects')->name('preview-effects');
        Route::get('create', 'create')->name('create');
        Route::post('create', 'store')->name('store');
        Route::get('edit/{id}', 'edit')->name('edit');
        Route::post('edit/{id}', 'update')->name('update');
        Route::post('status/{id}', 'status')->name('status');
        Route::post('delete/{id}', 'destroy')->name('destroy');
    });

    // Bonus Codes
    Route::controller('BonusCodeController')->name('bonus.code.')->prefix('bonus-code')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('create', 'store')->name('store');
        Route::get('edit/{id}', 'edit')->name('edit');
        Route::post('edit/{id}', 'update')->name('update');
        Route::post('status/{id}', 'status')->name('status');
        Route::post('delete/{id}', 'destroy')->name('destroy');
        Route::get('redemptions', 'redemptions')->name('redemptions');
    });

    // Users Manager
    Route::controller('ManageUsersController')->name('users.')->prefix('users')->group(function () {
        Route::get('/', 'allUsers')->name('all');
        Route::get('active', 'activeUsers')->name('active');
        Route::get('banned', 'bannedUsers')->name('banned');
        Route::get('email-verified', 'emailVerifiedUsers')->name('email.verified');
        Route::get('email-unverified', 'emailUnverifiedUsers')->name('email.unverified');
        Route::get('mobile-unverified', 'mobileUnverifiedUsers')->name('mobile.unverified');
        Route::get('kyc-unverified', 'kycUnverifiedUsers')->name('kyc.unverified');
        Route::get('kyc-pending', 'kycPendingUsers')->name('kyc.pending');
        Route::get('mobile-verified', 'mobileVerifiedUsers')->name('mobile.verified');
        Route::get('with-balance', 'usersWithBalance')->name('with.balance');

        Route::get('detail/{id}', 'detail')->name('detail');
        Route::get('kyc-data/{id}', 'kycDetails')->name('kyc.details');
        Route::post('kyc-approve/{id}', 'kycApprove')->name('kyc.approve');
        Route::post('kyc-reject/{id}', 'kycReject')->name('kyc.reject');
        Route::post('update/{id}', 'update')->name('update');
        Route::post('add-sub-balance/{id}', 'addSubBalance')->name('add.sub.balance');
        Route::get('send-notification/{id}', 'showNotificationSingleForm')->name('notification.single');
        Route::post('send-notification/{id}', 'sendNotificationSingle')->name('notification.single');
        Route::post('login/{id}', 'login')->name('login');
        Route::post('status/{id}', 'status')->name('status');
        Route::post('promote-to-agent/{id}', 'promoteToAgent')->name('promote.agent');

        Route::get('send-notification', 'showNotificationAllForm')->name('notification.all');
        Route::post('send-notification', 'sendNotificationAll')->name('notification.all.send');
        Route::get('list', 'list')->name('list');
        Route::get('count-by-segment/{methodName}', 'countBySegment')->name('segment.count');
        Route::get('notification-log/{id}', 'notificationLog')->name('notification.log');

        Route::post('update-demo-balance', 'updateDemoBalance')->name('update.demo.balance');
    });

    // Subscriber
    Route::controller('SubscriberController')->prefix('subscriber')->name('subscriber.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('send-email', 'sendEmailForm')->name('send.email');
        Route::post('remove/{id}', 'remove')->name('remove');
        Route::post('send-email', 'sendEmail')->name('send.email');
    });

    // Deposit Gateway
    Route::name('gateway.')->prefix('gateway')->group(function () {
        // Automatic Gateway
        Route::controller('AutomaticGatewayController')->prefix('automatic')->name('automatic.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('edit/{alias}', 'edit')->name('edit');
            Route::post('update/{code}', 'update')->name('update');
            Route::post('remove/{id}', 'remove')->name('remove');
            Route::post('status/{id}', 'status')->name('status');
        });

        // Manual Methods
        Route::controller('ManualGatewayController')->prefix('manual')->name('manual.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('new', 'create')->name('create');
            Route::post('new', 'store')->name('store');
            Route::get('edit/{alias}', 'edit')->name('edit');
            Route::post('update/{id}', 'update')->name('update');
            Route::post('status/{id}', 'status')->name('status');
        });
    });

    // DEPOSIT SYSTEM
    Route::controller('DepositController')->prefix('deposit')->name('deposit.')->group(function () {
        Route::get('all/{user_id?}', 'deposit')->name('list');
        Route::get('pending/{user_id?}', 'pending')->name('pending');
        Route::get('rejected/{user_id?}', 'rejected')->name('rejected');
        Route::get('approved/{user_id?}', 'approved')->name('approved');
        Route::get('successful/{user_id?}', 'successful')->name('successful');
        Route::get('initiated/{user_id?}', 'initiated')->name('initiated');
        Route::get('details/{id}', 'details')->name('details');
        Route::post('reject', 'reject')->name('reject');
        Route::post('approve/{id}', 'approve')->name('approve');

    });

    // WITHDRAW SYSTEM
    Route::name('withdraw.')->prefix('withdraw')->group(function () {

        Route::controller('WithdrawalController')->name('data.')->group(function () {
            Route::get('pending/{user_id?}', 'pending')->name('pending');
            Route::get('approved/{user_id?}', 'approved')->name('approved');
            Route::get('rejected/{user_id?}', 'rejected')->name('rejected');
            Route::get('all/{user_id?}', 'all')->name('all');
            Route::get('details/{id}', 'details')->name('details');
            Route::post('approve', 'approve')->name('approve');
            Route::post('reject', 'reject')->name('reject');
        });

        // Withdraw Method
        Route::controller('WithdrawMethodController')->prefix('method')->name('method.')->group(function () {
            Route::get('/', 'methods')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('create', 'store')->name('store');
            Route::get('edit/{id}', 'edit')->name('edit');
            Route::post('edit/{id}', 'update')->name('update');
            Route::post('status/{id}', 'status')->name('status');
        });
    });

    // Report
    Route::controller('ReportController')->prefix('report')->name('report.')->group(function () {
        Route::get('transaction/{user_id?}', 'transaction')->name('transaction');
        Route::get('transfer', 'transfer')->name('transfer');
        Route::get('login/history', 'loginHistory')->name('login.history');
        Route::get('login/ip-history/{ip}', 'loginIpHistory')->name('login.ip.history');
        Route::get('notification/history', 'notificationHistory')->name('notification.history');
        Route::get('email/detail/{id}', 'emailDetails')->name('email.details');
    });

    // Admin Support
    Route::controller('SupportAgentController')->prefix('support/agents')->name('support.agents.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('create', 'store')->name('store');
        Route::get('edit/{id}', 'edit')->name('edit');
        Route::post('edit/{id}', 'update')->name('update');
        Route::post('status/{id}', 'status')->name('status');
        Route::post('delete/{id}', 'destroy')->name('delete');
    });

    Route::controller('SupportBotController')->prefix('support/bot')->name('support.bot.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('settings', 'updateSettings')->name('settings');
        Route::get('trainings/create', 'trainingCreate')->name('trainings.create');
        Route::post('trainings/create', 'trainingStore')->name('trainings.store');
        Route::get('trainings/{id}/edit', 'trainingEdit')->name('trainings.edit');
        Route::post('trainings/{id}/edit', 'trainingUpdate')->name('trainings.update');
        Route::post('trainings/{id}/status', 'trainingStatus')->name('trainings.status');
        Route::post('trainings/{id}/delete', 'trainingDestroy')->name('trainings.delete');
    });

    Route::controller('SupportTicketController')->prefix('ticket')->name('ticket.')->group(function () {
        Route::get('/', 'tickets')->name('index');
        Route::get('pending', 'pendingTicket')->name('pending');
        Route::get('closed', 'closedTicket')->name('closed');
        Route::get('answered', 'answeredTicket')->name('answered');
        Route::get('view/{id}', 'ticketReply')->name('view');
        Route::post('reply/{id}', 'replyTicket')->name('reply');
        Route::post('close/{id}', 'closeTicket')->name('close');
        Route::get('download/{attachment_id}', 'ticketDownload')->name('download');
        Route::post('delete/{id}', 'ticketDelete')->name('delete');
    });

    // Language Manager
    Route::controller('LanguageController')->prefix('language')->name('language.')->group(function () {
        Route::get('/', 'langManage')->name('manage');
        Route::post('/', 'langStore')->name('manage.store');
        Route::post('delete/{id}', 'langDelete')->name('manage.delete');
        Route::post('update/{id}', 'langUpdate')->name('manage.update');
        Route::get('edit/{id}', 'langEdit')->name('key');
        Route::post('import', 'langImport')->name('import.lang');
        Route::post('store/key/{id}', 'storeLanguageJson')->name('store.key');
        Route::post('delete/key/{id}', 'deleteLanguageJson')->name('delete.key');
        Route::post('update/key/{id}', 'updateLanguageJson')->name('update.key');
        Route::get('get-keys', 'getKeys')->name('get.key');
    });

    Route::controller('GeneralSettingController')->group(function () {

        Route::get('system-setting', 'systemSetting')->name('setting.system');

        // General Setting
        Route::get('general-setting', 'general')->name('setting.general');
        Route::post('general-setting', 'generalUpdate');

        Route::get('setting/social/credentials', 'socialiteCredentials')->name('setting.socialite.credentials');
        Route::post('setting/social/credentials/update/{key}', 'updateSocialiteCredential')->name('setting.socialite.credentials.update');
        Route::post('setting/social/credentials/status/{key}', 'updateSocialiteCredentialStatus')->name('setting.socialite.credentials.status.update');

        //configuration
        Route::get('setting/system-configuration', 'systemConfiguration')->name('setting.system.configuration');
        Route::post('setting/system-configuration', 'systemConfigurationSubmit');

        // Logo-Icon
        Route::get('setting/logo-icon', 'logoIcon')->name('setting.logo.icon');
        Route::post('setting/logo-icon', 'logoIconUpdate')->name('setting.logo.icon');

        //Custom CSS
        Route::get('custom-css', 'customCss')->name('setting.custom.css');
        Route::post('custom-css', 'customCssSubmit');

        //Custom CSS
        Route::get('sitemap', 'sitemap')->name('setting.sitemap');
        Route::post('sitemap', 'sitemapSubmit');

        //Custom CSS
        Route::get('robot', 'robot')->name('setting.robot');
        Route::post('robot', 'robotSubmit');

        //Cookie
        Route::get('cookie', 'cookie')->name('setting.cookie');
        Route::post('cookie', 'cookieSubmit');

        //maintenance_mode
        Route::get('maintenance-mode', 'maintenanceMode')->name('maintenance.mode');
        Route::post('maintenance-mode', 'maintenanceModeSubmit');

    });

    Route::controller('CronConfigurationController')->name('cron.')->prefix('cron')->group(function () {
        Route::get('index', 'cronJobs')->name('index');
        Route::post('store', 'cronJobStore')->name('store');
        Route::post('update', 'cronJobUpdate')->name('update');
        Route::post('delete/{id}', 'cronJobDelete')->name('delete');
        Route::get('schedule', 'schedule')->name('schedule');
        Route::post('schedule/store', 'scheduleStore')->name('schedule.store');
        Route::post('schedule/status/{id}', 'scheduleStatus')->name('schedule.status');
        Route::get('schedule/pause/{id}', 'schedulePause')->name('schedule.pause');
        Route::get('schedule/logs/{id}', 'scheduleLogs')->name('schedule.logs');
        Route::post('schedule/log/resolved/{id}', 'scheduleLogResolved')->name('schedule.log.resolved');
        Route::post('schedule/log/flush/{id}', 'logFlush')->name('log.flush');
    });

    //KYC setting
    Route::controller('KycController')->group(function () {
        Route::get('kyc-setting', 'setting')->name('kyc.setting');
        Route::post('kyc-setting', 'settingUpdate');
    });

    //Notification Setting
    Route::name('setting.notification.')->controller('NotificationController')->prefix('notification')->group(function () {
        //Template Setting
        Route::get('global/email', 'globalEmail')->name('global.email');
        Route::post('global/email/update', 'globalEmailUpdate')->name('global.email.update');

        Route::get('global/sms', 'globalSms')->name('global.sms');
        Route::post('global/sms/update', 'globalSmsUpdate')->name('global.sms.update');

        Route::get('global/push', 'globalPush')->name('global.push');
        Route::post('global/push/update', 'globalPushUpdate')->name('global.push.update');

        Route::get('templates', 'templates')->name('templates');
        Route::get('template/edit/{type}/{id}', 'templateEdit')->name('template.edit');
        Route::post('template/update/{type}/{id}', 'templateUpdate')->name('template.update');

        //Email Setting
        Route::get('email/setting', 'emailSetting')->name('email');
        Route::post('email/setting', 'emailSettingUpdate');
        Route::post('email/test', 'emailTest')->name('email.test');

        //SMS Setting
        Route::get('sms/setting', 'smsSetting')->name('sms');
        Route::post('sms/setting', 'smsSettingUpdate');
        Route::post('sms/test', 'smsTest')->name('sms.test');

        Route::get('notification/push/setting', 'pushSetting')->name('push');
        Route::post('notification/push/setting', 'pushSettingUpdate');
        Route::post('notification/push/setting/upload', 'pushSettingUpload')->name('push.upload');
        Route::get('notification/push/setting/download', 'pushSettingDownload')->name('push.download');
    });

    // Plugin
    Route::controller('ExtensionController')->prefix('extensions')->name('extensions.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('update/{id}', 'update')->name('update');
        Route::post('status/{id}', 'status')->name('status');
    });

    // Official social media links (landing page + sidebar "Official" button)
    Route::controller('SocialLinkController')->prefix('social-links')->name('social.links.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'update')->name('update');
    });

    //System Information
    Route::controller('SystemController')->name('system.')->prefix('system')->group(function () {
        Route::get('info', 'systemInfo')->name('info');
        Route::get('server-info', 'systemServerInfo')->name('server.info');
        Route::get('optimize', 'optimize')->name('optimize');
        Route::get('optimize-clear', 'optimizeClear')->name('optimize.clear');
        Route::get('database-update', 'databaseUpdate')->name('database.update');
        Route::post('database-update/run', 'databaseUpdateRun')->name('database.update.run');
        Route::post('seed-nocturne-pages', 'seedNocturnePages')->name('seed.nocturne.pages');
    });

    // SEO
    Route::get('seo', 'FrontendController@seoEdit')->name('seo');

    // Frontend
    Route::name('frontend.')->prefix('frontend')->group(function () {

        Route::controller('FrontendController')->group(function () {
            Route::get('index', 'index')->name('index');
            Route::get('templates', 'templates')->name('templates');
            Route::post('templates', 'templatesActive')->name('templates.active');
            Route::get('frontend-sections/{key?}', 'frontendSections')->name('sections');
            Route::post('frontend-content/{key}', 'frontendContent')->name('sections.content');
            Route::get('frontend-element/{key}/{id?}', 'frontendElement')->name('sections.element');
            Route::get('frontend-slug-check/{key}/{id?}', 'frontendElementSlugCheck')->name('sections.element.slug.check');
            Route::get('frontend-element-seo/{key}/{id}', 'frontendSeo')->name('sections.element.seo');
            Route::post('frontend-element-seo/{key}/{id}', 'frontendSeoUpdate');
            Route::post('update-seo', 'updateSeoContent')->name('seo.update');
            Route::post('remove/{id}', 'remove')->name('remove');

            Route::post('import-content/{key}', 'importContent')->name('import');
        });

        // Page Builder
        Route::controller('PageBuilderController')->group(function () {
            Route::get('manage-pages', 'managePages')->name('manage.pages');
            Route::get('manage-pages/check-slug/{id?}', 'checkSlug')->name('manage.pages.check.slug');
            Route::post('manage-pages', 'managePagesSave')->name('manage.pages.save');
            Route::post('manage-pages/update', 'managePagesUpdate')->name('manage.pages.update');
            Route::post('manage-pages/delete/{id}', 'managePagesDelete')->name('manage.pages.delete');
            Route::get('manage-section/{id}', 'manageSection')->name('manage.section');
            Route::post('manage-section/{id}', 'manageSectionUpdate')->name('manage.section.update');

            Route::get('manage-seo/{id}', 'manageSeo')->name('manage.pages.seo');
            Route::post('manage-seo/{id}', 'manageSeoStore');
        });

    });
});
