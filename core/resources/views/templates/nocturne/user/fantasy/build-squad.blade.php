@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account ff-page">
    @include('Template::user.fantasy.partials.nav')
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Build Your Squad')</span></div>

        <section class="ff-build-hero">
            <div class="ff-build-hero__glow"></div>
            <div class="ff-build-hero__copy">
                <span class="ff-build-hero__kicker"><i class="ph-fill ph-soccer-ball"></i>@lang('FANTASY FOOTBALL')</span>
                <h1>@lang('Build Your Squad')</h1>
                <p>@lang('Pick 15 players within your budget and get ready for kickoff.')</p>
            </div>
            <div class="ff-build-hero__rules">
                <span class="ff-rule-pill"><strong>100.0</strong>@lang('credits')</span>
                <span class="ff-rule-pill"><strong>2</strong>GK</span>
                <span class="ff-rule-pill"><strong>5</strong>DEF</span>
                <span class="ff-rule-pill"><strong>5</strong>MID</span>
                <span class="ff-rule-pill"><strong>3</strong>FWD</span>
                <span class="ff-rule-pill ff-rule-pill--muted"><i class="ph-bold ph-shield-warning"></i>@lang('Max 3 per club')</span>
            </div>
        </section>

        <form action="{{ route('user.fantasy.create.store') }}" method="POST" id="ff-build-form">
            @csrf

            <div class="ff-field">
                <label class="form--label">@lang('Team Name')</label>
                <div class="ff-team-name-control">
                    <i class="ph-bold ph-pencil-simple" aria-hidden="true"></i>
                    <input type="text" name="name" required maxlength="60" placeholder="@lang('e.g. Ironbridge Invincibles')">
                </div>
            </div>

            <div class="ff-summary-bar" id="ff-summary-bar">
                <div class="ff-summary-budget">
                    <span class="ff-summary-label">@lang('Budget Left')</span>
                    <strong id="ff-budget-left">100.0</strong>
                    <div class="ff-budget-track"><div class="ff-budget-fill" id="ff-budget-fill"></div></div>
                </div>
                <div class="ff-summary-positions" id="ff-shape">
                    <span class="ff-pos-chip ff-pos-chip--gk" data-pos="GK"><b>0</b>/2 <small>GK</small></span>
                    <span class="ff-pos-chip ff-pos-chip--def" data-pos="DEF"><b>0</b>/5 <small>DEF</small></span>
                    <span class="ff-pos-chip ff-pos-chip--mid" data-pos="MID"><b>0</b>/5 <small>MID</small></span>
                    <span class="ff-pos-chip ff-pos-chip--fwd" data-pos="FWD"><b>0</b>/3 <small>FWD</small></span>
                </div>
                <div class="ff-summary-count">
                    <span class="ff-summary-label">@lang('Selected')</span>
                    <strong><span id="ff-count">0</span>/15</strong>
                </div>
            </div>

            <div class="ff-build-filters">
                <div class="ff-position-pills" id="ff-position-pills">
                    <button type="button" class="ff-pos-pill is-active" data-pos="">@lang('All')</button>
                    <button type="button" class="ff-pos-pill ff-pos-pill--gk" data-pos="GK">GK</button>
                    <button type="button" class="ff-pos-pill ff-pos-pill--def" data-pos="DEF">DEF</button>
                    <button type="button" class="ff-pos-pill ff-pos-pill--mid" data-pos="MID">MID</button>
                    <button type="button" class="ff-pos-pill ff-pos-pill--fwd" data-pos="FWD">FWD</button>
                </div>
                <div class="ff-filter-row">
                    <div class="ff-select-wrap">
                        <i class="ph-bold ph-shield" aria-hidden="true"></i>
                        <select id="ff-filter-club">
                            <option value="">@lang('All clubs')</option>
                            @foreach ($clubs as $club)<option value="{{ $club->id }}">{{ $club->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="ff-search-wrap">
                        <i class="ph-bold ph-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" id="ff-filter-search" placeholder="@lang('Search player...')">
                    </div>
                </div>
            </div>

            <div class="ff-player-grid" id="ff-player-grid">
                @foreach ($players as $player)
                    <label class="ff-player-card" data-position="{{ $player->position }}" data-club="{{ $player->club_id }}" data-price="{{ $player->price }}" data-name="{{ strtolower($player->name) }}">
                        <input type="checkbox" name="player_ids[]" value="{{ $player->id }}" hidden>
                        <span class="ff-player-card__check"><i class="ph-bold ph-check"></i></span>
                        <span class="ff-player-card__pos ff-player-card__pos--{{ strtolower($player->position) }}">{{ $player->position }}</span>
                        <span class="ff-player-card__shirt"><x-fantasy-shirt :primary="$player->club->color_primary" :secondary="$player->club->color_secondary" :pattern="$player->club->pattern" :size="44" /></span>
                        <span class="ff-player-card__name">{{ $player->name }}</span>
                        <span class="ff-player-card__club">{{ $player->club->short_code }}</span>
                        <span class="ff-player-card__stats">
                            <span class="ff-player-card__rating"><i class="ph-fill ph-star"></i>{{ number_format($player->rating, 0) }}</span>
                            <span class="ff-player-card__price">{{ number_format($player->price, 1) }}</span>
                        </span>
                    </label>
                @endforeach
                <p class="ff-no-results d-none" id="ff-no-results">@lang('No players match your filters.')</p>
            </div>

            <div class="ff-confirm-spacer"></div>
            <div class="ff-confirm-bar">
                <div class="ff-confirm-bar__info">
                    <strong><span id="ff-count-2">0</span>/15</strong>
                    <span>@lang('Budget'): <b id="ff-budget-left-2">100.0</b></span>
                </div>
                <button type="submit" class="btn btn--gradient" id="ff-build-submit" disabled>@lang('Confirm Squad')</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('style')
<style>
.ff-build-hero {
    position: relative; overflow: hidden; border-radius: 20px; padding: 26px 24px;
    background: linear-gradient(135deg, #0d1023 0%, #10152c 60%, #0d1023 100%);
    border: 1px solid #242b4a; margin-bottom: 18px;
}
.ff-build-hero__glow {
    position: absolute; top: -60px; right: -60px; width: 220px; height: 220px; border-radius: 50%;
    background: radial-gradient(circle, rgba(0,239,135,.22), transparent 70%); pointer-events: none;
}
.ff-build-hero__copy { position: relative; }
.ff-build-hero__kicker {
    display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 800; letter-spacing: .08em;
    color: var(--nocturne-green, #00ef87); margin-bottom: 8px;
}
.ff-build-hero__copy h1 { margin: 0 0 6px; font-size: 26px; color: #fff; }
.ff-build-hero__copy p { margin: 0; color: var(--nocturne-muted, #929abc); font-size: 13.5px; }
.ff-build-hero__rules { position: relative; display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; }
.ff-rule-pill {
    display: inline-flex; align-items: center; gap: 5px; background: rgba(0,239,135,.1); border: 1px solid rgba(0,239,135,.28);
    color: #c9f9e2; font-size: 12px; font-weight: 700; padding: 6px 12px; border-radius: 999px;
}
.ff-rule-pill strong { color: var(--nocturne-green, #00ef87); font-size: 13px; }
.ff-rule-pill--muted { background: rgba(255,255,255,.04); border-color: #2c3453; color: var(--nocturne-muted, #929abc); }
.ff-rule-pill--muted i { color: #ffd166; }

.ff-field { margin-bottom: 16px; }
.ff-team-name-control {
    display: flex; align-items: center; gap: 10px; background: #0d1023; border: 1px solid #2c3453; border-radius: 13px;
    padding: 0 16px; min-height: 50px; transition: border-color .15s ease, box-shadow .15s ease;
}
.ff-team-name-control:focus-within { border-color: var(--nocturne-green, #00ef87); box-shadow: 0 0 0 3px rgba(0,239,135,.12); }
.ff-team-name-control i { color: var(--nocturne-muted, #929abc); font-size: 17px; }
.ff-team-name-control input { flex: 1; background: transparent; border: 0; color: #fff; font-size: 14px; height: 48px; outline: 0; min-width: 0; }

.ff-summary-bar {
    position: sticky; top: 8px; z-index: 5; display: flex; align-items: center; gap: 18px; flex-wrap: wrap;
    background: linear-gradient(180deg, #11162e, #0d1023); border: 1px solid #242b4a; border-radius: 16px;
    padding: 14px 18px; margin-bottom: 16px; box-shadow: 0 10px 30px -14px rgba(0,0,0,.6);
    backdrop-filter: blur(6px);
}
.ff-summary-label { display: block; font-size: 10.5px; color: var(--nocturne-muted, #929abc); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 2px; }
.ff-summary-budget { min-width: 130px; }
.ff-summary-budget strong { font-size: 19px; color: var(--nocturne-green, #00ef87); font-variant-numeric: tabular-nums; }
.ff-budget-track { width: 100%; max-width: 140px; height: 5px; border-radius: 999px; background: #1c2240; margin-top: 6px; overflow: hidden; }
.ff-budget-fill { height: 100%; width: 0%; background: linear-gradient(90deg, #00ef87, #00c2ff); border-radius: 999px; transition: width .25s ease; }
.ff-summary-positions { display: flex; gap: 6px; flex-wrap: wrap; flex: 1; justify-content: center; }
.ff-pos-chip {
    display: inline-flex; align-items: baseline; gap: 3px; font-size: 12px; font-weight: 700; color: #fff;
    background: #171c38; border: 1px solid #2c3453; border-radius: 999px; padding: 5px 10px; transition: border-color .15s ease, background .15s ease;
}
.ff-pos-chip small { color: var(--nocturne-muted, #929abc); font-weight: 800; font-size: 10px; margin-left: 2px; }
.ff-pos-chip.is-complete { border-color: var(--nocturne-green, #00ef87); background: rgba(0,239,135,.1); }
.ff-pos-chip.is-complete b { color: var(--nocturne-green, #00ef87); }
.ff-summary-count { text-align: right; min-width: 70px; }
.ff-summary-count strong { font-size: 19px; color: #fff; font-variant-numeric: tabular-nums; }

.ff-build-filters { margin-bottom: 16px; }
.ff-position-pills { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; margin-bottom: 10px; }
.ff-pos-pill {
    flex: 0 0 auto; background: #0d1023; border: 1px solid #2c3453; color: var(--nocturne-muted, #929abc);
    font-size: 12.5px; font-weight: 800; padding: 8px 16px; border-radius: 999px; cursor: pointer; transition: all .15s ease;
}
.ff-pos-pill.is-active { color: #073c2a; background: var(--nocturne-green, #00ef87); border-color: var(--nocturne-green, #00ef87); }
.ff-pos-pill--gk.is-active { background: #ffd166; border-color: #ffd166; }
.ff-pos-pill--def.is-active { background: #38bdf8; border-color: #38bdf8; }
.ff-pos-pill--fwd.is-active { background: #ff9a4d; border-color: #ff9a4d; }
.ff-filter-row { display: flex; gap: 10px; flex-wrap: wrap; }
.ff-select-wrap, .ff-search-wrap {
    display: flex; align-items: center; gap: 8px; background: #0d1023; border: 1px solid #2c3453; border-radius: 12px;
    padding: 0 14px; height: 46px; flex: 1; min-width: 160px;
}
.ff-select-wrap i, .ff-search-wrap i { color: var(--nocturne-muted, #929abc); font-size: 15px; }
.ff-select-wrap select, .ff-search-wrap input {
    flex: 1; background: transparent; border: 0; color: #fff; font-size: 13.5px; outline: 0; min-width: 0; height: 44px;
}
.ff-select-wrap select option { background: #0d1023; }

.ff-player-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; margin-bottom: 18px; }
.ff-player-card {
    position: relative; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 4px;
    background: linear-gradient(180deg, #11162e, #0d1023); border: 1px solid #242b4a; border-radius: 16px;
    padding: 16px 10px 12px; cursor: pointer; transition: transform .15s ease, border-color .15s ease, box-shadow .15s ease;
}
.ff-player-card:hover { transform: translateY(-2px); border-color: #3a4374; }
.ff-player-card:has(input:checked) { border-color: var(--nocturne-green, #00ef87); box-shadow: 0 0 0 3px rgba(0,239,135,.14), 0 10px 24px -12px rgba(0,239,135,.4); }
.ff-player-card:has(input:disabled):not(:has(input:checked)) { opacity: .35; cursor: not-allowed; pointer-events: none; }
.ff-player-card__check {
    position: absolute; top: 8px; right: 8px; width: 20px; height: 20px; border-radius: 50%; display: grid; place-items: center;
    background: var(--nocturne-green, #00ef87); color: #073c2a; font-size: 11px; opacity: 0; transform: scale(.6); transition: all .15s ease;
}
.ff-player-card:has(input:checked) .ff-player-card__check { opacity: 1; transform: scale(1); }
.ff-player-card__pos {
    position: absolute; top: 8px; left: 8px; font-size: 9.5px; font-weight: 800; padding: 2px 7px; border-radius: 6px; color: #0b0d1a;
}
.ff-player-card__pos--gk { background: #ffd166; }
.ff-player-card__pos--def { background: #38bdf8; }
.ff-player-card__pos--mid { background: var(--nocturne-green, #00ef87); }
.ff-player-card__pos--fwd { background: #ff9a4d; }
.ff-player-card__shirt { margin: 10px 0 6px; filter: drop-shadow(0 6px 10px rgba(0,0,0,.35)); }
.ff-player-card__name { font-size: 12.5px; font-weight: 800; color: #fff; line-height: 1.25; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ff-player-card__club { font-size: 10.5px; color: var(--nocturne-muted, #929abc); font-weight: 700; letter-spacing: .04em; }
.ff-player-card__stats { display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 6px; font-size: 11.5px; }
.ff-player-card__rating { display: inline-flex; align-items: center; gap: 3px; color: #ffd166; font-weight: 700; }
.ff-player-card__price { color: var(--nocturne-green, #00ef87); font-weight: 800; }
.ff-no-results { grid-column: 1 / -1; text-align: center; color: var(--nocturne-muted, #929abc); padding: 30px 0; }

.ff-confirm-spacer { height: 78px; }
.ff-confirm-bar {
    position: fixed; left: 0; right: 0; bottom: 0; z-index: 20; display: flex; align-items: center; justify-content: space-between;
    gap: 14px; background: rgba(13,16,35,.92); backdrop-filter: blur(10px); border-top: 1px solid #242b4a;
    padding: 12px max(16px, calc(env(safe-area-inset-left) + 16px)) calc(12px + env(safe-area-inset-bottom)) max(16px, calc(env(safe-area-inset-right) + 16px));
}
.ff-confirm-bar__info { display: flex; flex-direction: column; font-size: 12px; color: var(--nocturne-muted, #929abc); }
.ff-confirm-bar__info strong { font-size: 16px; color: #fff; }
.ff-confirm-bar .btn--gradient { padding: 12px 28px; border-radius: 13px; font-size: 14.5px; min-height: 48px; }
.ff-confirm-bar .btn--gradient:disabled { opacity: .4; }

@media (prefers-reduced-motion: reduce) {
    .ff-player-card, .ff-player-card__check, .ff-budget-fill, .ff-pos-pill { transition: none !important; }
    .ff-player-card:hover { transform: none; }
}
@media (max-width: 575.98px) {
    .ff-build-hero { padding: 20px 16px; border-radius: 16px; }
    .ff-build-hero__copy h1 { font-size: 21px; }
    .ff-summary-bar { top: 0; border-radius: 0 0 16px 16px; gap: 10px; padding: 12px 14px; }
    .ff-summary-positions { order: 3; width: 100%; justify-content: flex-start; }
    .ff-player-grid { grid-template-columns: repeat(auto-fill, minmax(128px, 1fr)); gap: 9px; }
    .ff-player-card { padding: 12px 6px 10px; }
}
</style>
@endpush

@push('script')
<script>
"use strict";
(function() {
    var form = document.getElementById('ff-build-form');
    var cards = Array.from(form.querySelectorAll('.ff-player-card'));
    var budgetEls = [document.getElementById('ff-budget-left'), document.getElementById('ff-budget-left-2')];
    var countEls = [document.getElementById('ff-count'), document.getElementById('ff-count-2')];
    var budgetFill = document.getElementById('ff-budget-fill');
    var submitBtn = document.getElementById('ff-build-submit');
    var required = { GK: 2, DEF: 5, MID: 5, FWD: 3 };

    function recompute() {
        var selected = cards.filter(function(c) { return c.querySelector('input').checked; });
        var spent = selected.reduce(function(s, c) { return s + parseFloat(c.dataset.price); }, 0);
        var counts = { GK: 0, DEF: 0, MID: 0, FWD: 0 };
        var clubCounts = {};
        selected.forEach(function(c) { counts[c.dataset.position]++; clubCounts[c.dataset.club] = (clubCounts[c.dataset.club] || 0) + 1; });

        var budgetLeft = (100 - spent);
        budgetEls.forEach(function(el) { el.textContent = budgetLeft.toFixed(1); });
        countEls.forEach(function(el) { el.textContent = selected.length; });
        budgetFill.style.width = Math.min(100, (spent / 100) * 100) + '%';
        budgetFill.style.background = budgetLeft < 0 ? '#ff5c7a' : '';

        document.querySelectorAll('.ff-pos-chip').forEach(function(chip) {
            var pos = chip.dataset.pos;
            chip.querySelector('b').textContent = counts[pos];
            chip.classList.toggle('is-complete', counts[pos] === required[pos]);
        });

        var shapeOk = Object.keys(required).every(function(p) { return counts[p] <= required[p]; });
        var clubOk = Object.values(clubCounts).every(function(c) { return c <= 3; });
        var exact = Object.keys(required).every(function(p) { return counts[p] === required[p]; });
        submitBtn.disabled = !(exact && clubOk && spent <= 100 && selected.length === 15);

        cards.forEach(function(c) {
            var input = c.querySelector('input');
            if (!input.checked) {
                var wouldExceed = counts[c.dataset.position] >= required[c.dataset.position] || (clubCounts[c.dataset.club] || 0) >= 3;
                input.disabled = wouldExceed;
            }
        });
    }
    form.addEventListener('change', function(e) { if (e.target.matches('input[type=checkbox]')) recompute(); });

    var activePos = '';
    var posPills = Array.from(document.querySelectorAll('.ff-pos-pill'));
    var clubFilter = document.getElementById('ff-filter-club');
    var searchFilter = document.getElementById('ff-filter-search');
    var noResults = document.getElementById('ff-no-results');

    function applyFilters() {
        var club = clubFilter.value, term = searchFilter.value.toLowerCase();
        var visible = 0;
        cards.forEach(function(c) {
            var show = (!activePos || c.dataset.position === activePos) && (!club || c.dataset.club === club) && (!term || c.dataset.name.indexOf(term) !== -1);
            c.hidden = !show;
            if (show) visible++;
        });
        noResults.classList.toggle('d-none', visible !== 0);
    }
    posPills.forEach(function(pill) {
        pill.addEventListener('click', function() {
            activePos = pill.dataset.pos;
            posPills.forEach(function(p) { p.classList.toggle('is-active', p === pill); });
            applyFilters();
        });
    });
    [clubFilter, searchFilter].forEach(function(el) { el.addEventListener('input', applyFilters); });

    recompute();
})();
</script>
@endpush
