<header class="nocturne-topbar">
    <div class="nocturne-brand-group">
        <a class="nocturne-brand" href="{{ route('home') }}" aria-label="{{ gs('site_name') }} home">
            <img src="{{ siteLogo() }}" alt="{{ gs('site_name') }}">
        </a>
        <button class="nocturne-collapse d-none d-xl-grid" type="button" aria-label="@lang('Collapse menu')">
            <i class="ph-bold ph-caret-left"></i>
        </button>
    </div>
    <button class="nocturne-menu-toggle" type="button" aria-label="@lang('Toggle menu')" aria-expanded="false">
        <i class="ph-bold ph-list"></i>
    </button>
    <nav class="nocturne-topnav" aria-label="@lang('Primary navigation')">
        <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'is-active' : '' }}"><i class="ph-bold ph-house"></i><span>@lang('Home')</span></a>
        @auth
            <a href="{{ route('user.home') }}" class="{{ request()->routeIs('user.home') ? 'is-active' : '' }}"><i class="ph-bold ph-user"></i><span>@lang('Profile')</span></a>
            <a href="{{ route('user.referrals') }}" class="{{ request()->routeIs('user.referrals') ? 'is-active' : '' }}"><i class="ph-bold ph-gift"></i><span>@lang('Bonuses')</span></a>
        @else
            <a href="{{ route('games') }}" class="{{ request()->routeIs('games') ? 'is-active' : '' }}"><i class="ph-bold ph-dice-five"></i><span>@lang('Games')</span></a>
        @endauth
        <a href="{{ auth()->check() ? route('user.vip.index') : route('pages', 'vip-club') }}"><i class="ph-bold ph-crown"></i><span>@lang('VIP-Club')</span></a>
    </nav>
    <div class="nocturne-actions">
        @auth
            <div class="nocturne-balance"><span>@lang('Your Balance')</span><strong>{{ showAmount(auth()->user()->balance) }}</strong></div>
            <div class="nocturne-user-menu">
                <button class="nocturne-avatar" type="button" aria-haspopup="true" aria-expanded="false" aria-label="@lang('Account menu')">
                    <i class="ph-bold ph-user-circle"></i>
                </button>
                <div class="nocturne-user-dropdown" role="menu">
                    <div class="nocturne-user-dropdown__head">
                        <span class="nocturne-account-avatar"><i class="ph-bold ph-user-circle"></i></span>
                        <div>
                            <strong>{{ auth()->user()->username }}</strong>
                            <span>{{ showAmount(auth()->user()->balance) }}</span>
                        </div>
                    </div>
                    <a class="nocturne-user-dropdown__deposit" href="{{ route('user.deposit.index') }}"><i class="ph-bold ph-plus-circle"></i>@lang('Deposit')</a>
                    <div class="nocturne-user-dropdown__links">
                        <a href="{{ route('user.home') }}"><i class="ph-bold ph-squares-four"></i>@lang('Dashboard')</a>
                        <a href="{{ route('games') }}"><i class="ph-bold ph-dice-five"></i>@lang('Games')</a>
                        <a href="{{ route('user.referrals') }}"><i class="ph-bold ph-gift"></i>@lang('Bonuses')</a>
                        <a href="{{ route('user.vip.index') }}"><i class="ph-bold ph-crown"></i>@lang('VIP Club')</a>
                        <a href="{{ route('user.transactions') }}"><i class="ph-bold ph-bell"></i>@lang('Notifications')</a>
                        <a href="{{ route('user.profile.setting') }}"><i class="ph-bold ph-gear"></i>@lang('Settings')</a>
                    </div>
                    <a class="nocturne-user-dropdown__logout" href="{{ route('user.logout') }}"><i class="ph-bold ph-sign-out"></i>@lang('Log out')</a>
                </div>
            </div>
        @else
            <a class="nocturne-login" href="{{ route('user.login') }}"><i class="ph-bold ph-sign-in"></i><span>@lang('Login')</span></a>
            @if (gs('registration'))
                <a class="nocturne-register" href="{{ route('user.register') }}"><i class="ph-bold ph-user-plus"></i><span>@lang('Register')</span></a>
            @endif
        @endauth
    </div>
</header>
@auth
    @push('script')
        <script>
            (() => {
                const menu = document.querySelector('.nocturne-user-menu');
                const toggle = menu?.querySelector('.nocturne-avatar');
                if (!menu || !toggle) return;
                const close = () => { menu.classList.remove('is-open'); toggle.setAttribute('aria-expanded', 'false'); };
                toggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const open = menu.classList.toggle('is-open');
                    toggle.setAttribute('aria-expanded', String(open));
                });
                document.addEventListener('click', (e) => { if (!menu.contains(e.target)) close(); });
                document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
            })();
        </script>
    @endpush
@endauth
