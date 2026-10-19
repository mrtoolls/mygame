<!doctype html>
<html lang="{{ config('app.locale') }}" itemscope itemtype="http://schema.org/WebPage">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title> {{ gs()->siteName(__($pageTitle)) }}</title>
    @include('partials.seo')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Mulish:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css">
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
</head>

@php echo loadExtension('google-analytics') @endphp

<body>
    @stack('fbComment')

    <div class="preloader">
        <div class="loader-p"></div>
    </div>

    <div class="body-overlay"></div>

    <div class="sidebar-overlay"></div>

    <a class="scroll-top"><i class="fas fa-angle-double-up"></i></a>

    @yield('app')

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
            // The base theme only hides the preloader on the full window "load" event,
            // which waits for every image on the page - noticeably slow on asset-heavy
            // pages. Hide it as soon as the DOM is ready instead, with a short grace
            // period for first paint; window "load" stays as a harmless fallback.
            function hidePreloader() {
                $(".preloader").stop(true, true).fadeOut(180);
            }
            $(function() {
                setTimeout(hidePreloader, 120);
            });

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
                $(this)
                    .wrap(`<div class="position-relative"></div>`)
                    .select2({
                        dropdownParent: $(this).parent(),
                    });
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
                        "{{ asset('assets/global/js/pwa/serviceworker.js') }}");
                } catch (e) {
                    console.warn('SW registration failed');
                }
            }
        }
        window.addEventListener('load', () => {
            registerSW();
        });
    </script>
    @include('partials.support_chat_widget')
</body>

</html>
