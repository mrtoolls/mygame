@php
    $positionOrder = ['GK' => 0, 'DEF' => 1, 'MID' => 2, 'FWD' => 3];
    $rowsByPosition = $starting->groupBy(fn ($p) => $p->player->position);
    $chips = ['deep_bench' => ['Deep Bench', 'Bench players also score this gameweek', 'ph-users-three'], 'super_captain' => ['Super Captain', 'Captain scores triple instead of double', 'ph-crown'], 'fresh_start' => ['Fresh Start', 'Unlimited free transfers for the rest of the season', 'ph-sparkle'], 'one_week_reset' => ['One-Week Reset', 'Unlimited transfers this gameweek only, squad reverts after', 'ph-arrow-counter-clockwise']];
    $usedChips = $team->chipUses()->pluck('chip_type')->all();
@endphp
@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account ff-page">
    @include('Template::user.fantasy.partials.nav')
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Fantasy Football')</span></div>

        <section class="ff-team-hero">
            <div>
                <span class="ff-team-kicker"><i class="ph-fill ph-soccer-ball"></i>@lang('FANTASY FOOTBALL') · @lang('GAMEWEEK') {{ $gameweek?->number ?? '—' }}</span>
                <h1>{{ $team->name }}</h1>
                <p>@lang('Your squad is ready. Make your changes before the deadline.')</p>
            </div>
            @if ($gameweek)
                <div class="ff-countdown" data-deadline="{{ $gameweek->deadline_at->toIso8601String() }}">
                    <span class="ff-countdown__label"><i class="ph-fill ph-clock"></i> @lang('DEADLINE')</span>
                    <span class="ff-countdown__clock">--:--:--</span>
                </div>
            @endif
        </section>

        <div class="ff-actions">
            <a href="{{ route('user.fantasy.transfers') }}" class="btn btn--gradient"><i class="ph-bold ph-repeat"></i>@lang('Transfers')</a>
            <span class="ff-team-stat"><small>@lang('TOTAL POINTS')</small><strong>{{ number_format($team->total_points) }}</strong></span>
            <span class="ff-team-stat"><small>@lang('OVERALL RANK')</small><strong>#{{ number_format($team->overall_rank ?? 0) }}</strong></span>
            <span class="ff-budget"><i class="ph-fill ph-coin"></i><strong>{{ number_format($team->budget_remaining, 1) }}</strong> @lang('ITB')</span>
            <span class="ff-free-transfers"><i class="ph-fill ph-arrows-left-right"></i><strong>{{ $team->free_transfers_banked }}</strong> @lang('free transfers')</span>
        </div>

        <form action="{{ route('user.fantasy.lineup.store') }}" method="POST" id="ff-lineup-form">
            @csrf
            <div class="ff-pitch">
                <div class="ff-pitch__heading"><span><i class="ph-fill ph-soccer-ball"></i>@lang('Starting XI')</span><small>{{ $starting->count() }} / 11</small></div>
                @foreach (['GK', 'DEF', 'MID', 'FWD'] as $position)
                    <div class="ff-pitch-row" data-position="{{ $position }}">
                        @foreach ($rowsByPosition->get($position, collect()) as $tp)
                            @include('Template::user.fantasy.partials.player-card', ['tp' => $tp])
                        @endforeach
                    </div>
                @endforeach
            </div>

            <div class="ff-bench">
                <div class="ff-section-heading"><div><span class="ff-section-kicker">@lang('SQUAD DEPTH')</span><h2>@lang('Bench')</h2></div><span class="ff-section-meta">{{ $bench->count() }} @lang('players')</span></div>
                <div class="ff-bench-strip">
                    @foreach ($bench as $tp)
                        @include('Template::user.fantasy.partials.player-card', ['tp' => $tp, 'benchCard' => true])
                    @endforeach
                </div>
            </div>

            <div class="ff-lineup-actions">
                <button type="submit" class="btn btn--gradient"><i class="ph-bold ph-check"></i>@lang('Save Lineup')</button>
                <small class="text-muted">@lang('Tap a player to swap starting/bench. Tap the C/V badge to set captain or vice-captain.')</small>
            </div>
        </form>

        <div class="ff-chips">
            <div class="ff-section-heading"><div><span class="ff-section-kicker">@lang('TACTICAL BOOSTS')</span><h2>@lang('Chips')</h2></div><span class="ff-section-meta">@lang('Use each chip strategically')</span></div>
            <div class="ff-chip-grid">
                @foreach ($chips as $key => [$label, $desc, $icon])
                    <form action="{{ route('user.fantasy.chip.activate') }}" method="POST" class="ff-chip-card {{ in_array($key, $usedChips) ? 'is-used' : '' }}">
                        @csrf
                        <input type="hidden" name="chip" value="{{ $key }}">
                        <span class="ff-chip-card__icon"><i class="ph-fill {{ $icon }}"></i></span>
                        <strong>{{ __($label) }}</strong>
                        <span>{{ __($desc) }}</span>
                        <button type="submit" class="btn btn--sm" @disabled(in_array($key, $usedChips))>
                            {{ in_array($key, $usedChips) ? __('Used') : __('Activate') }}
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@push('style')
<style>
.ff-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
.ff-header h1 { margin: 0; font-size: 22px; color: var(--nocturne-text, #f5f6ff); }
.ff-sub { margin: 4px 0 0; color: var(--nocturne-muted, #929abc); font-size: 13px; }
.ff-team-hero { position:relative; isolation:isolate; display:flex; align-items:center; justify-content:space-between; gap:18px; overflow:hidden; padding:23px 24px; margin-bottom:13px; border:1px solid #252d4b; border-radius:20px; background:radial-gradient(ellipse at 90% 0,rgba(0,239,135,.14),transparent 38%),linear-gradient(135deg,#171d38,#10152a 70%,#101d2b); box-shadow:0 18px 45px -32px rgba(0,0,0,.8); }
.ff-team-hero::after { content:''; position:absolute; z-index:-1; right:11%; top:-108px; width:180px; height:180px; border:1px solid rgba(0,239,135,.12); border-radius:50%; box-shadow:0 0 0 22px rgba(0,239,135,.025),0 0 0 44px rgba(0,239,135,.02); pointer-events:none; }
.ff-team-kicker { display:inline-flex; align-items:center; gap:6px; margin-bottom:6px; color:#00ef87; font-size:9px; letter-spacing:.11em; font-weight:800; }
.ff-team-hero h1 { margin:0 0 4px; color:#f5f6ff; font-size:clamp(21px,2vw,27px); line-height:1.15; letter-spacing:-.03em; font-weight:750; }
.ff-team-hero p { margin:0; color:#9aa5c9; font-size:12px; line-height:1.5; }
.ff-countdown { min-width:145px; text-align:center; background:rgba(8,14,29,.5); border:1px solid rgba(0,239,135,.22); border-radius:14px; padding:9px 13px; }
.ff-countdown__label { display:block; font-size:8px; letter-spacing:.09em; color:#929cbd; }
.ff-countdown__label i { color:#00ef87; }
.ff-countdown__clock { font-size:18px; font-weight:750; color:#00ef87; font-variant-numeric:tabular-nums; }
.ff-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:14px; padding:9px; border:1px solid #252d4b; border-radius:15px; background:#11162c; }
.ff-actions > .btn { min-height:42px; display:inline-flex; align-items:center; gap:7px; padding:9px 15px; border-radius:10px; font-size:11px; font-weight:700; }
.ff-team-stat,.ff-budget,.ff-free-transfers { display:inline-flex; align-items:center; gap:6px; min-height:40px; padding:7px 11px; border:1px solid rgba(135,151,198,.15); border-radius:10px; background:#0d1227; color:#b7c0dc; font-size:10px; white-space:nowrap; }
.ff-team-stat { display:grid; gap:1px; }
.ff-team-stat small { color:#8994b9; font-size:7px; letter-spacing:.07em; }
.ff-team-stat strong,.ff-budget strong,.ff-free-transfers strong { color:#00ef87; font-size:12px; font-weight:750; }
.ff-budget i { color:#00ef87; }
.ff-free-transfers i { color:#8e9abe; }
.ff-pitch { background:radial-gradient(ellipse at center,rgba(29,111,66,.85),transparent 78%),repeating-linear-gradient(0deg,rgba(255,255,255,.025) 0 1px,transparent 1px 54px),linear-gradient(180deg,#10442b,#0c3524); border:1px solid rgba(120,205,153,.2); border-radius:18px; padding:16px 9px 18px; display:grid; gap:13px; position:relative; overflow:hidden; box-shadow:inset 0 0 45px rgba(0,0,0,.12),0 14px 32px -24px rgba(0,0,0,.65); }
.ff-pitch::before { content:''; position:absolute; inset:10px; border:1px solid rgba(255,255,255,.16); border-radius:14px; pointer-events:none; }
.ff-pitch::after { content:''; position:absolute; top:50%; left:10px; right:10px; border-top:1px solid rgba(255,255,255,.12); pointer-events:none; }
.ff-pitch__heading { position:relative; z-index:1; display:flex; align-items:center; justify-content:space-between; padding:0 10px 10px; border-bottom:1px solid rgba(255,255,255,.13); color:#effff7; }
.ff-pitch__heading > span { display:inline-flex; align-items:center; gap:7px; font-size:11px; font-weight:700; }
.ff-pitch__heading i { color:#8fffc3; }
.ff-pitch__heading small { color:#b4d7c5; font-size:9px; }
.ff-pitch-row { position:relative; z-index:1; display:flex; justify-content:center; gap:10px; flex-wrap:wrap; }
.ff-pitch-row::before { content:attr(data-position); position:absolute; left:7px; top:50%; transform:translateY(-50%); color:rgba(233,255,242,.46); font-size:8px; font-weight:700; letter-spacing:.08em; }
.ff-bench { margin-top:17px; }
.ff-section-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:12px; margin:0 0 9px; }
.ff-section-heading h2 { margin:0; color:#f5f6ff; font-size:16px; line-height:1.2; font-weight:700; letter-spacing:-.02em; }
.ff-section-kicker { display:block; margin-bottom:4px; color:#00ef87; font-size:8px; font-weight:800; letter-spacing:.11em; }
.ff-section-meta { color:#8994b9; font-size:10px; text-align:right; }
.ff-bench-strip { display:flex; justify-content:center; gap:10px; flex-wrap:wrap; background:linear-gradient(145deg,#151b34,#101529); border:1px solid #252d4b; border-radius:14px; padding:12px; }
.ff-lineup-actions { display:flex; align-items:center; gap:12px; margin:14px 0 0; padding:12px; flex-wrap:wrap; border:1px solid #252d4b; border-radius:14px; background:#11162c; }
.ff-lineup-actions > .btn { min-height:42px; display:inline-flex; align-items:center; justify-content:center; gap:7px; padding:9px 17px; border-radius:10px; font-size:11px; font-weight:700; }
.ff-lineup-actions small { flex:1; color:#8994b9!important; font-size:10px; line-height:1.45; }
.ff-chips { margin-top:23px; }
.ff-chip-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:9px; }
.ff-chip-card { position:relative; display:grid; align-content:start; gap:7px; min-width:0; border:1px solid #252d4b; border-radius:14px; padding:13px; background:radial-gradient(ellipse at 100% 0,rgba(0,239,135,.08),transparent 48%),linear-gradient(145deg,#151b34,#101529); transition:border-color .15s,transform .15s; }
.ff-chip-card:hover { border-color:#39466e; transform:translateY(-1px); }
.ff-chip-card__icon { display:grid; place-items:center; width:34px; height:34px; border:1px solid rgba(0,239,135,.17); border-radius:11px; color:#00ef87!important; background:rgba(0,239,135,.08); font-size:17px!important; }
.ff-chip-card strong { color:#f2f4ff; font-size:12px; font-weight:700; }
.ff-chip-card > span:not(.ff-chip-card__icon) { color:#929cbd; font-size:10px; line-height:1.45; }
.ff-chip-card.is-used { opacity:.62; }
.ff-chip-card .btn--sm { margin-top:4px; min-height:38px; padding:7px 10px; font-size:10px; border-radius:9px; background:#00df80; color:#062b20; border:0; font-weight:700; }
.ff-chip-card .btn--sm:disabled { background: #2c3453; color: var(--nocturne-muted, #929abc); cursor: not-allowed; }
@media (max-width: 480px) {
    .ff-page .nocturne-account-content { padding-bottom:calc(112px + env(safe-area-inset-bottom)); }
    .ff-team-hero { align-items:flex-start; padding:18px 15px; border-radius:17px; }
    .ff-team-hero p { max-width:190px; font-size:10px; line-height:1.45; }
    .ff-countdown { min-width:104px; padding:8px; }
    .ff-countdown__clock { font-size:15px; }
    .ff-actions { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:6px; padding:7px; }
    .ff-actions > .btn { grid-column:1/-1; justify-content:center; min-height:44px; }
    .ff-team-stat,.ff-budget,.ff-free-transfers { justify-content:center; min-width:0; padding:6px 5px; font-size:9px; }
    .ff-pitch { padding:13px 5px; gap:12px; border-radius:15px; }
    .ff-pitch::before { inset:7px; border-radius:11px; }
    .ff-pitch-row { gap:4px; }
    .ff-pitch-row::before { left:1px; font-size:7px; }
    .ff-bench-strip { gap:4px; padding:8px 4px; }
    .ff-player-card { width:66px; padding:5px 2px; }
    .ff-player-name { max-width:64px; font-size:9px; }
    .ff-player-meta { font-size:8px; }
    .ff-pick-c,.ff-pick-v { width:20px; height:20px; }
    .ff-lineup-actions { align-items:stretch; }
    .ff-lineup-actions .btn { width:100%; min-height:44px; }
    .ff-lineup-actions small { text-align:center; font-size:10px; line-height:1.45; }
    .ff-chip-grid { grid-template-columns:repeat(2,minmax(0,1fr)); gap:7px; }
    .ff-chip-card { padding:11px; border-radius:12px; }
    .ff-chip-card strong { font-size:11px; }
    .ff-chip-card span { font-size:10px; line-height:1.4; }
    .ff-chip-card .btn--sm { min-height:38px; }
}
@media(max-width:360px) { .ff-team-hero { display:grid; grid-template-columns:1fr; }.ff-countdown { justify-self:start; }.ff-chip-grid { grid-template-columns:1fr; }.ff-pitch-row::before { position:static; transform:none; width:100%; text-align:center; margin-bottom:1px; } }
@media(hover:none) { .ff-chip-card:hover { transform:none; } }
@media(prefers-reduced-motion:reduce) { .ff-chip-card { transition:none; } }
</style>
@endpush

@push('script')
<script>
"use strict";
(function() {
    var el = document.querySelector('.ff-countdown');
    if (!el) return;
    var deadline = new Date(el.dataset.deadline).getTime();
    var clock = el.querySelector('.ff-countdown__clock');
    function tick() {
        var diff = deadline - Date.now();
        if (diff <= 0) { clock.textContent = "@lang('Locked')"; return; }
        var h = Math.floor(diff / 3600000), m = Math.floor((diff % 3600000) / 60000), s = Math.floor((diff % 60000) / 1000);
        var d = Math.floor(h / 24);
        clock.textContent = (d > 0 ? d + 'd ' : '') + String(h % 24).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        if (!document.hidden) requestAnimationFrame(function() { setTimeout(tick, 1000); });
    }
    tick();
    document.addEventListener('visibilitychange', function() { if (!document.hidden) tick(); });
})();
</script>
@endpush
