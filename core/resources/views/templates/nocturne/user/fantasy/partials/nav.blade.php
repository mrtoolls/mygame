{{-- The .nocturne-account wrapper's CSS is a 2-column grid (a 300px sidebar + the content pane) -
without a first child here, the lone .nocturne-account-content was landing in the SIDEBAR's
column instead of the content column on desktop, squeezing the entire page into ~300px. This
sidebar both fixes that and gives Fantasy Football its own section navigation, matching how every
other account area (partials/account_nav.blade.php) already works. --}}
<aside class="nocturne-account-nav">
    <div class="nocturne-account-card">
        <div class="nocturne-account-id">
            <span class="nocturne-account-avatar"><i class="ph-fill ph-soccer-ball"></i></span>
            <div>
                <strong>@lang('Fantasy Football')</strong>
                <small>@lang('Manage your squad')</small>
            </div>
        </div>
        <nav class="nocturne-account-links">
            <a href="{{ route('user.fantasy.team') }}" class="{{ request()->routeIs('user.fantasy.team', 'user.fantasy.create', 'user.fantasy.create.store') ? 'is-active' : '' }}"><i class="ph-fill ph-shield-star"></i>@lang('My Team')</a>
            <a href="{{ route('user.fantasy.transfers') }}" class="{{ request()->routeIs('user.fantasy.transfers*') ? 'is-active' : '' }}"><i class="ph-fill ph-repeat"></i>@lang('Transfers')</a>
            <a href="{{ route('user.fantasy.points') }}" class="{{ request()->routeIs('user.fantasy.points') ? 'is-active' : '' }}"><i class="ph-fill ph-chart-line-up"></i>@lang('Points')</a>
            <a href="{{ route('user.fantasy.fixtures.index') }}" class="{{ request()->routeIs('user.fantasy.fixtures.*') ? 'is-active' : '' }}"><i class="ph-fill ph-calendar-check"></i>@lang('Fixtures')</a>
            <a href="{{ route('user.fantasy.leagues.index') }}" class="{{ request()->routeIs('user.fantasy.leagues.*') ? 'is-active' : '' }}"><i class="ph-fill ph-trophy"></i>@lang('Leagues')</a>
            <a href="{{ route('user.fantasy.contests.index') }}" class="{{ request()->routeIs('user.fantasy.contests.*') ? 'is-active' : '' }}"><i class="ph-fill ph-coins"></i>@lang('Contests')</a>
            <a href="{{ route('user.fantasy.rules') }}" class="{{ request()->routeIs('user.fantasy.rules') ? 'is-active' : '' }}"><i class="ph-fill ph-book-open"></i>@lang('Rules & Fair Play')</a>
        </nav>
    </div>
</aside>
