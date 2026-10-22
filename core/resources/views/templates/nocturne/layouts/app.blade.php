<!doctype html>
<html lang="{{ config('app.locale') }}" itemscope itemtype="http://schema.org/WebPage">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, shrink-to-fit=no, interactive-widget=resizes-content">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#111429">
    <link rel="apple-touch-icon" href="{{ getImage(getFilePath('logoIcon') . '/pwa_favicon.png') }}">
    <title> {{ gs()->siteName(__($pageTitle)) }}</title>
    @include('partials.seo')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,600;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css">
    <link href="{{ asset('assets/global/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/global/css/all.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/global/css/line-awesome.min.css') }}">
    <link href="{{ asset('assets/global/css/lightcase.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/global/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'css/slick.css') }}">
    <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'css/odometer.css') }}">
    <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'css/iconmoon.css') }}">
    <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'css/main.css') }}">
    <link href="{{ asset(activeTemplate(true) . 'css/custom.css') }}" rel="stylesheet">
    <link href="{{ asset(activeTemplate(true) . 'css/nocturne.css') }}?v={{ @filemtime(public_path(activeTemplate(true) . 'css/nocturne.css')) }}" rel="stylesheet">

    @stack('style-lib')
    <link rel="manifest" href="{{ route('pwa.configuration') }}">
    @stack('style')

    <link
        href="{{ asset(activeTemplate(true) . 'css/color.php') }}?color={{ gs('base_color') }}&secondColor={{ gs('secondary_color') }}"
        rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/global/css/mobile-inputs.css') }}?v={{ @filemtime(public_path('assets/global/css/mobile-inputs.css')) }}">
    <style>
        /* Sitewide guard against any single page's element pushing the document wider than the
           viewport (several pages have hit this independently - a fixed/absolute-positioned
           element whose content extends past the viewport edge can still widen the page's own
           scrollable area even though it looks visually "contained"). Elements with their own
           explicit overflow-x:auto/scroll (carousels, horizontally-scrolling tab rows, etc.)
           keep scrolling internally regardless of this - it only stops the whole document from
           scrolling sideways. */
        html, body { max-width: 100%; overflow-x: hidden; }
        /* No "back to top" button anywhere, on any device. */
        .scroll-top { display: none !important; }
        /* Sitewide mobile polish: the bottom tab bar sat flush against the
           very edge of the screen (home-indicator area on notched phones),
           reading as cramped rather than a native app's tab bar, which
           always leaves a little breathing room above the edge. */
        @media (max-width: 575.98px) {
            .nocturne-mobile-nav { padding-bottom: calc(14px + env(safe-area-inset-bottom)) !important; }
        }
        /* Breadcrumb trails read as clutter on a phone-width screen where there's no room for
           them to earn their keep - hidden on mobile only, every page's breadcrumb markup and
           desktop display is untouched. Covers every breadcrumb class used across the theme. */
        @media (max-width: 575.98px) {
            .nocturne-vip-crumb,
            .nocturne-arena-crumb,
            .nocturne-news-crumb { display: none !important; }
            /* The Arena crumb shares its row with the notification bell - only the crumb
               hides, and the bell (the row's only remaining child) stays pinned to the right
               instead of collapsing to the start once its sibling is gone. */
            .nocturne-arena-crumb-row { justify-content: flex-end !important; }
        }
        /* This is an app, not a webpage with visible scrollbars - hide them everywhere on
           mobile/tablet while leaving the actual scrolling untouched. Desktop keeps native
           scrollbars (expected there, and useful affordance with mouse/trackpad input). */
        @media (max-width: 991.98px) {
            * { scrollbar-width: none; -ms-overflow-style: none; }
            *::-webkit-scrollbar { display: none; width: 0; height: 0; }
        }
        /* The full footer (sitemap columns, newsletter, trust badges) is
           too heavy for mobile on most pages - hide it there except on the
           policy/legal pages and News, where the footer's links are the
           most relevant navigation on the page. */
        @media (max-width: 767.98px) {
            .nocturne-site-footer:not(.nocturne-footer--show-mobile) { display: none !important; }
        }
        /* "Live" indicators (menu + Arena widget) blink green so the UI reads
           as actively broadcasting rather than a static icon. */
        @keyframes nocturneLiveBlink {
            0%, 100% { opacity: 1; color: #00e78a; text-shadow: 0 0 2px rgba(0,231,138,.5); }
            50% { opacity: .3; color: #00e78a; text-shadow: 0 0 10px rgba(0,231,138,.9); }
        }
        .nocturne-live-pulse { animation: nocturneLiveBlink 1.4s ease-in-out infinite; }
        /* Preloader: the base theme's .loader-p is a circular mask (border-radius:50%),
           which was clipping the corners off the brand icon. Drop the circular clip and
           give the icon a slightly punchier, glowing pulse instead of a flat fade. */
        .loader-p { border-radius: 0 !important; background-size: 68% auto !important; animation: nocturneLoaderPulse 1.1s ease-in-out infinite !important; }
        @keyframes nocturneLoaderPulse {
            0%, 100% { transform: scale(.9); opacity: .7; filter: drop-shadow(0 0 0 rgba(0,239,135,0)); }
            50% { transform: scale(1.06); opacity: 1; filter: drop-shadow(0 0 18px rgba(0,239,135,.55)); }
        }
    </style>
</head>

@php echo loadExtension('google-analytics') @endphp

<body @if (session('impersonating_admin_id') && auth()->check()) class="has-impersonation-bar" @endif>
    @if (session('impersonating_admin_id') && auth()->check())
        <div class="impersonation-bar">
            <i class="ph-bold ph-eye"></i>
            <span>{{ __('Viewing as :user - logged in by admin :admin', ['user' => auth()->user()->username, 'admin' => session('impersonating_admin_name', 'admin')]) }}</span>
            <form action="{{ route('admin.impersonate.return') }}" method="post">
                @csrf
                <button type="submit">{{ __('Return to admin') }}</button>
            </form>
        </div>
    @endif
    @stack('fbComment')

    <div class="preloader" id="site-preloader">
        <div class="loader-p"></div>
    </div>
    <script>
        // Vanilla JS, inlined right here, so the preloader can hide itself the moment the
        // DOM is parsed - it does not wait for jQuery or any of the ~8 plugin scripts loaded
        // near the end of <body>, which is what made the preloader feel slow on a throttled
        // connection (it used to only hide once jQuery itself had finished loading).
        (function () {
            var el = document.getElementById('site-preloader');
            if (!el) return;
            function hide() {
                if (!el || el.dataset.hidden) return;
                el.dataset.hidden = '1';
                el.style.transition = 'opacity .18s ease';
                el.style.opacity = '0';
                setTimeout(function () { el.remove(); }, 200);
            }
            if (document.readyState === 'interactive' || document.readyState === 'complete') {
                setTimeout(hide, 80);
            } else {
                document.addEventListener('DOMContentLoaded', function () { setTimeout(hide, 80); });
            }
            window.addEventListener('load', hide);
            setTimeout(hide, 4000); // safety net - never leave a visitor stuck behind it
        })();
    </script>

    <div class="body-overlay"></div>

    <div class="sidebar-overlay"></div>

    @yield('app')

    <aside class="app-install" id="app-install" aria-label="Install app" hidden>
        <img class="app-install__icon" src="{{ getImage(getFilePath('logoIcon') . '/pwa_favicon.png') }}" alt="" width="42" height="42">
        <div class="app-install__copy">
            <strong>@lang('Take the app with you')</strong>
            <span>@lang('A faster, full-screen experience')</span>
        </div>
        <button type="button" class="app-install__action" id="app-install-action">@lang('Install')</button>
        <button type="button" class="app-install__dismiss" id="app-install-dismiss" aria-label="@lang('Dismiss install notice')">&times;</button>
    </aside>

    <div class="app-install-modal" id="app-install-modal" hidden>
        <button class="app-install-modal__backdrop" type="button" data-install-close aria-label="@lang('Close instructions')"></button>
        <section class="app-install-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="app-install-title">
            <button type="button" class="app-install-modal__close" data-install-close aria-label="@lang('Close')">&times;</button>
            <span class="app-install-modal__eyebrow"><i class="las la-mobile-alt"></i> @lang('Your app, one tap away')</span>
            <h2 id="app-install-title">@lang('Add Winxpot to your iPhone')</h2>
            <p class="app-install-modal__intro">@lang('Safari can add this app to your Home Screen in a few seconds.')</p>
            <div class="app-install-steps">
                <article class="app-install-step">
                    <span class="app-install-step__number">1</span>
                    <div class="app-install-step__screen app-install-step__screen--share" aria-hidden="true">
                        <div class="app-install-step__browser"><i class="las la-lock"></i> winxpot.com <span>•••</span></div>
                        <div class="app-install-step__share-icon"><i class="las la-share-square"></i></div>
                        <div class="app-install-step__caption">Share</div>
                    </div>
                    <strong>@lang('Tap Share')</strong>
                    <p>@lang('In Safari, tap the share button at the bottom of the screen.') </p>
                </article>
                <article class="app-install-step">
                    <span class="app-install-step__number">2</span>
                    <div class="app-install-step__screen app-install-step__screen--home" aria-hidden="true">
                        <div class="app-install-step__sheet-title">Share</div>
                        <div class="app-install-step__home-icon"><i class="las la-plus"></i></div>
                        <div class="app-install-step__home-label">Add to Home Screen</div>
                        <i class="las la-chevron-right app-install-step__chevron"></i>
                    </div>
                    <strong>@lang('Choose Add to Home Screen')</strong>
                    <p>@lang('Scroll the share menu and tap Add to Home Screen.') </p>
                </article>
                <article class="app-install-step">
                    <span class="app-install-step__number">3</span>
                    <div class="app-install-step__screen app-install-step__screen--add" aria-hidden="true">
                        <div class="app-install-step__app-icon"><img src="{{ getImage(getFilePath('logoIcon') . '/pwa_favicon.png') }}" alt=""></div>
                        <strong>Winxpot</strong>
                        <span class="app-install-step__add-button">Add</span>
                    </div>
                    <strong>@lang('Tap Add')</strong>
                    <p>@lang('Confirm in the top-right corner. The app will appear on your Home Screen.') </p>
                </article>
            </div>
            <p class="app-install-modal__tip"><i class="las la-info-circle"></i> @lang('Use Safari for these steps. Other iPhone browsers use the same Share menu.') </p>
        </section>
    </div>

    <div class="pull-refresh" id="pull-refresh" aria-live="polite" aria-hidden="true"><span class="pull-refresh__spinner"></span><span class="pull-refresh__text">@lang('Pull to refresh')</span></div>

    <div class="win-loss-popup" aria-hidden="true" data-currency="{{ gs('cur_text') }}">
        <div class="win-loss-popup__bg" data-popup-backdrop>
            <section class="win-loss-popup__inner" role="dialog" aria-modal="true" aria-labelledby="game-result-title" aria-describedby="game-result-amount">
                <button type="button" class="win-loss-popup__close" aria-label="@lang('Close result')"><i class="ph-bold ph-x"></i></button>
                <div class="win-loss-popup__body">
                    <div class="win-loss-popup__art" aria-hidden="true">
                        <span class="win-loss-popup__orbit"></span>
                        <span class="win-loss-popup__coin win-loss-popup__coin--one">✦</span>
                        <span class="win-loss-popup__coin win-loss-popup__coin--two">✦</span>
                        <svg class="win-loss-popup__icon win-loss-popup__icon--win" viewBox="0 0 96 96" fill="none"><path d="M28 18h40v19c0 16-9 27-20 27S28 53 28 37V18Z"/><path d="M28 26H15v8c0 12 7 18 18 19M68 26h13v8c0 12-7 18-18 19M48 64v12m-16 7h32M38 82l3-6h14l3 6"/><path d="m41 40 5 5 10-12"/></svg>
                        <svg class="win-loss-popup__icon win-loss-popup__icon--loss" viewBox="0 0 96 96" fill="none"><path d="M48 12 80 70H16L48 12Z"/><path d="M48 35v16m0 9v1"/></svg>
                        <svg class="win-loss-popup__icon win-loss-popup__icon--push" viewBox="0 0 96 96" fill="none"><path d="M20 28h56v40H20z"/><path d="M31 48h34M48 37l-11 11 11 11"/></svg>
                        <span class="win-loss-popup__spark win-loss-popup__spark--one"></span><span class="win-loss-popup__spark win-loss-popup__spark--two"></span><span class="win-loss-popup__spark win-loss-popup__spark--three"></span><span class="win-loss-popup__spark win-loss-popup__spark--four"></span>
                    </div>
                    <h2 id="game-result-title" class="win-loss-popup__title">@lang('Round complete')</h2>
                    <p id="game-result-amount" class="win-loss-popup__amount" aria-live="polite"></p>
                    <p class="win-loss-popup__message">@lang('Your result has been added to your balance.')</p>
                    <div class="win-loss-popup__legacy" aria-hidden="true">
                        <img class="img-glow lose d-none" src="{{ asset(activeTemplate(true) . 'images/play/lose-message.png') }}" alt="">
                        <img class="img-glow win d-none" src="{{ asset(activeTemplate(true) . 'images/play/win-message.png') }}" alt="">
                        <img class="img-glow push d-none" src="{{ asset(activeTemplate(true) . 'images/play/push-message.png') }}" alt="">
                        <h2 class="result-text">@lang('The result is') <span class="data-result"></span></h2>
                        <h5></h5>
                    </div>
                </div>
                <div class="win-loss-popup__actions">
                    <button type="button" class="win-loss-popup__again">@lang('Play Again') <i class="ph-bold ph-arrow-right"></i></button>
                    <button type="button" class="win-loss-popup__dismiss">@lang('Close')</button>
                </div>
            </section>
        </div>
    </div>

    @php
        $cookie = App\Models\Frontend::where('data_keys', 'cookie.data')->first();
    @endphp

    @if ($cookie->data_values->status == Status::ENABLE && !\Cookie::get('gdpr_cookie'))
        <div class="cookies-card hide">
            <div class="cookies-card__row">
                <div class="cookies-card__icon"><x-icon name="cookie" /></div>
                <p class="cookies-card__content">{{ $cookie->data_values->short_desc }}</p>
            </div>
            <div class="cookies-card__actions">
                <a class="cookies-card__learn" href="{{ route('cookie.policy') }}" target="_blank">@lang('Learn more') <x-icon name="arrow-right" /></a>
                <a class="cookies-card__allow policy" href="javascript:void(0)">@lang('Allow')</a>
            </div>
        </div>
    @endif

    <script src="{{ asset('assets/global/js/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ asset('assets/global/js/bootstrap.bundle.min.js') }}"></script>

    @stack('script-lib')

    @php echo loadExtension('tawk-chat') @endphp

    @include('partials.notify')

    @if (gs('pn'))
        @include('partials.push_script')
    @endif

    <script src="{{ asset('assets/global/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/global/js/lightcase.js') }}"></script>
    <script src="{{ asset(activeTemplate(true) . 'js/slick.min.js') }}"></script>
    <script src="{{ asset(activeTemplate(true) . 'js/odometer.min.js') }}"></script>
    <script src="{{ asset(activeTemplate(true) . 'js/viewport.jquery.js') }}"></script>
    <script src="{{ asset(activeTemplate(true) . 'js/main.js') }}"></script>

    @stack('script')

    <script>
        (() => {
            'use strict';
            const notice = document.querySelector('#app-install');
            const action = document.querySelector('#app-install-action');
            const modal = document.querySelector('#app-install-modal');
            const dismiss = document.querySelector('#app-install-dismiss');
            const refresh = document.querySelector('#pull-refresh');
            // Homepage hero only - swaps between an "Install App" button and an "Arena" link
            // depending on whether the site is already running installed. Queried up front (not
            // gated behind the sitewide elements above) since this page-specific pair can be
            // absent on every other page without affecting the rest of this script.
            const heroInstallBtn = document.querySelector('#hero-install-btn');
            const heroArenaLink = document.querySelector('#hero-arena-link');

            const standalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            const ios = /iPhone|iPad|iPod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

            function syncHeroInstallButton() {
                if (!heroInstallBtn || !heroArenaLink) return;
                const installed = standalone();
                heroInstallBtn.hidden = installed;
                heroArenaLink.hidden = !installed;
            }
            syncHeroInstallButton();
            heroInstallBtn?.addEventListener('click', () => action?.click());

            if (!notice || !action || !modal || !refresh) return;
            const dismissedKey = 'winxpot-install-dismissed-until';
            let installPrompt = null;
            let installReady = false;
            const storage = {
                get(key) { try { return localStorage.getItem(key); } catch (_) { return null; } },
                set(key, value) { try { localStorage.setItem(key, value); } catch (_) {} },
                remove(key) { try { localStorage.removeItem(key); } catch (_) {} }
            };

            function showInstallNotice() {
                if (standalone()) {
                    storage.remove(dismissedKey);
                    notice.hidden = true;
                    return;
                }
                if (Number(storage.get(dismissedKey) || 0) > Date.now()) return;
                notice.hidden = !(ios || installReady);
            }

            window.addEventListener('beforeinstallprompt', event => {
                event.preventDefault();
                installPrompt = event;
                installReady = true;
                showInstallNotice();
            });
            window.addEventListener('appinstalled', () => {
                installPrompt = null;
                installReady = false;
                storage.remove(dismissedKey);
                notice.hidden = true;
                syncHeroInstallButton();
            });
            action.addEventListener('click', async () => {
                if (ios || !installPrompt) {
                    modal.hidden = false;
                    document.body.classList.add('app-install-open');
                    modal.querySelector('[data-install-close]').focus();
                    return;
                }
                installPrompt.prompt();
                const choice = await installPrompt.userChoice;
                installPrompt = null;
                installReady = false;
                if (choice?.outcome === 'accepted') notice.hidden = true;
                else showInstallNotice();
            });
            dismiss?.addEventListener('click', () => {
                storage.set(dismissedKey, String(Date.now() + 7 * 24 * 60 * 60 * 1000));
                notice.hidden = true;
            });
            modal.querySelectorAll('[data-install-close]').forEach(button => button.addEventListener('click', () => {
                modal.hidden = true;
                document.body.classList.remove('app-install-open');
                action.focus();
            }));
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && !modal.hidden) {
                    modal.hidden = true;
                    document.body.classList.remove('app-install-open');
                    action.focus();
                }
            });
            showInstallNotice();

            let touchStartY = 0;
            let touchStartX = 0;
            let pulling = false;
            let refreshing = false;
            let pullDistance = 0;
            const threshold = 70;
            const ignoredTarget = target => target.closest('input, textarea, select, button, a, [contenteditable="true"], .table-chat, .table-chat__messages, .game-board, canvas, video, audio, [data-no-pull-refresh]');
            const isAtTop = target => {
                let element = target instanceof Element ? target : null;
                while (element && element !== document.body && element !== document.documentElement) {
                    const overflow = window.getComputedStyle(element).overflowY;
                    if ((overflow === 'auto' || overflow === 'scroll') && element.scrollHeight > element.clientHeight && element.scrollTop > 1) return false;
                    element = element.parentElement;
                }
                return (window.scrollY || document.documentElement.scrollTop || document.body.scrollTop || 0) <= 1;
            };

            window.addEventListener('touchstart', event => {
                if (event.touches.length !== 1 || refreshing || !isAtTop(event.target) || ignoredTarget(event.target)) return;
                touchStartY = event.touches[0].clientY;
                touchStartX = event.touches[0].clientX;
                pulling = true;
                pullDistance = 0;
            }, { passive: true });
            window.addEventListener('touchmove', event => {
                if (!pulling) return;
                if (event.touches.length !== 1 || !isAtTop(event.target)) { cancelPull(); return; }
                const dy = event.touches[0].clientY - touchStartY;
                const dx = event.touches[0].clientX - touchStartX;
                if (dy <= 0 || Math.abs(dx) > dy) { if (Math.abs(dx) > dy) cancelPull(); return; }
                if (dy < 8) return;
                event.preventDefault();
                pullDistance = Math.min(112, dy * 0.72);
                refresh.style.setProperty('--pull-progress', `${pullDistance}px`);
                refresh.classList.add('is-pulling');
                refresh.querySelector('.pull-refresh__text').textContent = pullDistance >= threshold ? @json(__('Release to refresh')) : @json(__('Pull to refresh'));
            }, { passive: false });
            function cancelPull() {
                pulling = false;
                pullDistance = 0;
                refresh.classList.remove('is-pulling');
                refresh.style.removeProperty('--pull-progress');
            }
            function finishPull() {
                if (!pulling) return;
                pulling = false;
                if (pullDistance >= threshold) {
                    refreshing = true;
                    refresh.classList.remove('is-pulling');
                    refresh.classList.add('is-refreshing');
                    refresh.querySelector('.pull-refresh__text').textContent = @json(__('Refreshing…'));
                    setTimeout(() => window.location.reload(), 180);
                    return;
                }
                cancelPull();
            }
            window.addEventListener('touchend', finishPull, { passive: true });
            window.addEventListener('touchcancel', cancelPull, { passive: true });
        })();
    </script>

    <script>
        (function ($) {
            'use strict';
            const popup = document.querySelector('.win-loss-popup');
            if (!popup) return;
            const title = popup.querySelector('.win-loss-popup__title');
            const amount = popup.querySelector('.win-loss-popup__amount');
            const message = popup.querySelector('.win-loss-popup__message');
            const currency = popup.dataset.currency || '';
            const formatAmount = value => `${currency} ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`.trim();

            function syncPopup() {
                const opened = popup.classList.contains('active');
                popup.setAttribute('aria-hidden', opened ? 'false' : 'true');
                if (!opened) return;
                const status = Number(popup.dataset.resultStatus || 0);
                const state = status === 2 || popup.querySelector('.push:not(.d-none)') ? 'push' :
                    popup.querySelector('.win:not(.d-none)') ? 'win' :
                    popup.querySelector('.lose:not(.d-none)') ? 'loss' :
                    (status === 1 ? 'win' : 'loss');
                const stateClass = `is-${state}`;
                if (!popup.classList.contains(stateClass)) {
                    popup.classList.remove('is-win', 'is-loss', 'is-push');
                    popup.classList.add(stateClass);
                }
                const winAmount = Number(popup.dataset.winAmount || 0);
                const investAmount = Number(popup.dataset.investAmount || 0);
                if (state === 'win') {
                    title.textContent = @json(__('You won!'));
                    amount.textContent = `+${formatAmount(winAmount)}`;
                    message.textContent = @json(__('Your payout has been added to your balance.'));
                } else if (state === 'push') {
                    title.textContent = @json(__('It’s a tie'));
                    amount.textContent = investAmount ? formatAmount(investAmount) : '';
                    message.textContent = @json(__('Your stake has been returned.'));
                } else {
                    title.textContent = @json(__('Round over'));
                    amount.textContent = investAmount ? `−${formatAmount(investAmount)}` : '';
                    message.textContent = @json(__('Better luck on your next round.'));
                }
            }

            // Observe only popup-owned state. Watching descendant classes caused syncPopup's
            // own is-win/is-loss class update to retrigger itself continuously after a result.
            new MutationObserver(syncPopup).observe(popup, { attributes: true, attributeFilter: ['class', 'data-win-amount', 'data-invest-amount', 'data-result-status'] });
            syncPopup();
            $(document).on('ajaxSuccess', function (_event, _xhr, settings, response) {
                if (!response || typeof response !== 'object') return;
                const data = response.data && typeof response.data === 'object' ? response.data : response;
                if (data.win_amount !== undefined) popup.dataset.winAmount = data.win_amount;
                if (data.invest_amount !== undefined) popup.dataset.investAmount = data.invest_amount;
                if (data.win_status !== undefined) popup.dataset.resultStatus = data.win_status;
            });
            function closePopup() { popup.classList.remove('active'); }
            popup.querySelectorAll('.win-loss-popup__close, .win-loss-popup__dismiss').forEach(button => button.addEventListener('click', closePopup));
            popup.querySelector('[data-popup-backdrop]').addEventListener('click', event => {
                if (event.target === event.currentTarget) closePopup();
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && popup.classList.contains('active')) closePopup();
            });
            popup.querySelector('.win-loss-popup__again').addEventListener('click', function () {
                const specificAgain = document.querySelector('.play-again-btn:not(.d-none), .resetBtn:not(.d-none)');
                if (specificAgain) specificAgain.click();
                else {
                    const playButton = document.querySelector('.nocturne-game-page .game-playbtn button:not(:disabled)');
                    if (playButton && !playButton.classList.contains('disabled')) playButton.click();
                }
                closePopup();
            });
            syncPopup();
        })(jQuery);
    </script>

    <script>
        (function () {
            'use strict';
            const coarsePointer = window.matchMedia('(hover: none), (pointer: coarse)');
            if (!coarsePointer.matches) return;

            document.addEventListener('click', function (event) {
                const card = event.target.closest('.game-item');
                if (!card || event.target.closest('.game-item__play a, .game-item__play button')) return;

                const wasOpen = card.classList.contains('is-actions-visible');
                document.querySelectorAll('.game-item.is-actions-visible').forEach(function (openCard) {
                    openCard.classList.remove('is-actions-visible');
                    openCard.setAttribute('aria-expanded', 'false');
                });
                if (!wasOpen) {
                    card.classList.add('is-actions-visible');
                    card.setAttribute('aria-expanded', 'true');
                }
            });

            document.addEventListener('click', function (event) {
                if (event.target.closest('.game-item')) return;
                document.querySelectorAll('.game-item.is-actions-visible').forEach(function (card) {
                    card.classList.remove('is-actions-visible');
                    card.setAttribute('aria-expanded', 'false');
                });
            });
        })();
    </script>

    <script>
        (function($) {
            "use strict";

            $(".langSel").on("click", function() {
                window.location.href = "{{ route('home') }}/change/" + $(this).data('lang_code');
            });

            $('.policy').on('click', function() {
                $.get('{{ route('cookie.accept') }}', function(response) {
                    $('.cookies-card').addClass('d-none');
                });
            });

            setTimeout(function() {
                $('.cookies-card').removeClass('hide')
            }, 2000);

            var inputElements = $('[type=text],select,textarea');
            $.each(inputElements, function(index, element) {
                element = $(element);
                element.closest('.form-group').find('label').attr('for', element.attr('name'));
                element.attr('id', element.attr('name'))
            });

            $.each($('input:not([type=checkbox]):not([type=hidden]), select, textarea'), function(i, element) {
                var elementType = $(element);
                if (elementType.attr('type') != 'checkbox') {
                    if (element.hasAttribute('required')) {
                        $(element).closest('.form-group').find('label').addClass('required');
                    }
                }
            });

            let disableSubmission = false;
            $('.disableSubmission').on('submit', function(e) {
                if (disableSubmission) {
                    e.preventDefault()
                } else {
                    disableSubmission = true;
                }
            });

            $.each($(".select2"), function() {
                // Pinning dropdownParent to the select's own local wrapper clipped the open
                // dropdown panel against any ancestor with overflow:hidden (cards, modals,
                // the page itself) - it could render cut off or invisible instead of floating
                // over the page. Select2's default (append the panel to <body>) avoids that
                // entirely, so only pin it to a modal - the one case that actually needs a
                // local parent, to keep the dropdown stacked above that modal's backdrop.
                var modalParent = $(this).closest('.modal');
                var options = modalParent.length ? { dropdownParent: modalParent } : {};
                $(this).select2(options);
            });


            Array.from(document.querySelectorAll('table')).forEach(table => {
                let heading = table.querySelectorAll('thead tr th');
                if (heading.length > 0) {
                    Array.from(table.querySelectorAll('tbody tr')).forEach((row) => {
                        Array.from(row.querySelectorAll('td')).forEach((colum, i) => {
                            colum.setAttribute('data-label', heading[i].innerText)
                        });
                    });
                }
            });




            let elements = document.querySelectorAll('[s-break]');
            Array.from(elements).forEach(element => {
                let html = element.innerHTML;
                if (typeof html != 'string') {
                    return false;
                }
                let breakLength = parseInt(element.getAttribute('s-break'));
                html = html.split(" ");
                var colorText = [];
                if (breakLength < 0) {
                    colorText = html.slice(breakLength);
                } else {
                    colorText = html.slice(0, breakLength);
                }
                let solidText = [];
                html.filter(ele => {
                    if (!colorText.includes(ele)) {
                        solidText.push(ele);
                    }
                });
                var color = element.getAttribute('s-color') || "title-color";
                colorText = `<span class="${color}">${colorText.toString().replaceAll(',', ' ')}</span>`;
                solidText = solidText.toString().replaceAll(',', ' ');
                breakLength < 0 ? element.innerHTML = `${solidText} ${colorText}` : element.innerHTML =
                    `${colorText} ${solidText}`
            });
        })(jQuery);

        async function registerSW() {
            if ('serviceWorker' in navigator) {
                try {
                    await navigator.serviceWorker.register(
                        "{{ asset('serviceworker.js') }}", { scope: '/' });
                } catch (e) {
                    console.warn('SW registration failed');
                }
            }
        }
        window.addEventListener('load', () => {
            registerSW();
        });

        // Belt-and-braces pinch-zoom lock: the viewport meta's maximum-scale/user-scalable
        // already blocks this on most browsers, but recent iOS Safari can still honor a
        // two-finger gesture despite it - this is the app, not a web page, it shouldn't move.
        document.addEventListener('gesturestart', (e) => e.preventDefault());
    </script>
    @auth
        <script>
            // Feeds the public "Total Players Online" counter - a deliberate periodic ping
            // (not a middleware on every request) so write frequency stays fully controlled
            // here, not on every page load. Only pings while the tab is actually visible.
            (function () {
                function ping() {
                    if (document.hidden) return;
                    fetch('{{ route('user.presence.ping') }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', Accept: 'application/json' },
                        keepalive: true,
                    }).catch(() => {});
                }
                ping();
                setInterval(ping, 60000);
                document.addEventListener('visibilitychange', () => { if (!document.hidden) ping(); });
            })();
        </script>
    @endauth
    @if (request()->routeIs('home') || request()->routeIs('user.home'))
        @include('partials.support_chat_widget')
    @endif
</body>

</html>
