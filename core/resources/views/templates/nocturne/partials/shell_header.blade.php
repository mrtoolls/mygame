<header class="nocturne-topbar">
    <a class="nocturne-brand" href="{{ route('home') }}" aria-label="{{ gs('site_name') }} home">
        <img src="{{ siteLogo() }}" alt="{{ gs('site_name') }}">
    </a>
    <button class="nocturne-menu-toggle" type="button" aria-label="Toggle menu" aria-expanded="false">
        <i class="las la-bars"></i>
    </button>
    <nav class="nocturne-topnav" aria-label="@lang('Primary navigation')">
        <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'is-active' : '' }}"><i class="las la-home"></i><span>@lang('Home')</span></a>
        <a href="{{ route('games') }}" class="{{ request()->routeIs('games') ? 'is-active' : '' }}"><i class="las la-dice"></i><span>@lang('Games')</span></a>
        @auth
            <a href="{{ route('user.home') }}" class="{{ request()->routeIs('user.home') ? 'is-active' : '' }}"><i class="las la-user"></i><span>@lang('Account')</span></a>
        @endauth
    </nav>
    <div class="nocturne-actions">
        @auth
            <div class="nocturne-balance"><span>@lang('Balance')</span><strong>{{ showAmount(auth()->user()->balance) }}</strong></div>
            <a class="nocturne-deposit" href="{{ route('user.deposit.index') }}"><i class="las la-wallet"></i><span>@lang('Deposit')</span></a>
            <a class="nocturne-avatar" href="{{ route('user.profile.setting') }}" aria-label="@lang('Profile')"><i class="las la-user-circle"></i></a>
            <a class="nocturne-notifications" href="{{ route('user.transactions') }}" aria-label="@lang('Transactions')"><i class="las la-bell"></i></a>
        @else
            <a class="nocturne-login" href="{{ route('user.login') }}">@lang('Login')</a>
            @if (gs('registration'))
                <a class="nocturne-deposit" href="{{ route('user.register') }}">@lang('Create account')</a>
            @endif
        @endauth
    </div>
</header>
