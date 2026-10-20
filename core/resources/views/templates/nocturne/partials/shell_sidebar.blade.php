<div class="nocturne-sidebar-backdrop"></div>
<aside class="nocturne-sidebar" aria-label="@lang('Site menu')">
    <div class="nocturne-sidebar-scroll">
        <div class="nocturne-game-tabs" aria-label="@lang('Catalogue')">
            <a href="{{ route('games') }}" class="{{ request()->routeIs('games') ? 'is-active' : '' }}"><i class="ph-fill ph-dice-five"></i>@lang('Games')</a>
            <a href="{{ route('user.tables.index') }}" class="{{ request()->routeIs('user.tables.*') ? 'is-active' : '' }}"><i class="ph-fill ph-broadcast"></i>@lang('Live')</a>
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
            @auth
                <a href="{{ route('user.home') }}" class="{{ request()->routeIs('user.home') ? 'is-active' : '' }}"><i class="ph-fill ph-user"></i><span>@lang('Profile')</span></a>
                <a href="{{ route('user.tables.index') }}" class="{{ request()->routeIs('user.tables.*') ? 'is-active' : '' }}"><i class="ph-fill ph-sword"></i><span>@lang('Live Games')</span></a>
                <a href="{{ route('ticket.index') }}" class="{{ request()->routeIs('ticket.*') ? 'is-active' : '' }}"><i class="ph-fill ph-headset"></i><span>@lang('Live Support')</span></a>
                <a href="{{ route('user.deposit.index') }}" class="{{ request()->routeIs('user.deposit.*') && !request()->routeIs('user.deposit.history') ? 'is-active' : '' }}"><i class="ph-fill ph-wallet"></i><span>@lang('Deposit')</span></a>
                <a href="{{ route('user.withdraw') }}" class="{{ request()->routeIs('user.withdraw*') ? 'is-active' : '' }}"><i class="ph-fill ph-money-wavy"></i><span>@lang('Withdraw')</span></a>
                <a href="{{ route('user.referrals') }}" class="{{ request()->routeIs('user.referrals') ? 'is-active' : '' }}"><i class="ph-fill ph-gift"></i><span>@lang('Bonuses')</span></a>
            @else
                <a href="{{ route('user.login') }}"><i class="ph-fill ph-user"></i><span>@lang('Sign in')</span></a>
                <a href="{{ route('contact') }}" class="{{ request()->routeIs('contact') ? 'is-active' : '' }}"><i class="ph-fill ph-headset"></i><span>@lang('Live Support')</span></a>
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
        </section>
        @if (gs('multi_language'))
            <ul class="nocturne-language list-unstyled mb-0">@include('Template::partials.language', ['class' => ''])</ul>
        @endif
        @auth
            <a class="nocturne-logout" href="{{ route('user.logout') }}"><i class="ph-fill ph-sign-out"></i><span>@lang('Log out')</span></a>
        @endauth
    </div>
</aside>
<nav class="nocturne-mobile-nav" aria-label="@lang('Quick navigation')">
    <button class="nocturne-mobile-menu" type="button"><i class="ph-fill ph-squares-four"></i><span>@lang('Menu')</span></button>
    <a href="{{ route('games') }}" class="{{ request()->routeIs('games') ? 'is-active' : '' }}"><i class="ph-fill ph-dice-five"></i><span>@lang('Games')</span></a>
    <a href="{{ route('user.tables.index') }}" class="{{ request()->routeIs('user.tables.*') ? 'is-active' : '' }}"><i class="ph-fill ph-broadcast"></i><span>@lang('Live')</span></a>
    @auth
        <a href="{{ route('user.referrals') }}" class="{{ request()->routeIs('user.referrals') ? 'is-active' : '' }}"><i class="ph-fill ph-gift"></i><span>@lang('Bonuses')</span></a>
        <a href="{{ route('user.home') }}" class="{{ request()->routeIs('user.home') ? 'is-active' : '' }}"><i class="ph-fill ph-user"></i><span>@lang('Profile')</span></a>
    @else
        <a href="{{ route('user.register') }}"><i class="ph-fill ph-gift"></i><span>@lang('Bonuses')</span></a>
        <a href="{{ route('user.login') }}"><i class="ph-fill ph-user"></i><span>@lang('Login')</span></a>
    @endauth
</nav>
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
