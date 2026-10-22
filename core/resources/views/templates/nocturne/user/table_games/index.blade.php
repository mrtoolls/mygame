@extends('Template::layouts.master')
@section('content')
    <section class="table-lobby" data-create-url="{{ route('user.tables.create') }}" data-csrf="{{ csrf_token() }}">
        <header class="table-lobby__hero">
            <div class="table-lobby__hero-text">
                <span class="table-lobby__eyebrow">@lang('Play together')</span>
                <h1>@lang('Live Tables')</h1>
                <p>@lang('Choose a game and take your seat. Moves and table balances are confirmed by the game server.')</p>
            </div>
            <span class="table-lobby__live-badge"><i></i>{{ count($tables) }} @lang('live now')</span>
        </header>

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
                    <div class="table-game-picker" id="table-game-picker">
                        @foreach ($catalog as $game)
                            <button type="button" class="table-game-picker__item{{ $game['key'] === 'tic_tac_toe' ? ' is-selected' : '' }}" data-game="{{ $game['key'] }}" @disabled(!$game['enabled'] || !$game['available'])>
                                <span class="table-game-picker__icon">@if($game['card_image_url'])<img src="{{ $game['card_image_url'] }}" alt="">@else<i class="las {{ $game['icon_class'] }}"></i>@endif</span>
                                <small>{{ __($game['name']) }}</small>
                            </button>
                        @endforeach
                    </div>
                </label>
                <label class="table-field"><span>@lang('Play mode')</span><select name="play_mode" id="table-play-mode"><option value="friendly">@lang('Friendly game · no money involved')</option><option value="staked">@lang('Staked game')</option></select><small class="table-mode-hint" id="table-mode-hint">@lang('Play for fun. No balance is charged and no money is won or lost.')</small></label>
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
                <div class="table-card__heading"><span class="table-card__icon table-card__icon--live"><i class="las la-satellite-dish"></i></span><div><h2>@lang('Live Games')</h2><p>@lang('Tables currently waiting for players or in progress.')</p></div><span class="table-live-count">{{ count($tables) }} @lang('live')</span></div>
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
                <a class="table-history-link" href="{{ route('user.tables.index') }}">@lang('Refresh live tables') <i class="las la-sync"></i></a>
            </section>
        </div>
    </section>
@endsection

@push('style')
    <style>
        .table-lobby{--tg-line:var(--nocturne-line,rgba(145,156,205,.16));--tg-panel:var(--nocturne-panel,#151a31);--tg-raised:var(--nocturne-raised,#232a47);--tg-text:var(--nocturne-text,#f5f6ff);--tg-muted:var(--nocturne-muted,#929abc);--tg-green:var(--nocturne-green,#00ef87);max-width:1120px;margin:auto;color:var(--tg-text)}
        .table-lobby__hero{position:relative;overflow:hidden;display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;margin:0 0 22px;padding:clamp(22px,4vw,38px);border:1px solid var(--tg-line);border-radius:24px;background:radial-gradient(ellipse at 90% 15%,rgba(0,239,135,.16),transparent 35%),linear-gradient(125deg,#171d37,#11152a)}
        .table-lobby__hero-text{min-width:0}
        .table-lobby__eyebrow{color:var(--tg-green);font-size:11px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.table-lobby__hero h1{margin:7px 0;font-size:clamp(27px,4vw,38px);font-weight:950}.table-lobby__hero p{max-width:660px;margin:0;color:var(--tg-muted);font-size:14px;line-height:1.6}
        .table-lobby__live-badge{display:inline-flex;flex:0 0 auto;align-items:center;gap:8px;padding:10px 16px;border:1px solid rgba(0,239,135,.3);border-radius:999px;background:rgba(0,239,135,.1);color:var(--tg-green);font-size:12px;font-weight:950;white-space:nowrap}
        .table-lobby__live-badge i{width:7px;height:7px;border-radius:50%;background:currentColor;box-shadow:0 0 8px currentColor;animation:table-status-pulse 1.4s ease-in-out infinite}
        .table-lobby__layout{display:grid;grid-template-columns:minmax(0,.92fr) minmax(0,1.08fr);gap:16px;align-items:start}.table-card{min-width:0;padding:clamp(17px,2.5vw,24px);border:1px solid var(--tg-line);border-radius:22px;background:linear-gradient(145deg,rgba(24,30,55,.98),rgba(16,20,39,.98));box-shadow:0 16px 42px rgba(0,0,0,.15)}
        .table-card__heading{display:flex;align-items:center;gap:12px;margin-bottom:20px}.table-card__icon{display:grid;flex:0 0 44px;width:44px;height:44px;place-items:center;border-radius:14px;background:rgba(0,239,135,.12);color:var(--tg-green);font-size:23px}.table-card__icon--live{background:rgba(122,151,255,.14);color:#a7bbff}.table-card__heading h2{margin:0;font-size:18px;font-weight:900}.table-card__heading p{margin:3px 0 0;color:var(--tg-muted);font-size:11px}.table-live-count{margin-left:auto;padding:7px 10px;border:1px solid rgba(0,239,135,.2);border-radius:20px;color:var(--tg-green);font-size:10px;font-weight:900;white-space:nowrap}
        .table-field{display:grid;gap:7px;min-width:0;margin-bottom:14px}.table-field>span{color:#b5bdd8;font-size:11px;font-weight:850}.table-field select,.table-field input{width:100%;height:47px;min-width:0;padding:0 13px;border:1px solid var(--tg-line);border-radius:12px;background:#11162c;color:var(--tg-text);font:inherit;font-size:13px;outline:none}.table-field select:focus,.table-field input:focus{border-color:rgba(0,239,135,.6);box-shadow:0 0 0 3px rgba(0,239,135,.1)}.table-field option:disabled{color:#778}.table-field-row{display:grid;grid-template-columns:.7fr 1.3fr;gap:12px}.table-field__money{position:relative}.table-field__money>span{position:absolute;top:50%;left:12px;transform:translateY(-50%);color:var(--tg-muted);font-size:12px}.table-field__money input{padding-left:31px}
        .table-cost-preview{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:3px 0 13px;padding:13px;border:1px solid var(--tg-line);border-radius:15px;background:rgba(10,13,28,.55)}.table-cost-preview>div{display:grid;gap:5px;min-width:0}.table-cost-preview span{color:var(--tg-muted);font-size:9px}.table-cost-preview strong{font-size:12px;font-weight:900;overflow-wrap:anywhere}.table-cost-preview__net strong{color:var(--tg-green)}.table-rules-note{margin:0 0 10px;color:#a8b4d6;font-size:10px;line-height:1.5}.table-disclaimer{margin:0;padding:12px;border-left:2px solid rgba(0,239,135,.65);border-radius:0 10px 10px 0;background:rgba(0,239,135,.045);color:#929abc;font-size:10px;line-height:1.6}
        .table-create-button,.table-join-button{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:45px;padding:0 16px;border:0;border-radius:13px;background:linear-gradient(105deg,#00ef87,#00d77a);color:#062b1d;font-size:12px;font-weight:950;text-decoration:none;transition:transform .1s ease,filter .15s ease}.table-create-button{width:100%;margin-top:14px}.table-create-button i{font-size:17px}.table-create-button:active,.table-join-button:active{transform:scale(.96)}.table-form-message{min-height:15px;margin:8px 0 0;color:#ff9eab;font-size:11px}.table-list{display:grid;gap:9px}.table-list-item{display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:center;gap:12px;padding:13px;border:1px solid var(--tg-line);border-radius:16px;background:rgba(35,42,71,.46);transition:border-color .15s ease,background .15s ease}.table-list-item:hover{border-color:rgba(0,239,135,.3);background:rgba(35,42,71,.65)}.table-list-item--waiting{border-left:2px solid rgba(0,239,135,.5)}.table-list-item--active{border-left:2px solid rgba(255,181,71,.45)}.table-list-item__game{display:flex;align-items:center;gap:10px;min-width:0}.table-game-badge{display:grid;flex:0 0 42px;width:42px;height:42px;place-items:center;overflow:hidden;border-radius:13px;background:linear-gradient(145deg,rgba(0,239,135,.18),rgba(0,239,135,.04));color:var(--tg-green);font-size:19px;box-shadow:0 4px 10px rgba(0,0,0,.2)}.table-game-badge img{width:100%;height:100%;object-fit:cover}.table-list-item__game>div{display:grid;gap:5px;min-width:0}.table-list-item__game strong{overflow:hidden;font-size:12.5px;font-weight:850;text-overflow:ellipsis;white-space:nowrap}.table-list-item small{color:var(--tg-muted);font-size:9px}.table-list-item__meta{display:flex;align-items:center;gap:7px;flex-wrap:wrap}
        .table-status-pill{display:inline-flex;align-items:center;gap:5px;padding:2px 8px;border-radius:999px;font-size:8.5px;font-weight:900;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap}.table-status-pill i{width:5px;height:5px;border-radius:50%;background:currentColor}.table-status-pill--waiting{background:rgba(0,239,135,.14);color:var(--tg-green)}.table-status-pill--waiting i{animation:table-status-pulse 1.4s ease-in-out infinite}.table-status-pill--active{background:rgba(255,181,71,.16);color:#ffb547}
        @keyframes table-status-pulse{0%,100%{opacity:1}50%{opacity:.3}}
        .table-occupancy{display:inline-flex;gap:3px}.table-occupancy span{width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,.14)}.table-occupancy span.is-filled{background:var(--tg-green)}
        .table-list-item__stake{display:grid;gap:4px;text-align:right;white-space:nowrap}.table-list-item__stake strong{font-size:11px}.join-table-form{margin:0}.table-join-button{min-height:37px;padding:0 13px;border-radius:11px;font-size:10px}.table-join-button--open{border:1px solid var(--tg-line);background:var(--tg-raised);color:var(--tg-text)}.table-join-button--watch{border:1px solid rgba(122,151,255,.3);background:rgba(122,151,255,.1);color:#a7bbff}.table-empty{display:grid;justify-items:center;gap:6px;padding:28px 14px;text-align:center}.table-empty>span{display:grid;width:45px;height:45px;place-items:center;border-radius:50%;background:rgba(0,239,135,.1);color:var(--tg-green);font-size:22px}.table-empty strong{font-size:13px}.table-empty p{margin:0;color:var(--tg-muted);font-size:10px}.table-history-link{display:flex;justify-content:center;gap:7px;margin-top:14px;color:var(--tg-muted);font-size:10px;font-weight:800;text-decoration:none}
        .table-join-button--disabled{display:inline-flex;align-items:center;justify-content:center;opacity:.6;cursor:not-allowed}
        @media(max-width:850px){.table-lobby__layout{grid-template-columns:1fr}.table-card--live{order:-1}}
        @media(max-width:575px){
            .table-lobby{overflow-x:hidden}
            .table-lobby__hero{margin-inline:-2px;padding:18px;border-radius:19px;gap:12px}
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
            .table-game-picker__item{width:64px;padding:8px 5px}
            .table-game-picker__icon{width:34px;height:34px;font-size:15px}
            .table-field-row{grid-template-columns:1fr 1fr;gap:9px}
            .table-cost-preview{grid-template-columns:1fr 1fr;gap:8px 6px}
            .table-cost-preview__net{grid-column:1/-1;padding-top:7px;border-top:1px dashed var(--tg-line)}
            .table-cost-preview span{font-size:9px}
            .table-cost-preview strong{font-size:11px}
            .table-list-item{grid-template-columns:minmax(0,1fr) auto;gap:8px 10px;padding:12px}
            .table-list-item__stake{grid-column:2;grid-row:1;text-align:right}
            .join-table-form,.table-join-button--open,.table-join-button--watch{grid-column:1/-1;width:100%}
            .table-list-item__game{grid-column:1;grid-row:1}
            .table-game-badge{flex:0 0 38px;width:38px;height:38px}
        }
    </style>
    <style>.table-mode-hint{display:block;margin-top:-2px;color:var(--tg-muted);font-size:10px;line-height:1.5}.table-field input:disabled{opacity:.58;cursor:not-allowed}
        .table-field__sr-select{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
        .table-game-picker{display:flex;flex-wrap:wrap;gap:8px}
        .table-game-picker__item{display:flex;flex-direction:column;align-items:center;gap:6px;width:72px;padding:10px 6px;border:1px solid var(--tg-line);border-radius:14px;background:rgba(35,42,71,.4);color:var(--tg-muted);cursor:pointer;transition:border-color .15s ease,background .15s ease,color .15s ease}
        .table-game-picker__item:hover{border-color:rgba(0,239,135,.35)}
        .table-game-picker__item.is-selected{border-color:rgba(0,239,135,.6);background:rgba(0,239,135,.1);color:var(--tg-text)}
        .table-game-picker__item:disabled{opacity:.45;cursor:not-allowed}
        .table-game-picker__icon{display:grid;place-items:center;width:38px;height:38px;overflow:hidden;border-radius:11px;background:linear-gradient(145deg,rgba(0,239,135,.18),rgba(0,239,135,.04));color:var(--tg-green);font-size:17px}
        .table-game-picker__icon img{width:100%;height:100%;object-fit:cover}
        .table-game-picker__item small{overflow:hidden;max-width:100%;font-size:9.5px;font-weight:800;text-overflow:ellipsis;white-space:nowrap}
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
            function updateTotals(){const option=gameSelect.selectedOptions[0];const players=Number(seats.value)||2;const friendly=playMode.value==='friendly';stake.disabled=friendly;stake.required=!friendly;const per=friendly?0n:toUnits(stake.value);const pot=BigInt(players)*per;const rateText=friendly?'0':(option.dataset.commission||'5.00');const [rateWhole,rateFraction='']=rateText.split('.');const rateBps=BigInt(rateWhole||0)*100n+BigInt((rateFraction+'00').slice(0,2));const commission=pot*rateBps/10000n;const rate=Number(rateText);document.querySelector('#table-pot').textContent=fmt(pot);document.querySelector('#table-commission').textContent=`${fmt(commission)} (${rate}%)`;document.querySelector('#table-winner-net').textContent=fmt(pot-commission);document.querySelector('#table-mode-hint').textContent=friendly?'Play for fun. No balance is charged and no money is won or lost.':`Each player stakes ${fmt(per)}. The winner receives the pot after ${rate}% commission.`;document.querySelector('#table-disclaimer').textContent=friendly?`Friendly game: no balance changes, payout, or commission. The game starts when all ${players} seats are full, or the creator starts it early with at least two players.`:`Each player puts in ${fmt(per)}; the winner takes the ${fmt(pot)} pot less ${rate}% commission (${fmt(commission)}). A draw refunds every stake. It starts when all ${players} seats are full, or the creator starts it early with 2 or more players. Leaving or missing two turns puts you out; your stake stays in the pot.`;document.querySelector('#create-table-form button[type="submit"] span').textContent=friendly?`Create friendly game for ${players}`:`Create staked game for ${players} - ${fmt(per)}`;}
            const requestedGame=new URLSearchParams(location.search).get('game');if(requestedGame){const match=Array.from(gameSelect.options).find(option=>option.value===requestedGame&&!option.disabled);if(match){gameSelect.value=requestedGame;document.querySelector('#create-table-form')?.scrollIntoView({behavior:'smooth',block:'start'});}}
            if(gameSelect.selectedOptions[0]?.disabled){const first=Array.from(gameSelect.options).find(option=>!option.disabled);if(first)gameSelect.value=first.value;}
            const gamePicker=document.querySelector('#table-game-picker');
            function syncGamePicker(){gamePicker?.querySelectorAll('.table-game-picker__item').forEach(item=>item.classList.toggle('is-selected',item.dataset.game===gameSelect.value));}
            gamePicker?.querySelectorAll('.table-game-picker__item:not(:disabled)').forEach(item=>item.addEventListener('click',()=>{gameSelect.value=item.dataset.game;gameSelect.dispatchEvent(new Event('change'));}));
            gameSelect.addEventListener('change',()=>{updateOptions();syncGamePicker();});playMode.addEventListener('change',updateTotals);seats.addEventListener('change',updateTotals);stake.addEventListener('input',updateTotals);updateOptions();syncGamePicker();
            document.querySelectorAll('.join-table-form').forEach(form=>form.addEventListener('submit',async event=>{event.preventDefault();const button=form.querySelector('button');button.disabled=true;button.textContent='Joining...';try{const response=await fetch(form.action,{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value}});const result=await response.json();if(!response.ok)throw new Error(result.message||'Unable to join this table.');location.href=form.action.replace(/\/join$/, '');}catch(error){button.disabled=false;button.textContent='Join';window.alert(error.message);}}));
            document.querySelectorAll('.accept-invite-form').forEach(form=>form.addEventListener('submit',async event=>{event.preventDefault();const button=form.querySelector('button');button.disabled=true;try{const response=await fetch(form.action,{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value}});const result=await response.json();if(!response.ok)throw new Error(result.message||'Unable to accept this invitation.');location.href=`{{ url('/user/tables') }}/${result.table.id}`;}catch(error){button.disabled=false;window.alert(error.message);}}));
            document.querySelector('#create-table-form').addEventListener('submit',async event=>{event.preventDefault();const message=document.querySelector('#table-form-message');const button=event.currentTarget.querySelector('button[type=submit]');button.disabled=true;message.textContent='';try{const response=await fetch(root.dataset.createUrl,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':root.dataset.csrf},body:JSON.stringify({game_type:gameSelect.value,player_limit:Number(seats.value),play_mode:playMode.value,stake:playMode.value==='friendly'?'0':stake.value,idempotency_key:createKey})});const raw=await response.text();let result;try{result=raw?JSON.parse(raw):{};}catch{throw new Error(response.status===419?'Your session expired. Refresh the page and try again.':`The server returned an unexpected response (${response.status}). Please refresh and try again.`);}if(!response.ok)throw new Error(result.message||result.errors&&Object.values(result.errors).flat()[0]||'Unable to create table.');if(!result.table?.id)throw new Error(result.message||'The server did not confirm that the table was created.');sessionStorage.removeItem('table-create-key');location.href=`{{ url('/user/tables') }}/${result.table.id}`;}catch(error){message.textContent=error.message;}finally{button.disabled=false;} });
        })();
    </script>
@endpush
