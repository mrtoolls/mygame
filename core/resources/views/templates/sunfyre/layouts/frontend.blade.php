@extends('Template::layouts.app')
@section('app')
    @include('Template::partials.header')

    @yield('content')

    @include('Template::partials.footer')
@endsection
