<div class="nocturne-sidebar-backdrop"></div>
<aside class="nocturne-sidebar" aria-label="@lang('Site menu')">
    <div class="nocturne-sidebar-scroll">
        <div class="nocturne-shortcuts">
            <a href="{{ route('games') }}"><i class="las la-dice"></i>@lang('Games')</a>
            <a href="{{ route('games') }}"><i class="las la-star"></i>@lang('Explore')</a>
        </div>
        <a class="nocturne-promo" href="{{ route('games') }}">
            <span class="nocturne-promo-kicker">{{ gs('site_name') }}</span>
            <strong>@lang('Find your next favorite game')</strong>
            <span class="nocturne-promo-link">@lang('Browse games') <i class="las la-arrow-right"></i></span>
            <i class="las la-gift nocturne-promo-art" aria-hidden="true"></i>
        </a>
        <section class="nocturne-menu-group" aria-label="@lang('Play')">
            <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'is-active' : '' }}"><i class="las la-home"></i><span>@lang('Home')</span></a>
            <a href="{{ route('games') }}" class="{{ request()->routeIs('games') ? 'is-active' : '' }}"><i class="las la-dice"></i><span>@lang('Games')</span></a>
            @auth
                <a href="{{ route('user.home') }}" class="{{ request()->routeIs('user.home') ? 'is-active' : '' }}"><i class="las la-user"></i><span>@lang('Dashboard')</span></a>
                <a href="{{ route('user.deposit.index') }}" class="{{ request()->routeIs('user.deposit.*') ? 'is-active' : '' }}"><i class="las la-wallet"></i><span>@lang('Deposit')</span></a>
                <a href="{{ route('user.withdraw') }}" class="{{ request()->routeIs('user.withdraw*') ? 'is-active' : '' }}"><i class="las la-money-bill-wave"></i><span>@lang('Withdraw')</span></a>
                <a href="{{ route('user.transactions') }}" class="{{ request()->routeIs('user.transactions') ? 'is-active' : '' }}"><i class="las la-receipt"></i><span>@lang('Transactions')</span></a>
            @else
                <a href="{{ route('user.login') }}"><i class="las la-user"></i><span>@lang('Sign in')</span></a>
            @endauth
        </section>
        @auth
            <section class="nocturne-menu-group" aria-label="@lang('Your account')">
                <h6>@lang('Your account')</h6>
                <a href="{{ route('user.profile.setting') }}" class="{{ request()->routeIs('user.profile.*') ? 'is-active' : '' }}"><i class="las la-id-card"></i><span>@lang('Profile settings')</span></a>
                <a href="{{ route('user.referrals') }}" class="{{ request()->routeIs('user.referrals') ? 'is-active' : '' }}"><i class="las la-users"></i><span>@lang('Referrals')</span></a>
                <a href="{{ route('user.change.password') }}" class="{{ request()->routeIs('user.change.password') ? 'is-active' : '' }}"><i class="las la-cog"></i><span>@lang('Security')</span></a>
                <a href="{{ route('user.twofactor') }}" class="{{ request()->routeIs('user.twofactor') ? 'is-active' : '' }}"><i class="las la-shield-alt"></i><span>@lang('Two-factor authentication')</span></a>
                <a href="{{ route('user.game.log') }}" class="{{ request()->routeIs('user.game.log*') ? 'is-active' : '' }}"><i class="las la-dice-d20"></i><span>@lang('Game history')</span></a>
                <a href="{{ route('ticket.index') }}" class="{{ request()->routeIs('ticket.*') ? 'is-active' : '' }}"><i class="las la-comments"></i><span>@lang('Support')</span></a>
            </section>
        @endauth
        <section class="nocturne-menu-group" aria-label="@lang('More')">
            <h6>@lang('Discover')</h6>
            <a href="{{ route('blog') }}" class="{{ request()->routeIs('blog*') ? 'is-active' : '' }}"><i class="las la-newspaper"></i><span>@lang('News')</span></a>
            <a href="{{ route('contact') }}" class="{{ request()->routeIs('contact') ? 'is-active' : '' }}"><i class="las la-headset"></i><span>@lang('Contact')</span></a>
        </section>
        @if (gs('multi_language'))
            <ul class="nocturne-language list-unstyled mb-0">@include('Template::partials.language', ['class' => ''])</ul>
        @endif
        @auth
            <a class="nocturne-logout" href="{{ route('user.logout') }}"><i class="las la-sign-out-alt"></i>@lang('Log out')</a>
        @endauth
    </div>
</aside>
<nav class="nocturne-mobile-nav" aria-label="@lang('Quick navigation')">
    <button class="nocturne-mobile-menu" type="button"><i class="las la-th-large"></i><span>@lang('Menu')</span></button>
    <a href="{{ route('games') }}"><i class="las la-dice"></i><span>@lang('Games')</span></a>
    @auth
        <a href="{{ route('user.deposit.index') }}"><i class="las la-wallet"></i><span>@lang('Deposit')</span></a>
        <a href="{{ route('user.home') }}"><i class="las la-user"></i><span>@lang('Account')</span></a>
    @else
        <a href="{{ route('user.login') }}"><i class="las la-user"></i><span>@lang('Login')</span></a>
    @endauth
</nav>
@push('script')
<script>
    (() => {
        const shell = document.querySelector('.nocturne-shell');
        const toggle = document.querySelector('.nocturne-menu-toggle');
        const mobileToggle = document.querySelector('.nocturne-mobile-menu');
        const backdrop = document.querySelector('.nocturne-sidebar-backdrop');
        const closeMenu = () => { shell?.classList.remove('menu-open'); toggle?.setAttribute('aria-expanded', 'false'); };
        [toggle, mobileToggle].forEach(button => button?.addEventListener('click', () => {
            const open = shell?.classList.toggle('menu-open');
            toggle?.setAttribute('aria-expanded', String(Boolean(open)));
        }));
        backdrop?.addEventListener('click', closeMenu);
        document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(); });
    })();
</script>
@endpush
