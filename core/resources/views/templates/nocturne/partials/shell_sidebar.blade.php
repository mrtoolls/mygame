<div class="nocturne-sidebar-backdrop"></div>
<aside class="nocturne-sidebar" aria-label="@lang('Site menu')">
    <div class="nocturne-sidebar-scroll">
        <div class="nocturne-game-tabs" aria-label="@lang('Catalogue')">
            <a href="{{ route('games') }}" class="{{ request()->routeIs('games') ? 'is-active' : '' }}"><i class="ph-fill ph-dice-five"></i>@lang('Games')</a>
            <a href="{{ route('user.tables.index') }}" class="{{ request()->routeIs('user.tables.*') ? 'is-active' : '' }}"><i class="ph-fill ph-broadcast nocturne-live-pulse"></i>@lang('Live')</a>
        </div>
        @php
            $bonusCountdown = getContent('bonus_countdown.content', true);
            $countdownEndAt = @$bonusCountdown->data_values->end_at ? strtotime($bonusCountdown->data_values->end_at) : null;
            $countdownActive = $countdownEndAt && $countdownEndAt > time();
            $rewardArt = !empty($bonusCountdown->data_values->background_image) ? frontendImage('bonus_countdown', $bonusCountdown->data_values->background_image, '480x320') : null;
        @endphp
        <a class="nocturne-reward {{ $rewardArt ? 'has-art' : '' }}" href="{{ @$bonusCountdown->data_values->button_url ?: (auth()->check() ? route('user.deposit.index') : route('user.register')) }}" @if($rewardArt) style="background-image:url('{{ $rewardArt }}')" @endif>
            <span class="nocturne-reward-badge">{{ __(@$bonusCountdown->data_values->badge) ?: __('Welcome offer') }}</span>
            <strong class="nocturne-reward-title">{{ __(@$bonusCountdown->data_values->heading) ?: __('Claim your free reward') }}</strong>
            @if ($countdownActive)
                <span class="nocturne-reward-countdown" data-countdown-end="{{ $countdownEndAt * 1000 }}">
                    <span class="nocturne-reward-countdown__unit" data-unit="h">00</span><span class="nocturne-reward-countdown__sep">:</span><span class="nocturne-reward-countdown__unit" data-unit="m">00</span><span class="nocturne-reward-countdown__sep">:</span><span class="nocturne-reward-countdown__unit" data-unit="s">00</span>
                </span>
            @endif
            @unless($rewardArt)<span class="nocturne-reward-icon"><i class="ph-fill ph-gift"></i></span>@endunless
        </a>
        <section class="nocturne-menu-group" aria-label="@lang('Play')">
            <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'is-active' : '' }}"><i class="ph-fill ph-house"></i><span>@lang('Home')</span></a>
            <a href="{{ route('arena.index') }}" class="{{ request()->routeIs('arena.*') ? 'is-active' : '' }}"><i class="ph-fill ph-fire"></i><span>@lang('Arena')</span></a>
            @auth
                <a href="{{ route('user.home') }}" class="{{ request()->routeIs('user.home') ? 'is-active' : '' }}"><i class="ph-fill ph-user"></i><span>@lang('Profile')</span></a>
                <a href="{{ route('user.tables.index') }}" class="{{ request()->routeIs('user.tables.*') ? 'is-active' : '' }}"><i class="ph-fill ph-sword nocturne-live-pulse"></i><span>@lang('Live Games')</span></a>
                <a href="{{ route('user.competitions.index') }}" class="{{ request()->routeIs('user.competitions.*') ? 'is-active' : '' }}"><i class="ph-fill ph-trophy"></i><span>@lang('Competitions')</span></a>
                <a href="javascript:void(0)" data-open-support><i class="ph-fill ph-headset"></i><span>@lang('Live Support')</span></a>
                <a href="{{ route('user.deposit.index') }}" class="{{ request()->routeIs('user.deposit.*') && !request()->routeIs('user.deposit.history') ? 'is-active' : '' }}"><i class="ph-fill ph-wallet"></i><span>@lang('Deposit')</span></a>
                @if(gs('coin_mode'))
                    @if(auth()->user()->country?->coin_marketplace_enabled)
                        <a href="{{ route('user.coin.marketplace') }}" class="{{ request()->routeIs('user.coin.*') ? 'is-active' : '' }}"><i class="ph-fill ph-coins"></i><span>@lang('Marketplace')</span></a>
                    @endif
                @else
                    <a href="{{ route('user.withdraw') }}" class="{{ request()->routeIs('user.withdraw*') ? 'is-active' : '' }}"><i class="ph-fill ph-money-wavy"></i><span>@lang('Withdraw')</span></a>
                @endif
                <a href="{{ route('user.referrals') }}" class="{{ request()->routeIs('user.referrals') ? 'is-active' : '' }}"><i class="ph-fill ph-gift"></i><span>@lang('Bonuses')</span></a>
            @else
                <a href="{{ route('user.login') }}"><i class="ph-fill ph-user"></i><span>@lang('Sign in')</span></a>
                <a href="javascript:void(0)" data-open-support><i class="ph-fill ph-headset"></i><span>@lang('Live Support')</span></a>
            @endauth
        </section>
        <section class="nocturne-menu-group" aria-label="@lang('More')">
            <h6>@lang('Discover')</h6>
            <a href="{{ route('pages', 'about-us') }}"><i class="ph-fill ph-book-open"></i><span>@lang('About Us')</span></a>
            <a href="{{ route('pages', 'promotions') }}"><i class="ph-fill ph-ticket"></i><span>@lang('Promotions')</span></a>
            <a href="{{ route('pages', 'sponsorships') }}"><i class="ph-fill ph-shield-check"></i><span>@lang('Sponsorships')</span></a>
            <a href="{{ route('pages', 'licenses-security') }}"><i class="ph-fill ph-lock"></i><span>@lang('Licenses & Security')</span></a>
            <a href="{{ auth()->check() ? route('user.vip.index') : route('pages', 'vip-club') }}"><i class="ph-fill ph-crown"></i><span>@lang('VIP-Club')</span></a>
            <a href="{{ route('feedback') }}"><i class="ph-fill ph-chat-circle"></i><span>@lang('Feedback About Us')</span></a>
            <a href="{{ route('blog') }}" class="{{ request()->routeIs('blog*') ? 'is-active' : '' }}"><i class="ph-fill ph-newspaper"></i><span>@lang('News')</span></a>
            <div class="nocturne-menu-group__official">
                @include('Template::partials.official_button')
            </div>
        </section>
        @if (gs('multi_language'))
            <ul class="nocturne-language list-unstyled mb-0">@include('Template::partials.language', ['class' => ''])</ul>
        @endif
        @auth
            <a class="nocturne-logout" href="{{ route('user.logout') }}"><i class="ph-fill ph-sign-out"></i><span>@lang('Log out')</span></a>
        @endauth
    </div>
</aside>
{{-- Guests never see this bar at all (on any page it would otherwise render on) - it's built
     around account-specific actions (Live tables, Bonuses, Profile), so a logged-out visitor
     gets no persistent nav chrome here and is pointed at Sign in/Join Us through the page's own
     content instead. .nocturne-main reserves bottom padding to clear this fixed bar on mobile;
     see the matching nocturne-no-mobile-nav class on <main> in master.blade.php/
     frontend.blade.php, which drops that reservation for guests so it doesn't leave a dead gap. --}}
@auth
<nav class="nocturne-mobile-nav" aria-label="@lang('Quick navigation')">
    <svg class="nocturne-mobile-nav__shape" viewBox="0 0 390 96" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="M0,96 V28 A28,28 0 0 1 28,0 H156 C174,0 178,34 195,34 C212,34 216,0 234,0 H362 A28,28 0 0 1 390,28 V96 Z" /><path class="nocturne-mobile-nav__outline" d="M0,28 A28,28 0 0 1 28,0 H156 C174,0 178,34 195,34 C212,34 216,0 234,0 H362 A28,28 0 0 1 390,28" /></svg>
    <div class="nocturne-mobile-nav__items">
        <button class="nocturne-mobile-menu" type="button"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.7"/><rect x="14" y="3" width="7" height="7" rx="1.7"/><rect x="3" y="14" width="7" height="7" rx="1.7"/><rect x="14" y="14" width="7" height="7" rx="1.7"/></svg><span class="nocturne-mobile-nav__label">@lang('Menu')</span></button>
        <a href="{{ route('games') }}" aria-label="@lang('Games')" class="{{ request()->routeIs('games') ? 'is-active' : '' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="2.5" width="19" height="19" rx="5"/><circle cx="8" cy="8" r="1"/><circle cx="16" cy="8" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="8" cy="16" r="1"/><circle cx="16" cy="16" r="1"/></svg><span class="nocturne-mobile-nav__label">@lang('Games')</span></a>
        <a href="{{ route('user.tables.index') }}" aria-label="@lang('Live')" class="nocturne-mobile-nav__live {{ request()->routeIs('user.tables.*') ? 'is-active' : '' }}">
            <svg class="nocturne-mobile-nav__live-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path class="wave wave-in" d="M5.6 8.6a6.4 6.4 0 0 0 0 6.8"/><path class="wave wave-in" d="M18.4 8.6a6.4 6.4 0 0 1 0 6.8"/><path class="wave wave-out" d="M2.6 6.4a10.4 10.4 0 0 0 0 11.2"/><path class="wave wave-out" d="M21.4 6.4a10.4 10.4 0 0 1 0 11.2"/><path d="M10.3 8.7v6.6a.5.5 0 0 0 .76.43l5.3-3.3a.5.5 0 0 0 0-.86l-5.3-3.3a.5.5 0 0 0-.76.43Z" fill="currentColor" stroke-width="1.6"/>
            </svg><span class="nocturne-mobile-nav__label">@lang('Live')</span>
        </a>
        <a href="{{ route('user.referrals') }}" aria-label="@lang('Bonuses')" class="{{ request()->routeIs('user.referrals') ? 'is-active' : '' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10h18v11H3zM2 6h20v4H2zM12 6v15M12 6H7.5a2.5 2.5 0 1 1 2.3-3.5L12 6Zm0 0h4.5a2.5 2.5 0 1 0-2.3-3.5L12 6Z"/></svg><span class="nocturne-mobile-nav__label">@lang('Bonuses')</span></a>
        <a href="{{ route('user.home') }}" aria-label="@lang('Profile')" class="{{ request()->routeIs('user.home') ? 'is-active' : '' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="7.5" r="4"/><path d="M3.5 21a8.5 8.5 0 0 1 17 0"/></svg><span class="nocturne-mobile-nav__label">@lang('Profile')</span></a>
    </div>
    <span class="nocturne-mobile-nav__home-indicator" aria-hidden="true"></span>
</nav>
@endauth
@push('script')
<script>
    (() => {
        const shell = document.querySelector('.nocturne-shell');
        const toggle = document.querySelector('.nocturne-menu-toggle');
        const mobileToggle = document.querySelector('.nocturne-mobile-menu');
        const collapseToggle = document.querySelector('.nocturne-collapse');
        const backdrop = document.querySelector('.nocturne-sidebar-backdrop');
        const closeMenu = () => { shell?.classList.remove('menu-open'); toggle?.setAttribute('aria-expanded', 'false'); };
        [toggle, mobileToggle].forEach(button => button?.addEventListener('click', () => {
            const open = shell?.classList.toggle('menu-open');
            toggle?.setAttribute('aria-expanded', String(Boolean(open)));
        }));
        collapseToggle?.addEventListener('click', () => {
            const collapsed = shell?.classList.toggle('sidebar-collapsed');
            try { localStorage.setItem('nocturneSidebarCollapsed', collapsed ? '1' : '0'); } catch (e) {}
        });
        try {
            if (window.innerWidth >= 1200 && localStorage.getItem('nocturneSidebarCollapsed') === '1') {
                shell?.classList.add('sidebar-collapsed');
            }
        } catch (e) {}
        backdrop?.addEventListener('click', closeMenu);
        document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(); });

        const countdown = document.querySelector('.nocturne-reward-countdown');
        if (countdown) {
            const endAt = Number(countdown.dataset.countdownEnd);
            const unitH = countdown.querySelector('[data-unit="h"]');
            const unitM = countdown.querySelector('[data-unit="m"]');
            const unitS = countdown.querySelector('[data-unit="s"]');
            const pad = n => String(n).padStart(2, '0');
            const tick = () => {
                const remaining = endAt - Date.now();
                if (remaining <= 0) {
                    countdown.closest('.nocturne-reward')?.classList.add('is-ended');
                    unitH.textContent = unitM.textContent = unitS.textContent = '00';
                    clearInterval(timer);
                    return;
                }
                unitH.textContent = pad(Math.floor(remaining / 3600000));
                unitM.textContent = pad(Math.floor((remaining % 3600000) / 60000));
                unitS.textContent = pad(Math.floor((remaining % 60000) / 1000));
            };
            tick();
            const timer = setInterval(tick, 1000);
        }
    })();
</script>
@endpush
