@extends('Template::layouts.app')
@section('app')
    <div class="nocturne-shell">
        @include('Template::partials.shell_header')
        @include('Template::partials.shell_sidebar')
        <main class="nocturne-main">
            <div class="nocturne-content">
                @yield('content')
            </div>
            @include('Template::partials.footer')
        </main>
    </div>
@endsection
