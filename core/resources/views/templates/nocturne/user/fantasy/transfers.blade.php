@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account ff-page">
    @include('Template::user.fantasy.partials.nav')
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><a href="{{ route('user.fantasy.team') }}">@lang('My Team')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Transfers')</span></div>

        <section class="ff-build-hero ff-transfer-hero">
            <div class="ff-build-hero__glow"></div>
            <div class="ff-build-hero__copy">
                <span class="ff-build-hero__kicker"><i class="ph-fill ph-arrows-left-right"></i>@lang('TRANSFERS')</span>
                <h1>@lang('Swap Your Squad')</h1>
                <p>@lang('Tap a player to sell, then tap a replacement of the same position.')</p>
            </div>
            <div class="ff-build-hero__rules">
                <span class="ff-rule-pill"><strong id="ff-t-budget">{{ number_format($team->budget_remaining, 1) }}</strong>@lang('budget')</span>
                <span class="ff-rule-pill"><strong id="ff-t-free">{{ $team->fresh_start_active ? '∞' : $team->free_transfers_banked }}</strong>@lang('free transfers')</span>
                <span class="ff-rule-pill ff-rule-pill--muted"><i class="ph-bold ph-coin"></i>@lang('4 pts per extra transfer')</span>
            </div>
        </section>

        <form action="{{ route('user.fantasy.transfers.store') }}" method="POST" id="ff-transfer-form">
            @csrf

            <div class="ff-transfer-summary-bar">
                <div class="ff-transfer-stat"><span>@lang('Transfers')</span><b id="ff-t-count">0</b></div>
                <div class="ff-transfer-stat"><span>@lang('Point cost')</span><b id="ff-t-cost">0</b></div>
                <div class="ff-transfer-stat"><span>@lang('Budget after')</span><b id="ff-t-budget-after">{{ number_format($team->budget_remaining, 1) }}</b></div>
            </div>

            <div class="ff-transfer-grid">
                <div class="ff-transfer-col">
                    <h3><i class="ph-bold ph-sign-out"></i>@lang('Your Squad')<span class="ff-transfer-col__hint">@lang('tap to sell')</span></h3>
                    <div class="ff-transfer-list">
                        @foreach ($team->players as $tp)
                            <label class="ff-transfer-row ff-out-row" data-player="{{ $tp->player_id }}" data-position="{{ $tp->player->position }}" data-club="{{ $tp->player->club_id }}" data-sell="{{ $tp->sellingPrice() }}" data-name="{{ $tp->player->name }}">
                                <input type="checkbox" class="ff-out-check" hidden>
                                <x-fantasy-shirt :primary="$tp->player->club->color_primary" :secondary="$tp->player->club->color_secondary" :pattern="$tp->player->club->pattern" :size="30" />
                                <span class="ff-transfer-row__info">
                                    <strong>{{ $tp->player->name }}</strong>
                                    <small>{{ $tp->player->club->short_code }} &middot; {{ $tp->player->position }}</small>
                                </span>
                                <span class="ff-transfer-row__price">{{ number_format($tp->sellingPrice(), 1) }}</span>
                                <span class="ff-transfer-row__check"><i class="ph-bold ph-check"></i></span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="ff-transfer-swap-icon"><i class="ph-bold ph-arrows-left-right"></i></div>
                <div class="ff-transfer-col">
                    <h3><i class="ph-bold ph-sign-in"></i>@lang('Available Players')<span class="ff-transfer-col__hint" id="ff-in-hint">@lang('select an outgoing player first')</span></h3>
                    <div class="ff-transfer-filters">
                        <div class="ff-position-pills ff-position-pills--compact" id="ff-in-pos-pills">
                            <button type="button" class="ff-pos-pill is-active" data-pos="">@lang('All')</button>
                            <button type="button" class="ff-pos-pill ff-pos-pill--gk" data-pos="GK">GK</button>
                            <button type="button" class="ff-pos-pill ff-pos-pill--def" data-pos="DEF">DEF</button>
                            <button type="button" class="ff-pos-pill ff-pos-pill--mid" data-pos="MID">MID</button>
                            <button type="button" class="ff-pos-pill ff-pos-pill--fwd" data-pos="FWD">FWD</button>
                        </div>
                        <div class="ff-search-wrap">
                            <i class="ph-bold ph-magnifying-glass" aria-hidden="true"></i>
                            <input type="search" id="ff-filter-search" placeholder="@lang('Search player...')">
                        </div>
                    </div>
                    <div class="ff-transfer-list" id="ff-in-list">
                        @foreach ($available as $player)
                            <label class="ff-transfer-row ff-in-row" data-player="{{ $player->id }}" data-position="{{ $player->position }}" data-club="{{ $player->club_id }}" data-price="{{ $player->price }}" data-name="{{ strtolower($player->name) }}">
                                <input type="checkbox" class="ff-in-check" hidden>
                                <x-fantasy-shirt :primary="$player->club->color_primary" :secondary="$player->club->color_secondary" :pattern="$player->club->pattern" :size="30" />
                                <span class="ff-transfer-row__info">
                                    <strong>{{ $player->name }}</strong>
                                    <small>{{ $player->club->short_code }} &middot; {{ $player->position }}</small>
                                </span>
                                <span class="ff-transfer-row__price">{{ number_format($player->price, 1) }}</span>
                                <span class="ff-transfer-row__check"><i class="ph-bold ph-check"></i></span>
                            </label>
                        @endforeach
                        <p class="ff-no-results d-none" id="ff-no-results">@lang('No players match your filters.')</p>
                    </div>
                </div>
            </div>

            <div id="ff-hidden-inputs"></div>
            <div class="ff-confirm-spacer"></div>
            <div class="ff-confirm-bar">
                <div class="ff-confirm-bar__info">
                    <strong><span id="ff-t-count-2">0</span> @lang('transfers')</strong>
                    <span>@lang('Cost'): <b id="ff-t-cost-2">0 pts</b></span>
                </div>
                <button type="button" class="btn btn--gradient" id="ff-review-btn" disabled>@lang('Review Transfers')</button>
            </div>
        </form>
    </div>
</div>

<div class="ff-sheet-overlay d-none" id="ff-review-overlay">
    <div class="ff-sheet" id="ff-review-sheet" role="dialog" aria-modal="true" aria-label="@lang('Confirm transfers')">
        <div class="ff-sheet__handle"></div>
        <div class="ff-sheet__head">
            <h3>@lang('Confirm Transfers')</h3>
            <button type="button" class="ff-sheet__close" id="ff-review-close" aria-label="@lang('Close')"><i class="ph-bold ph-x"></i></button>
        </div>
        <div class="ff-sheet__body">
            <div id="ff-review-pairs"></div>
            <div class="ff-review-totals" id="ff-review-totals"></div>
        </div>
        <div class="ff-sheet__foot">
            <button type="button" class="btn btn--sm btn--outline-gradient" id="ff-review-back">@lang('Back')</button>
            <button type="submit" form="ff-transfer-form" class="btn btn--sm btn--gradient" id="ff-review-confirm">@lang('Confirm & Save')</button>
        </div>
    </div>
</div>
@endsection

@push('style')
<style>
.ff-transfer-hero .ff-build-hero__copy h1 { font-size: 22px; }
.ff-transfer-summary-bar {
    display: flex; gap: 12px; background: linear-gradient(145deg, #121732, #0b0e1f); border: 1px solid #242b4a; border-radius: 16px;
    padding: 14px 18px; margin-bottom: 16px; position: sticky; top: 8px; z-index: 5; box-shadow: 0 14px 34px -16px rgba(0,0,0,.65);
}
.ff-transfer-stat { flex: 1; text-align: center; }
.ff-transfer-stat span { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: var(--nocturne-muted, #929abc); margin-bottom: 4px; }
.ff-transfer-stat b { font-size: 18px; color: #fff; }
.ff-transfer-stat:nth-child(2) b { color: #ff9a4d; }
.ff-transfer-stat:nth-child(3) b { color: var(--nocturne-green, #00ef87); }

.ff-transfer-grid { display: grid; grid-template-columns: 1fr auto 1fr; gap: 16px; align-items: start; margin-bottom: 18px; }
.ff-transfer-col h3 { display: flex; align-items: center; gap: 7px; color: var(--nocturne-text, #f5f6ff); font-size: 13.5px; margin: 0 0 10px; }
.ff-transfer-col__hint { font-size: 10.5px; font-weight: 600; color: var(--nocturne-muted, #929abc); margin-left: auto; }
.ff-transfer-swap-icon {
    display: flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 50%;
    background: #0d1023; border: 1px solid #2c3453; color: var(--nocturne-green, #00ef87); margin-top: 40px; font-size: 16px;
}
.ff-transfer-filters { margin-bottom: 10px; }
.ff-position-pills--compact { margin-bottom: 8px; }
.ff-transfer-list { display: flex; flex-direction: column; gap: 8px; max-height: 560px; overflow-y: auto; padding-right: 2px; }
.ff-transfer-row {
    display: flex; align-items: center; gap: 10px; background: linear-gradient(180deg, #11162e, #0d1023); border: 1px solid #242b4a;
    border-radius: 13px; padding: 9px 12px; cursor: pointer; transition: border-color .15s ease, background .15s ease;
}
.ff-transfer-row:hover { border-color: #3a4374; }
.ff-transfer-row:has(input:checked) { border-color: #ff9a4d; background: rgba(255,154,77,.08); }
.ff-in-row:has(input:checked) { border-color: var(--nocturne-green, #00ef87); background: rgba(0,239,135,.08); }
.ff-transfer-row:has(input:disabled):not(:has(input:checked)) { opacity: .35; cursor: not-allowed; pointer-events: none; }
.ff-transfer-row__info { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.ff-transfer-row__info strong { font-size: 12.5px; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ff-transfer-row__info small { font-size: 10.5px; color: var(--nocturne-muted, #929abc); font-weight: 700; }
.ff-transfer-row__price { font-size: 13px; font-weight: 800; color: var(--nocturne-green, #00ef87); }
.ff-transfer-row__check { width: 18px; height: 18px; border-radius: 50%; border: 1px solid #2c3453; display: grid; place-items: center; font-size: 10px; color: transparent; }
.ff-transfer-row:has(input:checked) .ff-transfer-row__check { background: #ff9a4d; border-color: #ff9a4d; color: #2b1400; }
.ff-in-row:has(input:checked) .ff-transfer-row__check { background: var(--nocturne-green, #00ef87); border-color: var(--nocturne-green, #00ef87); color: #073c2a; }

.ff-review-pair { display: flex; align-items: center; gap: 10px; background: #0d1023; border: 1px solid #2c3453; border-radius: 12px; padding: 10px 14px; margin-bottom: 10px; font-size: 12.5px; }
.ff-review-pair .out { color: #ff9a4d; font-weight: 700; flex: 1; }
.ff-review-pair .in { color: var(--nocturne-green, #00ef87); font-weight: 700; flex: 1; text-align: right; }
.ff-review-pair i { color: var(--nocturne-muted, #929abc); }
.ff-review-totals { display: flex; flex-direction: column; gap: 6px; background: rgba(255,255,255,.03); border-radius: 12px; padding: 12px 14px; font-size: 12.5px; color: var(--nocturne-muted, #929abc); }
.ff-review-totals b { color: #fff; }
.ff-review-totals .warn { color: #ff5c7a; }

.ff-confirm-spacer { height: 78px; }
.ff-confirm-bar {
    position: fixed; left: 0; right: 0; bottom: 0; z-index: 20; display: flex; align-items: center; justify-content: space-between;
    gap: 14px; background: rgba(13,16,35,.95); backdrop-filter: blur(10px); border-top: 1px solid #242b4a;
    padding: 12px max(16px, calc(env(safe-area-inset-left) + 16px)) calc(12px + env(safe-area-inset-bottom)) max(16px, calc(env(safe-area-inset-right) + 16px));
}
.ff-confirm-bar__info { display: flex; flex-direction: column; font-size: 12px; color: var(--nocturne-muted, #929abc); }
.ff-confirm-bar__info strong { font-size: 16px; color: #fff; }
.ff-confirm-bar .btn--gradient { padding: 12px 28px; border-radius: 13px; font-size: 14.5px; min-height: 48px; }
.ff-confirm-bar .btn--gradient:disabled { opacity: .4; }

@media (max-width: 1199.98px) {
    .ff-confirm-bar { bottom: calc(96px + env(safe-area-inset-bottom)); border-radius: 16px 16px 0 0; }
    .ff-confirm-spacer { height: calc(78px + 96px + env(safe-area-inset-bottom)); }
}
@media (max-width: 900px) {
    .ff-transfer-grid { grid-template-columns: 1fr; }
    .ff-transfer-swap-icon { display: none; }
}
</style>
@endpush

@push('script')
<script>
"use strict";
(function() {
    var form = document.getElementById('ff-transfer-form');
    var freeTransfers = {{ $team->fresh_start_active ? 999 : (int) $team->free_transfers_banked }};
    var budget = {{ (float) $team->budget_remaining }};
    var reviewBtn = document.getElementById('ff-review-btn');

    function selectedOut() { return Array.from(form.querySelectorAll('.ff-out-row')).filter(function(r) { return r.querySelector('input').checked; }); }
    function selectedIn() { return Array.from(form.querySelectorAll('.ff-in-row')).filter(function(r) { return r.querySelector('input').checked; }); }

    function recompute() {
        var outs = selectedOut(), ins = selectedIn();
        var pairs = Math.min(outs.length, ins.length);
        var sellTotal = outs.reduce(function(s, r) { return s + parseFloat(r.dataset.sell); }, 0);
        var buyTotal = ins.reduce(function(s, r) { return s + parseFloat(r.dataset.price); }, 0);
        var cost = Math.max(0, outs.length - freeTransfers) * 4;
        var budgetAfter = budget + sellTotal - buyTotal;

        [document.getElementById('ff-t-count'), document.getElementById('ff-t-count-2')].forEach(function(el) { el.textContent = pairs; });
        [document.getElementById('ff-t-cost'), document.getElementById('ff-t-cost-2')].forEach(function(el) { el.textContent = (el.id === 'ff-t-cost-2' ? cost + ' pts' : cost); });
        document.getElementById('ff-t-budget-after').textContent = budgetAfter.toFixed(1);

        var valid = pairs > 0 && outs.length === ins.length && budgetAfter >= -0.001;
        reviewBtn.disabled = !valid;

        // Disable "in" candidates that don't actually form a legal pair with the selected "out"
        // players - same position required, and the incoming club must not exceed 3 once swapped.
        var outPositions = outs.map(function(r) { return r.dataset.position; });
        var inHint = document.getElementById('ff-in-hint');
        inHint.textContent = outs.length ? ('replacing ' + outPositions.join(', ')) : 'select an outgoing player first';

        var clubCounts = {};
        Array.from(form.querySelectorAll('.ff-out-row')).forEach(function(r) {
            if (selectedOut().indexOf(r) !== -1) return; // being sold, don't count it
            clubCounts[r.dataset.club] = (clubCounts[r.dataset.club] || 0) + 1;
        });
        ins.forEach(function(r) { clubCounts[r.dataset.club] = (clubCounts[r.dataset.club] || 0) + 1; });

        // A same-position-only guard, matching the server's own rule (FantasyTeamService::transfer
        // requires the incoming player's position to equal the outgoing one's) - loose on purpose:
        // it only requires the position to appear SOMEWHERE among the selected outgoing players,
        // not a strict per-index pairing, since pairing order is just DOM/selection order and the
        // server is what actually enforces the exact pairing at save time.
        Array.from(form.querySelectorAll('.ff-in-row')).forEach(function(r) {
            var input = r.querySelector('input');
            if (input.checked) return;
            var positionIsBeingReplaced = outPositions.indexOf(r.dataset.position) !== -1;
            var clubWouldExceed = (clubCounts[r.dataset.club] || 0) >= 3;
            input.disabled = outs.length > 0 && (!positionIsBeingReplaced || clubWouldExceed);
        });

        var hidden = document.getElementById('ff-hidden-inputs');
        hidden.innerHTML = '';
        outs.forEach(function(r, i) {
            if (!ins[i]) return;
            hidden.insertAdjacentHTML('beforeend', '<input type="hidden" name="out[]" value="' + r.dataset.player + '"><input type="hidden" name="in[]" value="' + ins[i].dataset.player + '">');
        });
    }
    form.addEventListener('change', function(e) { if (e.target.type === 'checkbox') recompute(); });

    var posFilter = '';
    var posPills = Array.from(document.querySelectorAll('#ff-in-pos-pills .ff-pos-pill'));
    var searchFilter = document.getElementById('ff-filter-search');
    var noResults = document.getElementById('ff-no-results');
    function applyFilters() {
        var term = searchFilter.value.toLowerCase();
        var visible = 0;
        document.querySelectorAll('.ff-in-row').forEach(function(r) {
            var show = (!posFilter || r.dataset.position === posFilter) && (!term || r.dataset.name.indexOf(term) !== -1);
            r.hidden = !show;
            if (show) visible++;
        });
        noResults.classList.toggle('d-none', visible !== 0);
    }
    posPills.forEach(function(pill) {
        pill.addEventListener('click', function() {
            posFilter = pill.dataset.pos;
            posPills.forEach(function(p) { p.classList.toggle('is-active', p === pill); });
            applyFilters();
        });
    });
    searchFilter.addEventListener('input', applyFilters);

    // ---------- Confirmation summary before saving ----------
    var overlay = document.getElementById('ff-review-overlay');
    reviewBtn.addEventListener('click', function() {
        var outs = selectedOut(), ins = selectedIn();
        var pairsHtml = '';
        var sellTotal = 0, buyTotal = 0;
        outs.forEach(function(r, i) {
            var inRow = ins[i];
            if (!inRow) return;
            sellTotal += parseFloat(r.dataset.sell);
            buyTotal += parseFloat(inRow.dataset.price);
            pairsHtml += '<div class="ff-review-pair"><span class="out">' + r.dataset.name + ' (' + parseFloat(r.dataset.sell).toFixed(1) + ')</span><i class="ph-bold ph-arrow-right"></i><span class="in">' + inRow.querySelector('.ff-transfer-row__info strong').textContent + ' (' + parseFloat(inRow.dataset.price).toFixed(1) + ')</span></div>';
        });
        document.getElementById('ff-review-pairs').innerHTML = pairsHtml;
        var cost = Math.max(0, outs.length - freeTransfers) * 4;
        var budgetAfter = budget + sellTotal - buyTotal;
        var freeUsed = Math.min(outs.length, freeTransfers);
        document.getElementById('ff-review-totals').innerHTML =
            '<span>Free transfers used: <b>' + freeUsed + (freeTransfers >= 999 ? ' (unlimited)' : ' / ' + freeTransfers) + '</b></span>'
            + '<span>Point cost: <b class="' + (cost > 0 ? 'warn' : '') + '">' + cost + ' pts</b></span>'
            + '<span>Budget after: <b>' + budgetAfter.toFixed(1) + '</b></span>';
        overlay.classList.remove('d-none');
    });
    document.getElementById('ff-review-close').addEventListener('click', function() { overlay.classList.add('d-none'); });
    document.getElementById('ff-review-back').addEventListener('click', function() { overlay.classList.add('d-none'); });
    overlay.addEventListener('click', function(e) { if (e.target === overlay) overlay.classList.add('d-none'); });

    recompute();
})();
</script>
@endpush
