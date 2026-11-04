@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account ff-page">
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><a href="{{ route('user.fantasy.team') }}">@lang('My Team')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Points')</span></div>
        <div class="ff-header"><div><h1>@lang('Points')</h1><p class="ff-sub">{{ $team->name }} &middot; {{ number_format($team->total_points) }} @lang('total points')</p></div></div>

        <div class="ff-gw-history">
            <table class="ff-table">
                <thead><tr><th>@lang('GW')</th><th>@lang('Points')</th><th>@lang('Bench')</th><th>@lang('Transfers')</th><th>@lang('Cost')</th><th>@lang('Chip')</th></tr></thead>
                <tbody>
                    @forelse ($history as $row)
                        <tr><td>{{ $row->gameweek->number }}</td><td><strong>{{ $row->points }}</strong></td><td>{{ $row->bench_points }}</td><td>{{ $row->transfers_made }}</td><td>{{ $row->transfer_cost ? '-' . $row->transfer_cost : '-' }}</td><td>{{ $row->chip_used ? ucfirst(str_replace('_', ' ', $row->chip_used)) : '-' }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="text-muted text-center">@lang('No finalized gameweeks yet.')</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($breakdown->isNotEmpty())
            <h3 class="ff-section-title">@lang('Latest Gameweek Breakdown')</h3>
            <div class="ff-breakdown-list">
                @foreach ($breakdown->sortByDesc('points') as $row)
                    <div class="ff-breakdown-row">
                        <span class="ff-breakdown-name">{{ $row['player']->name }} @if ($row['captain'])<b class="ff-tag ff-tag--c">C</b>@endif @if ($row['vice'])<b class="ff-tag ff-tag--v">V</b>@endif</span>
                        <span class="ff-breakdown-role">{{ $row['role'] === 'starting' ? __('Starting') : __('Bench') }}</span>
                        <span class="ff-breakdown-points">{{ $row['points'] }} pts</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

@push('style')
<style>
.ff-table { width: 100%; border-collapse: collapse; background: #0d1023; border: 1px solid #2c3453; border-radius: 12px; overflow: hidden; }
.ff-table th, .ff-table td { padding: 10px 12px; text-align: center; font-size: 13px; color: var(--nocturne-text, #f5f6ff); border-bottom: 1px solid #2c3453; }
.ff-table th { color: var(--nocturne-muted, #929abc); font-weight: 700; font-size: 11px; text-transform: uppercase; }
.ff-section-title { color: var(--nocturne-text, #f5f6ff); font-size: 15px; margin: 22px 0 10px; }
.ff-breakdown-list { display: flex; flex-direction: column; gap: 6px; }
.ff-breakdown-row { display: flex; align-items: center; gap: 10px; background: #0d1023; border: 1px solid #2c3453; border-radius: 10px; padding: 8px 12px; font-size: 13px; color: #fff; }
.ff-breakdown-name { flex: 1; }
.ff-breakdown-role { color: var(--nocturne-muted, #929abc); font-size: 11px; }
.ff-breakdown-points { font-weight: 800; color: var(--nocturne-green, #00ef87); }
.ff-tag { display: inline-grid; place-items: center; width: 16px; height: 16px; border-radius: 50%; font-size: 9px; margin-left: 4px; color: #073c2a; }
.ff-tag--c { background: var(--nocturne-green, #00ef87); }
.ff-tag--v { background: #ffd166; }
</style>
@endpush
