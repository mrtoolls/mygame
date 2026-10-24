@extends('Template::layouts.app')
@section('app')
    <div class="nocturne-shell">
        @include('Template::partials.shell_header')
        @include('Template::partials.shell_sidebar')
        <main class="nocturne-main nocturne-footer-always {{ auth()->guest() ? 'nocturne-no-mobile-nav' : '' }}">
            <div class="nocturne-content">
                @yield('content')
            </div>
            @include('Template::partials.footer')
        </main>
    </div>
@endsection
