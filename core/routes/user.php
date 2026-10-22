<?php

use Illuminate\Support\Facades\Route;

Route::namespace('User\Auth')->name('user.')->middleware('guest')->group(function () {
    Route::controller('LoginController')->group(function () {
        Route::get('/login', 'showLoginForm')->name('login');
        Route::post('/login', 'login');
        Route::get('logout', 'logout')->middleware('auth')->withoutMiddleware('guest')->name('logout');
    });

    Route::controller('RegisterController')->group(function () {
        Route::get('register', 'showRegistrationForm')->name('register');
        Route::post('register', 'register');
        Route::post('check-user', 'checkUser')->name('checkUser')->withoutMiddleware('guest');
    });

    Route::controller('ForgotPasswordController')->prefix('password')->name('password.')->group(function () {
        Route::get('reset', 'showLinkRequestForm')->name('request');
        Route::post('email', 'sendResetCodeEmail')->name('email');
        Route::get('code-verify', 'codeVerify')->name('code.verify');
        Route::post('verify-code', 'verifyCode')->name('verify.code');
    });

    Route::controller('ResetPasswordController')->group(function () {
        Route::post('password/reset', 'reset')->name('password.update');
        Route::get('password/reset/{token}', 'showResetForm')->name('password.reset');
    });

    Route::controller('SocialiteController')->group(function () {
        Route::get('social-login/{provider}', 'socialLogin')->name('social.login');
        Route::get('social-login/callback/{provider}', 'callback')->name('social.login.callback');
    });
});

Route::middleware('auth')->name('user.')->group(function () {
    Route::get('user-data', 'User\UserController@userData')->name('data');
    Route::post('user-data-submit', 'User\UserController@userDataSubmit')->name('data.submit');
    Route::post('presence/ping', 'User\PresenceController@ping')->name('presence.ping')->middleware('throttle:10,1');

    //authorization
    Route::middleware('registration.complete')->namespace('User')->controller('AuthorizationController')->group(function () {
        Route::get('authorization', 'authorizeForm')->name('authorization');
        Route::get('resend-verify/{type}', 'sendVerifyCode')->name('send.verify.code');
        Route::post('verify-email', 'emailVerification')->name('verify.email');
        Route::post('verify-mobile', 'mobileVerification')->name('verify.mobile');
        Route::post('verify-g2fa', 'g2faVerification')->name('2fa.verify');
    });

    Route::middleware(['check.status', 'registration.complete'])->group(function () {
        Route::namespace('User')->group(function () {
            Route::controller('UserController')->group(function () {
                Route::get('dashboard', 'home')->name('home');
                Route::get('download-attachments/{file_hash}', 'downloadAttachment')->name('download.attachment');

                //2FA
                Route::get('twofactor', 'show2faForm')->name('twofactor');
                Route::post('twofactor/enable', 'create2fa')->name('twofactor.enable');
                Route::post('twofactor/disable', 'disable2fa')->name('twofactor.disable');

                //KYC
                Route::get('kyc-form', 'kycForm')->name('kyc.form');
                Route::get('kyc-data', 'kycData')->name('kyc.data');
                Route::post('kyc-submit', 'kycSubmit')->name('kyc.submit');

                //Report
                Route::any('deposit/history', 'depositHistory')->name('deposit.history');
                Route::get('transactions', 'transactions')->name('transactions');
                Route::get('referral', 'referrals')->name('referrals');
                Route::post('bonus-code/activate', 'activateBonusCode')->name('bonus.code.activate');

                Route::post('add-device-token', 'addDeviceToken')->name('add.device.token');
                Route::get('notifications/badge', 'notificationBadge')->name('notifications.badge');
                Route::post('notifications/read-all', 'notificationsReadAll')->name('notifications.read.all');

                Route::get('game/log', 'gameLog')->name('game.log');
                Route::get('game/demo-log', 'demoGameLog')->name('game.log.demo');
                Route::get('commission/log', 'commissionLog')->name('commission.log');
            });

            // VIP Club
            Route::controller('VipController')->prefix('vip')->name('vip.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('claim-rakeback', 'claimRakeback')->name('claim.rakeback');
            });

            // Competitions
            Route::controller('CompetitionController')->prefix('competitions')->name('competitions.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('{slug}', 'show')->name('show');
                Route::post('{slug}/flag', 'flag')->middleware('throttle:10,1')->name('flag');
            });

            // Arena (social feed) - the feed itself is public, see routes/web.php
            Route::controller('ArenaController')->prefix('arena')->name('arena.')->group(function () {
                Route::post('post', 'store')->name('store');
                Route::post('post/{post}/vote', 'vote')->name('vote');
                Route::post('react', 'react')->name('react');
                Route::get('notifications', 'notifications')->name('notifications');
                Route::post('notifications/read', 'notificationsRead')->name('notifications.read');
                Route::get('my-posts', 'myPosts')->name('my-posts');
            });
            Route::controller('ArenaCommentController')->prefix('arena/post/{post}/comments')->name('arena.comments.')->group(function () {
                Route::post('/', 'store')->name('store');
            });
            Route::controller('ArenaFollowController')->prefix('arena/follow')->name('arena.follow.')->group(function () {
                Route::post('{user}', 'store')->name('store');
                Route::delete('{user}', 'destroy')->name('destroy');
            });

            // Paid business promotions
            Route::controller('BusinessPromotionController')->prefix('business-promotions')->name('business-promotions.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
            });

            // Sponsorship proposals
            Route::controller('SponsorshipController')->prefix('sponsorships')->name('sponsorships.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
            });

            //Profile setting
            Route::controller('ProfileController')->group(function () {
                Route::get('profile-setting', 'profile')->name('profile.setting');
                Route::post('profile-setting', 'submitProfile');
                Route::post('profile-setting/currency', 'updateCurrency')->name('profile.currency')->middleware('throttle:10,1');
                Route::get('change-password', 'changePassword')->name('change.password');
                Route::post('change-password', 'submitPassword');
            });

            //Transfer money
            Route::controller('BalanceTransferController')->prefix('transfer')->name('transfer')->group(function () {
                Route::get('list', 'index')->name('.index');
                Route::post('store', 'store')->name('.store');
                Route::get('validate/username', 'validateUsername')->name('.validate.username');
            });

            // Withdraw
            Route::controller('WithdrawController')->prefix('withdraw')->name('withdraw')->group(function () {
                Route::middleware('kyc')->group(function () {
                    Route::get('/', 'withdrawMoney');
                    Route::post('/', 'withdrawStore')->name('.money');
                    Route::get('preview', 'withdrawPreview')->name('.preview');
                    Route::post('preview', 'withdrawSubmit')->name('.submit');
                });
                Route::get('history', 'withdrawLog')->name('.history');
            });

            Route::controller('PlayController')->prefix('play')->name('play.')->group(function () {
                Route::get('{alias}/{demo?}', 'playGame')->name('game');
                Route::post('invest/{alias}/{demo?}', 'investGame')->name('invest');
                Route::post('end/{alias}/{demo?}', 'gameEnd')->name('end');
            });

            Route::controller('TableGameController')->prefix('tables')->name('tables.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'create')->name('create');
                Route::get('leaderboard', 'leaderboard')->middleware('throttle:60,1')->name('leaderboard');
                Route::get('{id}', 'show')->whereUuid('id')->name('show');
                Route::get('{id}/snapshot', 'snapshot')->whereUuid('id')->name('snapshot');
                  Route::get('{id}/chat', 'messages')->whereUuid('id')->middleware('throttle:120,1')->name('chat');
                  Route::post('{id}/chat', 'sendMessage')->whereUuid('id')->middleware('throttle:60,1')->name('chat.send');
                  Route::post('{id}/chat/{message}/reaction', 'react')->whereUuid('id')->whereNumber('message')->middleware('throttle:60,1')->name('chat.react');
                  Route::post('{id}/applaud', 'applaud')->whereUuid('id')->middleware('throttle:30,1')->name('chat.applaud');
                  Route::get('{id}/chat/{message}/attachment', 'attachment')->whereUuid('id')->whereNumber('message')->name('chat.attachment');
                  Route::post('{id}/chat/{message}/report', 'reportMessage')->whereUuid('id')->whereNumber('message')->middleware('throttle:20,1')->name('chat.report');
                  Route::post('{id}/players/{user}/block', 'blockUser')->whereUuid('id')->whereNumber('user')->middleware('throttle:20,1')->name('players.block');
                  Route::post('players/{user}/unblock', 'unblockUser')->whereNumber('user')->middleware('throttle:20,1')->name('players.unblock');
                Route::post('{id}/join', 'join')->whereUuid('id')->name('join');
                Route::get('{id}/players/search', 'searchPlayers')->whereUuid('id')->middleware('throttle:60,1')->name('players.search');
                Route::post('{id}/invites', 'invite')->whereUuid('id')->name('invite');
                Route::post('invites/{invite}/accept', 'acceptInvite')->whereNumber('invite')->name('invite.accept');
                Route::post('{id}/start', 'start')->whereUuid('id')->name('start');
                Route::post('{id}/rematch', 'requestRematch')->whereUuid('id')->middleware('throttle:10,1')->name('rematch.request');
                Route::post('rematch/{rematch}/decline', 'declineRematch')->whereNumber('rematch')->middleware('throttle:20,1')->name('rematch.decline');
                Route::post('{id}/move', 'move')->whereUuid('id')->name('move');
                Route::post('{id}/leave', 'leave')->whereUuid('id')->name('leave');
                Route::post('{id}/gifts/{gift}/send', 'sendGift')->whereUuid('id')->whereNumber('gift')->middleware('throttle:20,1')->name('gifts.send');
            });

            Route::controller('GiftController')->prefix('gifts')->name('gifts.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('{gift}/buy', 'buy')->whereNumber('gift')->middleware('throttle:20,1')->name('buy');
                Route::post('{gift}/cashout', 'cashOut')->whereNumber('gift')->middleware('throttle:20,1')->name('cashout');
            });
        });

        // Payment
        Route::prefix('deposit')->name('deposit.')->controller('Gateway\PaymentController')->group(function () {
            Route::any('/', 'deposit')->name('index');
            Route::post('insert', 'depositInsert')->name('insert');
            Route::get('confirm', 'depositConfirm')->name('confirm');
            Route::get('manual', 'manualDepositConfirm')->name('manual.confirm');
            Route::post('manual', 'manualDepositUpdate')->name('manual.update');
            Route::post('confirmation/submit', 'confirmationSubmit')->name('confirmation.submit');
            Route::get('confirmation/pending', 'confirmationPending')->name('confirmation.pending');
            Route::get('confirmation/status', 'confirmationStatus')->name('confirmation.status');
            Route::post('confirmation/dispute', 'confirmationDispute')->name('confirmation.dispute');
        });
    });
});
