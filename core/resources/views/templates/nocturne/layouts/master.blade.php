@extends('Template::layouts.app')
@section('app')
    <div class="nocturne-shell">
        @include('Template::partials.shell_header')
        @include('Template::partials.shell_sidebar')
        <main class="nocturne-main {{ request()->routeIs('user.play.game') ? 'nocturne-game-page' : '' }} {{ request()->routeIs('user.tables.index', 'user.tables.show') ? 'nocturne-live-games-page' : '' }} {{ request()->routeIs('ticket.*') ? 'nocturne-footer-always' : '' }}">
            <div class="nocturne-content">
                <section class="py-100">
                    <div class="container">
                        @yield('content')
                    </div>
                </section>
            </div>
            @include('Template::partials.footer')
        </main>
    </div>
@endsection
