@extends('Template::layouts.app')
@section('app')
    <div class="nocturne-shell">
        @include('Template::partials.shell_header')
        @include('Template::partials.shell_sidebar')
        <main class="nocturne-main">
            @if (!request()->routeIs('home') && !request()->routeIs('user.*'))
                @include('Template::partials.breadcrumb')
            @endif
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

@push('script')
    <script>
        "use strict";
        $(document).on('click touchstart', function(e) {
            $('.win-loss-popup').removeClass('active');
        });
    </script>
@endpush
