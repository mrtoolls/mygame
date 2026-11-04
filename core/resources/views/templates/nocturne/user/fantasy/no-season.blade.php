@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account">
    @include('Template::user.fantasy.partials.nav')
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Fantasy Football')</span></div>
        <div class="ff-empty">
            <i class="ph-fill ph-soccer-ball"></i>
            <h2>@lang('No season is running yet')</h2>
            <p>@lang('Check back soon - a new Fantasy Football League season will kick off shortly.')</p>
        </div>
    </div>
</div>
@endsection

@push('style')
<style>
.ff-empty { display: grid; justify-items: center; gap: 12px; text-align: center; padding: 80px 20px; color: var(--nocturne-muted, #929abc); }
.ff-empty i { font-size: 48px; color: var(--nocturne-green, #00ef87); }
.ff-empty h2 { color: var(--nocturne-text, #f5f6ff); font-size: 20px; margin: 0; }
</style>
@endpush
