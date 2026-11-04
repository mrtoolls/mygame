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

        <div class="ff-header">
            <div>
                <h1>{{ $team->name }}</h1>
                <p class="ff-sub">@lang('Gameweek') {{ $gameweek?->number ?? '-' }} &middot; {{ number_format($team->total_points) }} @lang('pts') &middot; @lang('Rank') #{{ number_format($team->overall_rank ?? 0) }}</p>
            </div>
            @if ($gameweek)
                <div class="ff-countdown" data-deadline="{{ $gameweek->deadline_at->toIso8601String() }}">
                    <span class="ff-countdown__label">@lang('Deadline')</span>
                    <span class="ff-countdown__clock">--:--:--</span>
                </div>
            @endif
        </div>

        <div class="ff-actions">
            <a href="{{ route('user.fantasy.transfers') }}" class="btn btn--gradient"><i class="ph-bold ph-repeat"></i>@lang('Transfers')</a>
            <span class="ff-budget"><i class="ph-fill ph-coin"></i>{{ number_format($team->budget_remaining, 1) }} @lang('ITB')</span>
            <span class="ff-free-transfers">{{ $team->free_transfers_banked }} @lang('free transfers')</span>
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
.ff-countdown { text-align: right; background: #0d1023; border: 1px solid #2c3453; border-radius: 12px; padding: 8px 14px; }
.ff-countdown__label { display: block; font-size: 11px; color: var(--nocturne-muted, #929abc); }
.ff-countdown__clock { font-size: 18px; font-weight: 800; color: var(--nocturne-green, #00ef87); font-variant-numeric: tabular-nums; }
.ff-actions { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 18px; }
.ff-budget, .ff-free-transfers { background: #0d1023; border: 1px solid #2c3453; border-radius: 10px; padding: 8px 14px; font-size: 13px; color: var(--nocturne-text, #f5f6ff); display: inline-flex; align-items: center; gap: 6px; }
.ff-pitch { background: linear-gradient(180deg, #0b3a24, #0f4a2e); border-radius: 18px; padding: 18px 8px; display: grid; gap: 18px; position: relative; }
.ff-pitch::before { content: ''; position: absolute; inset: 10px; border: 1px solid rgba(255,255,255,.15); border-radius: 14px; pointer-events: none; }
.ff-pitch-row { display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; }
.ff-bench { margin-top: 20px; }
.ff-bench h3, .ff-chips h3 { color: var(--nocturne-text, #f5f6ff); font-size: 15px; margin: 0 0 10px; }
.ff-bench-strip { display: flex; gap: 10px; flex-wrap: wrap; background: #0d1023; border: 1px solid #2c3453; border-radius: 14px; padding: 14px; }
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
    .ff-pitch-row { gap: 6px; }
    .ff-header h1 { font-size: 18px; }
}
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
