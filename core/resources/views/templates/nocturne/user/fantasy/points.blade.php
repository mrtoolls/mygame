@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account ff-page">
    @include('Template::user.fantasy.partials.nav')
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><a href="{{ route('user.fantasy.team') }}">@lang('My Team')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Points')</span></div>
        <section class="ff-page-hero ff-points-hero">
            <div><span class="ff-page-kicker"><i class="ph-fill ph-chart-line-up"></i>@lang('SEASON PERFORMANCE')</span><h1>@lang('Your points')</h1><p>{{ $team->name }} · @lang('A clear view of your gameweek scores and player contributions.')</p></div>
            <div class="ff-points-total"><span>@lang('Total points')</span><strong>{{ number_format($team->total_points) }}</strong><small><i class="ph-fill ph-sparkle"></i> @lang('Season total')</small></div>
        </section>

        <div class="ff-gw-history">
            <table class="ff-table">
                <thead><tr><th>@lang('GW')</th><th>@lang('Points')</th><th>@lang('Bench')</th><th>@lang('Transfers')</th><th>@lang('Cost')</th><th>@lang('Chip')</th></tr></thead>
                <tbody>
                    @forelse ($history as $row)
                        <tr><td data-label="{{ __('Gameweek') }}"><span class="ff-gw-number">{{ $row->gameweek->number }}</span></td><td data-label="{{ __('Points') }}"><strong class="ff-history-points">{{ $row->points }}</strong></td><td data-label="{{ __('Bench') }}">{{ $row->bench_points }}</td><td data-label="{{ __('Transfers') }}">{{ $row->transfers_made }}</td><td data-label="{{ __('Cost') }}">{{ $row->transfer_cost ? '-' . $row->transfer_cost : '—' }}</td><td data-label="{{ __('Chip') }}">{{ $row->chip_used ? ucfirst(str_replace('_', ' ', $row->chip_used)) : '—' }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="text-muted text-center">@lang('No finalized gameweeks yet.')</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($breakdown->isNotEmpty())
            <div class="ff-section-heading"><div><span class="ff-page-kicker">@lang('PLAYER CONTRIBUTIONS')</span><h2>@lang('Latest gameweek')</h2></div><span class="ff-section-note">@lang('Points by player')</span></div>
            <div class="ff-breakdown-list">
                @foreach ($breakdown->sortByDesc('points') as $row)
                    <div class="ff-breakdown-row">
                        <span class="ff-breakdown-avatar"><i class="ph-fill ph-soccer-ball"></i></span>
                        <span class="ff-breakdown-name"><strong>{{ $row['player']->name }} @if ($row['captain'])<b class="ff-tag ff-tag--c">C</b>@endif @if ($row['vice'])<b class="ff-tag ff-tag--v">V</b>@endif</strong><small>{{ $row['player']->position }} · {{ $row['role'] === 'starting' ? __('Starting XI') : __('Bench') }} @if (!empty($row['minutes'])) · {{ $row['minutes'] }} @lang('min') @endif</small></span>
                        <span class="ff-breakdown-points">{{ $row['points'] }} <small>@lang('pts')</small></span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

@push('style')
<style>
.ff-page-hero { position:relative; isolation:isolate; display:flex; align-items:center; justify-content:space-between; gap:22px; overflow:hidden; padding:24px; margin-bottom:16px; border:1px solid #252d4b; border-radius:20px; background:radial-gradient(ellipse at 90% 0,rgba(0,239,135,.13),transparent 36%),linear-gradient(135deg,#171d38,#10152a 70%,#101d2b); }
.ff-page-hero h1 { margin:0 0 5px; color:#f5f6ff; font-size:clamp(22px,2.2vw,29px); line-height:1.15; letter-spacing:-.035em; font-weight:750; }
.ff-page-hero p { margin:0; max-width:620px; color:#9aa5c9; font-size:13px; line-height:1.55; }
.ff-page-kicker { display:inline-flex; align-items:center; gap:6px; margin-bottom:7px; color:#00ef87; font-size:9px; font-weight:800; letter-spacing:.12em; }
.ff-points-total { display:grid; gap:3px; min-width:150px; padding:12px 16px; border:1px solid rgba(0,239,135,.22); border-radius:15px; background:rgba(8,14,29,.52); }
.ff-points-total span,.ff-points-total small { color:#9ba6c9; font-size:10px; }
.ff-points-total strong { color:#00ef87; font-size:26px; line-height:1.1; font-variant-numeric:tabular-nums; }
.ff-points-total small i { color:#7ef6be; }
.ff-gw-history { overflow:hidden; margin-bottom:22px; border:1px solid #252d4b; border-radius:16px; background:#11162c; }
.ff-table { width:100%; border-collapse:collapse; background:transparent; }
.ff-table th,.ff-table td { padding:12px 14px; text-align:center; font-size:12px; color:#d8def2; border-bottom:1px solid rgba(135,151,198,.13); }
.ff-table th { color:#929cbd; font-size:9px; font-weight:700; letter-spacing:.09em; text-transform:uppercase; background:rgba(255,255,255,.025); }
.ff-table tbody tr:last-child td { border-bottom:0; }
.ff-gw-number { display:inline-grid; place-items:center; width:29px; height:29px; border-radius:9px; color:#00ef87; background:rgba(0,239,135,.1); font-weight:750; }
.ff-history-points { color:#00ef87; font-size:14px; }
.ff-section-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:12px; margin:22px 0 10px; }
.ff-section-heading h2 { margin:0; color:#f5f6ff; font-size:17px; font-weight:700; letter-spacing:-.02em; }
.ff-section-heading .ff-page-kicker { margin:0 0 4px; }
.ff-section-note { color:#8994b9; font-size:11px; }
.ff-breakdown-list { display:flex; flex-direction:column; gap:7px; }
.ff-breakdown-row { display:flex; align-items:center; gap:11px; min-width:0; padding:10px 13px; border:1px solid #252d4b; border-radius:13px; background:linear-gradient(110deg,#141a33,#101529); color:#fff; }
.ff-breakdown-avatar { display:grid; flex:0 0 36px; place-items:center; width:36px; height:36px; border-radius:11px; color:#00ef87; background:rgba(0,239,135,.09); font-size:17px; }
.ff-breakdown-name { display:grid; flex:1; min-width:0; gap:3px; }
.ff-breakdown-name strong { overflow:hidden; color:#f5f6ff; font-size:12px; font-weight:650; text-overflow:ellipsis; white-space:nowrap; }
.ff-breakdown-name small { color:#929cbd; font-size:10px; }
.ff-breakdown-points { color:#00ef87; font-size:15px; font-weight:750; white-space:nowrap; }
.ff-breakdown-points small { font-size:9px; font-weight:600; }
.ff-tag { display:inline-grid; place-items:center; width:16px; height:16px; border-radius:50%; font-size:9px; margin-left:4px; color:#073c2a; vertical-align:middle; }
.ff-tag--c { background:#00ef87; }.ff-tag--v { background:#ffd166; }
@media(max-width:700px) {
    .ff-page-hero { align-items:stretch; padding:18px 15px; border-radius:17px; gap:12px; }
    .ff-page-hero p { font-size:12px; }
    .ff-points-total { min-width:108px; padding:10px; align-content:center; }
    .ff-points-total strong { font-size:22px; }
    .ff-gw-history { overflow:visible; border:0; background:transparent; }
    .ff-table,.ff-table tbody { display:block; width:100%; }
    .ff-table thead { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; }
    .ff-table tbody { display:grid; gap:8px; }
    .ff-table tbody tr { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:0; overflow:hidden; border:1px solid #252d4b; border-radius:14px; background:linear-gradient(145deg,#151b34,#101529); }
    .ff-table td { display:grid; gap:5px; justify-items:center; padding:10px 6px; border:0; color:#dce1f3; font-size:11px; }
    .ff-table td[colspan] { grid-column:1/-1; }
    .ff-table td::before { content:attr(data-label); color:#8994b9; font-size:8px; letter-spacing:.06em; text-transform:uppercase; }
    .ff-table td:first-child { grid-column:span 1; }
    .ff-breakdown-row { gap:8px; padding:9px; }
    .ff-breakdown-avatar { flex-basis:32px; width:32px; height:32px; }
    .ff-breakdown-name strong { font-size:11px; }
    .ff-breakdown-points { font-size:14px; }
}
@media(max-width:380px) { .ff-page-hero { display:grid; grid-template-columns:1fr auto; } .ff-points-total { min-width:96px; } }
</style>
@endpush
