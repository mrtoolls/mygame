<div class="nocturne-sidebar-backdrop"></div>
<aside class="nocturne-sidebar" aria-label="@lang('Site menu')">
    <div class="nocturne-sidebar-scroll">
        <div class="nocturne-game-tabs" role="tablist" aria-label="@lang('Catalogue')">
            <button type="button" class="is-active" data-tab="games"><i class="las la-dice"></i>@lang('Games')</button>
            <button type="button" data-tab="slots"><i class="las la-gem"></i>@lang('Slots')</button>
        </div>
        <a class="nocturne-reward" href="{{ auth()->check() ? route('user.deposit.index') : route('user.register') }}">
            <span class="nocturne-reward-badge">@lang('Welcome offer')</span>
            <strong class="nocturne-reward-title">@lang('Claim your first deposit reward')</strong>
            <span class="nocturne-reward-icon"><i class="las la-gift"></i></span>
        </a>
        <section class="nocturne-menu-group" aria-label="@lang('Play')">
            <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'is-active' : '' }}"><i class="las la-home"></i><span>@lang('Home')</span></a>
            @auth
                <a href="{{ route('user.home') }}" class="{{ request()->routeIs('user.home') ? 'is-active' : '' }}"><i class="las la-user"></i><span>@lang('Profile')</span></a>
                <a href="{{ route('ticket.index') }}" class="{{ request()->routeIs('ticket.*') ? 'is-active' : '' }}"><i class="las la-headset"></i><span>@lang('Live Support')</span></a>
                <a href="{{ route('user.deposit.index') }}" class="{{ request()->routeIs('user.deposit.*') && !request()->routeIs('user.deposit.history') ? 'is-active' : '' }}"><i class="las la-wallet"></i><span>@lang('Deposit')</span></a>
                <a href="{{ route('user.withdraw') }}" class="{{ request()->routeIs('user.withdraw*') ? 'is-active' : '' }}"><i class="las la-money-bill-wave"></i><span>@lang('Withdraw')</span></a>
                <a href="{{ route('user.referrals') }}" class="{{ request()->routeIs('user.referrals') ? 'is-active' : '' }}"><i class="las la-gift"></i><span>@lang('Bonuses')</span></a>
            @else
                <a href="{{ route('user.login') }}"><i class="las la-user"></i><span>@lang('Sign in')</span></a>
                <a href="{{ route('contact') }}" class="{{ request()->routeIs('contact') ? 'is-active' : '' }}"><i class="las la-headset"></i><span>@lang('Live Support')</span></a>
            @endauth
        </section>
        <section class="nocturne-menu-group" aria-label="@lang('More')">
            <h6>@lang('Discover')</h6>
            <a href="{{ route('pages', 'about-us') }}"><i class="las la-book-open"></i><span>@lang('About Us')</span></a>
            <a href="{{ route('pages', 'promotions') }}"><i class="las la-ticket-alt"></i><span>@lang('Promotions')</span></a>
            <a href="{{ route('pages', 'sponsorships') }}"><i class="las la-shield-alt"></i><span>@lang('Sponsorships')</span></a>
            <a href="{{ route('pages', 'licenses-security') }}"><i class="las la-lock"></i><span>@lang('Licenses & Security')</span></a>
            <a href="{{ route('pages', 'vip-club') }}"><i class="las la-crown"></i><span>@lang('VIP-Club')</span></a>
            <a href="{{ route('contact') }}" class="{{ request()->routeIs('contact') ? 'is-active' : '' }}"><i class="las la-comment-dots"></i><span>@lang('Feedback About Us')</span></a>
            <a href="{{ route('blog') }}" class="{{ request()->routeIs('blog*') ? 'is-active' : '' }}"><i class="las la-newspaper"></i><span>@lang('News')</span></a>
        </section>
        @if (gs('multi_language'))
            <ul class="nocturne-language list-unstyled mb-0">@include('Template::partials.language', ['class' => ''])</ul>
        @endif
        @auth
            <a class="nocturne-logout" href="{{ route('user.logout') }}"><i class="las la-sign-out-alt"></i><span>@lang('Log out')</span></a>
        @endauth
    </div>
</aside>
<nav class="nocturne-mobile-nav" aria-label="@lang('Quick navigation')">
    <button class="nocturne-mobile-menu" type="button"><i class="las la-th-large"></i><span>@lang('Menu')</span></button>
    <a href="{{ route('games') }}" class="{{ request()->routeIs('games') ? 'is-active' : '' }}"><i class="las la-dice"></i><span>@lang('Games')</span></a>
    <a href="{{ route('games') }}"><i class="las la-gem"></i><span>@lang('Slots')</span></a>
    @auth
        <a href="{{ route('user.referrals') }}" class="{{ request()->routeIs('user.referrals') ? 'is-active' : '' }}"><i class="las la-gift"></i><span>@lang('Bonuses')</span></a>
        <a href="{{ route('user.home') }}" class="{{ request()->routeIs('user.home') ? 'is-active' : '' }}"><i class="las la-user"></i><span>@lang('Profile')</span></a>
    @else
        <a href="{{ route('user.register') }}"><i class="las la-gift"></i><span>@lang('Bonuses')</span></a>
        <a href="{{ route('user.login') }}"><i class="las la-user"></i><span>@lang('Login')</span></a>
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

        const tabs = document.querySelectorAll('.nocturne-game-tabs button');
        tabs.forEach(btn => btn.addEventListener('click', () => {
            tabs.forEach(b => b.classList.remove('is-active'));
            btn.classList.add('is-active');
        }));
    })();
</script>
@endpush
