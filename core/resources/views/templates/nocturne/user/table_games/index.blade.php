@extends('Template::layouts.master')
@section('content')
    <section class="table-lobby" data-create-url="{{ route('user.tables.create') }}" data-leaderboard-url="{{ route('user.tables.leaderboard') }}" data-csrf="{{ csrf_token() }}">
        <header class="table-lobby__hero">
            <div class="table-lobby__hero-text">
                <span class="table-lobby__eyebrow">@lang('Play together')</span>
                <h1>@lang('Live Tables')</h1>
                <p>@lang('Choose a game and take your seat. Moves and table balances are confirmed by the game server.')</p>
            </div>
            <div class="table-lobby__hero-stats">
                <span class="table-lobby__live-badge"><i></i>{{ count($tables) }} @lang('live now')</span>
                <button type="button" class="table-lobby__fame-trigger" id="hall-of-fame-open"><i class="las la-trophy"></i>@lang('Hall of Fame')</button>
            </div>
        </header>

        <div class="hall-of-fame" id="hall-of-fame" hidden role="dialog" aria-modal="true" aria-labelledby="hall-of-fame-title">
            <div class="hall-of-fame__backdrop" id="hall-of-fame-backdrop"></div>
            <div class="hall-of-fame__sheet">
                <header><span class="hall-of-fame__icon"><i class="las la-trophy"></i></span><div><h2 id="hall-of-fame-title">@lang('Hall of Fame')</h2><p>@lang('The best players of all time, by wins.')</p></div><button type="button" id="hall-of-fame-close" aria-label="@lang('Close')"><i class="las la-times"></i></button></header>
                <div class="hall-of-fame__games" id="hall-of-fame-games">
                    @foreach ($catalog as $game)
                        @if ($game['available'])
                            <button type="button" class="hall-of-fame__game{{ $game['key'] === 'ludo' ? ' is-selected' : '' }}" data-game="{{ $game['key'] }}">
                                <span>@if($game['card_image_url'])<img src="{{ $game['card_image_url'] }}" alt="">@else<i class="las {{ $game['icon_class'] }}"></i>@endif</span>
                                {{ __($game['name']) }}
                            </button>
                        @endif
                    @endforeach
                </div>
                <div class="hall-of-fame__tabs">
                    <button type="button" class="hall-of-fame__tab is-selected" data-tab="all-time">@lang('All-time')</button>
                    <button type="button" class="hall-of-fame__tab" data-tab="weekly">@lang('This week')</button>
                </div>
                <div class="hall-of-fame__list" id="hall-of-fame-list"><p class="hall-of-fame__loading">@lang('Loading...')</p></div>
            </div>
        </div>

        @if ($invites->isNotEmpty())
            <section class="table-card table-invitations"><div class="table-card__heading"><span class="table-card__icon table-card__icon--live"><i class="las la-envelope-open-text"></i></span><div><h2>@lang('Your Table Invitations')</h2><p>@lang('A player invited you to join a live table.')</p></div></div><div class="table-list">
                @foreach ($invites as $invite)
                    @php($game = collect($catalog)->firstWhere('key', $invite->table->game_type))
                    <article class="table-list-item"><div class="table-list-item__game"><span class="table-game-badge">@if($game['card_image_url'] ?? null)<img src="{{ $game['card_image_url'] }}" alt="">@else<i class="las {{ $game['icon_class'] ?? 'la-dice' }}"></i>@endif</span><div><strong>{{ __($game['name'] ?? $invite->table->game_type) }}</strong><small>{{ __('Invited by :name', ['name' => $invite->inviter?->username ?? __('Player')]) }} · {{ $invite->table->players->where('status', 'seated')->count() }}/{{ $invite->table->player_limit }} @lang('seats')</small></div></div><div class="table-list-item__stake"><small>@lang('Your stake')</small><strong>@if (bccomp((string) $invite->table->stake, '0', 8) === 0) @lang('Friendly · Free') @else {{ $invite->wallet_stake_preview !== null && auth()->user()->walletCurrency ? auth()->user()->walletCurrency->formatMinor((new \App\Support\Money())->toMinor($invite->wallet_stake_preview, (int) auth()->user()->walletCurrency->decimals), true) : __('Rate unavailable') }} @endif</strong></div><form method="post" action="{{ route('user.tables.invite.accept', $invite->id) }}" class="accept-invite-form">@csrf<button type="submit" class="table-join-button" @disabled(!$invite->wallet_stake_available)>@lang('Accept invite')</button></form></article>
                @endforeach
            </div></section>
        @endif

        <div class="table-lobby__layout">
            <form class="table-card" id="create-table-form">
                <div class="table-card__heading"><span class="table-card__icon"><i class="las la-plus"></i></span><div><h2>@lang('Create a Table')</h2><p>@lang('Set the game and stake for your table.')</p></div></div>
                <label class="table-field"><span>@lang('Game type')</span>
                    <select name="game_type" id="table-game-select" required class="table-field__sr-select">
                        @foreach ($catalog as $game)
                            <option value="{{ $game['key'] }}" data-min="{{ $game['min_players'] }}" data-max="{{ $game['max_players'] }}" data-commission="{{ $game['commission_percent'] }}" data-min-stake="{{ $game['min_stake'] }}" data-max-stake="{{ $game['max_stake'] }}" @disabled(!$game['enabled'] || !$game['available']) @selected($game['key'] === 'tic_tac_toe')>
                                {{ __($game['name']) }}{{ !$game['available'] ? ' — Coming soon' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <div class="table-game-picker-wrap">
                        <button type="button" class="table-game-picker-nav table-game-picker-nav--prev" id="table-game-picker-prev" aria-label="@lang('Previous games')"><i class="las la-angle-left"></i></button>
                        <div class="table-game-picker" id="table-game-picker">
                            @foreach ($catalog as $game)
                                <button type="button" class="table-game-picker__item{{ $game['key'] === 'tic_tac_toe' ? ' is-selected' : '' }}" data-game="{{ $game['key'] }}" @disabled(!$game['enabled'] || !$game['available'])>
                                    <span class="table-game-picker__icon">@if($game['card_image_url'])<img src="{{ $game['card_image_url'] }}" alt="">@else<i class="las {{ $game['icon_class'] }}"></i>@endif</span>
                                    <small>{{ __($game['name']) }}</small>
                                </button>
                            @endforeach
                        </div>
                        <button type="button" class="table-game-picker-nav table-game-picker-nav--next" id="table-game-picker-next" aria-label="@lang('Next games')"><i class="las la-angle-right"></i></button>
                    </div>
                </label>
                <label class="table-field"><span>@lang('Play mode')</span>
                    <select name="play_mode" id="table-play-mode" class="table-field__sr-select"><option value="friendly">@lang('Friendly · Free')</option><option value="staked">@lang('Staked')</option></select>
                    <div class="table-mode-toggle" id="table-mode-toggle">
                        <button type="button" class="table-mode-toggle__item is-selected" data-mode="friendly"><i class="las la-heart"></i><span>@lang('Friendly')<small>@lang('Free')</small></span></button>
                        <button type="button" class="table-mode-toggle__item" data-mode="staked"><i class="las la-coins"></i><span>@lang('Staked')<small>@lang('Real money')</small></span></button>
                    </div>
                    <small class="table-mode-hint" id="table-mode-hint">@lang('Just for fun - no money involved.')</small>
                </label>
                <div class="table-field-row">
                    <label class="table-field"><span>@lang('Seats')</span><select name="player_limit" id="table-player-limit" required></select></label>
                    <label class="table-field"><span>@lang('Stake per player')</span><div class="table-field__money"><span>{{ auth()->user()->walletCurrency?->symbol ?? gs('cur_sym') }}</span><input type="number" name="stake" id="table-stake" min="0.01" step="any" value="1" disabled></div><small>@lang('Only applies to staked games.') {{ auth()->user()->walletCurrency?->code }}</small></label>
                </div>
                <div class="table-cost-preview"><div><span>@lang('Total pot')</span><strong id="table-pot">—</strong></div><div><span>@lang('Commission')</span><strong id="table-commission">—</strong></div><div class="table-cost-preview__net"><span>@lang('Winner receives')</span><strong id="table-winner-net">—</strong></div></div>
                <p class="table-rules-note" id="table-rules-note"></p>
                <p class="table-disclaimer" id="table-disclaimer"></p>
                <button class="table-create-button" type="submit"><span>@lang('Create table')</span><i class="las la-arrow-right"></i></button>
                <p class="table-form-message" id="table-form-message" aria-live="polite"></p>
            </form>

            <section class="table-card table-card--live">
                <div class="table-card__heading"><span class="table-card__icon table-card__icon--live"><i class="las la-satellite-dish"></i></span><div><h2>@lang('Live Games')</h2><p>@lang('Tables currently waiting for players or in progress.')</p></div><span class="table-live-count">{{ count($tables) }} @lang('live')</span>
                    <div class="table-list-nav">
                        <button type="button" class="table-list-nav__btn" id="live-table-list-prev" aria-label="@lang('Scroll up')"><i class="las la-angle-up"></i></button>
                        <button type="button" class="table-list-nav__btn" id="live-table-list-next" aria-label="@lang('Scroll down')"><i class="las la-angle-down"></i></button>
                    </div>
                </div>
                <div class="table-list-viewport" id="live-table-list-viewport">
                <div class="table-list" id="live-table-list">
                    @forelse ($tables as $table)
                        @php($isSeated = collect($table['players'])->contains('id', (int) auth()->id()))
                        @php($game = collect($catalog)->firstWhere('key', $table['game']))
                        @php($seatedCount = count($table['players']))
                        <article class="table-list-item table-list-item--{{ $table['status'] }}" data-table-id="{{ $table['id'] }}">
                            <div class="table-list-item__game">
                                <span class="table-game-badge">@if($game['card_image_url'] ?? null)<img src="{{ $game['card_image_url'] }}" alt="">@else<i class="las {{ $game['icon_class'] ?? 'la-dice' }}"></i>@endif</span>
                                <div>
                                    <strong>{{ __($game['name'] ?? $table['game']) }}</strong>
                                    <div class="table-list-item__meta">
                                        <span class="table-status-pill table-status-pill--{{ $table['status'] }}"><i></i>{{ __(ucfirst($table['status'])) }}</span>
                                        <span class="table-occupancy" aria-hidden="true">@for ($i = 0; $i < $table['player_limit']; $i++)<span class="{{ $i < $seatedCount ? 'is-filled' : '' }}"></span>@endfor</span>
                                        <small>{{ $seatedCount }}/{{ $table['player_limit'] }} @lang('seats')</small>
                                    </div>
                                </div>
                            </div>
                            <div class="table-list-item__stake"><small>@lang('Your stake')</small><strong>@if (bccomp((string) $table['stake'], '0', 8) === 0) @lang('Friendly · Free') @else {{ $table['wallet_stake_formatted'] ?? __('Rate unavailable') }} @endif</strong></div>
                            @if ($table['status'] === 'waiting' && !$isSeated)
                                <form method="post" action="{{ route('user.tables.join', $table['id']) }}" class="join-table-form">@csrf<button type="submit" class="table-join-button" @disabled(!$table['can_join'])>@lang('Join')</button></form>
                            @elseif ($isSeated)
                                <a class="table-join-button table-join-button--open" href="{{ route('user.tables.show', $table['id']) }}">@lang('Open')</a>
                            @else
                                <a class="table-join-button table-join-button--watch" href="{{ route('user.tables.show', $table['id']) }}"><i class="las la-eye"></i>@lang('Watch')</a>
                            @endif
                        </article>
                    @empty
                        <div class="table-empty"><span><i class="las la-dice"></i></span><strong>@lang('No live tables yet')</strong><p>@lang('Create the first table and invite someone to play.')</p></div>
                    @endforelse
                </div>
                </div>
                <a class="table-history-link" href="{{ route('user.tables.index') }}">@lang('Refresh live tables') <i class="las la-sync"></i></a>
            </section>

            <aside class="table-card table-card--wins">
                @include('Template::sections.table_games_recent_wins')
            </aside>
        </div>
    </section>
@endsection

@push('style')
    <style>
        .table-lobby{--tg-line:var(--nocturne-line,rgba(145,156,205,.16));--tg-panel:var(--nocturne-panel,#151a31);--tg-raised:var(--nocturne-raised,#232a47);--tg-text:var(--nocturne-text,#f5f6ff);--tg-muted:var(--nocturne-muted,#929abc);--tg-green:var(--nocturne-green,#00ef87);width:100%;min-width:0;max-width:1120px;margin:auto;color:var(--tg-text);overflow-x:hidden;box-sizing:border-box}
        .table-lobby__hero{position:relative;overflow:hidden;display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;margin:0 0 22px;padding:clamp(22px,4vw,38px);border:1px solid var(--tg-line);border-radius:24px;background:radial-gradient(ellipse at 90% 15%,rgba(0,239,135,.16),transparent 35%),linear-gradient(125deg,#171d37,#11152a)}
        .table-lobby__hero-text{min-width:0}
        .table-lobby__eyebrow{color:var(--tg-green);font-size:11px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.table-lobby__hero h1{margin:7px 0;font-size:clamp(27px,4vw,38px);font-weight:950}.table-lobby__hero p{max-width:660px;margin:0;color:var(--tg-muted);font-size:14px;line-height:1.6}
        .table-lobby__live-badge{display:inline-flex;flex:0 0 auto;align-items:center;gap:8px;padding:10px 16px;border:1px solid rgba(0,239,135,.3);border-radius:999px;background:rgba(0,239,135,.1);color:var(--tg-green);font-size:12px;font-weight:950;white-space:nowrap}
        .table-lobby__live-badge i{width:7px;height:7px;border-radius:50%;background:currentColor;box-shadow:0 0 8px currentColor;animation:table-status-pulse 1.4s ease-in-out infinite}
        /* Named grid areas rather than a plain 2-up grid: the form spans both rows on the left,
           Live Games and Recent Wins stack independently in the right column on desktop, and
           on mobile the order becomes form -> wins -> live (Recent Wins moves up right after
           the create-table form, per request, instead of trailing after the live-tables list).
           Recent Wins is a fixed-height, self-contained ticker (see its own section below) so
           nothing here needs the old stretch-to-match-left-column height hack anymore - it
           can't grow unbounded regardless of how many wins or live tables exist. */
        .table-lobby__layout{display:grid;grid-template-columns:minmax(0,.92fr) minmax(0,1.08fr);grid-template-rows:auto auto;grid-template-areas:"form live" "form wins";gap:16px;align-items:start}
        #create-table-form{grid-area:form}
        .table-card--live{grid-area:live}
        .table-card--wins{grid-area:wins}
        .table-card{min-width:0;padding:clamp(17px,2.5vw,24px);border:1px solid var(--tg-line);border-radius:22px;background:linear-gradient(145deg,rgba(24,30,55,.98),rgba(16,20,39,.98));box-shadow:0 16px 42px rgba(0,0,0,.15)}
        .recent-wins__head{display:flex;align-items:center;gap:8px;margin-bottom:10px;color:var(--tg-text);font-size:13px;font-weight:900}.recent-wins__head i{color:#ffb547}.recent-wins__head small{margin-left:auto;padding:3px 9px;border-radius:999px;background:rgba(122,151,255,.12);color:#a7bbff;font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.04em}
        /* Fixed to exactly 5 rows' worth of height and auto-scrolling (see the keyframe) rather
           than growing with however many wins exist - that unbounded growth was what pushed
           the whole right column taller on both desktop and mobile as more live tables/wins
           came in. The edge mask fades the top/bottom row rather than hard-cropping it, so the
           endless vertical loop reads as continuous rather than visibly jumping. */
        .recent-wins__viewport{position:relative;height:300px;overflow:hidden;-webkit-mask-image:linear-gradient(180deg,transparent,#000 8%,#000 92%,transparent);mask-image:linear-gradient(180deg,transparent,#000 8%,#000 92%,transparent)}
        .recent-wins__track{display:flex;flex-direction:column;gap:7px;animation:recentWinsScrollY calc(var(--win-count,10) * 2.6s) linear infinite}
        .recent-wins__viewport:hover .recent-wins__track{animation-play-state:paused}
        @keyframes recentWinsScrollY{from{transform:translateY(0)}to{transform:translateY(-50%)}}
        @media(prefers-reduced-motion:reduce){.recent-wins__track{animation:none}.recent-wins__viewport{height:300px;overflow-y:auto;-webkit-mask-image:none;mask-image:none}}
        .recent-wins__item{display:grid;grid-template-columns:34px minmax(0,1fr) auto;align-items:center;gap:10px;padding:9px;border:1px solid var(--tg-line);border-radius:13px;background:rgba(35,42,71,.4)}
        .recent-wins__badge{display:grid;place-items:center;width:34px;height:34px;overflow:hidden;border-radius:10px;background:linear-gradient(145deg,rgba(0,239,135,.18),rgba(0,239,135,.04));color:var(--tg-green);font-size:15px}.recent-wins__badge img{width:100%;height:100%;object-fit:cover}
        .recent-wins__info{min-width:0;display:grid;gap:2px}.recent-wins__info strong{overflow:hidden;font-size:11.5px;font-weight:850;text-overflow:ellipsis;white-space:nowrap;color:var(--tg-text)}.recent-wins__info small{overflow:hidden;color:var(--tg-muted);font-size:9.5px;text-overflow:ellipsis;white-space:nowrap}
        .recent-wins__amount{color:var(--tg-green);font-size:11.5px;font-weight:950;white-space:nowrap}
        .recent-wins__empty{display:grid;justify-items:center;gap:6px;padding:20px 10px;text-align:center;color:var(--tg-muted)}.recent-wins__empty i{color:#ffb547;font-size:20px}.recent-wins__empty span{font-size:10.5px}
        /* Live Games list: bounded to ~6 rows plus a sliver of the 7th (user-driven slide, not
           auto-scrolling - scroll-snap makes manual swipe/arrow steps settle cleanly on a row
           instead of stopping mid-item). The up/down arrow pair is a desktop-only affordance
           (mobile relies on touch swipe) and sits in the header, never over the list itself. */
        .table-list-nav{display:none;align-items:center;gap:6px;margin-left:10px;flex:0 0 auto}
        .table-list-nav__btn{display:grid;place-items:center;width:28px;height:28px;border:1px solid var(--tg-line);border-radius:50%;background:var(--tg-raised);color:var(--tg-text);cursor:pointer;font-size:11px;transition:border-color .15s ease,color .15s ease,background .15s ease}
        .table-list-nav__btn:hover{border-color:rgba(0,239,135,.5);color:var(--tg-green);background:rgba(0,239,135,.08)}
        .table-list-nav__btn:disabled{opacity:.3;cursor:default}
        @media(min-width:851px){.table-list-nav{display:flex}}
        .table-list-viewport{max-height:480px;overflow-y:auto;scroll-snap-type:y mandatory;scroll-behavior:smooth;scrollbar-width:none}
        .table-list-viewport::-webkit-scrollbar{display:none}
        .table-card__heading{display:flex;align-items:center;gap:12px;margin-bottom:20px}.table-card__icon{display:grid;flex:0 0 44px;width:44px;height:44px;place-items:center;border-radius:14px;background:rgba(0,239,135,.12);color:var(--tg-green);font-size:23px}.table-card__icon--live{background:rgba(122,151,255,.14);color:#a7bbff}.table-card__heading h2{margin:0;font-size:18px;font-weight:900}.table-card__heading p{margin:3px 0 0;color:var(--tg-muted);font-size:11px}.table-live-count{margin-left:auto;padding:7px 10px;border:1px solid rgba(0,239,135,.2);border-radius:20px;color:var(--tg-green);font-size:10px;font-weight:900;white-space:nowrap}
        .table-field{display:grid;gap:7px;min-width:0;margin-bottom:14px}.table-field>span{color:#b5bdd8;font-size:11px;font-weight:850}.table-field select,.table-field input{width:100%;height:47px;min-width:0;padding:0 13px;border:1px solid var(--tg-line);border-radius:12px;background:#11162c;color:var(--tg-text);font:inherit;font-size:13px;outline:none}.table-field select:focus,.table-field input:focus{border-color:rgba(0,239,135,.6);box-shadow:0 0 0 3px rgba(0,239,135,.1)}.table-field option:disabled{color:#778}.table-field-row{display:grid;grid-template-columns:minmax(0,.7fr) minmax(0,1.3fr);gap:12px}.table-field__money{position:relative}.table-field__money>span{position:absolute;top:50%;left:12px;transform:translateY(-50%);color:var(--tg-muted);font-size:12px}.table-field__money input{padding-left:31px}
        .table-cost-preview{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin:3px 0 13px;padding:13px;border:1px solid var(--tg-line);border-radius:15px;background:rgba(10,13,28,.55)}.table-cost-preview>div{display:grid;gap:5px;min-width:0}.table-cost-preview span{color:var(--tg-muted);font-size:9px}.table-cost-preview strong{font-size:12px;font-weight:900;overflow-wrap:anywhere}.table-cost-preview__net strong{color:var(--tg-green)}.table-rules-note{margin:0 0 10px;color:#a8b4d6;font-size:10px;line-height:1.5;overflow-wrap:anywhere}.table-disclaimer{margin:0;padding:12px;border-left:2px solid rgba(0,239,135,.65);border-radius:0 10px 10px 0;background:rgba(0,239,135,.045);color:#929abc;font-size:10px;line-height:1.6;overflow-wrap:anywhere}
        /* These labels/text get rewritten by JS as the user changes game/seats/stake - a huge
           stake produces a long run of digits with no spaces (e.g. a formatted "$999,999.99"),
           which is exactly the kind of single "word" that can force a box wider than its
           container and create the sideways page-push the create-table form has been blamed
           for. overflow-wrap:anywhere on every dynamically-written node closes that off. */
        .table-create-button,.table-join-button{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:45px;padding:0 16px;border:0;border-radius:13px;background:linear-gradient(105deg,#00ef87,#00d77a);color:#062b1d;font-size:12px;font-weight:950;text-decoration:none;transition:transform .1s ease,filter .15s ease}.table-create-button{width:100%;min-width:0;margin-top:14px}.table-create-button span{min-width:0;overflow-wrap:anywhere}.table-create-button i{flex:0 0 auto;font-size:17px}.table-create-button:active,.table-join-button:active{transform:scale(.96)}.table-form-message{min-height:15px;margin:8px 0 0;color:#ff9eab;font-size:11px;overflow-wrap:anywhere}.table-list{display:grid;gap:9px}.table-list-item{display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:center;gap:12px;padding:13px;border:1px solid var(--tg-line);border-radius:16px;background:rgba(35,42,71,.46);transition:border-color .15s ease,background .15s ease;scroll-snap-align:start}.table-list-item:hover{border-color:rgba(0,239,135,.3);background:rgba(35,42,71,.65)}.table-list-item--waiting{border-left:2px solid rgba(0,239,135,.5)}.table-list-item--active{border-left:2px solid rgba(255,181,71,.45)}.table-list-item__game{display:flex;align-items:center;gap:10px;min-width:0}.table-game-badge{display:grid;flex:0 0 42px;width:42px;height:42px;place-items:center;overflow:hidden;border-radius:13px;background:linear-gradient(145deg,rgba(0,239,135,.18),rgba(0,239,135,.04));color:var(--tg-green);font-size:19px;box-shadow:0 4px 10px rgba(0,0,0,.2)}.table-game-badge img{width:100%;height:100%;object-fit:cover}.table-list-item__game>div{display:grid;gap:5px;min-width:0}.table-list-item__game strong{overflow:hidden;font-size:12.5px;font-weight:850;text-overflow:ellipsis;white-space:nowrap}.table-list-item small{color:var(--tg-muted);font-size:9px}.table-list-item__meta{display:flex;align-items:center;gap:7px;flex-wrap:wrap}
        .table-status-pill{display:inline-flex;align-items:center;gap:5px;padding:2px 8px;border-radius:999px;font-size:8.5px;font-weight:900;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap}.table-status-pill i{width:5px;height:5px;border-radius:50%;background:currentColor}.table-status-pill--waiting{background:rgba(0,239,135,.14);color:var(--tg-green)}.table-status-pill--waiting i{animation:table-status-pulse 1.4s ease-in-out infinite}.table-status-pill--active{background:rgba(255,181,71,.16);color:#ffb547}
        @keyframes table-status-pulse{0%,100%{opacity:1}50%{opacity:.3}}
        .table-occupancy{display:inline-flex;gap:3px}.table-occupancy span{width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,.14)}.table-occupancy span.is-filled{background:var(--tg-green)}
        .table-list-item__stake{display:grid;gap:4px;text-align:right;white-space:nowrap}.table-list-item__stake strong{font-size:11px}.join-table-form{margin:0}.table-join-button{min-height:37px;padding:0 13px;border-radius:11px;font-size:10px}.table-join-button--open{border:1px solid var(--tg-line);background:var(--tg-raised);color:var(--tg-text)}.table-join-button--watch{border:1px solid rgba(122,151,255,.3);background:rgba(122,151,255,.1);color:#a7bbff}.table-empty{display:grid;justify-items:center;gap:6px;padding:28px 14px;text-align:center}.table-empty>span{display:grid;width:45px;height:45px;place-items:center;border-radius:50%;background:rgba(0,239,135,.1);color:var(--tg-green);font-size:22px}.table-empty strong{font-size:13px}.table-empty p{margin:0;color:var(--tg-muted);font-size:10px}.table-history-link{display:flex;justify-content:center;gap:7px;margin-top:14px;color:var(--tg-muted);font-size:10px;font-weight:800;text-decoration:none}
        .table-join-button--disabled{display:inline-flex;align-items:center;justify-content:center;opacity:.6;cursor:not-allowed}
        @media(max-width:850px){
            .table-lobby__layout{grid-template-columns:1fr;grid-template-areas:"form" "wins" "live"}
            .table-list-item{width:100%;min-width:0;max-width:100%;box-sizing:border-box;grid-template-columns:minmax(0,1fr) minmax(0,auto);gap:8px 10px}
            .table-list-item__stake{grid-column:2;grid-row:1;min-width:0;max-width:100%;text-align:right;white-space:normal;overflow-wrap:anywhere}
            .table-list-item__stake strong{max-width:100%;overflow-wrap:anywhere}
            .join-table-form,.table-join-button--open,.table-join-button--watch{grid-column:1/-1;width:100%}
            .table-list-item__game{grid-column:1;grid-row:1;min-width:0}
        }
        @media(max-width:575px){
            .table-lobby{width:100%;min-width:0;overflow-x:hidden}
            .table-lobby__hero{margin-inline:0;padding:18px;border-radius:19px;gap:12px}
            .table-lobby__hero h1{font-size:23px}
            .table-lobby__hero p{font-size:12.5px}
            .table-lobby__live-badge{padding:8px 13px;font-size:11px}
            .table-lobby__layout{gap:12px}
            .table-card{padding:15px;border-radius:17px}
            .table-card__heading{gap:9px;margin-bottom:16px}
            .table-card__icon{flex:0 0 38px;width:38px;height:38px;font-size:19px}
            .table-card__heading h2{font-size:15px}
            .table-live-count{font-size:9px}
            .table-game-picker{gap:7px}
            .table-game-picker__item{flex:0 0 30%;padding:8px 5px}
            .table-game-picker__icon{width:34px;height:34px;font-size:15px}
            .table-field-row{grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:9px}
            .table-cost-preview{grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:8px 6px}
            .table-cost-preview__net{grid-column:1/-1;padding-top:7px;border-top:1px dashed var(--tg-line)}
            .table-cost-preview span{font-size:9px}
            .table-cost-preview strong{font-size:11px}
            .table-card__heading>div{flex:1;min-width:0}
            .table-card__heading p{overflow-wrap:anywhere}
            .table-list-item{width:100%;min-width:0;max-width:100%;box-sizing:border-box;grid-template-columns:minmax(0,1fr) minmax(0,auto);gap:8px 10px;padding:12px}
            .table-list-item__stake{grid-column:2;grid-row:1;min-width:0;max-width:100%;text-align:right;white-space:normal;overflow-wrap:anywhere}
            .table-list-item__stake strong{max-width:100%;overflow-wrap:anywhere}
            .join-table-form,.table-join-button--open,.table-join-button--watch{grid-column:1/-1;width:100%}
            .table-list-item__game{grid-column:1;grid-row:1;min-width:0}
            .table-game-badge{flex:0 0 38px;width:38px;height:38px}
        }
    </style>
    <style>.table-mode-hint{display:block;margin-top:7px;color:var(--tg-muted);font-size:10px;line-height:1.5;overflow-wrap:anywhere}.table-field input:disabled{opacity:.58;cursor:not-allowed}
        .table-mode-toggle{display:flex;gap:8px}
        .table-mode-toggle__item{display:flex;flex:1;min-width:0;align-items:center;gap:9px;min-height:54px;padding:0 14px;border:1px solid var(--tg-line);border-radius:13px;background:rgba(35,42,71,.4);color:var(--tg-muted);font-size:17px;cursor:pointer;transition:border-color .15s ease,background .15s ease,color .15s ease}
        .table-mode-toggle__item span{min-width:0;overflow:hidden}
        .table-mode-toggle__item span strong,.table-mode-toggle__item span small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .table-mode-toggle__item span{display:flex;flex-direction:column;align-items:flex-start;gap:1px;color:inherit}
        .table-mode-toggle__item span strong,.table-mode-toggle__item span{font-size:12.5px;font-weight:850}
        .table-mode-toggle__item small{color:var(--tg-muted);font-size:9.5px;font-weight:700}
        .table-mode-toggle__item:hover{border-color:rgba(0,239,135,.3)}
        .table-mode-toggle__item.is-selected{border-color:rgba(0,239,135,.55);background:rgba(0,239,135,.1);color:var(--tg-text)}
        .table-mode-toggle__item.is-selected i{color:var(--tg-green)}
        .table-field__sr-select{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
        /* Horizontal carousel, not a wrapping grid: shows 4 full tiles + a sliver of the 5th on
           desktop (3 + a sliver of the 4th on phones) via percentage flex-basis, so "how many
           fit" scales with the actual column width instead of a fixed px count. Touch users
           swipe it directly; the arrow pair is a desktop-only affordance next to it, external
           to the scroller rather than overlaid, so it can never cover a tile's icon or label. */
        .table-game-picker-wrap{display:flex;align-items:center;gap:8px}
        .table-game-picker-nav{display:none;flex:0 0 auto;align-items:center;justify-content:center;width:30px;height:30px;border:1px solid var(--tg-line);border-radius:50%;background:var(--tg-raised);color:var(--tg-text);cursor:pointer;font-size:12px;transition:border-color .15s ease,color .15s ease,background .15s ease}
        .table-game-picker-nav:hover{border-color:rgba(0,239,135,.5);color:var(--tg-green);background:rgba(0,239,135,.08)}
        .table-game-picker-nav:disabled{opacity:.3;cursor:default}
        @media(min-width:576px){.table-game-picker-nav{display:flex}}
        .table-game-picker{flex:1;min-width:0;display:flex;flex-wrap:nowrap;gap:8px;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;scrollbar-width:none;-webkit-overflow-scrolling:touch}
        .table-game-picker::-webkit-scrollbar{display:none}
        .table-game-picker__item{flex:0 0 22%;scroll-snap-align:start;display:flex;flex-direction:column;align-items:center;gap:6px;padding:10px 6px;border:1px solid var(--tg-line);border-radius:14px;background:rgba(35,42,71,.4);color:var(--tg-muted);cursor:pointer;transition:border-color .15s ease,background .15s ease,color .15s ease}
        .table-game-picker__item:hover{border-color:rgba(0,239,135,.35)}
        .table-game-picker__item.is-selected{border-color:rgba(0,239,135,.6);background:rgba(0,239,135,.1);color:var(--tg-text)}
        .table-game-picker__item:disabled{opacity:.45;cursor:not-allowed}
        .table-game-picker__icon{display:grid;place-items:center;width:38px;height:38px;overflow:hidden;border-radius:11px;background:linear-gradient(145deg,rgba(0,239,135,.18),rgba(0,239,135,.04));color:var(--tg-green);font-size:17px}
        .table-game-picker__icon img{width:100%;height:100%;object-fit:cover}
        .table-game-picker__item small{overflow:hidden;max-width:100%;font-size:9.5px;font-weight:800;text-overflow:ellipsis;white-space:nowrap}
        .table-lobby__hero-stats{display:flex;flex-direction:column;align-items:flex-end;gap:9px;flex:0 0 auto}
        .table-lobby__fame-trigger{display:inline-flex;align-items:center;gap:7px;padding:9px 15px;border:1px solid var(--tg-line);border-radius:999px;background:rgba(255,255,255,.04);color:var(--tg-text);font-size:11px;font-weight:900;cursor:pointer;white-space:nowrap}
        .table-lobby__fame-trigger:hover{border-color:rgba(255,181,71,.4);color:#ffb547}
        .table-lobby__fame-trigger i{color:#ffb547}
        .hall-of-fame{position:fixed;inset:0;z-index:1200;display:flex;align-items:center;justify-content:center;padding:18px;overflow:hidden}
        .hall-of-fame[hidden]{display:none}
        .hall-of-fame__backdrop{position:absolute;inset:0;background:rgba(6,8,18,.72);backdrop-filter:blur(5px)}
        .hall-of-fame__sheet{position:relative;width:min(480px,100%);max-height:86vh;display:flex;flex-direction:column;padding:22px;border:1px solid var(--tg-line);border-radius:24px;background:radial-gradient(ellipse at 50% 0,rgba(255,181,71,.1),transparent 45%),linear-gradient(170deg,#1a2041,#10142a);box-shadow:0 30px 70px rgba(0,0,0,.5)}
        .hall-of-fame__sheet>header{display:flex;align-items:center;gap:12px;margin-bottom:16px}
        .hall-of-fame__icon{display:grid;flex:0 0 42px;width:42px;height:42px;place-items:center;border-radius:13px;background:rgba(255,181,71,.14);color:#ffb547;font-size:21px}
        .hall-of-fame__sheet>header h2{margin:0;font-size:16px;font-weight:950}
        .hall-of-fame__sheet>header p{margin:2px 0 0;color:var(--tg-muted);font-size:10.5px}
        .hall-of-fame__sheet>header button{margin-left:auto;display:grid;width:30px;height:30px;flex:0 0 auto;place-items:center;border:0;border-radius:9px;background:rgba(255,255,255,.06);color:var(--tg-muted);cursor:pointer}
        .hall-of-fame__games{display:flex;gap:7px;margin-bottom:12px;padding-bottom:12px;overflow-x:auto;border-bottom:1px solid var(--tg-line)}
        .hall-of-fame__game{display:flex;flex-direction:column;align-items:center;gap:5px;flex:0 0 auto;width:58px;padding:6px 4px;border:0;background:transparent;color:var(--tg-muted);cursor:pointer;opacity:.6}
        .hall-of-fame__game span{display:grid;place-items:center;width:34px;height:34px;overflow:hidden;border-radius:10px;background:rgba(255,255,255,.05);color:var(--tg-green);font-size:15px}
        .hall-of-fame__game span img{width:100%;height:100%;object-fit:cover}
        .hall-of-fame__game small,.hall-of-fame__game{font-size:8.5px;font-weight:800;text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:58px}
        .hall-of-fame__game.is-selected{opacity:1;color:var(--tg-text)}
        .hall-of-fame__game.is-selected span{background:rgba(0,239,135,.14);color:var(--tg-green)}
        .hall-of-fame__tabs{display:flex;gap:8px;margin-bottom:12px}
        .hall-of-fame__tab{flex:1;padding:9px;border:1px solid var(--tg-line);border-radius:11px;background:transparent;color:var(--tg-muted);font-size:11px;font-weight:900;cursor:pointer}
        .hall-of-fame__tab.is-selected{border-color:rgba(0,239,135,.4);background:rgba(0,239,135,.08);color:var(--tg-green)}
        .hall-of-fame__list{display:grid;gap:7px;overflow-y:auto}
        .hall-of-fame__row{display:flex;align-items:center;gap:10px;padding:9px 11px;border:1px solid var(--tg-line);border-radius:13px;background:rgba(35,42,71,.4)}
        .hall-of-fame__rank{display:grid;flex:0 0 22px;place-items:center;color:var(--tg-muted);font-size:11px;font-weight:950}
        .hall-of-fame__row:nth-child(1) .hall-of-fame__rank{color:#ffd166}.hall-of-fame__row:nth-child(2) .hall-of-fame__rank{color:#cfd8e3}.hall-of-fame__row:nth-child(3) .hall-of-fame__rank{color:#d7935b}
        .hall-of-fame__avatar{display:grid;flex:0 0 34px;width:34px;height:34px;place-items:center;overflow:hidden;border-radius:50%;background:var(--tg-raised);color:#bec8e8;font-size:12px;font-weight:950}
        .hall-of-fame__avatar img{width:100%;height:100%;object-fit:cover}
        .hall-of-fame__row strong{flex:1;min-width:0;overflow:hidden;font-size:12px;text-overflow:ellipsis;white-space:nowrap}
        .hall-of-fame__row small{color:var(--tg-muted);font-size:9.5px}
        .hall-of-fame__wins{color:var(--tg-green);font-size:12px;font-weight:950;white-space:nowrap}
        .hall-of-fame__loading,.hall-of-fame__empty{padding:22px;color:var(--tg-muted);font-size:11px;text-align:center}
        @media(max-width:575px){.table-lobby__hero{flex-direction:column;align-items:flex-start}.table-lobby__hero-stats{flex-direction:row;align-items:center;width:100%;justify-content:space-between}.hall-of-fame__sheet{padding:16px;border-radius:19px}}
    </style>
@endpush

@push('script')
    <script>
        (() => {
            const root = document.querySelector('.table-lobby'); if (!root) return;
            const catalog = @json($catalog); const gameSelect = document.querySelector('#table-game-select');
            const seats = document.querySelector('#table-player-limit'); const stake = document.querySelector('#table-stake'); const playMode = document.querySelector('#table-play-mode');
            const createKey = sessionStorage.getItem('table-create-key') || crypto.randomUUID(); sessionStorage.setItem('table-create-key', createKey);
            const currency = @json(auth()->user()->walletCurrency?->symbol ?? gs('cur_sym')); const currencyDecimals = Number(@json(auth()->user()->walletCurrency?->decimals ?? 2)); const scale = 100000000n;
            const toUnits = value => { const [whole, fraction = ''] = String(value || '0').split('.'); return BigInt(whole || '0') * scale + BigInt((fraction + '00000000').slice(0,8)); };
            const fmt = units => { const precision = Math.min(3, Math.max(0, currencyDecimals)); const factor = 10n ** BigInt(8 - precision); const minor = (units + factor / 2n) / factor; const divisor = 10n ** BigInt(precision); const whole = minor / divisor; const fraction = precision ? `.${String(minor % divisor).padStart(precision,'0')}` : ''; return `${currency}${whole.toLocaleString()}${fraction}`; };
            function updateOptions(){const item=catalog.find(game=>game.key===gameSelect.value);if(!item)return;const option=gameSelect.selectedOptions[0];const min=Number(option.dataset.min),max=Number(option.dataset.max);const previous=Number(seats.value)||min;seats.replaceChildren();for(let n=min;n<=max;n++){const node=document.createElement('option');node.value=n;node.textContent=`${n} players`;seats.append(node);}seats.value=Math.min(max,Math.max(min,previous));stake.min=option.dataset.minStake;stake.max=option.dataset.maxStake;document.querySelector('#table-rules-note').textContent=item.draw_rule;updateTotals();}
            function updateTotals(){const option=gameSelect.selectedOptions[0];const players=Number(seats.value)||2;const friendly=playMode.value==='friendly';stake.disabled=friendly;stake.required=!friendly;const per=friendly?0n:toUnits(stake.value);const pot=BigInt(players)*per;const rateText=friendly?'0':(option.dataset.commission||'5.00');const [rateWhole,rateFraction='']=rateText.split('.');const rateBps=BigInt(rateWhole||0)*100n+BigInt((rateFraction+'00').slice(0,2));const commission=pot*rateBps/10000n;const rate=Number(rateText);document.querySelector('#table-pot').textContent=fmt(pot);document.querySelector('#table-commission').textContent=`${fmt(commission)} (${rate}%)`;document.querySelector('#table-winner-net').textContent=fmt(pot-commission);document.querySelector('#table-mode-hint').textContent=friendly?'Just for fun - no money involved.':`Each player stakes ${fmt(per)}. The winner receives the pot after ${rate}% commission.`;document.querySelector('#table-disclaimer').textContent=friendly?`No money involved. Starts when all ${players} seats fill, or you start early with 2+.`:`Each player puts in ${fmt(per)}; the winner takes the ${fmt(pot)} pot less ${rate}% commission (${fmt(commission)}). A draw refunds every stake. It starts when all ${players} seats are full, or the creator starts it early with 2 or more players. Leaving or missing two turns puts you out; your stake stays in the pot.`;document.querySelector('#create-table-form button[type="submit"] span').textContent=friendly?`Create friendly game for ${players}`:`Create staked game for ${players} - ${fmt(per)}`;}
            const requestedGame=new URLSearchParams(location.search).get('game');if(requestedGame){const match=Array.from(gameSelect.options).find(option=>option.value===requestedGame&&!option.disabled);if(match){gameSelect.value=requestedGame;document.querySelector('#create-table-form')?.scrollIntoView({behavior:'smooth',block:'start'});}}
            if(gameSelect.selectedOptions[0]?.disabled){const first=Array.from(gameSelect.options).find(option=>!option.disabled);if(first)gameSelect.value=first.value;}
            const gamePicker=document.querySelector('#table-game-picker');
            function syncGamePicker(){gamePicker?.querySelectorAll('.table-game-picker__item').forEach(item=>item.classList.toggle('is-selected',item.dataset.game===gameSelect.value));}
            gamePicker?.querySelectorAll('.table-game-picker__item:not(:disabled)').forEach(item=>item.addEventListener('click',()=>{gameSelect.value=item.dataset.game;gameSelect.dispatchEvent(new Event('change'));}));

            // Carousel arrows (desktop) for the game-type picker and the live-games list: step
            // by ~85% of the viewport so the trailing tile/row stays visible as a landmark
            // between clicks, and disable an arrow once there's nothing further that way.
            function wireCarouselNav(scrollEl,prevBtn,nextBtn,axis){
                if(!scrollEl||!prevBtn||!nextBtn)return;
                const sizeProp=axis==='x'?'clientWidth':'clientHeight',scrollProp=axis==='x'?'scrollLeft':'scrollTop',scrollSizeProp=axis==='x'?'scrollWidth':'scrollHeight';
                const step=()=>Math.round(scrollEl[sizeProp]*0.85);
                const scrollBy=amount=>scrollEl.scrollBy(axis==='x'?{left:amount,behavior:'smooth'}:{top:amount,behavior:'smooth'});
                prevBtn.addEventListener('click',()=>scrollBy(-step()));
                nextBtn.addEventListener('click',()=>scrollBy(step()));
                function updateDisabled(){
                    const max=scrollEl[scrollSizeProp]-scrollEl[sizeProp];
                    prevBtn.disabled=scrollEl[scrollProp]<=2;
                    nextBtn.disabled=scrollEl[scrollProp]>=max-2;
                }
                scrollEl.addEventListener('scroll',updateDisabled,{passive:true});
                window.addEventListener('resize',updateDisabled);
                updateDisabled();
            }
            wireCarouselNav(gamePicker,document.querySelector('#table-game-picker-prev'),document.querySelector('#table-game-picker-next'),'x');
            wireCarouselNav(document.querySelector('#live-table-list-viewport'),document.querySelector('#live-table-list-prev'),document.querySelector('#live-table-list-next'),'y');
            const modeToggle=document.querySelector('#table-mode-toggle');
            function syncModeToggle(){modeToggle?.querySelectorAll('.table-mode-toggle__item').forEach(item=>item.classList.toggle('is-selected',item.dataset.mode===playMode.value));}
            modeToggle?.querySelectorAll('.table-mode-toggle__item').forEach(item=>item.addEventListener('click',()=>{playMode.value=item.dataset.mode;playMode.dispatchEvent(new Event('change'));}));
            gameSelect.addEventListener('change',()=>{updateOptions();syncGamePicker();});playMode.addEventListener('change',()=>{updateTotals();syncModeToggle();});seats.addEventListener('change',updateTotals);stake.addEventListener('input',updateTotals);updateOptions();syncGamePicker();syncModeToggle();
            document.querySelectorAll('.join-table-form').forEach(form=>form.addEventListener('submit',async event=>{event.preventDefault();const button=form.querySelector('button');button.disabled=true;button.textContent='Joining...';try{const response=await fetch(form.action,{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value}});const result=await response.json();if(!response.ok)throw new Error(result.message||'Unable to join this table.');location.href=form.action.replace(/\/join$/, '');}catch(error){button.disabled=false;button.textContent='Join';notify('error',error.message);}}));
            document.querySelectorAll('.accept-invite-form').forEach(form=>form.addEventListener('submit',async event=>{event.preventDefault();const button=form.querySelector('button');button.disabled=true;try{const response=await fetch(form.action,{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value}});const result=await response.json();if(!response.ok)throw new Error(result.message||'Unable to accept this invitation.');location.href=`{{ url('/user/tables') }}/${result.table.id}`;}catch(error){button.disabled=false;notify('error',error.message);}}));
            document.querySelector('#create-table-form').addEventListener('submit',async event=>{event.preventDefault();const message=document.querySelector('#table-form-message');const button=event.currentTarget.querySelector('button[type=submit]');button.disabled=true;message.textContent='';try{const response=await fetch(root.dataset.createUrl,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':root.dataset.csrf},body:JSON.stringify({game_type:gameSelect.value,player_limit:Number(seats.value),play_mode:playMode.value,stake:playMode.value==='friendly'?'0':stake.value,idempotency_key:createKey})});const raw=await response.text();let result;try{result=raw?JSON.parse(raw):{};}catch{throw new Error(response.status===419?'Your session expired. Refresh the page and try again.':`The server returned an unexpected response (${response.status}). Please refresh and try again.`);}if(!response.ok)throw new Error(result.message||result.errors&&Object.values(result.errors).flat()[0]||'Unable to create table.');if(!result.table?.id)throw new Error(result.message||'The server did not confirm that the table was created.');sessionStorage.removeItem('table-create-key');location.href=`{{ url('/user/tables') }}/${result.table.id}`;}catch(error){message.textContent=error.message;}finally{button.disabled=false;} });

            (()=>{
                const overlay=document.querySelector('#hall-of-fame');if(!overlay)return;
                const openBtn=document.querySelector('#hall-of-fame-open'),closeBtn=document.querySelector('#hall-of-fame-close'),backdrop=document.querySelector('#hall-of-fame-backdrop');
                const gamesRow=document.querySelector('#hall-of-fame-games'),tabs=document.querySelectorAll('.hall-of-fame__tab'),list=document.querySelector('#hall-of-fame-list');
                let currentGame='ludo',currentTab='all-time',cache={};
                function open(){overlay.hidden=false;load();}
                function close(){overlay.hidden=true;}
                openBtn?.addEventListener('click',open);closeBtn?.addEventListener('click',close);backdrop?.addEventListener('click',close);
                gamesRow?.querySelectorAll('.hall-of-fame__game').forEach(btn=>btn.addEventListener('click',()=>{
                    currentGame=btn.dataset.game;
                    gamesRow.querySelectorAll('.hall-of-fame__game').forEach(item=>item.classList.toggle('is-selected',item===btn));
                    load();
                }));
                tabs.forEach(tab=>tab.addEventListener('click',()=>{
                    currentTab=tab.dataset.tab;
                    tabs.forEach(item=>item.classList.toggle('is-selected',item===tab));
                    render();
                }));
                async function load(){
                    list.innerHTML='<p class="hall-of-fame__loading">Loading…</p>';
                    if(cache[currentGame]){render();return;}
                    try{
                        const url=new URL(root.dataset.leaderboardUrl,location.href);url.searchParams.set('game',currentGame);
                        const response=await fetch(url,{headers:{Accept:'application/json'}});
                        if(!response.ok)throw new Error('Could not load the leaderboard.');
                        cache[currentGame]=await response.json();
                        render();
                    }catch(e){list.innerHTML=`<p class="hall-of-fame__empty">${e.message}</p>`;}
                }
                function render(){
                    const data=cache[currentGame];if(!data)return;
                    const rows=currentTab==='all-time'?data.hall_of_fame:data.weekly_winners;
                    if(!rows?.length){list.innerHTML=`<p class="hall-of-fame__empty">${currentTab==='all-time'?'No wins recorded for this game yet.':'No wins this week yet.'}</p>`;return;}
                    list.innerHTML='';
                    rows.forEach((row,index)=>{
                        const item=document.createElement('div');item.className='hall-of-fame__row';
                        const rank=document.createElement('span');rank.className='hall-of-fame__rank';rank.textContent=index<3?['🥇','🥈','🥉'][index]:String(index+1);
                        const avatar=document.createElement('span');avatar.className='hall-of-fame__avatar';
                        if(row.avatar){const img=document.createElement('img');img.src=row.avatar;img.alt='';avatar.append(img);}else{avatar.textContent=(row.username||'?').slice(0,1).toUpperCase();}
                        const name=document.createElement('strong');name.textContent='@'+row.username;
                        const wins=document.createElement('span');wins.className='hall-of-fame__wins';wins.textContent=`${row.wins} ${row.wins===1?'win':'wins'}`;
                        item.append(rank,avatar,name,wins);
                        list.append(item);
                    });
                }
            })();
        })();
    </script>
@endpush
