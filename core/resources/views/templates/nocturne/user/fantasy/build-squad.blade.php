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

        <div class="ff-draft-banner d-none" id="ff-draft-banner">
            <i class="ph-fill ph-clock-counter-clockwise"></i>
            <span>@lang('You have an unfinished draft saved on this device.')</span>
            <button type="button" class="btn btn--sm btn--gradient" id="ff-draft-resume">@lang('Resume draft')</button>
            <button type="button" class="ff-draft-dismiss" id="ff-draft-dismiss" aria-label="@lang('Dismiss')"><i class="ph-bold ph-x"></i></button>
        </div>

        <form action="{{ route('user.fantasy.create.store') }}" method="POST" id="ff-build-form">
            @csrf

            <div class="ff-field">
                <label class="form--label">@lang('Team Name')</label>
                <div class="ff-team-name-control">
                    <i class="ph-bold ph-pencil-simple" aria-hidden="true"></i>
                    <input type="text" name="name" id="ff-team-name" required maxlength="60" placeholder="@lang('e.g. Ironbridge Invincibles')">
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
                        <div class="ff-pos-track__max"><span class="ff-pos-track__max-label">@lang('max/slot')</span> <b class="ff-pos-track__max-value" data-pos="GK">-</b></div>
                    </div>
                    <div class="ff-pos-track" data-pos="DEF">
                        <div class="ff-pos-track__head"><span>DEF</span><b><span class="ff-pos-track__count">0</span>/5</b></div>
                        <div class="ff-pos-track__bar"><div class="ff-pos-track__fill"></div></div>
                        <div class="ff-pos-track__max"><span class="ff-pos-track__max-label">@lang('max/slot')</span> <b class="ff-pos-track__max-value" data-pos="DEF">-</b></div>
                    </div>
                    <div class="ff-pos-track" data-pos="MID">
                        <div class="ff-pos-track__head"><span>MID</span><b><span class="ff-pos-track__count">0</span>/5</b></div>
                        <div class="ff-pos-track__bar"><div class="ff-pos-track__fill"></div></div>
                        <div class="ff-pos-track__max"><span class="ff-pos-track__max-label">@lang('max/slot')</span> <b class="ff-pos-track__max-value" data-pos="MID">-</b></div>
                    </div>
                    <div class="ff-pos-track" data-pos="FWD">
                        <div class="ff-pos-track__head"><span>FWD</span><b><span class="ff-pos-track__count">0</span>/3</b></div>
                        <div class="ff-pos-track__bar"><div class="ff-pos-track__fill"></div></div>
                        <div class="ff-pos-track__max"><span class="ff-pos-track__max-label">@lang('max/slot')</span> <b class="ff-pos-track__max-value" data-pos="FWD">-</b></div>
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

            <div class="ff-insight-row">
                <span class="ff-insight-chip"><i class="ph-bold ph-calculator"></i>@lang('Avg per open slot'): <b id="ff-avg-slot">6.7</b></span>
                <label class="ff-affordable-toggle">
                    <input type="checkbox" id="ff-affordable-only" checked>
                    <span class="ff-affordable-toggle__track"><span class="ff-affordable-toggle__thumb"></span></span>
                    <span class="ff-affordable-toggle__label">@lang('Affordable only')</span>
                </label>
            </div>

            <div class="ff-mode-row">
                <div class="ff-mode-toggle" role="tablist">
                    <button type="button" class="ff-mode-btn is-active" data-mode="browse">@lang('Browse')</button>
                    <button type="button" class="ff-mode-btn" data-mode="guided">@lang('Guided Build')</button>
                    <button type="button" class="ff-mode-btn" data-mode="compare">@lang('Compare')</button>
                </div>
                <div class="ff-history-actions">
                    <button type="button" class="ff-icon-btn" id="ff-undo-btn" disabled title="@lang('Undo')"><i class="ph-bold ph-arrow-counter-clockwise"></i></button>
                    <button type="button" class="ff-icon-btn" id="ff-redo-btn" disabled title="@lang('Redo')"><i class="ph-bold ph-arrow-clockwise"></i></button>
                    <button type="button" class="ff-icon-btn" id="ff-reset-btn" title="@lang('Reset squad')"><i class="ph-bold ph-trash"></i></button>
                    <button type="button" class="ff-icon-btn" id="ff-planner-btn" title="@lang('Budget planner')"><i class="ph-bold ph-sliders-horizontal"></i></button>
                    <button type="button" class="btn btn--sm btn--outline-gradient" id="ff-autofill-btn"><i class="ph-bold ph-magic-wand"></i>@lang('Auto-fill rest')</button>
                </div>
            </div>

            <div class="ff-guided-stepper d-none" id="ff-guided-stepper">
                <div class="ff-guided-steps" id="ff-guided-steps">
                    <button type="button" class="ff-guided-step is-active" data-step="GK">1 · GK</button>
                    <button type="button" class="ff-guided-step" data-step="DEF">2 · DEF</button>
                    <button type="button" class="ff-guided-step" data-step="MID">3 · MID</button>
                    <button type="button" class="ff-guided-step" data-step="FWD">4 · FWD</button>
                    <button type="button" class="ff-guided-step" data-step="REVIEW">5 · @lang('Review')</button>
                </div>
                <div class="ff-guided-nav">
                    <button type="button" class="btn btn--sm btn--outline-gradient" id="ff-guided-back" disabled>@lang('Back')</button>
                    <span class="ff-guided-hint" id="ff-guided-hint"></span>
                    <button type="button" class="btn btn--sm btn--gradient" id="ff-guided-next">@lang('Next')</button>
                </div>
            </div>

            <div class="ff-planner-panel d-none" id="ff-planner-panel">
                <div class="ff-planner-head">
                    <h3><i class="ph-bold ph-sliders-horizontal"></i>@lang('Budget Planner')</h3>
                    <p>@lang('Drag to set a target spend per position - a guide only, it never locks your picks.')</p>
                </div>
                <div class="ff-planner-rows" id="ff-planner-rows">
                    @foreach (['GK' => 2, 'DEF' => 5, 'MID' => 5, 'FWD' => 3] as $pos => $count)
                        <div class="ff-planner-row" data-pos="{{ $pos }}">
                            <div class="ff-planner-row__head"><span>{{ $pos }} ({{ $count }})</span><b class="ff-planner-row__value">0.0</b></div>
                            <input type="range" min="0" max="100" step="0.5" value="0" class="ff-planner-slider" data-pos="{{ $pos }}">
                            <span class="ff-planner-row__per-slot"></span>
                        </div>
                    @endforeach
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
                    <div class="ff-search-wrap">
                        <i class="ph-bold ph-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" id="ff-filter-search" placeholder="@lang('Search player...')">
                    </div>
                    <button type="button" class="ff-filters-btn" id="ff-filters-open"><i class="ph-bold ph-funnel"></i>@lang('Filters')<span class="ff-filters-count d-none" id="ff-filters-count">0</span></button>
                </div>
                <div class="ff-quick-chips" id="ff-quick-chips">
                    <button type="button" class="ff-quick-chip" data-chip="budget"><i class="ph-bold ph-coin"></i>@lang('Budget')</button>
                    <button type="button" class="ff-quick-chip" data-chip="premium"><i class="ph-bold ph-crown"></i>@lang('Premium')</button>
                    <button type="button" class="ff-quick-chip" data-chip="value"><i class="ph-bold ph-trend-up"></i>@lang('Best value')</button>
                    <button type="button" class="ff-quick-chip" data-chip="form"><i class="ph-bold ph-fire"></i>@lang('In form')</button>
                    <button type="button" class="ff-quick-chip" data-chip="differential"><i class="ph-bold ph-sparkle"></i>@lang('Differential')</button>
                </div>
            </div>

            <div class="ff-player-grid" id="ff-player-grid">
                @foreach ($players as $player)
                    <label class="ff-player-card" data-position="{{ $player->position }}" data-club="{{ $player->club_id }}" data-price="{{ $player->price }}" data-name="{{ strtolower($player->name) }}"
                        data-status="{{ $player->status }}" data-points="{{ $player->points_total }}" data-form="{{ $player->form ?? '' }}"
                        data-ownership="{{ $player->ownership_pct }}" data-value="{{ $player->value_score }}"
                        data-fixtures="{{ json_encode($player->next_fixtures) }}">
                        <input type="checkbox" name="player_ids[]" value="{{ $player->id }}" hidden>
                        <span class="ff-player-card__check"><i class="ph-bold ph-check"></i></span>
                        <span class="ff-player-card__pos ff-player-card__pos--{{ strtolower($player->position) }}">{{ $player->position }}</span>
                        @if ($player->status !== 'fit')
                            <span class="ff-player-card__flag ff-player-card__flag--{{ $player->status }}" title="{{ ucfirst($player->status) }}"><i class="ph-fill ph-first-aid-kit"></i></span>
                        @endif
                        <button type="button" class="ff-player-card__compare" data-compare-id="{{ $player->id }}" title="@lang('Add to compare')"><i class="ph-bold ph-scales"></i></button>
                        <span class="ff-player-card__shirt"><x-fantasy-shirt :primary="$player->club->color_primary" :secondary="$player->club->color_secondary" :pattern="$player->club->pattern" :size="44" /></span>
                        <span class="ff-player-card__name">{{ $player->name }}</span>
                        <span class="ff-player-card__club">{{ $player->club->short_code }}</span>
                        <span class="ff-player-card__stats">
                            <span class="ff-player-card__rating"><i class="ph-fill ph-star"></i>{{ number_format($player->rating, 0) }}</span>
                            <span class="ff-player-card__price">{{ number_format($player->price, 1) }}</span>
                        </span>
                        <span class="ff-player-card__meta">
                            <span>{{ $player->points_total }}@lang('pts')</span>
                            <span>{{ $player->ownership_pct }}%@lang('own')</span>
                        </span>
                        @if (!empty($player->next_fixtures))
                            <span class="ff-player-card__fixtures">
                                @foreach ($player->next_fixtures as $fx)
                                    <span class="ff-fx-dot ff-fx-dot--{{ $fx['difficulty'] }}" title="{{ $fx['opponent'] }}">{{ $fx['opponent'] }}</span>
                                @endforeach
                            </span>
                        @endif
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

<div class="ff-sheet-overlay d-none" id="ff-filter-sheet-overlay">
    <div class="ff-sheet" id="ff-filter-sheet" role="dialog" aria-modal="true" aria-label="@lang('Filters')">
        <div class="ff-sheet__handle"></div>
        <div class="ff-sheet__head">
            <h3>@lang('Filters & Sort')</h3>
            <button type="button" class="ff-sheet__close" id="ff-filters-close" aria-label="@lang('Close')"><i class="ph-bold ph-x"></i></button>
        </div>
        <div class="ff-sheet__body">
            <div class="ff-sheet-group">
                <label class="ff-sheet-label">@lang('Sort by')</label>
                <select id="ff-filter-sort" class="ff-sheet-select">
                    <option value="rating_desc">@lang('Rating (high to low)')</option>
                    <option value="price_desc">@lang('Price (high to low)')</option>
                    <option value="price_asc">@lang('Price (low to high)')</option>
                    <option value="points_desc">@lang('Points')</option>
                    <option value="form_desc">@lang('Form')</option>
                    <option value="value_desc">@lang('Value (points per credit)')</option>
                    <option value="ownership_desc">@lang('Ownership %')</option>
                    <option value="ownership_asc">@lang('Differential (low ownership)')</option>
                    <option value="difficulty_asc">@lang('Easiest next fixture')</option>
                </select>
            </div>
            <div class="ff-sheet-group">
                <label class="ff-sheet-label">@lang('Price range'): <span id="ff-price-range-label">0.0 - 15.0</span></label>
                <div class="ff-range-dual">
                    <input type="range" min="0" max="15" step="0.5" value="0" id="ff-price-min">
                    <input type="range" min="0" max="15" step="0.5" value="15" id="ff-price-max">
                </div>
            </div>
            <div class="ff-sheet-group">
                <label class="ff-sheet-label">@lang('Availability')</label>
                <div class="ff-chip-group" id="ff-availability-group">
                    <button type="button" class="ff-chip-option is-active" data-value="">@lang('Any')</button>
                    <button type="button" class="ff-chip-option" data-value="fit">@lang('Fit')</button>
                    <button type="button" class="ff-chip-option" data-value="doubtful">@lang('Doubtful')</button>
                    <button type="button" class="ff-chip-option" data-value="injured">@lang('Injured')</button>
                    <button type="button" class="ff-chip-option" data-value="suspended">@lang('Suspended')</button>
                </div>
            </div>
            <div class="ff-sheet-group">
                <label class="ff-sheet-label">@lang('Clubs')</label>
                <div class="ff-club-grid" id="ff-club-grid">
                    @foreach ($clubs as $club)
                        <label class="ff-club-check">
                            <input type="checkbox" value="{{ $club->id }}">
                            <span>{{ $club->short_code }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="ff-sheet__foot">
            <button type="button" class="btn btn--sm btn--outline-gradient" id="ff-filters-clear">@lang('Clear all')</button>
            <button type="button" class="btn btn--sm btn--gradient" id="ff-filters-apply">@lang('Show results')</button>
        </div>
    </div>
</div>

<div class="ff-compare-panel d-none" id="ff-compare-panel">
    <div class="ff-compare-head">
        <h4><i class="ph-bold ph-scales"></i>@lang('Compare Players')</h4>
        <button type="button" class="ff-sheet__close" id="ff-compare-close" aria-label="@lang('Close')"><i class="ph-bold ph-x"></i></button>
    </div>
    <div class="ff-compare-body" id="ff-compare-body"></div>
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

.ff-draft-banner {
    display: flex; align-items: center; gap: 10px; background: rgba(56,189,248,.08); border: 1px solid rgba(56,189,248,.3);
    color: #bfe9ff; font-size: 12.5px; font-weight: 600; padding: 12px 16px; border-radius: 13px; margin-bottom: 14px; flex-wrap: wrap;
}
.ff-draft-banner i.ph-fill { color: #38bdf8; font-size: 16px; }
.ff-draft-banner span { flex: 1; min-width: 160px; }
.ff-draft-dismiss { background: transparent; border: 0; color: var(--nocturne-muted, #929abc); cursor: pointer; font-size: 14px; padding: 4px; }

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
    padding: 18px 22px; margin-bottom: 10px; box-shadow: 0 14px 34px -16px rgba(0,0,0,.65);
    backdrop-filter: blur(6px);
}

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
.ff-pos-track__max { display: flex; justify-content: space-between; gap: 4px; margin-top: 4px; font-size: 9.5px; color: var(--nocturne-muted, #929abc); }
.ff-pos-track__max-value { color: #c9f9e2; font-weight: 700; }
.ff-pos-track.is-tight .ff-pos-track__max-value { color: #ffd166; }
.ff-pos-track.is-blocked .ff-pos-track__max-value { color: #ff5c7a; }

.ff-status-side { display: flex; align-items: center; }
.ff-status-gauge--squad { display: flex; flex-direction: column; align-items: center; gap: 6px; }
.ff-gauge-wrap { position: relative; }
.ff-gauge--squad { background: conic-gradient(var(--nocturne-green, #00ef87) calc(var(--pct) * 1%), #1c2240 0); }
.ff-gauge--squad .ff-gauge__value { font-size: 14px; }
.ff-gauge--squad .ff-gauge__value small { font-size: 10px; color: var(--nocturne-muted, #929abc); font-weight: 700; }

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

.ff-insight-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
.ff-insight-chip { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; color: var(--nocturne-muted, #929abc); font-weight: 600; }
.ff-insight-chip i { color: var(--nocturne-green, #00ef87); }
.ff-insight-chip b { color: #fff; }
.ff-affordable-toggle { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; }
.ff-affordable-toggle input { position: absolute; opacity: 0; width: 1px; height: 1px; }
.ff-affordable-toggle__track { width: 38px; height: 22px; border-radius: 999px; background: #1c2240; border: 1px solid #2c3453; position: relative; transition: background .15s ease; }
.ff-affordable-toggle__thumb { position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%; background: var(--nocturne-muted, #929abc); transition: transform .15s ease, background .15s ease; }
.ff-affordable-toggle input:checked + .ff-affordable-toggle__track { background: rgba(0,239,135,.18); border-color: var(--nocturne-green, #00ef87); }
.ff-affordable-toggle input:checked + .ff-affordable-toggle__track .ff-affordable-toggle__thumb { transform: translateX(16px); background: var(--nocturne-green, #00ef87); }
.ff-affordable-toggle__label { font-size: 12px; font-weight: 700; color: var(--nocturne-text, #f5f6ff); }

.ff-mode-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
.ff-mode-toggle { display: flex; gap: 6px; background: #0d1023; border: 1px solid #2c3453; border-radius: 999px; padding: 3px; }
.ff-mode-btn { background: transparent; border: 0; color: var(--nocturne-muted, #929abc); font-size: 11.5px; font-weight: 800; padding: 7px 13px; border-radius: 999px; cursor: pointer; transition: all .15s ease; }
.ff-mode-btn.is-active { background: var(--nocturne-green, #00ef87); color: #073c2a; }
.ff-history-actions { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.ff-icon-btn {
    width: 36px; height: 36px; border-radius: 10px; background: #0d1023; border: 1px solid #2c3453; color: var(--nocturne-muted, #929abc);
    display: grid; place-items: center; cursor: pointer; font-size: 14px; transition: all .15s ease;
}
.ff-icon-btn:hover:not(:disabled) { border-color: var(--nocturne-green, #00ef87); color: var(--nocturne-green, #00ef87); }
.ff-icon-btn:disabled { opacity: .35; cursor: not-allowed; }
.ff-history-actions .btn--sm { padding: 8px 14px; font-size: 12px; min-height: 36px; display: inline-flex; align-items: center; gap: 6px; }

.ff-guided-stepper { background: #0d1023; border: 1px solid #2c3453; border-radius: 14px; padding: 14px 16px; margin-bottom: 14px; }
.ff-guided-steps { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px; }
.ff-guided-step { flex: 1 1 0; min-width: 70px; background: #171c38; border: 1px solid #2c3453; color: var(--nocturne-muted, #929abc); font-size: 11px; font-weight: 800; padding: 8px 6px; border-radius: 9px; cursor: pointer; text-align: center; }
.ff-guided-step.is-active { background: var(--nocturne-green, #00ef87); color: #073c2a; border-color: var(--nocturne-green, #00ef87); }
.ff-guided-step.is-done { border-color: var(--nocturne-green, #00ef87); color: var(--nocturne-green, #00ef87); }
.ff-guided-nav { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.ff-guided-hint { font-size: 12px; color: var(--nocturne-muted, #929abc); flex: 1; text-align: center; }
.ff-guided-nav .btn--sm { padding: 8px 16px; font-size: 12px; min-height: 36px; }

.ff-planner-panel { background: #0d1023; border: 1px solid #2c3453; border-radius: 14px; padding: 16px; margin-bottom: 14px; }
.ff-planner-head h3 { margin: 0 0 4px; font-size: 13.5px; color: #fff; display: flex; align-items: center; gap: 7px; }
.ff-planner-head p { margin: 0 0 12px; font-size: 11.5px; color: var(--nocturne-muted, #929abc); }
.ff-planner-rows { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px; }
.ff-planner-row__head { display: flex; justify-content: space-between; font-size: 11.5px; font-weight: 700; color: var(--nocturne-text, #f5f6ff); margin-bottom: 4px; }
.ff-planner-row__value { color: var(--nocturne-green, #00ef87); }
.ff-planner-slider { width: 100%; accent-color: var(--nocturne-green, #00ef87); }
.ff-planner-row__per-slot { display: block; font-size: 10px; color: var(--nocturne-muted, #929abc); margin-top: 2px; }

.ff-build-filters { margin-bottom: 10px; }
.ff-position-pills { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; margin-bottom: 10px; }
.ff-pos-pill {
    flex: 0 0 auto; background: #0d1023; border: 1px solid #2c3453; color: var(--nocturne-muted, #929abc);
    font-size: 12.5px; font-weight: 800; padding: 8px 16px; border-radius: 999px; cursor: pointer; transition: all .15s ease;
}
.ff-pos-pill.is-active { color: #073c2a; background: var(--nocturne-green, #00ef87); border-color: var(--nocturne-green, #00ef87); }
.ff-pos-pill--gk.is-active { background: #ffd166; border-color: #ffd166; }
.ff-pos-pill--def.is-active { background: #38bdf8; border-color: #38bdf8; }
.ff-pos-pill--fwd.is-active { background: #ff9a4d; border-color: #ff9a4d; }
.ff-filter-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
.ff-search-wrap {
    display: flex; align-items: center; gap: 8px; background: #0d1023; border: 1px solid #2c3453; border-radius: 12px;
    padding: 0 14px; height: 46px; flex: 1; min-width: 160px;
}
.ff-search-wrap i { color: var(--nocturne-muted, #929abc); font-size: 15px; }
.ff-search-wrap input { flex: 1; background: transparent; border: 0; color: #fff; font-size: 13.5px; outline: 0; min-width: 0; height: 44px; }
.ff-filters-btn {
    display: inline-flex; align-items: center; gap: 8px; background: #0d1023; border: 1px solid #2c3453; color: var(--nocturne-text, #f5f6ff);
    font-size: 13px; font-weight: 700; padding: 0 16px; height: 46px; border-radius: 12px; cursor: pointer; position: relative;
}
.ff-filters-btn:hover { border-color: var(--nocturne-green, #00ef87); }
.ff-filters-count { background: var(--nocturne-green, #00ef87); color: #073c2a; font-size: 10px; font-weight: 800; padding: 1px 6px; border-radius: 999px; }
.ff-quick-chips { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; }
.ff-quick-chip {
    flex: 0 0 auto; display: inline-flex; align-items: center; gap: 5px; background: rgba(255,255,255,.04); border: 1px solid #2c3453;
    color: var(--nocturne-muted, #929abc); font-size: 11.5px; font-weight: 700; padding: 7px 13px; border-radius: 999px; cursor: pointer; transition: all .15s ease;
}
.ff-quick-chip.is-active { background: rgba(0,239,135,.14); border-color: var(--nocturne-green, #00ef87); color: var(--nocturne-green, #00ef87); }

.ff-player-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; margin-bottom: 18px; }
.ff-player-card {
    position: relative; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 4px;
    background: linear-gradient(180deg, #11162e, #0d1023); border: 1px solid #242b4a; border-radius: 16px;
    padding: 16px 10px 12px; cursor: pointer; transition: transform .15s ease, border-color .15s ease, box-shadow .15s ease;
}
.ff-player-card:hover { transform: translateY(-2px); border-color: #3a4374; }
.ff-player-card:has(input:checked) { border-color: var(--nocturne-green, #00ef87); box-shadow: 0 0 0 3px rgba(0,239,135,.14), 0 10px 24px -12px rgba(0,239,135,.4); }
.ff-player-card:has(input:disabled):not(:has(input:checked)) { opacity: .35; cursor: not-allowed; pointer-events: none; }
.ff-player-card--budget-locked:has(input:disabled):not(:has(input:checked)) { opacity: .55; }
.ff-player-card--budget-locked .ff-player-card__price { color: #ff5c7a; }
.ff-player-card--compared { border-color: #38bdf8; box-shadow: 0 0 0 3px rgba(56,189,248,.18); }
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
.ff-player-card__flag { position: absolute; top: 34px; left: 8px; color: #ff5c7a; font-size: 13px; }
.ff-player-card__compare {
    position: absolute; top: 8px; right: 32px; width: 20px; height: 20px; border-radius: 50%; background: rgba(255,255,255,.06);
    border: 1px solid #2c3453; color: var(--nocturne-muted, #929abc); font-size: 10px; display: none; place-items: center; cursor: pointer; z-index: 2;
    pointer-events: auto; /* a disabled (unaffordable/full) card's label sets pointer-events:none on
    itself and every descendant - comparison must still work on a card you can't currently pick. */
}
.ff-build-form.is-compare-mode .ff-player-card__compare, body.ff-compare-mode .ff-player-card__compare { display: grid; }
.ff-player-card__compare.is-selected { background: #38bdf8; color: #072035; border-color: #38bdf8; }
.ff-player-card__shirt { margin: 8px 0 4px; filter: drop-shadow(0 6px 10px rgba(0,0,0,.35)); }
.ff-player-card__name { font-size: 12.5px; font-weight: 800; color: #fff; line-height: 1.25; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ff-player-card__club { font-size: 10.5px; color: var(--nocturne-muted, #929abc); font-weight: 700; letter-spacing: .04em; }
.ff-player-card__stats { display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 4px; font-size: 11.5px; }
.ff-player-card__rating { display: inline-flex; align-items: center; gap: 3px; color: #ffd166; font-weight: 700; }
.ff-player-card__price { color: var(--nocturne-green, #00ef87); font-weight: 800; }
.ff-player-card__meta { display: flex; gap: 8px; font-size: 9.5px; color: var(--nocturne-muted, #929abc); font-weight: 600; }
.ff-player-card__fixtures { display: flex; gap: 3px; margin-top: 3px; flex-wrap: wrap; justify-content: center; }
.ff-fx-dot { font-size: 8px; font-weight: 800; padding: 2px 4px; border-radius: 4px; color: #0b0d1a; }
.ff-fx-dot--easy { background: #00ef87; }
.ff-fx-dot--medium { background: #ffd166; }
.ff-fx-dot--hard { background: #ff5c7a; color: #fff; }
.ff-no-results { grid-column: 1 / -1; text-align: center; color: var(--nocturne-muted, #929abc); padding: 30px 0; }

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

/* Filter bottom sheet (mobile) / side panel (desktop) */
.ff-sheet-overlay { position: fixed; inset: 0; z-index: 60; background: rgba(5,7,16,.6); display: flex; align-items: flex-end; justify-content: center; }
.ff-sheet-overlay.d-none { display: none; }
.ff-sheet {
    width: 100%; max-width: 480px; max-height: 86vh; background: #10142b; border: 1px solid #2c3453; border-radius: 22px 22px 0 0;
    display: flex; flex-direction: column; box-shadow: 0 -20px 50px -20px rgba(0,0,0,.7);
}
.ff-sheet__handle { width: 42px; height: 4px; border-radius: 999px; background: #2c3453; margin: 10px auto 4px; }
.ff-sheet__head { display: flex; align-items: center; justify-content: space-between; padding: 8px 20px 12px; border-bottom: 1px solid #242b4a; }
.ff-sheet__head h3 { margin: 0; font-size: 15px; color: #fff; }
.ff-sheet__close { background: #1c2240; border: 1px solid #2c3453; color: var(--nocturne-muted, #929abc); width: 30px; height: 30px; border-radius: 50%; cursor: pointer; }
.ff-sheet__body { flex: 1; overflow-y: auto; padding: 16px 20px; }
.ff-sheet-group { margin-bottom: 20px; }
.ff-sheet-label { display: block; font-size: 11.5px; font-weight: 800; color: var(--nocturne-muted, #929abc); text-transform: uppercase; letter-spacing: .04em; margin-bottom: 8px; }
.ff-sheet-select { width: 100%; background: #0d1023; border: 1px solid #2c3453; color: #fff; font-size: 13.5px; height: 44px; border-radius: 11px; padding: 0 12px; }
.ff-range-dual { position: relative; height: 30px; }
.ff-range-dual input[type=range] { position: absolute; width: 100%; top: 10px; background: transparent; pointer-events: none; accent-color: var(--nocturne-green, #00ef87); appearance: none; -webkit-appearance: none; }
.ff-range-dual input[type=range]::-webkit-slider-thumb { pointer-events: auto; -webkit-appearance: none; width: 16px; height: 16px; border-radius: 50%; background: var(--nocturne-green, #00ef87); cursor: pointer; }
.ff-range-dual input[type=range]::-moz-range-thumb { pointer-events: auto; width: 16px; height: 16px; border-radius: 50%; background: var(--nocturne-green, #00ef87); cursor: pointer; border: 0; }
.ff-chip-group { display: flex; gap: 8px; flex-wrap: wrap; }
.ff-chip-option { background: #0d1023; border: 1px solid #2c3453; color: var(--nocturne-muted, #929abc); font-size: 12px; font-weight: 700; padding: 7px 14px; border-radius: 999px; cursor: pointer; }
.ff-chip-option.is-active { background: var(--nocturne-green, #00ef87); color: #073c2a; border-color: var(--nocturne-green, #00ef87); }
.ff-club-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(70px, 1fr)); gap: 8px; max-height: 220px; overflow-y: auto; }
.ff-club-check { display: flex; align-items: center; gap: 6px; background: #0d1023; border: 1px solid #2c3453; border-radius: 9px; padding: 7px 8px; font-size: 11.5px; font-weight: 700; color: var(--nocturne-muted, #929abc); cursor: pointer; }
.ff-club-check:has(input:checked) { border-color: var(--nocturne-green, #00ef87); color: var(--nocturne-green, #00ef87); background: rgba(0,239,135,.08); }
.ff-club-check input { accent-color: var(--nocturne-green, #00ef87); }
.ff-sheet__foot { display: flex; gap: 10px; padding: 14px 20px calc(16px + env(safe-area-inset-bottom)); border-top: 1px solid #242b4a; }
.ff-sheet__foot .btn { flex: 1; }

.ff-compare-panel {
    position: fixed; left: 50%; transform: translateX(-50%); bottom: 14px; z-index: 40; width: min(600px, calc(100vw - 28px));
    background: #10142b; border: 1px solid #2c3453; border-radius: 18px; box-shadow: 0 20px 50px -20px rgba(0,0,0,.7); overflow: hidden;
}
.ff-compare-panel.d-none { display: none; }
.ff-compare-head { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid #242b4a; }
.ff-compare-head h4 { margin: 0; font-size: 13.5px; color: #fff; display: flex; align-items: center; gap: 6px; }
.ff-compare-body { padding: 14px 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; max-height: 40vh; overflow-y: auto; }
.ff-compare-empty { grid-column: 1 / -1; text-align: center; color: var(--nocturne-muted, #929abc); font-size: 12.5px; padding: 10px 0; }
.ff-compare-card { background: #0d1023; border: 1px solid #2c3453; border-radius: 12px; padding: 12px; font-size: 12px; }
.ff-compare-card h5 { margin: 0 0 8px; font-size: 13px; color: #fff; }
.ff-compare-card dl { margin: 0; display: grid; grid-template-columns: auto auto; gap: 4px 10px; }
.ff-compare-card dt { color: var(--nocturne-muted, #929abc); }
.ff-compare-card dd { margin: 0; text-align: right; color: #fff; font-weight: 700; }

@media (prefers-reduced-motion: reduce) {
    .ff-player-card, .ff-player-card__check, .ff-pos-track__fill, .ff-gauge, .ff-pos-pill { transition: none !important; }
    .ff-player-card:hover { transform: none; }
}
@media (max-width: 1199.98px) {
    .ff-confirm-bar { bottom: calc(96px + env(safe-area-inset-bottom)); border-radius: 16px 16px 0 0; }
    .ff-confirm-spacer { height: calc(78px + 96px + env(safe-area-inset-bottom)); }
    .ff-compare-panel { bottom: calc(96px + env(safe-area-inset-bottom) + 14px); }
}
@media (min-width: 992px) {
    .ff-sheet-overlay { align-items: stretch; justify-content: flex-end; }
    .ff-sheet { max-width: 400px; max-height: 100vh; border-radius: 0; }
}
@media (max-width: 767.98px) {
    .ff-status-panel { grid-template-columns: auto 1fr auto; gap: 14px; }
    .ff-compare-body { grid-template-columns: 1fr; }
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
    var avgSlotEl = document.getElementById('ff-avg-slot');
    var affordableOnly = document.getElementById('ff-affordable-only');
    var required = { GK: 2, DEF: 5, MID: 5, FWD: 3 };
    var labels = { GK: 'goalkeeper', DEF: 'defender', MID: 'midfielder', FWD: 'forward' };
    var BUDGET = 100;
    var DRAFT_KEY = 'ff_build_draft_v1';

    // ---------- Popover (unchanged behaviour from the shipped fix) ----------
    function positionPopover() {
        var btnRect = infoBtn.getBoundingClientRect();
        var popWidth = popover.offsetWidth || 270;
        var top = btnRect.bottom + 8;
        var left = Math.min(btnRect.left, window.innerWidth - popWidth - 12);
        popover.style.top = Math.max(8, top) + 'px';
        popover.style.left = Math.max(8, left) + 'px';
    }
    function openPopover() { popover.hidden = false; infoBtn.setAttribute('aria-expanded', 'true'); positionPopover(); }
    function closePopover() { popover.hidden = true; infoBtn.setAttribute('aria-expanded', 'false'); }
    infoBtn.addEventListener('click', function(e) { e.stopPropagation(); if (popover.hidden) openPopover(); else closePopover(); });
    document.addEventListener('click', function(e) { if (!popover.hidden && !popover.contains(e.target) && e.target !== infoBtn) closePopover(); });
    window.addEventListener('scroll', function() { if (!popover.hidden) positionPopover(); }, true);
    window.addEventListener('resize', function() { if (!popover.hidden) positionPopover(); });

    // ---------- Undo / redo / reset history ----------
    var history = [[]];
    var historyIndex = 0;
    var applyingHistory = false;
    var undoBtn = document.getElementById('ff-undo-btn');
    var redoBtn = document.getElementById('ff-redo-btn');
    var resetBtn = document.getElementById('ff-reset-btn');

    function currentSelectionIds() {
        return cards.filter(function(c) { return c.querySelector('input').checked; }).map(function(c) { return c.querySelector('input').value; });
    }
    function applySelection(ids) {
        applyingHistory = true;
        var idSet = {};
        ids.forEach(function(id) { idSet[id] = true; });
        cards.forEach(function(c) {
            var input = c.querySelector('input');
            input.checked = !!idSet[input.value];
        });
        applyingHistory = false;
        recompute();
        saveDraft();
    }
    function pushHistory() {
        if (applyingHistory) return;
        var ids = currentSelectionIds();
        history = history.slice(0, historyIndex + 1);
        history.push(ids);
        historyIndex = history.length - 1;
        updateHistoryButtons();
    }
    function updateHistoryButtons() {
        undoBtn.disabled = historyIndex <= 0;
        redoBtn.disabled = historyIndex >= history.length - 1;
    }
    undoBtn.addEventListener('click', function() { if (historyIndex > 0) { historyIndex--; applySelection(history[historyIndex]); updateHistoryButtons(); } });
    redoBtn.addEventListener('click', function() { if (historyIndex < history.length - 1) { historyIndex++; applySelection(history[historyIndex]); updateHistoryButtons(); } });
    resetBtn.addEventListener('click', function() {
        if (!currentSelectionIds().length) return;
        if (!confirm('Clear your whole squad selection?')) return;
        applySelection([]);
        pushHistory();
    });

    // ---------- Draft autosave (per device, this browser only) ----------
    function saveDraft() {
        try {
            localStorage.setItem(DRAFT_KEY, JSON.stringify({ name: document.getElementById('ff-team-name').value, ids: currentSelectionIds(), t: Date.now() }));
        } catch (e) { /* storage unavailable - draft saving is a convenience only */ }
    }
    function loadDraftBanner() {
        try {
            var raw = localStorage.getItem(DRAFT_KEY);
            if (!raw) return;
            var draft = JSON.parse(raw);
            if (!draft || !draft.ids || !draft.ids.length) return;
            document.getElementById('ff-draft-banner').classList.remove('d-none');
            document.getElementById('ff-draft-resume').addEventListener('click', function() {
                document.getElementById('ff-team-name').value = draft.name || '';
                applySelection(draft.ids);
                pushHistory();
                document.getElementById('ff-draft-banner').classList.add('d-none');
            });
            document.getElementById('ff-draft-dismiss').addEventListener('click', function() {
                document.getElementById('ff-draft-banner').classList.add('d-none');
            });
        } catch (e) { /* ignore corrupt/blocked storage */ }
    }
    document.getElementById('ff-team-name').addEventListener('input', saveDraft);
    form.addEventListener('submit', function() { try { localStorage.removeItem(DRAFT_KEY); } catch (e) {} });

    // ---------- Core recompute: budget, positions, per-slot affordability ----------
    function recompute() {
        var selected = cards.filter(function(c) { return c.querySelector('input').checked; });
        var spent = selected.reduce(function(s, c) { return s + parseFloat(c.dataset.price); }, 0);
        var counts = { GK: 0, DEF: 0, MID: 0, FWD: 0 };
        var clubCounts = {};
        selected.forEach(function(c) { counts[c.dataset.position]++; clubCounts[c.dataset.club] = (clubCounts[c.dataset.club] || 0) + 1; });

        var budgetLeft = (BUDGET - spent);
        budgetEls.forEach(function(el) { el.textContent = budgetLeft.toFixed(1); });
        countEls.forEach(function(el) { el.textContent = selected.length; });

        var spentPct = Math.max(0, Math.min(100, (spent / BUDGET) * 100));
        gauge.style.setProperty('--pct', spentPct);
        gauge.classList.toggle('is-warning', budgetLeft >= 0 && budgetLeft < 10);
        gauge.classList.toggle('is-over', budgetLeft < 0);

        var openSlots = 0;
        Object.keys(required).forEach(function(p) { openSlots += Math.max(0, required[p] - counts[p]); });
        avgSlotEl.textContent = openSlots > 0 ? Math.max(0, budgetLeft / openSlots).toFixed(1) : '0.0';

        // Cheapest still-eligible (not shape/club blocked) unselected card per position - the
        // "minimum price" the spec's max-for-slot formula reserves against, computed per
        // position so a squad can never be talked into a dead end by spending too much on one
        // slot while a DIFFERENT position's cheapest option costs more than the global minimum.
        var cheapestByPos = { GK: Infinity, DEF: Infinity, MID: Infinity, FWD: Infinity };
        cards.forEach(function(c) {
            var input = c.querySelector('input');
            if (input.checked) return;
            var pos = c.dataset.position;
            if (counts[pos] >= required[pos]) return;
            if ((clubCounts[c.dataset.club] || 0) >= 3) return;
            var price = parseFloat(c.dataset.price);
            if (price < cheapestByPos[pos]) cheapestByPos[pos] = price;
        });

        function maxForSlot(pos) {
            var reserve = 0;
            Object.keys(required).forEach(function(p) {
                var need = Math.max(0, required[p] - counts[p]) - (p === pos ? 1 : 0);
                if (need > 0) reserve += need * cheapestByPos[p];
            });
            return budgetLeft - reserve;
        }

        var maxPerPos = {};
        document.querySelectorAll('.ff-pos-track').forEach(function(track) {
            var pos = track.dataset.pos;
            var pct = Math.min(100, (counts[pos] / required[pos]) * 100);
            track.querySelector('.ff-pos-track__count').textContent = counts[pos];
            track.querySelector('.ff-pos-track__fill').style.width = pct + '%';
            track.classList.toggle('is-complete', counts[pos] === required[pos]);
            var m = counts[pos] >= required[pos] ? null : maxForSlot(pos);
            maxPerPos[pos] = m;
            var valueEl = track.querySelector('.ff-pos-track__max-value');
            if (m === null) { valueEl.textContent = '-'; track.classList.remove('is-tight', 'is-blocked'); }
            else {
                valueEl.textContent = isFinite(m) ? m.toFixed(1) : '-';
                track.classList.toggle('is-blocked', isFinite(m) ? m < 0 : true);
                track.classList.toggle('is-tight', isFinite(m) && m >= 0 && m < 2);
            }
        });
        squadGauge.style.setProperty('--pct', Math.min(100, (selected.length / 15) * 100));

        var shapeOk = Object.keys(required).every(function(p) { return counts[p] <= required[p]; });
        var clubOk = Object.values(clubCounts).every(function(c) { return c <= 3; });
        var exact = Object.keys(required).every(function(p) { return counts[p] === required[p]; });
        var canConfirm = exact && clubOk && budgetLeft >= 0 && selected.length === 15;
        submitBtn.disabled = !canConfirm;

        var affordableLeft = { GK: false, DEF: false, MID: false, FWD: false };
        cards.forEach(function(c) {
            var input = c.querySelector('input');
            if (!input.checked) {
                var price = parseFloat(c.dataset.price);
                var pos = c.dataset.position;
                var wouldExceedShape = counts[pos] >= required[pos] || (clubCounts[c.dataset.club] || 0) >= 3;
                // The per-position max-for-slot ceiling, not just the raw budget left - this is
                // what stops a squad from ever being talked into an impossible dead end, not
                // merely explaining one after the fact.
                var ceiling = maxPerPos[pos];
                var wouldExceedBudget = !wouldExceedShape && ceiling !== null && price > ceiling + 0.001;
                input.disabled = wouldExceedShape || wouldExceedBudget;
                var budgetLocked = !wouldExceedShape && wouldExceedBudget;
                c.classList.toggle('ff-player-card--budget-locked', budgetLocked);
                if (!wouldExceedShape && !wouldExceedBudget) affordableLeft[pos] = true;
            }
        });

        var reason = '';
        if (budgetLeft < 0) {
            reason = 'You are ' + Math.abs(budgetLeft).toFixed(1) + ' credits over budget - remove a player to continue.';
        } else {
            var missing = Object.keys(required).filter(function(p) { return counts[p] < required[p]; });
            if (missing.length) {
                reason = 'Pick ' + missing.map(function(p) { return (required[p] - counts[p]) + ' more ' + labels[p] + (required[p] - counts[p] > 1 ? 's' : ''); }).join(', ') + '.';
                var blocked = missing.filter(function(p) { return !affordableLeft[p]; });
                if (blocked.length) {
                    reason += ' No affordable ' + blocked.map(function(p) { return labels[p] + 's'; }).join('/') + ' left within budget - remove a pick to free up room.';
                }
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

        applyAffordableOnly();
        updateGuidedHint(counts);
        updatePlannerPerSlot(counts, budgetLeft);
    }
    form.addEventListener('change', function(e) {
        if (e.target.matches('input[type=checkbox]') && e.target.closest('.ff-player-card')) {
            recompute();
            if (!applyingHistory) { saveDraft(); pushHistory(); }
        }
    });

    // ---------- Position / club / search / affordable-only filtering ----------
    var activePos = '';
    var posPills = Array.from(document.querySelectorAll('.ff-pos-pill'));
    var searchFilter = document.getElementById('ff-filter-search');
    var noResults = document.getElementById('ff-no-results');
    var activeClubs = [];
    var priceMin = 0, priceMax = 15;
    var availabilityFilter = '';
    var sortMode = 'rating_desc';
    var activeQuickChip = null;

    function cardAffordable(c) {
        if (!affordableOnly.checked) return true;
        var input = c.querySelector('input');
        return !input.disabled || input.checked;
    }

    function applyFilters() {
        var term = searchFilter.value.toLowerCase();
        var visible = 0;
        var visibleCards = [];
        cards.forEach(function(c) {
            var price = parseFloat(c.dataset.price);
            var show = (!activePos || c.dataset.position === activePos)
                && (!activeClubs.length || activeClubs.indexOf(c.dataset.club) !== -1)
                && (!term || c.dataset.name.indexOf(term) !== -1)
                && (price >= priceMin && price <= priceMax)
                && (!availabilityFilter || c.dataset.status === availabilityFilter)
                && cardAffordable(c);
            c.hidden = !show;
            if (show) { visible++; visibleCards.push(c); }
        });
        noResults.classList.toggle('d-none', visible !== 0);
        sortVisible(visibleCards);
        refreshFiltersCount();
    }
    function applyAffordableOnly() { applyFilters(); }

    function sortVisible(visibleCards) {
        var grid = document.getElementById('ff-player-grid');
        var key = { rating_desc: 'rating', price_desc: 'price', price_asc: 'price', points_desc: 'points', form_desc: 'form', value_desc: 'value', ownership_desc: 'ownership', ownership_asc: 'ownership', difficulty_asc: 'fixtures' }[sortMode];
        var dir = sortMode.indexOf('_asc') !== -1 ? 1 : -1;
        visibleCards.sort(function(a, b) {
            var av, bv;
            if (key === 'rating') { av = a.querySelector('.ff-player-card__rating').textContent; bv = b.querySelector('.ff-player-card__rating').textContent; }
            else if (key === 'fixtures') {
                av = difficultyScore(a.dataset.fixtures); bv = difficultyScore(b.dataset.fixtures);
            } else { av = parseFloat(a.dataset[key] || 0) || 0; bv = parseFloat(b.dataset[key] || 0) || 0; }
            return (av - bv) * -dir;
        });
        visibleCards.forEach(function(c) { grid.appendChild(c); });
        var noRes = document.getElementById('ff-no-results');
        grid.appendChild(noRes);
    }
    function difficultyScore(json) {
        try {
            var fixtures = JSON.parse(json || '[]');
            var map = { easy: 1, medium: 2, hard: 3 };
            if (!fixtures.length) return 2;
            return fixtures.reduce(function(s, f) { return s + (map[f.difficulty] || 2); }, 0) / fixtures.length;
        } catch (e) { return 2; }
    }
    posPills.forEach(function(pill) {
        pill.addEventListener('click', function() {
            activePos = pill.dataset.pos;
            posPills.forEach(function(p) { p.classList.toggle('is-active', p === pill); });
            applyFilters();
        });
    });
    searchFilter.addEventListener('input', applyFilters);
    affordableOnly.addEventListener('change', applyFilters);

    // ---------- Quick chips ----------
    document.querySelectorAll('.ff-quick-chip').forEach(function(chip) {
        chip.addEventListener('click', function() {
            var isActive = chip.classList.contains('is-active');
            document.querySelectorAll('.ff-quick-chip').forEach(function(c) { c.classList.remove('is-active'); });
            activeQuickChip = null;
            priceMin = 0; priceMax = 15; availabilityFilter = '';
            if (!isActive) {
                chip.classList.add('is-active');
                activeQuickChip = chip.dataset.chip;
                var prices = cards.map(function(c) { return parseFloat(c.dataset.price); }).sort(function(a, b) { return a - b; });
                var p25 = prices[Math.floor(prices.length * 0.25)] || 0, p75 = prices[Math.floor(prices.length * 0.75)] || 15;
                if (activeQuickChip === 'budget') { priceMax = p25; sortMode = 'value_desc'; }
                else if (activeQuickChip === 'premium') { priceMin = p75; sortMode = 'rating_desc'; }
                else if (activeQuickChip === 'value') { sortMode = 'value_desc'; }
                else if (activeQuickChip === 'form') { sortMode = 'form_desc'; }
                else if (activeQuickChip === 'differential') { sortMode = 'ownership_asc'; }
                document.getElementById('ff-filter-sort').value = sortMode;
            } else {
                sortMode = 'rating_desc';
                document.getElementById('ff-filter-sort').value = sortMode;
            }
            syncPriceSliders();
            applyFilters();
        });
    });

    // ---------- Filter sheet ----------
    var overlay = document.getElementById('ff-filter-sheet-overlay');
    var filtersOpenBtn = document.getElementById('ff-filters-open');
    var filtersCloseBtn = document.getElementById('ff-filters-close');
    var sortSelect = document.getElementById('ff-filter-sort');
    var priceMinSlider = document.getElementById('ff-price-min');
    var priceMaxSlider = document.getElementById('ff-price-max');
    var priceLabel = document.getElementById('ff-price-range-label');
    var clubChecks = Array.from(document.querySelectorAll('#ff-club-grid input'));

    (function initPriceRange() {
        var maxPrice = Math.max.apply(null, cards.map(function(c) { return parseFloat(c.dataset.price); }).concat([15]));
        priceMinSlider.max = priceMaxSlider.max = Math.ceil(maxPrice);
        priceMaxSlider.value = Math.ceil(maxPrice);
        priceMax = Math.ceil(maxPrice);
        syncPriceSliders();
    })();
    function syncPriceSliders() {
        priceMinSlider.value = priceMin;
        priceMaxSlider.value = priceMax;
        priceLabel.textContent = parseFloat(priceMinSlider.value).toFixed(1) + ' - ' + parseFloat(priceMaxSlider.value).toFixed(1);
    }
    function onPriceSlide() {
        var lo = parseFloat(priceMinSlider.value), hi = parseFloat(priceMaxSlider.value);
        if (lo > hi) { var t = lo; lo = hi; hi = t; }
        priceMin = lo; priceMax = hi;
        priceLabel.textContent = lo.toFixed(1) + ' - ' + hi.toFixed(1);
    }
    [priceMinSlider, priceMaxSlider].forEach(function(el) { el.addEventListener('input', onPriceSlide); });

    function openSheet() { overlay.classList.remove('d-none'); }
    function closeSheet() { overlay.classList.add('d-none'); }
    filtersOpenBtn.addEventListener('click', openSheet);
    filtersCloseBtn.addEventListener('click', closeSheet);
    overlay.addEventListener('click', function(e) { if (e.target === overlay) closeSheet(); });

    document.querySelectorAll('#ff-availability-group .ff-chip-option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            document.querySelectorAll('#ff-availability-group .ff-chip-option').forEach(function(o) { o.classList.remove('is-active'); });
            opt.classList.add('is-active');
            availabilityFilter = opt.dataset.value;
        });
    });

    document.getElementById('ff-filters-apply').addEventListener('click', function() {
        sortMode = sortSelect.value;
        activeClubs = clubChecks.filter(function(c) { return c.checked; }).map(function(c) { return c.value; });
        applyFilters();
        closeSheet();
    });
    document.getElementById('ff-filters-clear').addEventListener('click', function() {
        sortSelect.value = sortMode = 'rating_desc';
        clubChecks.forEach(function(c) { c.checked = false; });
        activeClubs = [];
        priceMin = 0; priceMax = parseFloat(priceMaxSlider.max);
        availabilityFilter = '';
        document.querySelectorAll('#ff-availability-group .ff-chip-option').forEach(function(o, i) { o.classList.toggle('is-active', i === 0); });
        syncPriceSliders();
        applyFilters();
    });
    function refreshFiltersCount() {
        var n = (activeClubs.length ? 1 : 0) + (availabilityFilter ? 1 : 0) + (priceMin > 0 || priceMax < parseFloat(priceMaxSlider.max) ? 1 : 0) + (sortMode !== 'rating_desc' ? 1 : 0);
        var el = document.getElementById('ff-filters-count');
        el.textContent = n;
        el.classList.toggle('d-none', n === 0);
    }

    // ---------- Compare mode ----------
    var compareMode = false;
    var compareIds = [];
    var comparePanel = document.getElementById('ff-compare-panel');
    var compareBody = document.getElementById('ff-compare-body');
    document.getElementById('ff-compare-close').addEventListener('click', function() { comparePanel.classList.add('d-none'); });

    function renderCompare() {
        if (!compareIds.length) { comparePanel.classList.add('d-none'); return; }
        comparePanel.classList.remove('d-none');
        compareBody.innerHTML = '';
        compareIds.forEach(function(id) {
            var card = cards.find(function(c) { return c.querySelector('input').value === id; });
            if (!card) return;
            var fixtures = JSON.parse(card.dataset.fixtures || '[]');
            var html = '<div class="ff-compare-card"><h5>' + card.querySelector('.ff-player-card__name').textContent + ' <small>(' + card.dataset.position + ')</small></h5><dl>'
                + '<dt>Price</dt><dd>' + parseFloat(card.dataset.price).toFixed(1) + '</dd>'
                + '<dt>Rating</dt><dd>' + card.querySelector('.ff-player-card__rating').textContent.replace(/[^0-9.]/g, '') + '</dd>'
                + '<dt>Points</dt><dd>' + (card.dataset.points || 0) + '</dd>'
                + '<dt>Form</dt><dd>' + (card.dataset.form || '-') + '</dd>'
                + '<dt>Ownership</dt><dd>' + (card.dataset.ownership || 0) + '%</dd>'
                + '<dt>Value</dt><dd>' + (card.dataset.value || 0) + '</dd>'
                + '<dt>Next</dt><dd>' + fixtures.map(function(f) { return f.opponent; }).join(', ') + '</dd>'
                + '</dl></div>';
            compareBody.insertAdjacentHTML('beforeend', html);
        });
        if (compareIds.length === 1) compareBody.insertAdjacentHTML('beforeend', '<p class="ff-compare-empty">Pick a second player to compare.</p>');
    }
    document.querySelectorAll('.ff-player-card__compare').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault(); e.stopPropagation();
            var id = btn.dataset.compareId;
            var idx = compareIds.indexOf(id);
            if (idx !== -1) { compareIds.splice(idx, 1); btn.classList.remove('is-selected'); }
            else {
                if (compareIds.length >= 2) { var removedId = compareIds.shift(); var removedBtn = document.querySelector('.ff-player-card__compare[data-compare-id="' + removedId + '"]'); if (removedBtn) removedBtn.classList.remove('is-selected'); }
                compareIds.push(id); btn.classList.add('is-selected');
            }
            renderCompare();
        });
    });

    // ---------- Mode toggle (browse / guided / compare) ----------
    var modeBtns = Array.from(document.querySelectorAll('.ff-mode-btn'));
    var guidedStepper = document.getElementById('ff-guided-stepper');
    modeBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            modeBtns.forEach(function(b) { b.classList.toggle('is-active', b === btn); });
            var mode = btn.dataset.mode;
            guidedStepper.classList.toggle('d-none', mode !== 'guided');
            compareMode = mode === 'compare';
            document.body.classList.toggle('ff-compare-mode', compareMode);
            if (mode === 'guided') goToStep('GK');
            if (mode !== 'compare') { comparePanel.classList.toggle('d-none', !compareIds.length); }
        });
    });

    // ---------- Guided step-by-step builder ----------
    var guidedSteps = ['GK', 'DEF', 'MID', 'FWD', 'REVIEW'];
    var currentStep = 'GK';
    var guidedBack = document.getElementById('ff-guided-back');
    var guidedNext = document.getElementById('ff-guided-next');
    var guidedHint = document.getElementById('ff-guided-hint');
    function goToStep(step) {
        currentStep = step;
        document.querySelectorAll('.ff-guided-step').forEach(function(b) { b.classList.toggle('is-active', b.dataset.step === step); });
        if (step === 'REVIEW') { activePos = ''; posPills.forEach(function(p) { p.classList.toggle('is-active', p.dataset.pos === ''); }); }
        else { activePos = step; posPills.forEach(function(p) { p.classList.toggle('is-active', p.dataset.pos === step); }); }
        applyFilters();
        guidedBack.disabled = guidedSteps.indexOf(step) === 0;
        guidedNext.textContent = step === 'REVIEW' ? 'Done' : 'Next';
    }
    document.querySelectorAll('.ff-guided-step').forEach(function(b) { b.addEventListener('click', function() { goToStep(b.dataset.step); }); });
    guidedBack.addEventListener('click', function() { var i = guidedSteps.indexOf(currentStep); if (i > 0) goToStep(guidedSteps[i - 1]); });
    guidedNext.addEventListener('click', function() { var i = guidedSteps.indexOf(currentStep); if (i < guidedSteps.length - 1) goToStep(guidedSteps[i + 1]); });
    function updateGuidedHint(counts) {
        if (guidedStepper.classList.contains('d-none') || currentStep === 'REVIEW') { guidedHint.textContent = ''; return; }
        var need = required[currentStep] - counts[currentStep];
        guidedHint.textContent = need > 0 ? (need + ' more ' + labels[currentStep] + (need > 1 ? 's' : '') + ' needed') : 'Step complete - move on when ready';
        document.querySelectorAll('.ff-guided-step').forEach(function(b) {
            var p = b.dataset.step;
            if (p === 'REVIEW') return;
            b.classList.toggle('is-done', counts[p] >= required[p]);
        });
    }

    // ---------- Budget planner (guide only, never restricts picks) ----------
    var plannerBtn = document.getElementById('ff-planner-btn');
    var plannerPanel = document.getElementById('ff-planner-panel');
    plannerBtn.addEventListener('click', function() { plannerPanel.classList.toggle('d-none'); });
    var plannerSliders = Array.from(document.querySelectorAll('.ff-planner-slider'));
    plannerSliders.forEach(function(slider) {
        slider.addEventListener('input', function() {
            slider.closest('.ff-planner-row').querySelector('.ff-planner-row__value').textContent = parseFloat(slider.value).toFixed(1);
            updatePlannerPerSlot();
        });
    });
    function updatePlannerPerSlot(counts, budgetLeft) {
        plannerSliders.forEach(function(slider) {
            var pos = slider.dataset.pos;
            var open = counts ? Math.max(0, required[pos] - counts[pos]) : required[pos];
            var perSlotEl = slider.closest('.ff-planner-row').querySelector('.ff-planner-row__per-slot');
            var target = parseFloat(slider.value);
            perSlotEl.textContent = open > 0 ? ('≈ ' + (target / open).toFixed(1) + ' per remaining slot') : 'complete';
        });
    }

    // ---------- Auto-fill the rest: greedy best-value, guaranteed valid or not touched ----------
    document.getElementById('ff-autofill-btn').addEventListener('click', function() {
        var selected = cards.filter(function(c) { return c.querySelector('input').checked; });
        var counts = { GK: 0, DEF: 0, MID: 0, FWD: 0 };
        var clubCounts = {};
        selected.forEach(function(c) { counts[c.dataset.position]++; clubCounts[c.dataset.club] = (clubCounts[c.dataset.club] || 0) + 1; });
        var spent = selected.reduce(function(s, c) { return s + parseFloat(c.dataset.price); }, 0);
        var budgetLeft = BUDGET - spent;

        var pool = cards.filter(function(c) { return !c.querySelector('input').checked; }).map(function(c) {
            var points = parseFloat(c.dataset.points) || 0;
            var rating = parseFloat(c.querySelector('.ff-player-card__rating').textContent.replace(/[^0-9.]/g, '')) || 0;
            var price = parseFloat(c.dataset.price);
            var score = (points > 0 ? points : rating) / Math.max(0.5, price);
            return { card: c, pos: c.dataset.position, club: c.dataset.club, price: price, score: score };
        }).sort(function(a, b) { return b.score - a.score; });

        var picks = [];
        Object.keys(required).forEach(function(pos) {
            var need = required[pos] - counts[pos];
            for (var i = 0; i < need; i++) {
                var candidate = pool.find(function(p) {
                    if (p.pos !== pos) return false;
                    if (picks.indexOf(p) !== -1) return false;
                    var usedClub = (clubCounts[p.club] || 0) + picks.filter(function(x) { return x.club === p.club; }).length;
                    if (usedClub >= 3) return false;
                    var alreadyPicked = picks.reduce(function(s, x) { return s + x.price; }, 0);
                    return (p.price + alreadyPicked) <= budgetLeft + 0.001;
                });
                if (!candidate) {
                    // Fall back to the cheapest legal remaining option for this slot so auto-fill
                    // always finishes a valid squad rather than silently leaving a gap.
                    candidate = pool.filter(function(p) {
                        if (p.pos !== pos || picks.indexOf(p) !== -1) return false;
                        var usedClub = (clubCounts[p.club] || 0) + picks.filter(function(x) { return x.club === p.club; }).length;
                        return usedClub < 3;
                    }).sort(function(a, b) { return a.price - b.price; })[0];
                }
                if (candidate) picks.push(candidate);
            }
        });
        var totalPickCost = picks.reduce(function(s, p) { return s + p.price; }, 0);
        if (totalPickCost > budgetLeft + 0.001) {
            alert('Auto-fill could not find a combination within your remaining budget - try removing an expensive pick first.');
            return;
        }
        picks.forEach(function(p) { p.card.querySelector('input').checked = true; });
        recompute();
        saveDraft();
        pushHistory();
    });

    loadDraftBanner();
    recompute();
    updateHistoryButtons();
})();
</script>
@endpush
