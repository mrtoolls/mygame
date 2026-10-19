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
                <span>@lang('Welcome to') {{ gs('site_name') }}</span>
                <h1>@lang('A sharper way to play.')</h1>
                <p>@lang('Explore the game library, manage your account, and keep track of your activity from one place.')</p>
                <div class="nocturne-welcome-actions">
                    <a class="nocturne-deposit" href="{{ route('games') }}"><i class="ph-bold ph-dice-five"></i>@lang('Explore games')</a>
                    @auth
                        <a class="nocturne-welcome-secondary" href="{{ route('user.home') }}">@lang('Open dashboard') <i class="ph-bold ph-arrow-right"></i></a>
                    @else
                        <a class="nocturne-welcome-secondary" href="{{ route('user.login') }}">@lang('Sign in') <i class="ph-bold ph-arrow-right"></i></a>
                    @endauth
                </div>
            </div>
            <div class="nocturne-welcome-art" aria-hidden="true"><span class="nocturne-orbit orbit-one"></span><span class="nocturne-orbit orbit-two"></span><i class="ph-bold ph-dice-six"></i><i class="ph-bold ph-dice-five"></i><i class="ph-bold ph-star"></i></div>
        </section>
        <section class="nocturne-home-cards" aria-label="@lang('Quick links')">
            <a href="{{ route('games') }}"><i class="ph-bold ph-dice-five"></i><span><strong>@lang('Browse games')</strong><small>@lang('Explore what is available')</small></span><i class="ph-bold ph-arrow-right"></i></a>
            @auth
                <a href="{{ route('user.deposit.index') }}"><i class="ph-bold ph-wallet"></i><span><strong>@lang('Your account')</strong><small>@lang('View your account options')</small></span><i class="ph-bold ph-arrow-right"></i></a>
            @else
                <a href="{{ route('user.register') }}"><i class="ph-bold ph-user-plus"></i><span><strong>@lang('Create an account')</strong><small>@lang('Get started in a few steps')</small></span><i class="ph-bold ph-arrow-right"></i></a>
            @endauth
            <a href="{{ route('contact') }}"><i class="ph-bold ph-headset"></i><span><strong>@lang('Need help?')</strong><small>@lang('Get in touch with the team')</small></span><i class="ph-bold ph-arrow-right"></i></a>
        </section>
    @endif

    @include('Template::sections.trending_games')
    @include('Template::sections.featured_games')
    @include('Template::sections.live_table_games')
@endsection
