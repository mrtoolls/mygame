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

            <div class="ff-status-panel" id="ff-summary-bar">
                <div class="ff-status-gauge">
                    <div class="ff-gauge" id="ff-budget-gauge" style="--pct:0">
                        <div class="ff-gauge__inner">
                            <span class="ff-gauge__value" id="ff-budget-left">100.0</span>
                            <span class="ff-gauge__label">@lang('left')</span>
                        </div>
                    </div>
                    <span class="ff-status-gauge__caption">@lang('BUDGET')</span>
                </div>

                <div class="ff-status-positions">
                    <div class="ff-pos-track" data-pos="GK">
                        <div class="ff-pos-track__head"><span>GK</span><b><span class="ff-pos-track__count">0</span>/2</b></div>
                        <div class="ff-pos-track__bar"><div class="ff-pos-track__fill"></div></div>
                    </div>
                    <div class="ff-pos-track" data-pos="DEF">
                        <div class="ff-pos-track__head"><span>DEF</span><b><span class="ff-pos-track__count">0</span>/5</b></div>
                        <div class="ff-pos-track__bar"><div class="ff-pos-track__fill"></div></div>
                    </div>
                    <div class="ff-pos-track" data-pos="MID">
                        <div class="ff-pos-track__head"><span>MID</span><b><span class="ff-pos-track__count">0</span>/5</b></div>
                        <div class="ff-pos-track__bar"><div class="ff-pos-track__fill"></div></div>
                    </div>
                    <div class="ff-pos-track" data-pos="FWD">
                        <div class="ff-pos-track__head"><span>FWD</span><b><span class="ff-pos-track__count">0</span>/3</b></div>
                        <div class="ff-pos-track__bar"><div class="ff-pos-track__fill"></div></div>
                    </div>
                </div>

                <div class="ff-status-side">
                    <div class="ff-status-gauge ff-status-gauge--squad">
                        <div class="ff-gauge-wrap">
                            <div class="ff-gauge ff-gauge--squad" id="ff-squad-gauge" style="--pct:0">
                                <div class="ff-gauge__inner">
                                    <span class="ff-gauge__value"><span id="ff-count">0</span><small>/15</small></span>
                                </div>
                            </div>
                            <button type="button" class="ff-status-info-btn" id="ff-status-info-btn" aria-expanded="false" aria-label="@lang('Squad requirements')">
                                <i class="ph-fill ph-info"></i>
                            </button>
                        </div>
                        <span class="ff-status-gauge__caption">@lang('SQUAD')</span>
                    </div>
                </div>
            </div>

            <div class="ff-status-popover" id="ff-status-popover" role="status" hidden>
                <i class="ph-fill ph-info"></i>
                <span id="ff-status-popover-text"></span>
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
                <button type="submit" class="btn btn--gradient" id="ff-build-submit" disabled title="@lang('See the info icon above for what is still needed.')">@lang('Confirm Squad')</button>
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

.ff-status-panel {
    position: sticky; top: 8px; z-index: 5; display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 22px;
    background: linear-gradient(145deg, #121732, #0b0e1f); border: 1px solid #242b4a; border-radius: 18px;
    padding: 18px 22px; margin-bottom: 16px; box-shadow: 0 14px 34px -16px rgba(0,0,0,.65);
    backdrop-filter: blur(6px);
}
.ff-summary-label { display: block; font-size: 10.5px; color: var(--nocturne-muted, #929abc); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 2px; }

/* Circular budget gauge: a conic-gradient ring whose fill angle tracks --pct (0-100, or beyond
   100 shown in red once over budget), with the numeric value centered inside via a masked inner
   circle - no SVG needed. */
.ff-status-gauge { display: flex; flex-direction: column; align-items: center; gap: 6px; }
.ff-gauge {
    --pct: 0; width: 84px; height: 84px; border-radius: 50%; display: grid; place-items: center; position: relative;
    background: conic-gradient(var(--nocturne-green, #00ef87) calc(var(--pct) * 1%), #1c2240 0);
    transition: background .3s ease;
}
.ff-gauge.is-warning { background: conic-gradient(#ffd166 calc(var(--pct) * 1%), #1c2240 0); }
.ff-gauge.is-over { background: conic-gradient(#ff5c7a 100%, #1c2240 0); }
.ff-gauge__inner {
    width: 66px; height: 66px; border-radius: 50%; background: #0d1023; display: grid; place-items: center;
    text-align: center; line-height: 1.1;
}
.ff-gauge__value { display: block; font-size: 15px; font-weight: 800; color: #fff; font-variant-numeric: tabular-nums; }
.ff-gauge.is-warning .ff-gauge__value { color: #ffd166; }
.ff-gauge.is-over .ff-gauge__value { color: #ff5c7a; }
.ff-gauge__label { display: block; font-size: 9px; color: var(--nocturne-muted, #929abc); text-transform: uppercase; letter-spacing: .05em; }
.ff-status-gauge__caption { font-size: 10px; font-weight: 800; letter-spacing: .08em; color: var(--nocturne-muted, #929abc); }

.ff-status-positions { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.ff-pos-track__head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 5px; font-size: 11px; font-weight: 800; color: var(--nocturne-muted, #929abc); letter-spacing: .03em; }
.ff-pos-track__head b { color: #fff; font-size: 12px; }
.ff-pos-track__bar { height: 6px; border-radius: 999px; background: #1c2240; overflow: hidden; }
.ff-pos-track__fill { height: 100%; width: 0%; border-radius: 999px; transition: width .25s ease, background .25s ease; background: #3a4374; }
.ff-pos-track[data-pos="GK"] .ff-pos-track__fill { background: linear-gradient(90deg, #a8761c, #ffd166); }
.ff-pos-track[data-pos="DEF"] .ff-pos-track__fill { background: linear-gradient(90deg, #1b7fa8, #38bdf8); }
.ff-pos-track[data-pos="MID"] .ff-pos-track__fill { background: linear-gradient(90deg, #0a9e5c, #00ef87); }
.ff-pos-track[data-pos="FWD"] .ff-pos-track__fill { background: linear-gradient(90deg, #c76a2e, #ff9a4d); }
.ff-pos-track.is-complete .ff-pos-track__head b { color: var(--nocturne-green, #00ef87); }

/* The squad counter mirrors the budget gauge exactly (same ring component, same caption style)
   instead of being plain stacked text - visually pairs the two "how am I doing" numbers as twin
   gauges, and a single-word caption in a generously-wide, centered column never wraps the way
   "Squad" did crammed into a squeezed flex column on narrow screens. */
.ff-status-side { display: flex; align-items: center; }
.ff-status-gauge--squad { display: flex; flex-direction: column; align-items: center; gap: 6px; }
.ff-gauge-wrap { position: relative; }
.ff-gauge--squad { background: conic-gradient(var(--nocturne-green, #00ef87) calc(var(--pct) * 1%), #1c2240 0); }
.ff-gauge--squad .ff-gauge__value { font-size: 14px; }
.ff-gauge--squad .ff-gauge__value small { font-size: 10px; color: var(--nocturne-muted, #929abc); font-weight: 700; }

/* A single, click-to-reveal explanation instead of a permanent sentence wherever the squad count
   appears - the info icon is the one place this text ever lives. */
.ff-status-info-btn {
    position: absolute; bottom: -2px; right: -2px; width: 22px; height: 22px; border-radius: 50%;
    display: grid; place-items: center; background: #1c2240; border: 2px solid #0b0e1f; color: #ffd166;
    font-size: 12px; cursor: pointer; transition: transform .15s ease, background .15s ease;
}
.ff-status-info-btn:hover, .ff-status-info-btn[aria-expanded="true"] { background: #2c3453; transform: scale(1.08); }
.ff-status-info-btn.is-ready { color: var(--nocturne-green, #00ef87); }
.ff-status-info-btn.is-over { color: #ff5c7a; }
.ff-status-popover {
    position: fixed; z-index: 30; display: flex; align-items: flex-start; gap: 8px;
    width: 270px; max-width: calc(100vw - 24px); box-sizing: border-box;
    background: #171c38; border: 1px solid #2c3453; border-radius: 13px; padding: 12px 14px; font-size: 12.5px;
    line-height: 1.4; color: var(--nocturne-text, #f5f6ff); box-shadow: 0 14px 30px -10px rgba(0,0,0,.6);
}
.ff-status-popover[hidden] { display: none; }
.ff-status-popover i { color: #ffd166; flex: 0 0 auto; margin-top: 1px; }
.ff-status-popover.is-ready i { color: var(--nocturne-green, #00ef87); }
.ff-status-popover.is-over i { color: #ff5c7a; }

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

/* The site's own fixed bottom mobile nav (.nocturne-mobile-nav, 96px tall + safe-area, z-index
   1040) only appears below the 1199.98px breakpoint - below it, this bar must sit ABOVE that nav
   instead of underneath it (z-index alone can't fix this: they'd just stack, with the site nav
   still covering this bar's lower portion), and the spacer must reserve room for both bars
   stacked, or the last row of player cards ends up hidden behind them. */
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

@media (prefers-reduced-motion: reduce) {
    .ff-player-card, .ff-player-card__check, .ff-pos-track__fill, .ff-gauge, .ff-pos-pill { transition: none !important; }
    .ff-player-card:hover { transform: none; }
}
@media (max-width: 1199.98px) {
    .ff-confirm-bar { bottom: calc(96px + env(safe-area-inset-bottom)); border-radius: 16px 16px 0 0; }
    .ff-confirm-spacer { height: calc(78px + 96px + env(safe-area-inset-bottom)); }
}
@media (max-width: 767.98px) {
    .ff-status-panel { grid-template-columns: auto 1fr auto; gap: 14px; }
}
@media (max-width: 575.98px) {
    .ff-build-hero { padding: 20px 16px; border-radius: 16px; }
    .ff-build-hero__copy h1 { font-size: 21px; }
    .ff-status-panel { padding: 16px; gap: 14px; border-radius: 16px; }
    .ff-status-positions { grid-template-columns: repeat(2, minmax(0, 1fr)); row-gap: 10px; }
    .ff-gauge { width: 72px; height: 72px; }
    .ff-gauge__inner { width: 56px; height: 56px; }
    .ff-gauge__value { font-size: 13px; }
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
    var gauge = document.getElementById('ff-budget-gauge');
    var squadGauge = document.getElementById('ff-squad-gauge');
    var submitBtn = document.getElementById('ff-build-submit');
    var infoBtn = document.getElementById('ff-status-info-btn');
    var popover = document.getElementById('ff-status-popover');
    var popoverText = document.getElementById('ff-status-popover-text');
    var required = { GK: 2, DEF: 5, MID: 5, FWD: 3 };
    var labels = { GK: 'goalkeeper', DEF: 'defender', MID: 'midfielder', FWD: 'forward' };

    function positionPopover() {
        var btnRect = infoBtn.getBoundingClientRect();
        var popWidth = popover.offsetWidth || 270;
        var top = btnRect.bottom + 8;
        var left = Math.min(btnRect.left, window.innerWidth - popWidth - 12);
        popover.style.top = Math.max(8, top) + 'px';
        popover.style.left = Math.max(8, left) + 'px';
    }
    function openPopover() {
        popover.hidden = false;
        infoBtn.setAttribute('aria-expanded', 'true');
        positionPopover();
    }
    function closePopover() {
        popover.hidden = true;
        infoBtn.setAttribute('aria-expanded', 'false');
    }
    infoBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        if (popover.hidden) openPopover(); else closePopover();
    });
    document.addEventListener('click', function(e) {
        if (!popover.hidden && !popover.contains(e.target) && e.target !== infoBtn) closePopover();
    });
    window.addEventListener('scroll', function() { if (!popover.hidden) positionPopover(); }, true);
    window.addEventListener('resize', function() { if (!popover.hidden) positionPopover(); });

    function recompute() {
        var selected = cards.filter(function(c) { return c.querySelector('input').checked; });
        var spent = selected.reduce(function(s, c) { return s + parseFloat(c.dataset.price); }, 0);
        var counts = { GK: 0, DEF: 0, MID: 0, FWD: 0 };
        var clubCounts = {};
        selected.forEach(function(c) { counts[c.dataset.position]++; clubCounts[c.dataset.club] = (clubCounts[c.dataset.club] || 0) + 1; });

        var budgetLeft = (100 - spent);
        budgetEls.forEach(function(el) { el.textContent = budgetLeft.toFixed(1); });
        countEls.forEach(function(el) { el.textContent = selected.length; });

        // The gauge fills clockwise with how much of the 100.0 budget is SPENT, not what's left -
        // an empty ring reads as "nothing spent yet", a full one as "every credit committed".
        var spentPct = Math.max(0, Math.min(100, (spent / 100) * 100));
        gauge.style.setProperty('--pct', spentPct);
        gauge.classList.toggle('is-warning', budgetLeft >= 0 && budgetLeft < 10);
        gauge.classList.toggle('is-over', budgetLeft < 0);

        document.querySelectorAll('.ff-pos-track').forEach(function(track) {
            var pos = track.dataset.pos;
            var pct = Math.min(100, (counts[pos] / required[pos]) * 100);
            track.querySelector('.ff-pos-track__count').textContent = counts[pos];
            track.querySelector('.ff-pos-track__fill').style.width = pct + '%';
            track.classList.toggle('is-complete', counts[pos] === required[pos]);
        });
        squadGauge.style.setProperty('--pct', Math.min(100, (selected.length / 15) * 100));

        var shapeOk = Object.keys(required).every(function(p) { return counts[p] <= required[p]; });
        var clubOk = Object.values(clubCounts).every(function(c) { return c <= 3; });
        var exact = Object.keys(required).every(function(p) { return counts[p] === required[p]; });
        var canConfirm = exact && clubOk && budgetLeft >= 0 && selected.length === 15;
        submitBtn.disabled = !canConfirm;

        // A single, click-to-reveal explanation (the info icon's popover) instead of a permanent
        // sentence repeated in multiple places - this text now lives in exactly one spot.
        var reason = '';
        if (budgetLeft < 0) {
            reason = 'You are ' + Math.abs(budgetLeft).toFixed(1) + ' credits over budget - remove a player to continue.';
        } else {
            var missing = Object.keys(required).filter(function(p) { return counts[p] < required[p]; });
            if (missing.length) {
                reason = 'Pick ' + missing.map(function(p) { return (required[p] - counts[p]) + ' more ' + labels[p] + (required[p] - counts[p] > 1 ? 's' : ''); }).join(', ') + '.';
            }
        }
        var state = canConfirm ? 'ready' : (budgetLeft < 0 ? 'over' : 'pending');
        var displayText = canConfirm ? 'Squad complete - ready to confirm!' : (reason || 'Pick 15 players to complete your squad.');
        popoverText.textContent = displayText;
        [infoBtn, popover].forEach(function(el) {
            el.classList.toggle('is-ready', state === 'ready');
            el.classList.toggle('is-over', state === 'over');
        });
        if (!popover.hidden) positionPopover();

        cards.forEach(function(c) {
            var input = c.querySelector('input');
            if (!input.checked) {
                var price = parseFloat(c.dataset.price);
                var wouldExceedShape = counts[c.dataset.position] >= required[c.dataset.position] || (clubCounts[c.dataset.club] || 0) >= 3;
                var wouldExceedBudget = price > budgetLeft + 0.001; // float-safe comparison
                input.disabled = wouldExceedShape || wouldExceedBudget;
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
