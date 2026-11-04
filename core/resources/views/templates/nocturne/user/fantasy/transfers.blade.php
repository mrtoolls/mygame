@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account ff-page">
    @include('Template::user.fantasy.partials.nav')
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><a href="{{ route('user.fantasy.team') }}">@lang('My Team')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Transfers')</span></div>
        <div class="ff-header"><div><h1>@lang('Transfers')</h1><p class="ff-sub">@lang('Budget'): {{ number_format($team->budget_remaining, 1) }} &middot; {{ $team->free_transfers_banked }} @lang('free transfers banked')</p></div></div>

        <form action="{{ route('user.fantasy.transfers.store') }}" method="POST" id="ff-transfer-form">
            @csrf
            <div class="ff-transfer-grid">
                <div>
                    <h3>@lang('Your Squad')</h3>
                    <div class="ff-player-list">
                        @foreach ($team->players as $tp)
                            <label class="ff-pick-row ff-out-row" data-player="{{ $tp->player_id }}" data-position="{{ $tp->player->position }}" data-sell="{{ $tp->sellingPrice() }}">
                                <input type="checkbox" class="ff-out-check" hidden>
                                <x-fantasy-shirt :primary="$tp->player->club->color_primary" :secondary="$tp->player->club->color_secondary" :pattern="$tp->player->club->pattern" :size="28" />
                                <span class="ff-pick-row__name">{{ $tp->player->name }}<small>{{ $tp->player->club->short_code }} &middot; {{ $tp->player->position }}</small></span>
                                <span class="ff-pick-row__price">{{ number_format($tp->sellingPrice(), 1) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <h3>@lang('Available Players')</h3>
                    <div class="ff-build-filters">
                        <select id="ff-filter-position"><option value="">@lang('All positions')</option><option value="GK">GK</option><option value="DEF">DEF</option><option value="MID">MID</option><option value="FWD">FWD</option></select>
                        <input type="search" id="ff-filter-search" placeholder="@lang('Search player...')">
                    </div>
                    <div class="ff-player-list" id="ff-in-list">
                        @foreach ($available as $player)
                            <label class="ff-pick-row ff-in-row" data-player="{{ $player->id }}" data-position="{{ $player->position }}" data-club="{{ $player->club_id }}" data-price="{{ $player->price }}" data-name="{{ strtolower($player->name) }}">
                                <input type="checkbox" class="ff-in-check" hidden>
                                <x-fantasy-shirt :primary="$player->club->color_primary" :secondary="$player->club->color_secondary" :pattern="$player->club->pattern" :size="28" />
                                <span class="ff-pick-row__name">{{ $player->name }}<small>{{ $player->club->short_code }} &middot; {{ $player->position }}</small></span>
                                <span class="ff-pick-row__price">{{ number_format($player->price, 1) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="ff-transfer-summary">
                <span>@lang('Transfers'): <strong id="ff-t-count">0</strong></span>
                <span>@lang('Cost'): <strong id="ff-t-cost">0 pts</strong></span>
                <span>@lang('Budget after'): <strong id="ff-t-budget">{{ number_format($team->budget_remaining, 1) }}</strong></span>
            </div>
            <div id="ff-hidden-inputs"></div>
            <button type="submit" class="btn btn--gradient" id="ff-transfer-submit" disabled>@lang('Confirm Transfers')</button>
        </form>
    </div>
</div>
@endsection

@push('style')
<style>
.ff-transfer-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.ff-transfer-grid h3 { color: var(--nocturne-text, #f5f6ff); font-size: 14px; margin: 0 0 8px; }
.ff-transfer-summary { display: flex; gap: 16px; flex-wrap: wrap; margin: 14px 0; color: var(--nocturne-text, #f5f6ff); font-size: 13px; }
.ff-transfer-summary strong { color: var(--nocturne-green, #00ef87); }
.ff-out-row:has(input:checked), .ff-in-row:has(input:checked) { border-color: #ff9a4d; background: rgba(255,154,77,.08); }
@media (max-width: 700px) { .ff-transfer-grid { grid-template-columns: 1fr; } }
</style>
@endpush

@push('script')
<script>
"use strict";
(function() {
    var form = document.getElementById('ff-transfer-form');
    var freeTransfers = {{ $team->fresh_start_active ? 999 : $team->free_transfers_banked }};
    var budget = {{ (float) $team->budget_remaining }};

    function selectedOut() { return Array.from(form.querySelectorAll('.ff-out-row')).filter(function(r) { return r.querySelector('input').checked; }); }
    function selectedIn() { return Array.from(form.querySelectorAll('.ff-in-row')).filter(function(r) { return r.querySelector('input').checked; }); }

    function recompute() {
        var outs = selectedOut(), ins = selectedIn();
        var n = Math.min(outs.length, ins.length);
        var sellTotal = outs.reduce(function(s, r) { return s + parseFloat(r.dataset.sell); }, 0);
        var buyTotal = ins.reduce(function(s, r) { return s + parseFloat(r.dataset.price); }, 0);
        var cost = Math.max(0, outs.length - freeTransfers) * 4;
        document.getElementById('ff-t-count').textContent = n;
        document.getElementById('ff-t-cost').textContent = cost + ' pts';
        document.getElementById('ff-t-budget').textContent = (budget + sellTotal - buyTotal).toFixed(1);
        document.getElementById('ff-transfer-submit').disabled = !(n > 0 && outs.length === ins.length);

        var hidden = document.getElementById('ff-hidden-inputs');
        hidden.innerHTML = '';
        outs.forEach(function(r, i) {
            if (!ins[i]) return;
            hidden.insertAdjacentHTML('beforeend', '<input type="hidden" name="out[]" value="' + r.dataset.player + '"><input type="hidden" name="in[]" value="' + ins[i].dataset.player + '">');
        });
    }
    form.addEventListener('change', function(e) { if (e.target.type === 'checkbox') recompute(); });

    var posFilter = document.getElementById('ff-filter-position');
    var searchFilter = document.getElementById('ff-filter-search');
    function applyFilters() {
        var pos = posFilter.value, term = searchFilter.value.toLowerCase();
        document.querySelectorAll('.ff-in-row').forEach(function(r) {
            r.hidden = (pos && r.dataset.position !== pos) || (term && r.dataset.name.indexOf(term) === -1);
        });
    }
    [posFilter, searchFilter].forEach(function(el) { el.addEventListener('input', applyFilters); });
    recompute();
})();
</script>
@endpush
