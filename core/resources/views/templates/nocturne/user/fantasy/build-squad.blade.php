@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account ff-page">
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Build Your Squad')</span></div>
        <div class="ff-header"><div><h1>@lang('Build Your Squad')</h1><p class="ff-sub">@lang('100.0 credit budget. 2 GK, 5 DEF, 5 MID, 3 FWD. Max 3 players per club.')</p></div></div>

        <form action="{{ route('user.fantasy.create.store') }}" method="POST" id="ff-build-form">
            @csrf
            <div class="ff-field">
                <label class="form--label">@lang('Team Name')</label>
                <input type="text" name="name" class="form--control" required maxlength="60" placeholder="@lang('e.g. Ironbridge Invincibles')">
            </div>

            <div class="ff-build-summary">
                <span>@lang('Budget'): <strong id="ff-budget-left">100.0</strong></span>
                <span>@lang('Selected'): <strong id="ff-count">0</strong>/15</span>
                <span id="ff-shape"></span>
            </div>

            <div class="ff-build-filters">
                <select id="ff-filter-position">
                    <option value="">@lang('All positions')</option>
                    <option value="GK">GK</option><option value="DEF">DEF</option><option value="MID">MID</option><option value="FWD">FWD</option>
                </select>
                <select id="ff-filter-club">
                    <option value="">@lang('All clubs')</option>
                    @foreach ($clubs as $club)<option value="{{ $club->id }}">{{ $club->name }}</option>@endforeach
                </select>
                <input type="search" id="ff-filter-search" placeholder="@lang('Search player...')">
            </div>

            <div class="ff-player-list">
                @foreach ($players as $player)
                    <label class="ff-pick-row" data-position="{{ $player->position }}" data-club="{{ $player->club_id }}" data-price="{{ $player->price }}" data-name="{{ strtolower($player->name) }}">
                        <input type="checkbox" name="player_ids[]" value="{{ $player->id }}" hidden>
                        <x-fantasy-shirt :primary="$player->club->color_primary" :secondary="$player->club->color_secondary" :pattern="$player->club->pattern" :size="28" />
                        <span class="ff-pick-row__name">{{ $player->name }}<small>{{ $player->club->short_code }} &middot; {{ $player->position }}</small></span>
                        <span class="ff-pick-row__price">{{ number_format($player->price, 1) }}</span>
                    </label>
                @endforeach
            </div>

            <button type="submit" class="btn btn--gradient w-100" id="ff-build-submit" disabled>@lang('Confirm Squad')</button>
        </form>
    </div>
</div>
@endsection

@push('style')
<style>
.ff-field { margin-bottom: 14px; }
.ff-field .form--control { width: 100%; background: #0d1023; border: 1px solid #2c3453; border-radius: 10px; padding: 10px 14px; color: #fff; }
.ff-build-summary { display: flex; gap: 16px; flex-wrap: wrap; color: var(--nocturne-text, #f5f6ff); font-size: 13px; margin-bottom: 12px; }
.ff-build-summary strong { color: var(--nocturne-green, #00ef87); }
.ff-build-filters { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
.ff-build-filters select, .ff-build-filters input { background: #0d1023; border: 1px solid #2c3453; border-radius: 8px; padding: 8px 10px; color: #fff; font-size: 13px; }
.ff-player-list { display: flex; flex-direction: column; gap: 6px; max-height: 60vh; overflow-y: auto; margin-bottom: 16px; }
.ff-pick-row { display: flex; align-items: center; gap: 10px; background: #0d1023; border: 1px solid #2c3453; border-radius: 10px; padding: 8px 12px; cursor: pointer; }
.ff-pick-row:has(input:checked) { border-color: var(--nocturne-green, #00ef87); background: rgba(0,239,135,.08); }
.ff-pick-row__name { flex: 1; font-size: 13px; color: #fff; display: flex; flex-direction: column; }
.ff-pick-row__name small { color: var(--nocturne-muted, #929abc); font-size: 11px; }
.ff-pick-row__price { font-weight: 800; color: var(--nocturne-green, #00ef87); font-size: 13px; }
</style>
@endpush

@push('script')
<script>
"use strict";
(function() {
    var form = document.getElementById('ff-build-form');
    var rows = Array.from(form.querySelectorAll('.ff-pick-row'));
    var budgetEl = document.getElementById('ff-budget-left');
    var countEl = document.getElementById('ff-count');
    var shapeEl = document.getElementById('ff-shape');
    var submitBtn = document.getElementById('ff-build-submit');
    var required = { GK: 2, DEF: 5, MID: 5, FWD: 3 };

    function recompute() {
        var selected = rows.filter(function(r) { return r.querySelector('input').checked; });
        var spent = selected.reduce(function(s, r) { return s + parseFloat(r.dataset.price); }, 0);
        var counts = { GK: 0, DEF: 0, MID: 0, FWD: 0 };
        var clubCounts = {};
        selected.forEach(function(r) { counts[r.dataset.position]++; clubCounts[r.dataset.club] = (clubCounts[r.dataset.club] || 0) + 1; });
        budgetEl.textContent = (100 - spent).toFixed(1);
        countEl.textContent = selected.length;
        var shapeOk = Object.keys(required).every(function(p) { return counts[p] <= required[p]; });
        var clubOk = Object.values(clubCounts).every(function(c) { return c <= 3; });
        var exact = Object.keys(required).every(function(p) { return counts[p] === required[p]; });
        shapeEl.textContent = 'GK ' + counts.GK + '/2  DEF ' + counts.DEF + '/5  MID ' + counts.MID + '/5  FWD ' + counts.FWD + '/3';
        submitBtn.disabled = !(exact && clubOk && spent <= 100 && selected.length === 15);
        rows.forEach(function(r) {
            var input = r.querySelector('input');
            if (!input.checked) {
                var wouldExceed = counts[r.dataset.position] >= required[r.dataset.position] || (clubCounts[r.dataset.club] || 0) >= 3;
                input.disabled = wouldExceed && !shapeOk ? false : wouldExceed;
            }
        });
    }
    form.addEventListener('change', function(e) { if (e.target.matches('input[type=checkbox]')) recompute(); });

    var posFilter = document.getElementById('ff-filter-position');
    var clubFilter = document.getElementById('ff-filter-club');
    var searchFilter = document.getElementById('ff-filter-search');
    function applyFilters() {
        var pos = posFilter.value, club = clubFilter.value, term = searchFilter.value.toLowerCase();
        rows.forEach(function(r) {
            var show = (!pos || r.dataset.position === pos) && (!club || r.dataset.club === club) && (!term || r.dataset.name.indexOf(term) !== -1);
            r.hidden = !show;
        });
    }
    [posFilter, clubFilter, searchFilter].forEach(function(el) { el.addEventListener('input', applyFilters); });

    recompute();
})();
</script>
@endpush
