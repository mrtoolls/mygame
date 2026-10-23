@extends('Template::layouts.app')
@section('app')
    <div class="nocturne-shell {{ !empty($hideMenuForGuest) ? 'nocturne-shell--no-sidebar' : '' }}">
        @include('Template::partials.shell_header')
        @unless(!empty($hideMenuForGuest))
            @include('Template::partials.shell_sidebar')
        @endunless
        <main class="nocturne-main nocturne-footer-always">
            <div class="nocturne-content">
                @yield('content')
            </div>
            @include('Template::partials.footer')
        </main>
    </div>
@endsection
