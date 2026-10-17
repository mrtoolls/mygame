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
            <a class="nocturne-deposit" href="{{ route('user.deposit.index') }}"><i class="ph-bold ph-wallet"></i><span>@lang('Deposit')</span></a>
            <a class="nocturne-avatar" href="{{ route('user.profile.setting') }}" aria-label="@lang('Profile')"><i class="ph-bold ph-user-circle"></i></a>
            <a class="nocturne-notifications" href="{{ route('user.transactions') }}" aria-label="@lang('Transactions')"><i class="ph-bold ph-bell"></i></a>
        @else
            <a class="nocturne-login" href="{{ route('user.login') }}">@lang('Login')</a>
            @if (gs('registration'))
                <a class="nocturne-deposit" href="{{ route('user.register') }}">@lang('Create account')</a>
            @endif
        @endauth
    </div>
</header>
