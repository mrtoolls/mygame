@extends('Template::layouts.frontend')
@section('content')
    @if (!empty($sections?->secs))
        @include('Template::sections.banner')
        @foreach (json_decode($sections->secs) as $sec)
            @include('Template::sections.' . $sec)
        @endforeach
    @else
        <section class="nocturne-welcome">
            <div class="nocturne-welcome-copy">
                <span class="nocturne-welcome-greeting"><i class="ph-fill ph-hand-waving" aria-hidden="true"></i>@lang('Welcome Back, Champion!')</span>
                <h1>@lang('Who’s your next opponent?')</h1>
                <div class="nocturne-welcome-actions">
                    <a class="nocturne-deposit" href="{{ route('games') }}"><i class="ph-bold ph-dice-five"></i>@lang('Explore games')</a>
                    <a id="hero-arena-link" class="nocturne-welcome-secondary" href="{{ route('arena.index') }}" hidden><i class="ph-fill ph-fire"></i>@lang('Arena')</a>
                    <button id="hero-install-btn" type="button" class="nocturne-welcome-secondary"><i class="ph-bold ph-download-simple"></i>@lang('Install App')</button>
                </div>
            </div>
            <div class="nocturne-welcome-art" aria-hidden="true"><span class="nocturne-orbit orbit-one"></span><span class="nocturne-orbit orbit-two"></span><i class="ph-bold ph-dice-six"></i><i class="ph-bold ph-dice-five"></i><i class="ph-bold ph-star"></i></div>
        </section>
        <section class="nocturne-home-cards" aria-label="@lang('Quick links')">
            <a href="{{ route('games') }}"><i class="ph-bold ph-dice-five"></i><span><strong>@lang('Browse games')</strong><small>@lang('Explore what is available')</small></span></a>
            @auth
                <a href="{{ route('user.deposit.index') }}"><i class="ph-bold ph-wallet"></i><span><strong>@lang('Your account')</strong><small>@lang('Manage your wallet')</small></span></a>
            @else
                <a href="{{ route('user.register') }}"><i class="ph-bold ph-user-plus"></i><span><strong>@lang('Create an account')</strong><small>@lang('Get started in seconds')</small></span></a>
            @endauth
            <a href="{{ route('arena.index') }}"><i class="ph-bold ph-fire"></i><span><strong>@lang('Arena')</strong><small>@lang('See what is happening')</small></span></a>
            <a href="{{ route('contact') }}"><i class="ph-bold ph-headset"></i><span><strong>@lang('Need help?')</strong><small>@lang('Talk to the team')</small></span></a>
        </section>
    @endif

    @include('Template::sections.trending_games')
    @include('Template::sections.latest_big_wins')
    @include('Template::sections.featured_games')
    @include('Template::sections.live_table_games')
    @include('Template::sections.home_reviews')
    @include('Template::sections.home_news')
@endsection
