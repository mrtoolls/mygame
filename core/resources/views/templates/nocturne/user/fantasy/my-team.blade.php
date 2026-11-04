@php
    $positionOrder = ['GK' => 0, 'DEF' => 1, 'MID' => 2, 'FWD' => 3];
    $rowsByPosition = $starting->groupBy(fn ($p) => $p->player->position);
    $chips = ['deep_bench' => ['Deep Bench', 'Bench players also score this gameweek'], 'super_captain' => ['Super Captain', 'Captain scores triple instead of double'], 'fresh_start' => ['Fresh Start', 'Unlimited free transfers for the rest of the season'], 'one_week_reset' => ['One-Week Reset', 'Unlimited transfers this gameweek only, squad reverts after']];
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
                @foreach (['GK', 'DEF', 'MID', 'FWD'] as $position)
                    <div class="ff-pitch-row">
                        @foreach ($rowsByPosition->get($position, collect()) as $tp)
                            @include('Template::user.fantasy.partials.player-card', ['tp' => $tp])
                        @endforeach
                    </div>
                @endforeach
            </div>

            <div class="ff-bench">
                <h3>@lang('Bench')</h3>
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
            <h3>@lang('Chips')</h3>
            <div class="ff-chip-grid">
                @foreach ($chips as $key => [$label, $desc])
                    <form action="{{ route('user.fantasy.chip.activate') }}" method="POST" class="ff-chip-card {{ in_array($key, $usedChips) ? 'is-used' : '' }}">
                        @csrf
                        <input type="hidden" name="chip" value="{{ $key }}">
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
.ff-team-hero { position:relative; isolation:isolate; display:flex; align-items:center; justify-content:space-between; gap:18px; overflow:hidden; padding:23px 24px; margin-bottom:13px; border:1px solid #252d4b; border-radius:20px; background:radial-gradient(ellipse at 90% 0,rgba(0,239,135,.14),transparent 38%),linear-gradient(135deg,#171d38,#10152a 70%,#101d2b); }
.ff-team-kicker { display:inline-flex; align-items:center; gap:6px; margin-bottom:6px; color:#00ef87; font-size:9px; letter-spacing:.11em; font-weight:800; }
.ff-team-hero h1 { margin:0 0 4px; color:#f5f6ff; font-size:clamp(21px,2vw,27px); line-height:1.15; letter-spacing:-.03em; font-weight:750; }
.ff-team-hero p { margin:0; color:#9aa5c9; font-size:12px; }
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
.ff-pitch { background:radial-gradient(ellipse at center,rgba(29,111,66,.85),transparent 78%),repeating-linear-gradient(0deg,rgba(255,255,255,.025) 0 1px,transparent 1px 54px),linear-gradient(180deg,#10442b,#0c3524); border:1px solid rgba(120,205,153,.2); border-radius:18px; padding:18px 8px; display:grid; gap:15px; position:relative; overflow:hidden; box-shadow:inset 0 0 45px rgba(0,0,0,.12); }
.ff-pitch::before { content:''; position:absolute; inset:10px; border:1px solid rgba(255,255,255,.16); border-radius:14px; pointer-events:none; }
.ff-pitch::after { content:''; position:absolute; top:50%; left:10px; right:10px; border-top:1px solid rgba(255,255,255,.12); pointer-events:none; }
.ff-pitch-row { position:relative; z-index:1; display:flex; justify-content:center; gap:10px; flex-wrap:wrap; }
.ff-bench { margin-top:16px; }
.ff-bench h3, .ff-chips h3 { color: var(--nocturne-text, #f5f6ff); font-size: 15px; margin: 0 0 10px; }
.ff-bench-strip { display:flex; justify-content:center; gap:10px; flex-wrap:wrap; background:linear-gradient(145deg,#151b34,#101529); border:1px solid #252d4b; border-radius:14px; padding:12px; }
.ff-lineup-actions { display: flex; align-items: center; gap: 12px; margin: 16px 0; flex-wrap: wrap; }
.ff-chips { margin-top: 24px; }
.ff-chip-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; }
.ff-chip-card { display: grid; gap: 6px; background: #0d1023; border: 1px solid #2c3453; border-radius: 14px; padding: 14px; }
.ff-chip-card strong { color: var(--nocturne-text, #f5f6ff); font-size: 13px; }
.ff-chip-card span { color: var(--nocturne-muted, #929abc); font-size: 11.5px; }
.ff-chip-card.is-used { opacity: .5; }
.ff-chip-card .btn--sm { margin-top: 4px; padding: 6px 10px; font-size: 12px; border-radius: 8px; background: var(--nocturne-green, #00ef87); color: #073c2a; border: 0; font-weight: 800; }
.ff-chip-card .btn--sm:disabled { background: #2c3453; color: var(--nocturne-muted, #929abc); cursor: not-allowed; }
@media (max-width: 480px) {
    .ff-team-hero { align-items:flex-start; padding:18px 15px; border-radius:17px; }
    .ff-team-hero p { max-width:190px; font-size:10px; line-height:1.45; }
    .ff-countdown { min-width:104px; padding:8px; }
    .ff-countdown__clock { font-size:15px; }
    .ff-actions { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:6px; padding:7px; }
    .ff-actions > .btn { grid-column:1/-1; justify-content:center; min-height:44px; }
    .ff-team-stat,.ff-budget,.ff-free-transfers { justify-content:center; min-width:0; padding:6px 5px; font-size:9px; }
    .ff-pitch { padding:15px 5px; gap:13px; border-radius:15px; }
    .ff-pitch::before { inset:7px; border-radius:11px; }
    .ff-pitch-row { gap:4px; }
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
@media(max-width:360px) { .ff-team-hero { display:grid; grid-template-columns:1fr; }.ff-countdown { justify-self:start; }.ff-chip-grid { grid-template-columns:1fr; } }
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
