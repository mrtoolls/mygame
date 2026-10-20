@extends('Template::layouts.master')
@push('script-lib')
<script src="{{ asset('assets/global/js/table-sound.js') }}"></script>
@endpush
@section('content')
    <main class="table-match" id="table-match" data-id="{{ $table->id }}" data-friendly="{{ bccomp((string) $table->stake, '0', 8) === 0 ? '1' : '0' }}" data-snapshot="{{ route('user.tables.snapshot', $table->id) }}" data-chat="{{ route('user.tables.chat', $table->id) }}" data-chat-send="{{ route('user.tables.chat.send', $table->id) }}" data-reaction-template="{{ route('user.tables.chat.react', [$table->id, '__MESSAGE__']) }}" data-report-template="{{ route('user.tables.chat.report', [$table->id, '__MESSAGE__']) }}" data-block-template="{{ route('user.tables.players.block', [$table->id, '__USER__']) }}" data-player-search="{{ route('user.tables.players.search', $table->id) }}" data-move="{{ route('user.tables.move', $table->id) }}" data-start="{{ route('user.tables.start', $table->id) }}" data-leave="{{ route('user.tables.leave', $table->id) }}" data-csrf="{{ csrf_token() }}" data-user="{{ auth()->id() }}" data-type="{{ $table->game_type }}" data-turn-seconds="{{ $turnSeconds }}" data-spectator="{{ $isSpectator ? '1' : '0' }}" data-applaud="{{ route('user.tables.chat.applaud', $table->id) }}" data-gift-send-template="{{ route('user.tables.gifts.send', [$table->id, '__GIFT__']) }}">
        <header class="table-match__top"><a href="{{ route('user.tables.index') }}" class="table-match__back"><i class="las la-arrow-left"></i></a><div class="table-match__title"><small>@lang('LIVE TABLE')</small><h1>{{ __($pageTitle) }}</h1></div>@if($isSpectator)<span class="table-match__spectator-badge"><i class="las la-eye"></i>@lang('Watching')</span>@endif<span class="table-match__watching" id="watching-count" hidden><i class="las la-eye"></i><b>0</b></span><button type="button" class="table-match__applaud" id="match-applaud" aria-label="@lang('Applaud')"><i class="las la-thumbs-up"></i></button>@if($gifts->isNotEmpty())<button type="button" class="table-match__applaud" id="match-gift-open" aria-label="@lang('Send a gift')"><i class="las la-gift"></i></button>@endif<button type="button" class="table-match__info" id="how-to-play-open" aria-label="@lang('How to play')"><i class="las la-info-circle"></i></button><div class="sound-control" id="sound-control"><button type="button" id="sound-toggle" aria-label="@lang('Toggle sound')"><i class="las la-volume-up"></i></button><input type="range" id="sound-volume" min="0" max="100" value="50" aria-label="@lang('Sound volume')"></div><span class="table-match__status" id="match-status">{{ __(ucfirst($table->status)) }}</span></header>
          <div class="table-match__layout"><section class="table-match__play"><div class="table-match__notice" id="match-notice" aria-live="polite">@lang('Waiting for the latest server state')</div><div class="table-board" id="table-board"></div><div class="table-match__footer"><span><i class="las la-coins"></i> @if ($isSpectator) @lang('Spectating · no stake involved') @elseif (bccomp((string) $table->stake, '0', 8) === 0) @lang('Friendly game · no money involved') @else @lang('Your stake') <strong>{{ $walletStakeFormatted ?? showAmount($walletStake) }}</strong> @lang(' · rate locked when you joined') @endif</span><span id="turn-clock"><i class="las la-hourglass-half"></i> <b>--</b></span></div></section>
            <aside class="table-match__side"><section class="table-match__panel"><div class="table-match__panel-title"><span><i class="las la-users"></i></span><div><h2>@lang('Seats')</h2><p>{{ $table->players->where('status', 'seated')->count() }}/{{ $table->player_limit }} @lang('players seated')</p></div></div><div id="match-players" class="table-match__players"></div></section><section class="table-match__panel table-match__rules"><div class="table-match__panel-title"><span><i class="las la-book-open"></i></span><div><h2>@lang('How to play')</h2><p>@lang('Server validated rules')</p></div></div><p id="match-rules"></p></section>@if($isSpectator)<p class="table-match__spectator-note"><i class="las la-info-circle"></i>@lang("You're watching this table. Cheer the players on in chat - you can't make moves here.")</p>@else @if($table->status==='waiting' && (int)$table->creator_id===(int)auth()->id())<form class="table-match__invite" id="match-invite" action="{{ route('user.tables.invite', $table->id) }}" method="post">@csrf<label for="invite-username">@lang('Invite a player by username')</label><div class="table-match__invite-control"><input id="invite-username" name="username" maxlength="40" required autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="@lang('Search players by username')" aria-autocomplete="list" aria-controls="invite-suggestions"><button type="submit"><i class="las la-paper-plane"></i> @lang('Invite')</button></div><div class="table-match__suggestions" id="invite-suggestions" role="listbox" hidden></div><small id="invite-message" aria-live="polite"></small></form>@endif<button class="table-match__start" id="match-start" type="button" @if ((int) $table->creator_id !== (int) auth()->id()) hidden @endif>@lang('Start with 2+ players')</button><button class="table-match__leave" id="match-leave" type="button">@lang('Stand up')</button><p class="table-match__error" id="match-error" role="status"></p>@endif</aside>
        </div>
        <section class="table-chat" id="table-chat" aria-label="Table chat">
            <header class="table-chat__head"><span class="table-chat__icon"><i class="las la-comments"></i></span><div><h2>@lang('Table chat')</h2><p>@lang('Chat, share a voice note, or react while you play')</p></div><span class="table-chat__live"><i></i>@lang('LIVE')</span></header>
            <p class="table-chat__safety"><i class="las la-shield-alt"></i>@lang('Keep it on-platform - never pay or trade outside the site. Deals made elsewhere are not protected.')</p>
            <div class="table-chat__messages" id="chat-messages" aria-live="polite"><p class="table-chat__empty" id="chat-empty">@lang('Say hello to everyone at the table.')</p></div>
            <button type="button" class="table-chat__jump" id="chat-jump" hidden><i class="las la-arrow-down"></i><span id="chat-jump-count">0</span> @lang('new')</button>
            <div class="table-chat__reply" id="chat-reply" hidden><div><small id="chat-reply-name"></small><p id="chat-reply-preview"></p></div><button type="button" id="chat-reply-cancel" aria-label="@lang('Cancel reply')"><i class="las la-times"></i></button></div>
            <div class="table-chat__record" id="chat-record" hidden>
                <button type="button" id="chat-record-cancel" aria-label="@lang('Cancel recording')"><i class="las la-trash"></i></button>
                <div class="table-chat__record-meter" id="chat-record-meter"></div>
                <span class="table-chat__record-time" id="chat-record-time">0:00</span>
                <button type="button" class="table-chat__record-stop" id="chat-record-stop" aria-label="@lang('Stop and send')"><i class="las la-paper-plane"></i></button>
            </div>
            <form class="table-chat__form" id="chat-form">
                <div class="table-chat__picker-anchor">
                    <button type="button" class="table-chat__icon-btn" id="chat-emoji-open" aria-label="@lang('Emoji')"><i class="las la-laugh-beam"></i></button>
                    <div class="emoji-picker" id="emoji-picker" hidden>
                        <div class="emoji-picker__search"><i class="las la-search"></i><input type="text" id="emoji-search" placeholder="{{ __('Search emoji') }}" autocomplete="off"></div>
                        <div class="emoji-picker__tabs" id="emoji-tabs"></div>
                        <div class="emoji-picker__grid" id="emoji-grid"></div>
                    </div>
                </div>
                <textarea id="chat-input" maxlength="1000" rows="1" placeholder="{{ __('Message the table…') }}" aria-label="{{ __('Message the table') }}"></textarea>
                <div class="table-chat__picker-anchor">
                    <button type="button" class="table-chat__icon-btn" id="chat-attach-open" aria-label="@lang('Attach')"><i class="las la-paperclip"></i></button>
                    <div class="attach-menu" id="attach-menu" hidden>
                        <button type="button" data-accept="image/*,video/*"><i class="las la-image"></i>@lang('Photo / Video')</button>
                        <button type="button" data-accept=".pdf,.txt,.csv,.zip,.doc,.docx"><i class="las la-file-alt"></i>@lang('Document')</button>
                        <button type="button" data-accept="image/*" data-capture="environment"><i class="las la-camera"></i>@lang('Camera')</button>
                    </div>
                    <input id="chat-file" type="file" hidden>
                </div>
                <button class="table-chat__send" type="submit" id="chat-send" aria-label="{{ __('Send message') }}"><i class="las la-arrow-up"></i></button>
                <button type="button" class="table-chat__voice" id="chat-voice" aria-label="@lang('Record voice note')"><i class="las la-microphone"></i></button>
            </form>
            <p class="table-chat__error" id="chat-error" role="status"></p>
        </section>
        <div class="how-to-play" id="how-to-play" hidden role="dialog" aria-modal="true" aria-labelledby="how-to-play-title">
            <div class="how-to-play__backdrop" id="how-to-play-backdrop"></div>
            <div class="how-to-play__sheet">
                <header><h2 id="how-to-play-title">@lang('How to Play') · {{ __($pageTitle) }}</h2><button type="button" id="how-to-play-close" aria-label="@lang('Close')"><i class="las la-times"></i></button></header>
                <div class="how-to-play__body">
                    @if(!empty($guide['goal']))<section><h3><i class="las la-bullseye"></i>@lang('Goal')</h3><p>{{ $guide['goal'] }}</p></section>@endif
                    @if(!empty($guide['setup']))<section><h3><i class="las la-border-all"></i>@lang('Setup')</h3><p>{{ $guide['setup'] }}</p></section>@endif
                    @if(!empty($guide['how_to_play']))<section><h3><i class="las la-list-ol"></i>@lang('How to Play')</h3><ol>@foreach($guide['how_to_play'] as $step)<li>{{ $step }}</li>@endforeach</ol></section>@endif
                    @if(!empty($guide['special_rules']))<section><h3><i class="las la-star"></i>@lang('Special Rules')</h3><p>{{ $guide['special_rules'] }}</p></section>@endif
                    @if(!empty($guide['draw']))<section><h3><i class="las la-handshake"></i>@lang('How a Draw Works')</h3><p>{{ $guide['draw'] }}</p></section>@endif
                    @if(!empty($guide['win']))<section><h3><i class="las la-trophy"></i>@lang('How a Win is Decided')</h3><p>{{ $guide['win'] }}</p></section>@endif
                    <section><h3><i class="las la-hourglass-half"></i>@lang('Turns & Timers')</h3><p>{{ __('Each turn has a :seconds-second timer. Missing two turns in a row puts you out of the game; any stake already committed stays in the pot.', ['seconds' => $turnSeconds]) }}</p></section>
                    <section><h3><i class="las la-coins"></i>@lang('Stakes & Pot')</h3>
                        @if(bccomp((string) $table->stake, '0', 8) === 0)
                            <p>@lang('This is a friendly table - no balance is charged and no money is won or lost.')</p>
                        @else
                            <p>{{ __('Your locked-in stake is :stake. The winner receives the full pot after a :rate% commission is retained by the platform. A draw refunds every stake in full.', ['stake' => $walletStakeFormatted ?? showAmount($walletStake), 'rate' => rtrim(rtrim($commissionPercent, '0'), '.') ?: '0']) }}</p>
                        @endif
                    </section>
                </div>
            </div>
        </div>
        <div class="result-popup" id="result-popup" hidden role="dialog" aria-modal="true" aria-labelledby="result-popup-title">
            <div class="result-popup__backdrop" id="result-popup-backdrop"></div>
            <div class="result-popup__card" id="result-popup-card">
                <div class="result-popup__fx" id="result-popup-fx" aria-hidden="true"></div>
                <button type="button" class="result-popup__close" id="result-popup-close" aria-label="@lang('Close')"><i class="las la-times"></i></button>
                <div class="result-popup__icon" id="result-popup-icon"><i class="las la-trophy"></i></div>
                <h2 class="result-popup__title" id="result-popup-title"></h2>
                <p class="result-popup__subtitle" id="result-popup-subtitle"></p>
                <div class="result-popup__amount" id="result-popup-amount" hidden>
                    <span class="result-popup__amount-value" id="result-popup-amount-value">0</span>
                    <span class="result-popup__amount-label" id="result-popup-amount-label"></span>
                </div>
                <div class="result-popup__actions">
                    <a class="result-popup__btn result-popup__btn--primary" id="result-popup-again" href="{{ route('user.tables.index') }}?game={{ $table->game_type }}"><i class="las la-redo"></i> <span>@lang('Play Again')</span></a>
                    <a class="result-popup__btn result-popup__btn--ghost" href="{{ route('user.tables.index') }}"><i class="las la-th-large"></i> <span>@lang('Back to Lobby')</span></a>
                </div>
            </div>
        </div>
        @if($gifts->isNotEmpty())
        <div class="gift-drawer" id="gift-drawer" hidden role="dialog" aria-modal="true" aria-labelledby="gift-drawer-title">
            <div class="gift-drawer__backdrop" id="gift-drawer-backdrop"></div>
            <div class="gift-drawer__sheet">
                <header><h2 id="gift-drawer-title"><i class="las la-gift"></i> @lang('Send a Gift')</h2><button type="button" id="gift-drawer-close" aria-label="@lang('Close')"><i class="las la-times"></i></button></header>
                <div class="gift-drawer__step" id="gift-drawer-pick">
                    <div class="gift-drawer__grid" id="gift-drawer-grid">
                        @foreach($gifts as $gift)
                            <button type="button" class="gift-drawer__item" data-gift-id="{{ $gift->id }}" data-gift-name="{{ $gift->name }}" data-gift-image="{{ $gift->imageUrl() }}">
                                <img src="{{ $gift->imageUrl() }}" alt="{{ $gift->name }}">
                                <strong>{{ $gift->name }}</strong>
                                <span>${{ showAmount($gift->buy_price, currencyFormat: false) }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
                <div class="gift-drawer__step" id="gift-drawer-send" hidden>
                    <button type="button" class="gift-drawer__back" id="gift-drawer-back"><i class="las la-arrow-left"></i>@lang('Choose a different gift')</button>
                    <div class="gift-drawer__preview"><img id="gift-drawer-preview-img" src="" alt=""><strong id="gift-drawer-preview-name"></strong></div>
                    <label class="gift-drawer__label">@lang('Send to')</label>
                    <div class="gift-drawer__recipients" id="gift-drawer-recipients"></div>
                    <label class="gift-drawer__label">@lang('Quantity')</label>
                    <input type="number" id="gift-drawer-quantity" min="1" max="99" value="1">
                    <button type="button" class="gift-drawer__send-btn" id="gift-drawer-send-btn"><i class="las la-paper-plane"></i> @lang('Send Gift')</button>
                    <p class="gift-drawer__error" id="gift-drawer-error" role="status"></p>
                </div>
            </div>
        </div>
        <div class="gift-burst" id="gift-burst" hidden aria-live="polite">
            <div class="gift-burst__sparkles" id="gift-burst-sparkles"></div>
            <div class="gift-burst__card">
                <img id="gift-burst-img" src="" alt="">
                <p id="gift-burst-text"></p>
            </div>
        </div>
        @endif
    </main>
@endsection
@push('style')
<style>
.table-match{--tm-line:var(--nocturne-line,rgba(145,156,205,.16));--tm-panel:var(--nocturne-panel,#151a31);--tm-raised:var(--nocturne-raised,#232a47);--tm-text:var(--nocturne-text,#f5f6ff);--tm-muted:var(--nocturne-muted,#929abc);--tm-green:var(--nocturne-green,#00ef87);max-width:1150px;margin:auto;color:var(--tm-text)}.table-match__top{display:flex;align-items:center;gap:13px;margin-bottom:16px;padding:17px;border:1px solid var(--tm-line);border-radius:19px;background:linear-gradient(120deg,#171d37,#11152a)}.table-match__back{display:grid;width:40px;height:40px;place-items:center;border-radius:13px;background:var(--tm-raised);color:var(--tm-text);font-size:20px;text-decoration:none}.table-match__title{min-width:0}.table-match__top small{color:var(--tm-green);font-size:9px;font-weight:950;letter-spacing:.13em}.table-match__top h1{margin:2px 0 0;overflow:hidden;font-size:clamp(15px,3vw,27px);font-weight:950;white-space:nowrap;text-overflow:ellipsis}.table-match__status{margin-left:auto;padding:7px 11px;border:1px solid var(--tm-line);border-radius:20px;color:#bdc7e6;font-size:10px;font-weight:900}.table-match__layout{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:14px}.table-match__play,.table-match__panel{border:1px solid var(--tm-line);border-radius:20px;background:linear-gradient(145deg,rgba(24,30,55,.98),rgba(16,20,39,.98))}.table-match__play{min-height:480px;padding:clamp(14px,3vw,26px)}.table-match__notice{margin-bottom:13px;padding:11px 13px;border:1px solid rgba(0,239,135,.13);border-radius:12px;background:rgba(0,239,135,.045);color:#aeb8d5;font-size:11px}.table-board{display:grid;min-height:320px;place-items:center;padding:12px}.board-grid{display:grid;gap:7px}.board-grid--xo{grid-template-columns:repeat(3,min(20vw,92px))}.board-cell{display:grid;width:min(20vw,92px);aspect-ratio:1;place-items:center;border:1px solid var(--tm-line);border-radius:15px;background:linear-gradient(145deg,#202846,#151a31);color:var(--tm-green);font-size:clamp(25px,7vw,44px);font-weight:950}.board-grid--connect{grid-template-columns:repeat(7,min(10vw,54px));gap:5px;padding:9px;border-radius:16px;background:#15244a}.board-cell--connect{width:min(10vw,54px);border-radius:50%;background:#0e1329;font-size:18px}.board-cell.is-seat-1{color:#00ef87}.board-cell.is-seat-2{color:#ffcc70}.board-cell:disabled{cursor:default;opacity:.9}.board-scorebar{display:flex;justify-content:center;gap:12px;flex-wrap:wrap;margin:0 0 10px;color:var(--tm-text);font-size:11px;font-weight:900}.board-scorebar span{padding:7px 10px;border:1px solid var(--tm-line);border-radius:10px;background:var(--tm-raised)}.board-pits{display:grid;grid-template-columns:repeat(6,minmax(34px,1fr));gap:8px;width:min(100%,560px);padding:18px;border-radius:40px;background:linear-gradient(130deg,#563817,#a16b31)}.board-pit{min-height:62px;border:2px solid rgba(255,228,182,.26);border-radius:50%;background:radial-gradient(circle at 40% 35%,#58381d,#27190f);color:#ffe7bb;font-size:17px;font-weight:900}.board-pit:disabled{opacity:.65}.board-domino-chain,.board-domino-hand{display:flex;justify-content:center;flex-wrap:wrap;gap:7px;width:100%;margin:8px 0}.board-domino-chain span{display:grid;min-width:54px;min-height:42px;place-items:center;border:1px solid var(--tm-line);border-radius:10px;background:linear-gradient(145deg,#f3e6c6,#d3bd8c);color:#25233a;font-size:12px;font-weight:950}.board-domino-hand button{min-height:38px;padding:0 10px;border:1px solid var(--tm-line);border-radius:10px;background:var(--tm-raised);color:var(--tm-text);font-size:10px;font-weight:850}.board-domino-hand button:disabled{opacity:.4}.board-track{display:grid;grid-template-columns:repeat(10,minmax(0,1fr));gap:5px;width:min(100%,560px)}.board-square{position:relative;display:grid;aspect-ratio:1;place-items:center;border:1px solid var(--tm-line);border-radius:9px;background:var(--tm-raised);color:#cdd5ef;font-size:10px;font-weight:800}.board-token{position:absolute;width:12px;height:12px;border:2px solid #fff;border-radius:50%;background:var(--tm-green);box-shadow:0 0 13px var(--tm-green)}.table-match__footer{display:flex;justify-content:space-between;gap:12px;margin-top:14px;padding:12px;border-top:1px solid var(--tm-line);color:var(--tm-muted);font-size:10px}.table-match__footer i{color:var(--tm-green)}.table-match__footer strong{color:var(--tm-text)}.table-match__side{display:grid;align-content:start;gap:12px}.table-match__panel{padding:16px}.table-match__panel-title{display:flex;align-items:center;gap:10px;margin-bottom:13px}.table-match__panel-title>span{display:grid;width:37px;height:37px;place-items:center;border-radius:12px;background:rgba(0,239,135,.1);color:var(--tm-green);font-size:20px}.table-match__panel-title h2{margin:0;font-size:13px;font-weight:900}.table-match__panel-title p{margin:3px 0 0;color:var(--tm-muted);font-size:9px}.table-match__players{display:grid;gap:7px}.match-player{display:flex;align-items:center;gap:9px;padding:9px;border:1px solid var(--tm-line);border-radius:11px;background:rgba(35,42,71,.4);transition:border-color .2s ease,box-shadow .2s ease}.match-player.is-turn{border-color:rgba(0,239,135,.4);box-shadow:0 0 0 1px rgba(0,239,135,.12),0 10px 24px rgba(0,239,135,.08);animation:tm-pulse 1.8s ease-in-out infinite}.match-player__ring{--pct:0;position:relative;display:grid;flex:0 0 auto;place-items:center;width:38px;height:38px;border-radius:50%;background:conic-gradient(var(--tm-green) calc(var(--pct)*1%),rgba(255,255,255,.1) 0);padding:3px;transition:background .2s linear}.match-player:not(.is-turn) .match-player__ring{background:rgba(255,255,255,.08)!important}.match-player__ring.is-low{background:conic-gradient(#ff7087 calc(var(--pct)*1%),rgba(255,255,255,.1) 0)}.match-player__avatar{display:grid;width:100%;height:100%;place-items:center;border-radius:50%;overflow:hidden;background:var(--tm-raised);color:#bec8e8;font-size:11px;font-weight:950}.match-player__avatar img{width:100%;height:100%;object-fit:cover}.match-player.is-turn .match-player__avatar{color:var(--tm-green)}@keyframes tm-pulse{0%,100%{box-shadow:0 0 0 1px rgba(0,239,135,.12),0 10px 24px rgba(0,239,135,.08)}50%{box-shadow:0 0 0 1px rgba(0,239,135,.3),0 10px 30px rgba(0,239,135,.18)}}.match-player strong{min-width:0;flex:1;overflow:hidden;font-size:10px;white-space:nowrap;text-overflow:ellipsis}.match-player small{flex:0 0 auto;margin-left:auto;color:var(--tm-muted);font-size:9px}.table-match__rules p{margin:0;color:#a8b3d0;font-size:10px;line-height:1.65}.table-match__start,.table-match__leave{min-height:43px;border:0;border-radius:12px;font-size:11px;font-weight:950}.table-match__start{background:var(--tm-green);color:#062b1d}.table-match__leave{border:1px solid rgba(255,112,135,.25);background:rgba(255,112,135,.08);color:#ff95a6}.table-match__error{min-height:15px;margin:0;color:#ff95a6;font-size:10px}.table-match__invite{display:grid;gap:8px;padding:13px;border:1px solid var(--tm-line);border-radius:14px;background:rgba(35,42,71,.28)}.table-match__invite label{color:#b5bdd8;font-size:10px;font-weight:850}.table-match__invite>div{display:flex;gap:7px}.table-match__invite input{min-width:0;flex:1;padding:0 10px;border:1px solid var(--tm-line);border-radius:9px;background:#11162c;color:var(--tm-text);font:inherit;font-size:11px}.table-match__invite button{padding:0 12px;border:0;border-radius:9px;background:var(--tm-green);color:#062b1d;font-size:10px;font-weight:900}.table-match__invite small{min-height:12px;color:var(--tm-muted);font-size:9px}@media(max-width:800px){.table-match__layout{grid-template-columns:1fr}.table-match__side{grid-template-columns:repeat(2,minmax(0,1fr))}.table-match__side>.table-match__panel:first-child{grid-row:span 2}}@media(max-width:575px){.table-match__layout{gap:10px}.table-match__play{min-height:370px;padding:12px;border-radius:17px}.table-board{min-height:270px;padding:6px}.board-grid{gap:5px}.board-grid--xo{grid-template-columns:repeat(3,min(25vw,82px))}.board-cell{width:min(25vw,82px);border-radius:12px}.board-grid--connect{grid-template-columns:repeat(7,min(11.4vw,45px));gap:4px}.board-cell--connect{width:min(11.4vw,45px)}.board-scorebar{display:flex;justify-content:center;gap:12px;flex-wrap:wrap;margin:0 0 10px;color:var(--tm-text);font-size:11px;font-weight:900}.board-scorebar span{padding:7px 10px;border:1px solid var(--tm-line);border-radius:10px;background:var(--tm-raised)}.board-pits{gap:5px;padding:12px;border-radius:24px}.board-pit{min-height:48px;font-size:14px}.table-match__side{grid-template-columns:1fr}.table-match__side>.table-match__panel:first-child{grid-row:auto}.table-match__top{padding:13px;border-radius:16px}.table-match__footer{font-size:9px}}
.table-match__spectator-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 11px;border:1px solid rgba(255,204,112,.3);border-radius:20px;background:rgba(255,204,112,.08);color:#ffcc70;font-size:10px;font-weight:900;white-space:nowrap}
.table-match__watching{display:inline-flex;align-items:center;gap:5px;color:var(--tm-muted);font-size:11px;font-weight:800}
.table-match__watching[hidden]{display:none}
.table-match__applaud{display:grid;flex:0 0 auto;place-items:center;width:36px;height:36px;margin-left:4px;border:1px solid var(--tm-line);border-radius:11px;background:var(--tm-raised);color:var(--tm-muted);font-size:16px;cursor:pointer;transition:color .15s ease,transform .1s ease}
.table-match__applaud:hover,.table-match__applaud.is-active{color:var(--tm-green);border-color:rgba(0,239,135,.4)}
.table-match__applaud:active{transform:scale(.9)}
.table-match__spectator-note{display:flex;align-items:flex-start;gap:9px;margin:0;padding:12px;border:1px solid rgba(0,239,135,.15);border-radius:12px;background:rgba(0,239,135,.045);color:#aeb8d5;font-size:11px;line-height:1.6}
.table-match__spectator-note i{flex:0 0 auto;margin-top:1px;color:var(--tm-green)}
.table-chat__row.is-applause .table-chat__message{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:999px;background:rgba(0,239,135,.1);border-color:rgba(0,239,135,.25);color:var(--tm-green);font-size:11px;font-weight:900}
@keyframes tm-applaud-pop{0%{transform:scale(1)}40%{transform:scale(1.35)}100%{transform:scale(1)}}
.table-match__applaud.is-popping{animation:tm-applaud-pop .35s ease}
@media(max-width:575px){.table-match__spectator-badge{padding:5px 9px;font-size:9px}.table-match__watching{font-size:10px}.table-match__applaud{width:32px;height:32px;font-size:14px}}
.gift-drawer{position:fixed;inset:0;z-index:1200;display:flex;align-items:flex-end;justify-content:center}
.gift-drawer[hidden]{display:none}
.gift-drawer__backdrop{position:absolute;inset:0;background:rgba(6,8,18,.68);backdrop-filter:blur(4px)}
.gift-drawer__sheet{position:relative;width:min(560px,100%);max-height:84vh;display:flex;flex-direction:column;overflow-y:auto;border:1px solid var(--tm-line,rgba(145,156,205,.16));border-radius:24px 24px 0 0;background:linear-gradient(170deg,#1a2041,#10142a);box-shadow:0 -20px 60px rgba(0,0,0,.45);padding-bottom:18px}
@media(min-width:720px){.gift-drawer{align-items:center}.gift-drawer__sheet{border-radius:22px;max-height:80vh}}
.gift-drawer__sheet>header{position:sticky;top:0;display:flex;align-items:center;gap:10px;padding:18px 20px;border-bottom:1px solid var(--tm-line,rgba(145,156,205,.16));background:inherit;z-index:1}
.gift-drawer__sheet>header h2{display:flex;align-items:center;gap:8px;margin:0;font-size:15px;font-weight:950;color:var(--tm-text,#f5f6ff)}
.gift-drawer__sheet>header button{display:grid;width:30px;height:30px;margin-left:auto;place-items:center;border:0;border-radius:9px;background:rgba(255,255,255,.06);color:var(--tm-muted,#929abc);cursor:pointer}
.gift-drawer__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:12px;padding:18px 20px}
.gift-drawer__item{display:grid;justify-items:center;gap:6px;padding:14px 8px;border:1px solid var(--tm-line,rgba(145,156,205,.16));border-radius:15px;background:rgba(35,42,71,.4);cursor:pointer;transition:border-color .15s ease,transform .1s ease}
.gift-drawer__item:hover{border-color:rgba(0,239,135,.4);transform:translateY(-2px)}
.gift-drawer__item img{width:48px;height:48px;object-fit:contain}
.gift-drawer__item strong{color:var(--tm-text,#f5f6ff);font-size:11.5px;font-weight:900}
.gift-drawer__item span{color:var(--tm-green,#00ef87);font-size:10.5px;font-weight:800}
.gift-drawer__step[hidden]{display:none}
.gift-drawer__back{display:inline-flex;align-items:center;gap:6px;margin:16px 20px 0;padding:0;border:0;background:none;color:var(--tm-muted,#929abc);font-size:11px;font-weight:800;cursor:pointer}
.gift-drawer__preview{display:grid;justify-items:center;gap:6px;margin:10px 20px 4px}
.gift-drawer__preview img{width:72px;height:72px;object-fit:contain}
.gift-drawer__preview strong{color:var(--tm-text,#f5f6ff);font-size:14px;font-weight:950}
.gift-drawer__label{display:block;margin:14px 20px 8px;color:#b5bdd8;font-size:11px;font-weight:850}
.gift-drawer__recipients{display:flex;flex-wrap:wrap;gap:8px;margin:0 20px}
.gift-drawer__recipient{min-height:36px;padding:0 14px;border:1px solid var(--tm-line,rgba(145,156,205,.16));border-radius:999px;background:rgba(35,42,71,.4);color:var(--tm-text,#f5f6ff);font-size:11.5px;font-weight:800;cursor:pointer}
.gift-drawer__recipient.is-selected{border-color:var(--tm-green,#00ef87);background:rgba(0,239,135,.12);color:var(--tm-green,#00ef87)}
#gift-drawer-quantity{width:calc(100% - 40px);min-height:44px;margin:0 20px;padding:0 12px;border:1px solid var(--tm-line,rgba(145,156,205,.16));border-radius:11px;background:#11162c;color:var(--tm-text,#f5f6ff);font-size:13px}
.gift-drawer__send-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:calc(100% - 40px);min-height:46px;margin:16px 20px 0;border:0;border-radius:13px;background:var(--tm-green,#00ef87);color:#062b1d;font-size:12.5px;font-weight:950;cursor:pointer}
.gift-drawer__send-btn:disabled{opacity:.6;cursor:not-allowed}
.gift-drawer__error{min-height:14px;margin:8px 20px 0;color:#ff95a6;font-size:11px}
.gift-burst{position:fixed;inset:0;z-index:1300;display:flex;align-items:center;justify-content:center;pointer-events:none}
.gift-burst[hidden]{display:none}
.gift-burst__card{display:grid;justify-items:center;gap:10px;padding:26px 34px;border-radius:24px;background:linear-gradient(160deg,rgba(26,32,65,.96),rgba(16,20,42,.96));border:1px solid rgba(0,239,135,.35);box-shadow:0 0 60px rgba(0,239,135,.25),0 30px 70px rgba(0,0,0,.5);animation:gift-burst-in .45s cubic-bezier(.2,.9,.3,1.3) forwards,gift-burst-out .4s ease .22s forwards}
.gift-burst__card img{width:110px;height:110px;object-fit:contain;filter:drop-shadow(0 0 22px rgba(0,239,135,.45))}
.gift-burst__card p{margin:0;max-width:260px;color:var(--tm-text,#f5f6ff);font-size:14px;font-weight:900;text-align:center}
@keyframes gift-burst-in{0%{transform:scale(.4) translateY(30px);opacity:0}60%{transform:scale(1.12) translateY(0);opacity:1}100%{transform:scale(1) translateY(0);opacity:1}}
@keyframes gift-burst-out{to{opacity:0;transform:translateY(-16px) scale(.96)}}
.gift-burst__sparkles{position:absolute;inset:0;overflow:hidden}
.gift-burst__sparkles i{position:absolute;bottom:40%;font-size:18px;color:#ffd76a;opacity:0;animation:gift-burst-sparkle 1.6s ease-out forwards}
@keyframes gift-burst-sparkle{0%{transform:translateY(0) scale(.3) rotate(0deg);opacity:0}15%{opacity:1}100%{transform:translateY(-220px) scale(1) rotate(180deg);opacity:0}}
@media(max-width:575px){.gift-drawer__grid{grid-template-columns:repeat(auto-fill,minmax(92px,1fr));padding:14px}.gift-burst__card{padding:20px 22px}.gift-burst__card img{width:84px;height:84px}}
.board-grid--chess{grid-template-columns:repeat(8,min(10vw,62px));gap:0;overflow:hidden;border:3px solid #8b98bd;border-radius:10px;box-shadow:0 14px 34px rgba(0,0,0,.35)}.board-cell--chess{width:min(10vw,62px);aspect-ratio:1;border:0;border-radius:0;background:#e9edf8;font-size:clamp(22px,5vw,38px);transition:background .15s ease}.board-cell--chess.is-dark{background:#7686ad}
.board-cell--chess.piece-light{color:#fdfdff;text-shadow:0 1px 1px rgba(0,0,0,.55),0 0 0 1px rgba(20,25,45,.5)}
.board-cell--chess.piece-dark{color:#14172a;text-shadow:0 1px 0 rgba(255,255,255,.35)}
.board-cell--chess.has-piece-own,.board-cell--chess.is-own-piece{cursor:pointer}
.board-cell--chess:not(:disabled):hover{background:rgba(0,239,135,.22)}
.board-cell--selected{box-shadow:inset 0 0 0 4px #00ef87;background:rgba(0,239,135,.28)!important}
.board-cell--last-move{background:rgba(255,204,112,.4)!important}
.board-cell--last-move.is-dark{background:rgba(214,163,78,.55)!important}
@keyframes chess-piece-in{from{transform:scale(.4) translateY(-14px) rotateX(50deg);opacity:0}60%{transform:scale(1.12) translateY(0) rotateX(0);opacity:1}to{transform:scale(1) translateY(0) rotateX(0);opacity:1}}
.board-cell--chess{transform-style:preserve-3d}
.board-cell--chess.piece-light:not(:empty){filter:drop-shadow(0 3px 2px rgba(0,0,0,.45))}
.board-cell--chess.piece-dark:not(:empty){filter:drop-shadow(0 2px 1px rgba(255,255,255,.25))}
.board-cell--chess:not(:empty){animation:chess-piece-in .18s ease}
@media(prefers-reduced-motion:reduce){.board-cell--chess:not(:empty){animation:none}}.board-grid--ludo{grid-template-columns:repeat(4,minmax(45px,72px));gap:9px}.board-token-button{display:grid;aspect-ratio:1;place-items:center;border:1px solid var(--tm-line);border-radius:50%;background:var(--tm-raised);color:var(--tm-text);font-size:20px;font-weight:900}.board-token-button.is-ready{border-color:var(--tm-green);box-shadow:0 0 0 3px rgba(0,239,135,.1)}.board-action-row{display:flex;flex-wrap:wrap;justify-content:center;gap:8px;margin-top:12px}.board-action-row button{min-height:38px;padding:0 12px;border:1px solid var(--tm-line);border-radius:10px;background:var(--tm-raised);color:var(--tm-text);font-size:10px;font-weight:850}
</style>
@endpush
@push('style')
<style>
.board-grid--sea{grid-template-columns:repeat(10,minmax(0,1fr));gap:3px;width:min(100%,440px)}.board-cell--sea{width:100%;min-width:0;aspect-ratio:1;border-radius:5px;background:#18304a;font-size:clamp(9px,2.4vw,16px)}.board-cell--sea.is-ship{background:#43566e}.board-cell--sea.is-hit{background:#9b3c52;color:#fff}.board-cell--sea.is-miss{background:#10182d;color:#91b3d0}
.table-chat{margin-top:14px;padding:16px;border:1px solid var(--tm-line);border-radius:20px;background:linear-gradient(145deg,rgba(24,30,55,.98),rgba(16,20,39,.98));color:var(--tm-text)}.table-chat__head{display:flex;align-items:center;gap:11px}.table-chat__icon{display:grid;width:42px;height:42px;place-items:center;border-radius:14px;background:rgba(0,239,135,.12);color:var(--tm-green);font-size:22px}.table-chat__head h2{margin:0;font-size:14px;font-weight:950}.table-chat__head p{margin:3px 0 0;color:var(--tm-muted);font-size:10px}.table-chat__live{margin-left:auto;color:var(--tm-green);font-size:9px;font-weight:950;letter-spacing:.1em}.table-chat__live i{display:inline-block;width:7px;height:7px;margin-right:5px;border-radius:50%;background:var(--tm-green);box-shadow:0 0 9px var(--tm-green)}.table-chat__messages{display:flex;min-height:110px;max-height:340px;overflow:auto;flex-direction:column;gap:10px;margin:14px 0;padding:12px;border:1px solid var(--tm-line);border-radius:14px;background:rgba(7,11,25,.38);overscroll-behavior:contain}.table-chat__empty{margin:auto;color:var(--tm-muted);font-size:11px}.table-chat__message{align-self:flex-start;max-width:min(82%,650px);padding:9px 11px;border:1px solid var(--tm-line);border-radius:14px 14px 14px 4px;background:var(--tm-raised)}.table-chat__message.is-mine{align-self:flex-end;border-color:rgba(0,239,135,.18);border-radius:14px 14px 4px 14px;background:rgba(0,239,135,.075)}.table-chat__meta{display:flex;gap:8px;margin-bottom:4px;color:var(--tm-muted);font-size:9px}.table-chat__meta strong{color:var(--tm-green)}.table-chat__body{margin:0;overflow-wrap:anywhere;white-space:pre-wrap;font-size:12px;line-height:1.5}.table-chat__attachment{display:inline-flex;align-items:center;gap:7px;margin-top:6px;color:#c7d2f2;font-size:11px;text-decoration:none}.table-chat__attachment:hover{color:var(--tm-green)}.table-chat__reactions{display:flex;gap:5px;flex-wrap:wrap;margin-top:7px}.table-chat__reactions button{min-height:25px;padding:2px 7px;border:1px solid var(--tm-line);border-radius:14px;background:#11162c;color:var(--tm-text);font-size:11px}.table-chat__reactions button.is-mine{border-color:var(--tm-green);background:rgba(0,239,135,.12)}.table-chat__tools{display:flex;align-items:center;gap:8px;margin:0 0 8px}.table-chat__file,.table-chat__voice{display:inline-flex;min-height:34px;align-items:center;justify-content:center;gap:6px;padding:0 10px;border:1px solid var(--tm-line);border-radius:11px;background:var(--tm-raised);color:#d1d8ef;font-size:11px}.table-chat__file{width:36px;padding:0;font-size:16px;cursor:pointer}.table-chat__voice.is-recording{border-color:#ff8198;color:#ff9caf}.table-chat__hint{margin-left:auto;color:var(--tm-muted);font-size:9px}.table-chat__form{display:flex;align-items:flex-end;gap:8px;padding:7px;border:1px solid var(--tm-line);border-radius:14px;background:#10152a}.table-chat__form textarea{flex:1;max-height:100px;resize:vertical;border:0;outline:0;background:transparent;color:var(--tm-text);font:inherit;font-size:12px}.table-chat__send{display:grid;width:37px;height:37px;flex:0 0 37px;place-items:center;border:0;border-radius:12px;background:var(--tm-green);color:#062b1d;font-size:19px}.table-chat__error{min-height:13px;margin:5px 2px 0;color:#ff95a6;font-size:10px}.table-chat__audio{display:block;width:min(280px,70vw);height:36px;margin-top:6px}.table-chat__image{display:block;max-width:min(240px,65vw);max-height:180px;margin-top:7px;border-radius:10px}.table-chat__attachment:focus-visible,.table-chat__reactions button:focus-visible,.table-chat__send:focus-visible{outline:2px solid var(--tm-green);outline-offset:2px}@media(max-width:575px){.table-chat{margin-top:10px;padding:12px;border-radius:16px}.table-chat__messages{max-height:42vh}.table-chat__message{max-width:92%}.table-chat__head p{max-width:220px}.table-chat__voice span{display:inline}}
</style>
@endpush
@push('style')
<style>.board-grid--bg{grid-template-columns:repeat(6,minmax(0,1fr));gap:5px;width:min(100%,560px)}.board-cell--bg{width:100%;min-width:0;aspect-ratio:1.2;border-radius:8px;font-size:clamp(9px,2vw,13px)}.board-cell--bg.is-own{color:var(--tm-green)}.board-select-row{display:flex;justify-content:center;gap:8px;flex-wrap:wrap;margin:12px}.board-select-row select{min-height:38px;padding:0 10px;border:1px solid var(--tm-line);border-radius:10px;background:var(--tm-raised);color:var(--tm-text)}</style>
@endpush
@push('style')
<style>
.table-match__invite{position:relative;gap:11px;padding:17px;border-radius:18px;background:linear-gradient(145deg,rgba(35,42,71,.7),rgba(17,22,44,.84));box-shadow:inset 0 1px rgba(255,255,255,.025)}.table-match__invite label{color:var(--tm-text);font-size:13px;font-weight:850}.table-match__invite-control{position:relative;display:flex;gap:9px}.table-match__invite input{height:46px;padding:0 14px;border-radius:13px;font-size:14px;transition:border-color .18s,box-shadow .18s}.table-match__invite input:focus{border-color:var(--tm-green);outline:0;box-shadow:0 0 0 3px rgba(0,239,135,.12)}.table-match__invite button{display:inline-flex;min-height:44px;align-items:center;justify-content:center;gap:6px;padding:0 16px;border-radius:13px;font-size:12px;white-space:nowrap;transition:filter .18s,transform .18s}.table-match__invite button:hover{filter:brightness(1.08);color:#062b1d;transform:translateY(-1px)}.table-match__invite button:disabled{cursor:wait;opacity:.65;transform:none}.table-match__suggestions{position:absolute;z-index:20;top:calc(100% + 7px);right:0;left:0;max-height:240px;overflow:auto;padding:6px;border:1px solid var(--tm-line);border-radius:14px;background:#171d35;box-shadow:0 18px 40px rgba(0,0,0,.38)}.table-match__suggestion{display:flex!important;width:100%;min-height:42px!important;justify-content:flex-start!important;padding:0 12px!important;border:0!important;border-radius:9px!important;background:transparent!important;color:var(--tm-text)!important;font-size:13px!important;text-align:left}.table-match__suggestion:hover,.table-match__suggestion:focus-visible{background:rgba(0,239,135,.1)!important;color:var(--tm-green)!important;transform:none!important}.table-match__suggestion-note{display:block;padding:11px;color:var(--tm-muted);font-size:12px}.table-match__invite small{min-height:14px;font-size:11px}
.table-chat{padding:19px;border-color:rgba(145,156,205,.2);border-radius:22px;background:radial-gradient(ellipse at 100% 0,rgba(0,239,135,.055),transparent 40%),linear-gradient(145deg,#171d35,#10152a);box-shadow:0 14px 34px rgba(0,0,0,.12)}.table-chat__head h2{font-size:16px}.table-chat__head p{font-size:11px}.table-chat__messages{min-height:190px;max-height:430px;gap:12px;padding:15px;border-radius:18px;background:radial-gradient(circle at 15% 15%,rgba(90,107,158,.08),transparent 40%),#0d1225}.table-chat__message{padding:10px 13px;border-radius:17px 17px 17px 5px;background:#202844;box-shadow:0 3px 12px rgba(0,0,0,.13)}.table-chat__message.is-mine{border-color:rgba(0,239,135,.22);border-radius:17px 17px 5px 17px;background:linear-gradient(135deg,rgba(0,239,135,.14),rgba(0,155,105,.1))}.table-chat__meta{align-items:center;font-size:10px}.table-chat__body{font-size:13px;line-height:1.55}.table-chat__tools{gap:9px;margin-bottom:10px}.table-chat__file,.table-chat__voice{min-height:39px;border-radius:13px;font-size:12px;transition:background .18s,border-color .18s,color .18s}.table-chat__file:hover,.table-chat__voice:hover{border-color:rgba(0,239,135,.45);background:rgba(0,239,135,.08);color:var(--tm-green)}.table-chat__form{align-items:flex-end;gap:10px;padding:9px;border-color:rgba(145,156,205,.22);border-radius:19px;background:#10162b;transition:border-color .18s,box-shadow .18s}.table-chat__form:focus-within{border-color:rgba(0,239,135,.58);box-shadow:0 0 0 3px rgba(0,239,135,.08)}.table-chat__form textarea{min-height:40px;max-height:160px;overflow-y:hidden;resize:none;padding:9px 10px;font-size:14px;line-height:1.5}.table-chat__form textarea::placeholder{color:#828daf;opacity:1}.table-chat__send{width:43px;height:43px;flex-basis:43px;border-radius:15px;box-shadow:0 5px 15px rgba(0,239,135,.16);transition:filter .18s,transform .18s}.table-chat__send:hover{filter:brightness(1.08);color:#062b1d;transform:translateY(-1px)}.table-chat__reactions button{min-height:28px;border-radius:16px;background:rgba(12,18,37,.7);transition:border-color .16s,background .16s}.table-chat__reactions button:hover{border-color:rgba(0,239,135,.48);background:rgba(0,239,135,.1);color:var(--tm-text)}
@media(max-width:575px){.table-match__invite{padding:15px}.table-match__invite-control{gap:7px}.table-match__invite input{height:45px;font-size:13px}.table-match__invite button{padding:0 12px}.table-chat{padding:14px}.table-chat__messages{min-height:170px;max-height:48vh;padding:11px}.table-chat__form{padding:7px}.table-chat__form textarea{font-size:14px}.table-chat__send{width:40px;height:40px;flex-basis:40px}}
</style>
@endpush
@push('style')
<style>
.result-popup{position:fixed;inset:0;z-index:1200;display:flex;align-items:center;justify-content:center;padding:18px}
.result-popup[hidden]{display:none}
.result-popup__backdrop{position:absolute;inset:0;background:rgba(6,8,18,.72);backdrop-filter:blur(6px)}
.result-popup__card{position:relative;width:min(380px,100%);padding:34px 26px 26px;border:1px solid var(--tm-line,rgba(145,156,205,.16));border-radius:26px;background:linear-gradient(160deg,#1a2041,#10142a);box-shadow:0 30px 80px rgba(0,0,0,.5);text-align:center;color:var(--tm-text,#f5f6ff);overflow:hidden;transform:scale(.9);opacity:0;animation:result-pop-in .32s cubic-bezier(.2,.9,.3,1.3) forwards}
@media(prefers-reduced-motion:reduce){.result-popup__card{animation:none;transform:none;opacity:1}}
@keyframes result-pop-in{to{transform:scale(1);opacity:1}}
.result-popup__fx{position:absolute;inset:0;overflow:hidden;pointer-events:none}
.result-popup__confetti{position:absolute;top:-12px;width:8px;height:14px;border-radius:2px;animation:result-confetti-fall linear forwards}
@keyframes result-confetti-fall{to{transform:translateY(420px) rotate(540deg);opacity:0}}
@media(prefers-reduced-motion:reduce){.result-popup__confetti{display:none}}
.result-popup__close{position:absolute;top:14px;right:14px;display:grid;width:32px;height:32px;place-items:center;border:0;border-radius:10px;background:rgba(255,255,255,.06);color:var(--tm-muted,#929abc);font-size:16px;cursor:pointer}
.result-popup__close:hover{background:rgba(255,255,255,.12);color:var(--tm-text,#f5f6ff)}
.result-popup__icon{position:relative;z-index:1;display:grid;width:78px;height:78px;margin:0 auto 14px;place-items:center;border-radius:50%;font-size:38px}
.result-popup.is-win .result-popup__icon{background:radial-gradient(circle,rgba(0,239,135,.22),rgba(0,239,135,.04));color:var(--tm-green,#00ef87);box-shadow:0 0 0 1px rgba(0,239,135,.25),0 0 40px rgba(0,239,135,.25)}
.result-popup.is-lose .result-popup__icon{background:radial-gradient(circle,rgba(255,112,135,.18),rgba(255,112,135,.03));color:#ff95a6;box-shadow:0 0 0 1px rgba(255,112,135,.22)}
.result-popup.is-draw .result-popup__icon{background:radial-gradient(circle,rgba(145,156,205,.22),rgba(145,156,205,.04));color:#bdc7e6;box-shadow:0 0 0 1px rgba(145,156,205,.22)}
.result-popup__title{position:relative;z-index:1;margin:0 0 6px;font-size:23px;font-weight:950}
.result-popup__subtitle{position:relative;z-index:1;margin:0;color:var(--tm-muted,#929abc);font-size:12.5px;line-height:1.5}
.result-popup__amount{position:relative;z-index:1;margin:18px 0;padding:14px;border-radius:16px;background:rgba(255,255,255,.04)}
.result-popup__amount-value{display:block;font-size:27px;font-weight:950}
.result-popup.is-win .result-popup__amount-value{color:var(--tm-green,#00ef87)}
.result-popup.is-lose .result-popup__amount-value{color:#ff95a6}
.result-popup__amount-label{display:block;margin-top:3px;color:var(--tm-muted,#929abc);font-size:10.5px;font-weight:800;letter-spacing:.04em;text-transform:uppercase}
.result-popup__actions{position:relative;z-index:1;display:flex;gap:10px;margin-top:6px}
.result-popup__btn{display:flex;flex:1;align-items:center;justify-content:center;gap:7px;min-height:46px;border-radius:13px;font-size:12.5px;font-weight:900;text-decoration:none}
.result-popup__btn--primary{background:var(--tm-green,#00ef87);color:#062b1d}
.result-popup__btn--primary:hover{filter:brightness(1.07)}
.result-popup__btn--ghost{border:1px solid var(--tm-line,rgba(145,156,205,.16));color:var(--tm-text,#f5f6ff)}
.result-popup__btn--ghost:hover{background:rgba(255,255,255,.06)}
.table-match__info{display:grid;width:38px;height:38px;place-items:center;margin-left:auto;border:1px solid var(--tm-line,rgba(145,156,205,.16));border-radius:12px;background:rgba(255,255,255,.03);color:var(--tm-text,#f5f6ff);font-size:18px;cursor:pointer}
.table-match__info:hover{border-color:rgba(0,239,135,.4);color:var(--tm-green,#00ef87)}
.how-to-play{position:fixed;inset:0;z-index:1190;display:flex;align-items:flex-end;justify-content:center}
.how-to-play[hidden]{display:none}
.how-to-play__backdrop{position:absolute;inset:0;background:rgba(6,8,18,.68);backdrop-filter:blur(4px)}
.how-to-play__sheet{position:relative;width:min(560px,100%);max-height:84vh;display:flex;flex-direction:column;border:1px solid var(--tm-line,rgba(145,156,205,.16));border-radius:24px 24px 0 0;background:linear-gradient(170deg,#1a2041,#10142a);box-shadow:0 -20px 60px rgba(0,0,0,.45);animation:how-to-play-up .26s cubic-bezier(.2,.9,.3,1.1) forwards;transform:translateY(16px);opacity:0}
@media(min-width:720px){.how-to-play{align-items:center}.how-to-play__sheet{border-radius:22px;max-height:80vh}}
@media(prefers-reduced-motion:reduce){.how-to-play__sheet{animation:none;transform:none;opacity:1}}
@keyframes how-to-play-up{to{transform:translateY(0);opacity:1}}
.how-to-play__sheet>header{display:flex;align-items:center;gap:10px;padding:18px 20px;border-bottom:1px solid var(--tm-line,rgba(145,156,205,.16))}
.how-to-play__sheet>header h2{margin:0;font-size:15px;font-weight:950;color:var(--tm-text,#f5f6ff)}
.how-to-play__sheet>header button{display:grid;width:30px;height:30px;margin-left:auto;place-items:center;border:0;border-radius:9px;background:rgba(255,255,255,.06);color:var(--tm-muted,#929abc);cursor:pointer}
.how-to-play__sheet>header button:hover{background:rgba(255,255,255,.12);color:var(--tm-text,#f5f6ff)}
.how-to-play__body{overflow-y:auto;padding:18px 20px 26px;display:grid;gap:16px}
.how-to-play__body section h3{display:flex;align-items:center;gap:8px;margin:0 0 6px;color:var(--tm-green,#00ef87);font-size:12px;font-weight:950;letter-spacing:.03em;text-transform:uppercase}
.how-to-play__body section p,.how-to-play__body section ol{margin:0;color:#c7cfea;font-size:12.5px;line-height:1.6}
.how-to-play__body section ol{padding-left:18px}
.how-to-play__body section li{margin-bottom:4px}
.sound-control{display:flex;align-items:center;gap:7px;margin-left:0;padding:5px 9px;border:1px solid var(--tm-line,rgba(145,156,205,.16));border-radius:13px;background:rgba(255,255,255,.03)}
.sound-control #sound-toggle{display:grid;width:26px;height:26px;place-items:center;border:0;border-radius:9px;background:transparent;color:var(--tm-text,#f5f6ff);font-size:16px;cursor:pointer}
.sound-control.is-muted #sound-toggle{color:var(--tm-muted,#929abc)}
.sound-control #sound-volume{width:64px;accent-color:var(--tm-green,#00ef87)}
@media(max-width:575px){.sound-control #sound-volume{display:none}}
</style>
@endpush
@push('style')
<style>
/* ---- Chat redesign overrides (later in source order = wins over the base chat rules above) ---- */
.table-chat__safety{display:flex;align-items:center;gap:7px;margin:10px 0 0;padding:8px 11px;border:1px solid rgba(255,204,112,.22);border-radius:11px;background:rgba(255,204,112,.06);color:#e8c88a;font-size:10.5px;line-height:1.4}
.table-chat__safety i{flex:0 0 auto;font-size:14px}
.table-chat__jump{display:flex;align-items:center;gap:6px;align-self:center;margin:-6px 0 8px;padding:6px 13px;border:1px solid rgba(0,239,135,.3);border-radius:999px;background:#122a22;color:var(--tm-green,#00ef87);font-size:11px;font-weight:800;cursor:pointer}
.table-chat__jump[hidden]{display:none}
.table-chat__reply{display:flex;align-items:center;gap:8px;margin-bottom:8px;padding:8px 10px;border-left:3px solid var(--tm-green,#00ef87);border-radius:9px;background:rgba(255,255,255,.04)}
.table-chat__reply[hidden]{display:none}
.table-chat__reply>div{min-width:0;flex:1}
.table-chat__reply small{display:block;color:var(--tm-green,#00ef87);font-size:10px;font-weight:900}
.table-chat__reply p{margin:1px 0 0;overflow:hidden;color:#c7cfea;font-size:11.5px;text-overflow:ellipsis;white-space:nowrap}
.table-chat__reply button{display:grid;width:24px;height:24px;flex:0 0 auto;place-items:center;border:0;border-radius:8px;background:rgba(255,255,255,.06);color:var(--tm-muted,#929abc);cursor:pointer}
.table-chat__record{display:flex;align-items:center;gap:10px;margin-bottom:8px;padding:9px 12px;border:1px solid rgba(255,129,152,.3);border-radius:14px;background:rgba(255,129,152,.06)}
.table-chat__record[hidden]{display:none}
.table-chat__record button{display:grid;width:32px;height:32px;flex:0 0 auto;place-items:center;border:0;border-radius:10px;cursor:pointer}
#chat-record-cancel{background:rgba(255,255,255,.06);color:#ff95a6}
.table-chat__record-stop{background:var(--tm-green,#00ef87);color:#062b1d}
.table-chat__record-meter{display:flex;align-items:center;gap:2px;flex:1;height:26px}
.table-chat__record-meter span{flex:1;min-width:2px;border-radius:2px;background:#ff95a6;transition:height .08s ease}
.table-chat__record-time{flex:0 0 auto;color:#ff95a6;font-size:11px;font-weight:900;font-variant-numeric:tabular-nums}
.table-chat__picker-anchor{position:relative;display:flex}
.table-chat__icon-btn{display:grid;width:36px;height:36px;place-items:center;border:0;border-radius:11px;background:transparent;color:var(--tm-muted,#929abc);font-size:18px;cursor:pointer}
.table-chat__icon-btn:hover{background:rgba(255,255,255,.06);color:var(--tm-text,#f5f6ff)}
.emoji-picker,.attach-menu{position:absolute;bottom:calc(100% + 8px);left:0;z-index:40;border:1px solid var(--tm-line,rgba(145,156,205,.16));border-radius:16px;background:#171d35;box-shadow:0 18px 44px rgba(0,0,0,.4)}
.emoji-picker[hidden],.attach-menu[hidden]{display:none}
.emoji-picker{width:min(300px,80vw);padding:10px}
.emoji-picker__search{display:flex;align-items:center;gap:8px;margin-bottom:8px;padding:0 10px;border:1px solid var(--tm-line,rgba(145,156,205,.16));border-radius:10px;background:#10142a}
.emoji-picker__search i{color:var(--tm-muted,#929abc);font-size:13px}
.emoji-picker__search input{flex:1;min-height:34px;border:0;background:transparent;color:var(--tm-text,#f5f6ff);font:inherit;font-size:12px;outline:0}
.emoji-picker__tabs{display:flex;gap:3px;margin-bottom:6px;overflow-x:auto}
.emoji-picker__tabs button{flex:0 0 auto;min-height:28px;padding:0 10px;border:0;border-radius:9px;background:transparent;color:var(--tm-muted,#929abc);font-size:11px;font-weight:800;cursor:pointer}
.emoji-picker__tabs button.is-active{background:rgba(0,239,135,.14);color:var(--tm-green,#00ef87)}
.emoji-picker__grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;max-height:200px;overflow-y:auto}
.emoji-picker__grid button{display:grid;aspect-ratio:1;place-items:center;border:0;border-radius:9px;background:transparent;font-size:19px;cursor:pointer}
.emoji-picker__grid button:hover{background:rgba(255,255,255,.07)}
.attach-menu{display:flex;flex-direction:column;min-width:190px;padding:6px;gap:2px}
.attach-menu button{display:flex;align-items:center;gap:11px;min-height:40px;padding:0 11px;border:0;border-radius:10px;background:transparent;color:var(--tm-text,#f5f6ff);font-size:12.5px;font-weight:700;cursor:pointer}
.attach-menu button:hover{background:rgba(0,239,135,.09);color:var(--tm-green,#00ef87)}
.attach-menu button i{width:18px;font-size:16px}
.table-chat__form{display:flex;align-items:center;gap:4px;padding:6px 7px;border:1px solid rgba(145,156,205,.22);border-radius:19px;background:#10162b;transition:border-color .18s,box-shadow .18s}
.table-chat__form:focus-within{border-color:rgba(0,239,135,.55);box-shadow:0 0 0 3px rgba(0,239,135,.08)}
.table-chat__form textarea{flex:1;min-width:0;min-height:38px;max-height:120px;margin:0 2px;padding:9px 2px;border:0;outline:0;resize:none;background:transparent;color:var(--tm-text,#f5f6ff);font:inherit;font-size:14px;line-height:1.4}
.table-chat__form textarea::placeholder{color:#828daf;opacity:1}
.table-chat__send,.table-chat__voice{display:none;place-items:center;width:40px;height:40px;flex:0 0 40px;border:0;border-radius:14px;background:var(--tm-green,#00ef87);color:#062b1d;font-size:18px;cursor:pointer;transition:filter .15s,transform .15s}
.table-chat__send:hover,.table-chat__voice:hover{filter:brightness(1.07);transform:translateY(-1px)}
.table-chat__form.has-text .table-chat__send{display:grid}
.table-chat__form:not(.has-text) .table-chat__voice{display:grid}
.table-chat__avatar{display:grid;flex:0 0 26px;width:26px;height:26px;margin-right:7px;place-items:center;align-self:flex-end;border-radius:50%;background:var(--tm-raised);color:#c7cfea;font-size:10.5px;font-weight:950;overflow:hidden}.table-chat__avatar img{width:100%;height:100%;object-fit:cover}
.table-chat__row{display:flex;align-items:flex-end}
.table-chat__row.is-mine{justify-content:flex-end}
.table-chat__row.is-grouped .table-chat__avatar{visibility:hidden}
.table-chat__date-sep{align-self:center;margin:4px 0;padding:4px 12px;border-radius:999px;background:rgba(255,255,255,.05);color:var(--tm-muted,#929abc);font-size:10px;font-weight:800}
.table-chat__reply-quote{margin:0 0 6px;padding:6px 9px;border-left:3px solid rgba(0,239,135,.5);border-radius:7px;background:rgba(0,0,0,.18);color:#aab3d4;font-size:11px}
.table-chat__reply-quote strong{display:block;color:var(--tm-green,#00ef87);font-size:10px}
.table-chat__ticks{margin-left:5px;font-size:12px;color:#8891b4}
.table-chat__ticks.is-read{color:#5bb8ff}
.table-chat__react-btn{display:inline-grid;width:22px;height:22px;margin-left:6px;place-items:center;border:0;border-radius:8px;background:rgba(255,255,255,.06);color:var(--tm-muted,#929abc);font-size:12px;cursor:pointer;vertical-align:middle}
.table-chat__react-btn:hover{background:rgba(0,239,135,.14);color:var(--tm-green,#00ef87)}
.table-chat__message{position:relative}
.quick-react-popover{position:absolute;z-index:45;bottom:calc(100% + 6px);right:0;display:flex;gap:2px;padding:5px;border:1px solid var(--tm-line,rgba(145,156,205,.16));border-radius:14px;background:#171d35;box-shadow:0 14px 34px rgba(0,0,0,.35)}
.quick-react-popover button{display:grid;width:30px;height:30px;place-items:center;border:0;border-radius:9px;background:transparent;font-size:17px;cursor:pointer}
.quick-react-popover button:hover{background:rgba(255,255,255,.08);transform:scale(1.15)}
.message-menu{flex-direction:column;min-width:180px;left:0;right:auto}
.message-menu button{width:auto;height:auto;justify-content:flex-start;gap:9px;padding:9px 11px;font-size:12.5px;font-weight:700;color:var(--tm-text,#f5f6ff)}
.message-menu button:hover{transform:none;background:rgba(0,239,135,.1);color:var(--tm-green,#00ef87)}
.table-chat__reactions:empty{display:none}
.table-chat__voice-player{display:flex;align-items:center;gap:9px;min-width:220px;margin-top:4px}
.table-chat__voice-player button{display:grid;width:34px;height:34px;flex:0 0 auto;place-items:center;border:0;border-radius:50%;background:rgba(255,255,255,.1);color:var(--tm-text,#f5f6ff);font-size:15px;cursor:pointer}
.table-chat__voice-track{position:relative;flex:1;height:26px;cursor:pointer}
.table-chat__voice-bars{position:absolute;inset:0;display:flex;align-items:center;gap:2px}
.table-chat__voice-bars span{flex:1;min-width:1px;border-radius:2px;background:rgba(255,255,255,.22)}
.table-chat__voice-progress{position:absolute;inset:0 100% 0 0;overflow:hidden}
.table-chat__voice-progress .table-chat__voice-bars span{background:var(--tm-green,#00ef87)}
.table-chat__voice-time{flex:0 0 auto;color:var(--tm-muted,#929abc);font-size:10px;font-variant-numeric:tabular-nums}
.table-chat__voice-speed{flex:0 0 auto;min-width:30px;height:22px;padding:0 6px;border-radius:7px;background:rgba(255,255,255,.08);color:var(--tm-text,#f5f6ff);font-size:10px;font-weight:800}
@media(max-width:575px){.table-chat__form textarea{font-size:16px}.emoji-picker{width:min(280px,82vw)}}
</style>
@endpush
@push('style')
<style>
/* ---- Playing-card visuals for Spades/Hearts/Rummy/Blackjack/Texas Hold'em/Crazy Eights/Whot ----
   .board-card had no styling at all before this - these rendered as bare default buttons. ---- */
.board-card-hand{display:flex;flex-wrap:wrap;justify-content:center;gap:8px;margin:10px 0;perspective:700px}
.board-card{
    display:inline-flex;flex-direction:column;align-items:flex-start;justify-content:flex-start;width:52px;height:74px;padding:6px;
    border:0;border-radius:9px;background:linear-gradient(160deg,#fbfbff,#e9ecf7);color:#1b1f33;font-size:16px;font-weight:950;line-height:1;
    box-shadow:0 1px 0 rgba(255,255,255,.6) inset,0 6px 14px rgba(0,0,0,.3);cursor:pointer;transition:transform .14s ease,box-shadow .14s ease;
    animation:board-card-deal .3s cubic-bezier(.2,.8,.3,1.1);transform-style:preserve-3d;backface-visibility:hidden;
}
@keyframes board-card-deal{from{transform:translateY(16px) rotateY(90deg) scale(.8);opacity:0}60%{transform:translateY(-3px) rotateY(0) scale(1.03);opacity:1}to{transform:none;opacity:1}}
@media(prefers-reduced-motion:reduce){.board-card{animation:none}}
.board-card:not(:disabled):hover{transform:translateY(-7px) rotateX(8deg);box-shadow:0 1px 0 rgba(255,255,255,.6) inset,0 16px 26px rgba(0,0,0,.4)}
.board-card:disabled{cursor:default;filter:grayscale(.55);opacity:.55;box-shadow:none}
.board-card[data-suit="H"],.board-card[data-suit="D"]{color:#c21e3f}
.board-card[data-suit="S"],.board-card[data-suit="C"]{color:#15182a}
.board-card-pile{margin:6px 0 10px;padding:9px 13px;border-radius:12px;background:rgba(255,255,255,.04);color:#c7cfea;font-size:11.5px;font-weight:700;text-align:center}
.board-card-suit{min-height:38px;margin:0 auto 10px;padding:0 12px;border:1px solid var(--tm-line,rgba(145,156,205,.16));border-radius:10px;background:var(--tm-raised,#232a47);color:var(--tm-text,#f5f6ff);display:block}
/* ---- Dice pip graphics (Ludo roll, Backgammon dice) ---- */
.dice-roll-row{display:flex;align-items:center;justify-content:center;gap:10px;margin:10px 0;color:var(--tm-muted,#929abc);font-size:11px;font-weight:800;perspective:500px}
.dice-face{display:grid;grid-template-columns:repeat(3,1fr);grid-template-rows:repeat(3,1fr);gap:3px;width:40px;height:40px;padding:6px;border-radius:9px;background:linear-gradient(160deg,#fbfbff,#e4e7f2);box-shadow:0 1px 0 rgba(255,255,255,.6) inset,0 6px 14px rgba(0,0,0,.3);animation:dice-tumble .5s cubic-bezier(.2,.8,.3,1.05)}
@keyframes dice-tumble{0%{transform:rotate3d(1,1,0,340deg) scale(.6);opacity:.2}70%{transform:rotate3d(1,1,0,-18deg) scale(1.08)}to{transform:none;opacity:1}}
@media(prefers-reduced-motion:reduce){.dice-face{animation:none}}
.dice-face i{border-radius:50%;background:transparent}
.dice-face i.is-on{background:#1b1f33}
/* ---- Physical felt table surface behind every board ---- */
.table-board{
    border-radius:18px;border:1px solid rgba(255,255,255,.05);perspective:900px;
    background:radial-gradient(ellipse at 50% 15%,rgba(0,239,135,.05),transparent 60%),radial-gradient(ellipse at center,#163026,#0d1f19 72%);
    box-shadow:inset 0 2px 0 rgba(255,255,255,.04),inset 0 -18px 40px rgba(0,0,0,.45),inset 0 0 0 10px rgba(80,55,28,.22);
}
</style>
@endpush
@push('script')
<script>
(()=>{const root=document.querySelector('#table-match');if(!root)return;const stateUrl=root.dataset.snapshot;const api={move:root.dataset.move,start:root.dataset.start,leave:root.dataset.leave};const board=document.querySelector('#table-board');const error=document.querySelector('#match-error');const notice=document.querySelector('#match-notice');const userId=Number(root.dataset.user);const type=root.dataset.type;const turnSeconds=Number(root.dataset.turnSeconds)||60;let latest=null,clockTimer=null,polling=false,pollTimer=null,selectedSquare=null,resultShown=false,warnedDeadline=null;
(()=>{const control=document.querySelector('#sound-control');if(!control||!window.TableSound)return;const toggle=document.querySelector('#sound-toggle');const volume=document.querySelector('#sound-volume');const icon=toggle.querySelector('i');const sync=()=>{const on=window.TableSound.isEnabled();control.classList.toggle('is-muted',!on);icon.className=on?'las la-volume-up':'las la-volume-mute';volume.value=String(Math.round(window.TableSound.getVolume()*100));};toggle.addEventListener('click',()=>{window.TableSound.setEnabled(!window.TableSound.isEnabled());if(window.TableSound.isEnabled())window.TableSound.play('click');sync();});volume.addEventListener('input',()=>{window.TableSound.setVolume(Number(volume.value)/100);});volume.addEventListener('change',()=>window.TableSound.play('click'));sync();})();
const resultPopup=document.querySelector('#result-popup');
function showResultPopup(data){
    if(!resultPopup||resultShown)return;resultShown=true;
    const mySeat=data.players.find(p=>p.id===userId)?.seat;
    let outcome='draw';
    if(data.settlement){outcome=data.settlement.type==='payout'?'win':data.settlement.type==='loss'?'lose':'draw';}
    else if(data.result&&data.result.startsWith('winner:')){outcome=Number(data.result.slice(7))===mySeat?'win':'lose';}
    else if(data.result&&data.result.startsWith('winners:')){outcome=data.result.slice(8).split(',').map(Number).includes(mySeat)?'win':'lose';}
    resultPopup.classList.remove('is-win','is-lose','is-draw');resultPopup.classList.add(`is-${outcome}`);
    const icon=document.querySelector('#result-popup-icon i');icon.className=outcome==='win'?'las la-trophy':outcome==='lose'?'las la-flag':'las la-handshake';
    const opponentNames=data.players.filter(p=>p.seat!==mySeat).map(p=>p.name).filter(Boolean).join(' & ')||'your opponent';
    document.querySelector('#result-popup-title').textContent=outcome==='win'?'Congratulations!':outcome==='lose'?'You Lost':'Game Drawn';
    document.querySelector('#result-popup-subtitle').textContent=outcome==='win'?`You beat ${opponentNames}.`:outcome==='lose'?`${opponentNames} won this round.`:'Stakes were refunded to every player.';
    const amountBox=document.querySelector('#result-popup-amount');
    if(data.settlement&&(outcome==='win'||outcome==='lose')){
        amountBox.hidden=false;
        const valueEl=document.querySelector('#result-popup-amount-value');
        const label=document.querySelector('#result-popup-amount-label');
        const target=Number(outcome==='win'?data.settlement.amount:data.settlement.stake);
        const formatted=outcome==='win'?data.settlement.amount_formatted:data.settlement.stake_formatted;
        label.textContent=outcome==='win'?'Amount won':'Amount lost';
        const reduceMotion=matchMedia('(prefers-reduced-motion: reduce)').matches;
        if(reduceMotion||!formatted){valueEl.textContent=formatted||target.toFixed(2);}
        else{
            const duration=900;const start=performance.now();
            const prefix=(formatted.match(/^[^0-9.-]*/)||[''])[0];
            const step=now=>{const progress=Math.min(1,(now-start)/duration);const eased=1-Math.pow(1-progress,3);valueEl.textContent=prefix+(target*eased).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});if(progress<1)requestAnimationFrame(step);else valueEl.textContent=formatted;};
            requestAnimationFrame(step);
        }
    }else{amountBox.hidden=true;}
    if(outcome==='win'&&!matchMedia('(prefers-reduced-motion: reduce)').matches){
        const fx=document.querySelector('#result-popup-fx');fx.replaceChildren();
        const colors=['#00ef87','#ffcc70','#7ea0ff','#ff95a6','#fff'];
        for(let i=0;i<36;i++){const piece=document.createElement('span');piece.className='result-popup__confetti';piece.style.left=Math.random()*100+'%';piece.style.background=colors[i%colors.length];piece.style.animationDuration=(1.4+Math.random()*1.1)+'s';piece.style.animationDelay=(Math.random()*.5)+'s';fx.append(piece);}
    }
    window.TableSound?.play?.(outcome);
    resultPopup.hidden=false;
}
document.querySelector('#result-popup-close')?.addEventListener('click',()=>{resultPopup.hidden=true;});
document.querySelector('#result-popup-backdrop')?.addEventListener('click',()=>{resultPopup.hidden=true;});
(()=>{const sheet=document.querySelector('#how-to-play');if(!sheet)return;const open=()=>{sheet.hidden=false;window.TableSound?.play?.('click');};const close=()=>{sheet.hidden=true;};document.querySelector('#how-to-play-open')?.addEventListener('click',open);document.querySelector('#how-to-play-close')?.addEventListener('click',close);document.querySelector('#how-to-play-backdrop')?.addEventListener('click',close);document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!sheet.hidden)close();});})();
const rules={ludo:'Roll a six to bring a token out of home. Six gives another roll. Landing on an opposing token captures it and sends it home. Finish all four tokens with an exact roll. The first player to finish wins.',chess:'Standard chess: legal moves, check/checkmate/stalemate, castling, en passant, promotion, draw offers and resignations. The server enforces each turn timer; a timeout forfeits the game.',draughts:'8x8 short-king draughts: captures are mandatory, men may capture forward or backward, and a capturing piece must finish available multi-jumps. Kings move one diagonal square. An 80-ply run without a capture or promotion is a draw.',tic_tac_toe:'Take turns placing X and O. Three in a row wins. A full board with no line is a draw, and stakes are returned.',four_in_a_row:'Drop one checker into a column. Four connected horizontally, vertically, or diagonally wins. A full board with no line is a draw.',snakes_and_ladders:'Take turns rolling the server-generated die. Ladders move you up, snakes move you down, and you must land exactly on 100 to win.',oware:'Standard two-row Oware: sow anticlockwise, feed the opponent when possible, and capture backward from their side only while the capture does not take their last seeds. The round ends at 25 seeds, when a side cannot move, or at the move cap; remaining seeds are counted. Equal scores draw and stakes are returned.'};rules.dominoes='Use a standard double-six set. Play a tile that matches either open end, draw until you can play or the stock is empty, then pass only when blocked. Empty your hand to win; if play is blocked, the fewest remaining pips wins. A tie is a draw.';rules.crazy_eights='Use a standard 52-card deck. Play a card matching the top rank or suit; an eight is wild and lets you choose the next suit. Draw when blocked. The first empty hand wins; if no cards remain and everyone is blocked, the table is a draw.';rules.sea_battle='Each player has a hidden server-placed fleet on a 10×10 grid. Fire one shot per turn at the target grid. Hits and misses are revealed after each shot; the first to hit all 17 fleet cells wins. Boards are generated and kept on the server.';rules.blackjack='Player-versus-player Blackjack: each player receives two private cards, then chooses Hit or Stand on their turn. Aces count as 1 or 11; face cards count as 10. The highest total at or below 21 wins; equal best totals and all-bust rounds are draws. Cards are shuffled server-side.';rules.rummy='Seven-card draw rummy: draw from the stock or discard, then discard one card. A valid winning hand partitions fully into 3+ card runs of one suit or sets of the same rank. The server shuffles and validates all cards.';rules.whot='Nigerian Whot: match the top number or active shape; Whot 20 is wild and names the next shape. Special cards: 1 Hold On (play again), 2 Pick Two, 5 Pick Three (the next player draws and is skipped), 8 Suspension (skip next player), 14 General Market (each other player draws one and you play again). Penalty cards do not stack in this table. Draw when blocked; first empty hand wins.';document.querySelector('#match-rules').textContent=rules[type]||'Follow the standard rules shown in the game guide.';
function gameState(data){
const previous=latest;
latest=data;
if(previous&&typeof previous.version==='number'&&data.version>previous.version&&data.status==='active')window.TableSound?.play?.('move');
if((!previous||previous.current_user_id!==userId)&&data.current_user_id===userId&&data.status==='active')window.TableSound?.play?.('turn');
if(previous&&data.players.length>previous.players.length)window.TableSound?.play?.('join');
document.querySelector('#match-status').textContent=data.status[0].toUpperCase()+data.status.slice(1);
const watchingEl=document.querySelector('#watching-count');if(watchingEl){const count=data.watching_count||0;watchingEl.hidden=count<1;watchingEl.querySelector('b').textContent=String(count);}
if(['finished','cancelled','force_closed'].includes(data.status)){if(pollTimer){clearTimeout(pollTimer);pollTimer=null;}if(clockTimer){clearInterval(clockTimer);clockTimer=null;}}
const active=data.status==='active';
const creator=data.players.some(player=>player.id===userId&&player.seat===0);
notice.textContent=data.status==='waiting'?'Waiting for players. This table and its invitations stay open for 24 hours unless the creator cancels.':data.status==='finished'&&data.result==='expired_refunded'?'Finished. The 24-hour waiting period ended and all player stakes were refunded to their wallets.':data.status==='finished'&&data.result==='cancelled_refunded'?'Finished. The creator cancelled this table and all player stakes were refunded to their wallets.':data.status==='finished'?(data.result==='draw'?'Game drawn.':`Game complete  ${(data.result.startsWith('winners:')?data.result.slice(8).split(',').map(seat=>data.players.find(p=>p.seat===Number(seat))?.name).filter(Boolean).join(' and '):data.players.find(p=>`winner:${p.seat}`===data.result)?.name)||'winner'} won.`):data.status==='cancelled'?'This table was cancelled and its unsettled stakes were refunded.':data.current_user_id===userId?'Your turn. Choose a move.':`Waiting for ${data.players.find(p=>p.id===data.current_user_id)?.name||'your opponent'}`;
const players=document.querySelector('#match-players');players.replaceChildren();data.players.forEach(player=>{const isTurn=active&&player.id===data.current_user_id;const row=document.createElement('div');row.className='match-player'+(isTurn?' is-turn':'');const ring=document.createElement('span');ring.className='match-player__ring';ring.dataset.playerId=player.id;ring.style.setProperty('--pct',isTurn?'100':'0');const avatar=document.createElement('span');avatar.className='match-player__avatar';if(player.avatar){const img=document.createElement('img');img.src=player.avatar;img.alt=player.name||'';avatar.append(img);}else{avatar.textContent=String(player.seat+1);}ring.append(avatar);const name=document.createElement('strong');name.textContent=player.name||`Player ${player.seat+1}`;const status=document.createElement('small');status.textContent=player.status==='refunded'?'Refunded':player.status==='out'?'Out':player.id===data.current_user_id?'Playing':'Seated';row.append(ring,name,status);players.append(row);});
if(data.status==='finished'&&data.result&&(data.result==='draw'||data.result.startsWith('winner:')||data.result.startsWith('winners:')))showResultPopup(data);
renderBoard(data);
const startButton=document.querySelector('#match-start');if(startButton)startButton.hidden=!(data.status==='waiting'&&data.players.filter(p=>p.status==='seated').length>=2&&creator);
const leaveButton=document.querySelector('#match-leave');if(leaveButton){leaveButton.hidden=!['waiting','active'].includes(data.status);leaveButton.textContent=data.status==='waiting'&&creator?'Cancel table and refund stakes':'Stand up';}
const inviteForm=document.querySelector('#match-invite');if(inviteForm)inviteForm.hidden=data.status!=='waiting';
const activeRing=active?document.querySelector(`.match-player__ring[data-player-id="${data.current_user_id}"]`):null;
if(data.turn_deadline_at){const deadline=Date.parse(data.turn_deadline_at);clearInterval(clockTimer);clockTimer=setInterval(()=>{const leftMs=Math.max(0,deadline-Date.now());const left=Math.ceil(leftMs/1000);document.querySelector('#turn-clock').innerHTML=`<i class="las la-hourglass-half"></i> <b>${left}s</b>`;if(activeRing){const pct=Math.max(0,Math.min(100,(leftMs/1000/turnSeconds)*100));activeRing.style.setProperty('--pct',String(pct));activeRing.classList.toggle('is-low',left<=10);}if(left<=10&&left>0&&data.current_user_id===userId&&warnedDeadline!==deadline){warnedDeadline=deadline;window.TableSound?.play?.('warning');}},250);}else{document.querySelector('#turn-clock').innerHTML='<i class="las la-hourglass-half"></i> <b>--</b>';clearInterval(clockTimer);}
}
function send(action){if(!latest||latest.status!=='active'||latest.current_user_id!==userId)return;error.textContent='';window.TableSound?.play?.(action&&(action.roll!==undefined)?'dice':action&&(action.card!==undefined||action.tile!==undefined)?'card':'click');fetch(api.move,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':root.dataset.csrf},body:JSON.stringify({action,version:latest.version,idempotency_key:crypto.randomUUID()})}).then(async response=>{const data=await response.json();if(!response.ok)throw new Error(data.message||'Move could not be made.');return refresh();}).catch(e=>{error.textContent=e.message;});}
const DICE_PIPS={1:[4],2:[0,8],3:[0,4,8],4:[0,2,6,8],5:[0,2,4,6,8],6:[0,2,3,5,6,8]};
function diceFace(value){const die=document.createElement('span');die.className='dice-face';const on=DICE_PIPS[value]||[];for(let i=0;i<9;i++){const dot=document.createElement('i');dot.className=on.includes(i)?'is-on':'';die.append(dot);}return die;}
function renderBoard(data) {
    board.replaceChildren();
    const active = data.status === 'active' && data.current_user_id === userId;
    const state = data.state || {};
    const makeActionRow = () => { const row = document.createElement('div'); row.className = 'board-action-row'; return row; };
    if (type === 'spades' || type === 'hearts') {
        const seat=latest.players.find(p=>p.id===userId)?.seat||0;const suitNames={S:'♠',H:'♥',D:'♦',C:'♣'};const guide=document.createElement('p');guide.className='board-scorebar';guide.textContent=type==='spades'?'Four-player partnership Spades: seats 1+3 and 2+4 form teams. Bid nil or 1–13, follow suit, and spades trump. One hand; contract points decide the winning team.':'Four-player Hearts: follow suit, avoid hearts and the queen of spades. Hearts cannot be led before broken. After one hand, the player with the fewest penalty points wins. No card passing.';board.append(guide);const stats=document.createElement('div');stats.className='board-scorebar';latest.players.forEach(player=>{const item=document.createElement('span');item.textContent=type==='spades'?`P${player.seat+1} bid ${state.bids?.[player.seat]??'—'} · tricks ${state.tricks_won?.[player.seat]||0}`:`P${player.seat+1} · points ${state.scores?.[player.seat]||0}`;stats.append(item);});board.append(stats);if(type==='spades'&&state.phase==='bid'&&active){const controls=document.createElement('div');controls.className='board-select-row';const bid=document.createElement('select');for(let value=0;value<=13;value++){const option=document.createElement('option');option.value=String(value);option.textContent=value===0?'Nil':`Bid ${value}`;bid.append(option);}const submit=document.createElement('button');submit.type='button';submit.className='table-match__start';submit.textContent='Submit bid';submit.onclick=()=>send({type:'bid',bid:Number(bid.value)});controls.append(bid,submit);board.append(controls);}const trick=document.createElement('div');trick.className='board-card-hand';(state.trick||[]).forEach(play=>{const card=document.createElement('span');card.className='board-card';card.dataset.suit=play.card.suit;card.textContent=`P${play.seat+1} · ${play.card.rank}${suitNames[play.card.suit]||''}`;trick.append(card);});board.append(trick);const hand=document.createElement('div');hand.className='board-card-hand';const own=state.hands?.[seat]||[];const leadSuit=state.trick?.[0]?.card?.suit;const canFollow=leadSuit&&own.some(card=>card.suit===leadSuit);const opening=state.moves<4;own.forEach((card,index)=>{let legal=!canFollow||card.suit===leadSuit;if(opening&&seat===state.first_player&&!state.trick.length)legal=card.rank==='2'&&card.suit==='C';if(type==='spades'&&state.trick.length===0&&!state.spades_broken&&card.suit==='S'&&own.some(item=>item.suit!=='S'))legal=false;if(type==='hearts'&&state.trick.length===0&&!state.hearts_broken&&card.suit==='H'&&own.some(item=>item.suit!=='H'))legal=false;if(type==='hearts'&&opening&&(card.suit==='H'||(card.suit==='S'&&card.rank==='Q'))&&own.some(item=>item.suit!=='H'&&!(item.suit==='S'&&item.rank==='Q')))legal=false;const button=document.createElement('button');button.type='button';button.className='board-card';button.dataset.suit=card.suit;button.textContent=`${card.rank}${suitNames[card.suit]||''}`;button.disabled=!active||state.phase!=='play'||!legal;button.onclick=()=>send({type:'play',card:index});hand.append(button);});board.append(hand);
    } else if (type === 'texas_holdem') {
        const suitNames={S:'♠',H:'♥',D:'♦',C:'♣'};const seat=latest.players.find(p=>p.id===userId)?.seat||0;const title=document.createElement('p');title.className='board-scorebar';title.textContent=`${String(state.street||'Preflop').toUpperCase()} · Fixed-stake showdown (no side bets)`;board.append(title);const community=document.createElement('div');community.className='board-card-hand';(state.board||[]).forEach(card=>{const node=document.createElement('span');node.className='board-card';node.dataset.suit=card.suit;node.textContent=`${card.rank}${suitNames[card.suit]||''}`;community.append(node);});board.append(community);const hand=document.createElement('div');hand.className='board-card-hand';(state.private_hand||[]).forEach(card=>{const node=document.createElement('span');node.className='board-card';node.dataset.suit=card.suit;node.textContent=`${card.rank}${suitNames[card.suit]||''}`;hand.append(node);});board.append(hand);const progress=document.createElement('div');progress.className='board-scorebar';latest.players.forEach(player=>{const label=document.createElement('span');label.textContent=`${player.name||'Player '+(player.seat+1)} · ${state.checked?.includes(player.seat)?'checked':'to act'}`;progress.append(label);});board.append(progress);if(active){const check=document.createElement('button');check.type='button';check.className='table-match__start';check.textContent='Check';check.onclick=()=>send({type:'check'});board.append(check);}
    } else if (type === 'backgammon') {
        const guide=document.createElement('p');guide.className='board-scorebar';guide.textContent='Move 15 checkers home, hit single opposing checkers, and bear off. If a checker is on the bar, re-enter it first.';board.append(guide);const seat=latest.players.find(p=>p.id===userId)?.seat||0;const boardView=document.createElement('div');boardView.className='board-grid board-grid--bg';const indices=seat===0?Array.from({length:24},(_,i)=>i):Array.from({length:24},(_,i)=>23-i);indices.forEach(index=>{const count=state.points?.[index]||0;const own=count*(seat===0?1:-1)>0;const cell=document.createElement('div');cell.className='board-cell board-cell--bg'+(own?' is-own':'');cell.textContent=`${index+1} · ${count===0?'—':Math.abs(count)+(own?'●':'○')}`;boardView.append(cell);});board.append(boardView);const status=document.createElement('p');status.className='board-scorebar';status.textContent=`Bar: you ${state.bar?.[seat]||0} · opponent ${state.bar?.[1-seat]||0}  |  Off: you ${state.off?.[seat]||0} · opponent ${state.off?.[1-seat]||0}`;board.append(status);const diceRow=document.createElement('div');diceRow.className='dice-roll-row';if(state.dice?.length){state.dice.forEach(value=>diceRow.append(diceFace(value)));}else{const label=document.createElement('span');label.textContent='Dice not rolled';diceRow.append(label);}board.append(diceRow);if(active){if(state.dice===null){const roll=document.createElement('button');roll.type='button';roll.className='table-match__start';roll.textContent='Roll dice';roll.onclick=()=>send({type:'roll'});board.append(roll);}else if(state.dice.length){const controls=document.createElement('div');controls.className='board-select-row';const from=document.createElement('select');const barOption=document.createElement('option');barOption.value='-1';barOption.textContent='From bar';if(!state.bar?.[seat])barOption.disabled=true;from.append(barOption);state.points?.forEach((count,index)=>{if(count*(seat===0?1:-1)>0){const option=document.createElement('option');option.value=String(index);option.textContent=`Point ${index+1} (${Math.abs(count)} checkers)`;from.append(option);}});const die=document.createElement('select');[...new Set(state.dice)].forEach(value=>{const option=document.createElement('option');option.value=String(value);option.textContent=`Die ${value}`;die.append(option);});const move=document.createElement('button');move.type='button';move.className='table-match__start';move.textContent='Move checker';move.onclick=()=>send({type:'move',from:Number(from.value),die:Number(die.value)});controls.append(from,die,move);board.append(controls);}}
    } else if (type === 'whot') {
        const seat=latest.players.find(p=>p.id===userId)?.seat||0;const top=state.discard?.at(-1)||{};const hand=state.hands?.[seat]||[];const playable=card=>card.number===20||card.number===top.number||card.shape===state.active_shape;const pile=document.createElement('div');pile.className='board-card-pile';pile.textContent=`${top.shape||'—'} ${top.number||''} · Active shape: ${state.active_shape} · Stock ${state.stock?.length||0}`;board.append(pile);if(active&&hand.some(card=>card.number===20)){const shape=document.createElement('select');shape.id='whot-shape';shape.className='board-card-suit';['Circle','Triangle','Cross','Square','Star'].forEach(value=>{const option=document.createElement('option');option.value=value;option.textContent=value;shape.append(option);});board.append(shape);}const cards=document.createElement('div');cards.className='board-card-hand';hand.forEach((card,index)=>{const button=document.createElement('button');button.type='button';button.className='board-card';button.textContent=card.number===20?'WHOT 20':`${card.number} ${card.shape}`;button.disabled=!active||!playable(card);button.onclick=()=>send({type:'play',card:index,...(card.number===20?{shape:document.querySelector('#whot-shape')?.value}:{})});cards.append(button);});board.append(cards);if(active&&!hand.some(playable)&&state.stock?.length){const draw=document.createElement('button');draw.type='button';draw.className='table-match__start';draw.textContent='Draw a card';draw.onclick=()=>send({type:'draw'});board.append(draw);}if(!active){const counts=document.createElement('p');counts.textContent=(state.hand_counts||[]).map((count,index)=>`Player ${index+1}: ${count} cards`).join(' · ');board.append(counts);}
    } else if (type === 'rummy') {
        const seat=latest.players.find(p=>p.id===userId)?.seat||0;const cards=state.hands?.[seat]||[];const suitNames={S:'♠',H:'♥',D:'♦',C:'♣'};const pile=document.createElement('div');pile.className='board-card-pile';pile.textContent=`Discard pile: ${state.discard?.at(-1)?.rank||'—'} ${suitNames[state.discard?.at(-1)?.suit]||''} · Stock ${state.stock?.length||0}`;board.append(pile);const hand=document.createElement('div');hand.className='board-card-hand';cards.forEach((card,index)=>{const button=document.createElement('button');button.type='button';button.className='board-card';button.dataset.suit=card.suit;button.textContent=`${card.rank}${suitNames[card.suit]||''}`;button.disabled=!active||state.phase!=='discard';button.onclick=()=>send({type:'discard',card:index});hand.append(button);});board.append(hand);if(active&&state.phase==='draw'){const stock=document.createElement('button');stock.type='button';stock.className='table-match__start';stock.textContent='Draw from stock';stock.onclick=()=>send({type:'draw_stock'});const discard=document.createElement('button');discard.type='button';discard.className='table-match__leave';discard.textContent='Take discard';discard.disabled=(state.discard||[]).length<2;discard.onclick=()=>send({type:'draw_discard'});board.append(stock,discard);}if(!active){const counts=document.createElement('p');counts.textContent=(state.hand_counts||[]).map((count,index)=>`Player ${index+1}: ${count} cards`).join(' · ');board.append(counts);}
    } else if (type === 'blackjack') {
        const seat=latest.players.find(p=>p.id===userId)?.seat||0;const suitNames={S:'♠',H:'♥',D:'♦',C:'♣'};const title=document.createElement('p');title.className='board-scorebar';title.textContent='Your hand';board.append(title);const handBox=document.createElement('div');handBox.className='board-card-hand';(state.hands?.[seat]||[]).forEach(card=>{const node=document.createElement('span');node.className='board-card';node.dataset.suit=card.suit;node.textContent=`${card.rank}${suitNames[card.suit]||''}`;handBox.append(node);});board.append(handBox);const totals=document.createElement('div');totals.className='board-scorebar';(latest.players||[]).forEach(player=>{const label=document.createElement('span');const total=state.hand_totals?.[player.seat];label.textContent=`${player.name||'Player '+(player.seat+1)} · ${state.stood?.[player.seat]?'stood':player.status==='out'?'out':'playing'} · ${total??'—'}`;totals.append(label);});board.append(totals);if(active&&!state.stood?.[seat]){const hit=document.createElement('button');hit.type='button';hit.className='table-match__start';hit.textContent='Hit';hit.onclick=()=>send({type:'hit'});const stand=document.createElement('button');stand.type='button';stand.className='table-match__leave';stand.textContent='Stand';stand.onclick=()=>send({type:'stand'});board.append(hit,stand);}
    } else if (type === 'sea_battle') {
        const seat=latest.players.find(p=>p.id===userId)?.seat||0;const myShots=new Map((state.my_shots||[]).map(shot=>[shot.cell,shot]));const incoming=new Map((state.incoming_shots||[]).map(shot=>[shot.cell,shot]));
        const heading=document.createElement('p');heading.className='board-scorebar';heading.textContent='Your fleet';board.append(heading);const own=document.createElement('div');own.className='board-grid board-grid--sea';(state.own_board||[]).forEach((row,r)=>row.forEach((ship,c)=>{const cell=document.createElement('div');const shot=incoming.get(`${r}:${c}`);cell.className='board-cell board-cell--sea'+(ship!==null?' is-ship':'')+(shot?(shot.hit?' is-hit':' is-miss'):'');cell.textContent=shot?(shot.hit?'✦':'·'):ship!==null?'▰':'';own.append(cell);}));board.append(own);
        const targetTitle=document.createElement('p');targetTitle.className='board-scorebar';targetTitle.textContent='Target waters';board.append(targetTitle);const target=document.createElement('div');target.className='board-grid board-grid--sea';for(let r=0;r<10;r++)for(let c=0;c<10;c++){const shot=myShots.get(`${r}:${c}`);const cell=document.createElement('button');cell.type='button';cell.className='board-cell board-cell--sea'+(shot?(shot.hit?' is-hit':' is-miss'):'');cell.textContent=shot?(shot.hit?'✦':'·'):'';cell.disabled=!active||!!shot;cell.onclick=()=>send({row:r,col:c});target.append(cell);}board.append(target);
    } else if (type === 'tic_tac_toe') {
        const grid = document.createElement('div'); grid.className = 'board-grid board-grid--xo';
        state.board?.forEach((value, index) => { const cell = document.createElement('button'); cell.type='button'; cell.className=`board-cell${value!==null?' is-seat-'+(value+1):''}`; cell.textContent=value===null?'':value===0?'X':'O'; cell.disabled=!active||value!==null; cell.onclick=()=>send({cell:index}); grid.append(cell); }); board.append(grid);
    } else if (type === 'four_in_a_row') {
        const grid = document.createElement('div'); grid.className='board-grid board-grid--connect';
        for(let column=0;column<7;column++){const button=document.createElement('button');button.type='button';button.className='board-cell board-cell--connect';button.textContent='';button.disabled=!active||state.board?.[0]?.[column]!==null;button.onclick=()=>send({column});grid.append(button);}
        state.board?.forEach(row=>row.forEach(value=>{const cell=document.createElement('div');cell.className=`board-cell board-cell--connect${value===null?'':' is-seat-'+(value+1)}`;cell.textContent=value===null?'':'';grid.append(cell);}));board.append(grid);
    } else if (type === 'crazy_eights') {
        const seat=latest.players.find(player=>player.id===userId)?.seat||0;const top=state.top||{};const suitNames={S:'Spades',H:'Hearts',D:'Diamonds',C:'Clubs'};const pile=document.createElement('div');pile.className='board-card-pile';pile.textContent=`Top card: ${top.rank||'?'} of ${suitNames[state.current_suit]||'?'}`;board.append(pile);
        const hand=state.hands?.[seat]||[];const canPlay=hand.some(card=>card.rank==='8'||card.rank===top.rank||card.suit===state.current_suit);
        const suitSymbols={S:'♠',H:'♥',D:'♦',C:'♣'};if(active){const suit=document.createElement('select');suit.className='board-card-suit';[['S','Spades'],['H','Hearts'],['D','Diamonds'],['C','Clubs']].forEach(([value,label])=>{const option=document.createElement('option');option.value=value;option.textContent=label;suit.append(option);});board.append(suit);const cards=document.createElement('div');cards.className='board-card-hand';hand.forEach((card,index)=>{const button=document.createElement('button');button.type='button';button.className='board-card';button.dataset.suit=card.suit;button.textContent=`${card.rank}${suitSymbols[card.suit]||card.suit}`;button.disabled=!(card.rank==='8'||card.rank===top.rank||card.suit===state.current_suit);button.onclick=()=>send({type:'play',card:index,...(card.rank==='8'?{suit:suit.value}:{})});cards.append(button);});board.append(cards);
            if(!canPlay&&(state.stock?.length||(state.discard||[]).length>1)){const draw=document.createElement('button');draw.type='button';draw.className='table-match__start';draw.textContent='Draw a card';draw.onclick=()=>send({type:'draw'});board.append(draw);}else if(!canPlay&&(!state.stock?.length)&&(state.discard||[]).length<=1){const pass=document.createElement('button');pass.type='button';pass.className='table-match__leave';pass.textContent='Pass';pass.onclick=()=>send({type:'pass'});board.append(pass);}
        }else{const counts=document.createElement('p');counts.textContent=(state.hand_counts||[]).map((count,index)=>`Player ${index+1}: ${count} cards`).join('  ');board.append(counts);}
    } else if (type === 'dominoes') {
        const seat=latest.players.find(player=>player.id===userId)?.seat||0;const chain=document.createElement('div');chain.className='board-domino-chain';(state.chain||[]).forEach(tile=>{const node=document.createElement('span');node.textContent=`${tile[0]} | ${tile[1]}`;chain.append(node);});board.append(chain);
        const hand=state.hands?.[seat]||[];const ends=state.ends;const canPlay=hand.some(tile=>!ends||tile.includes(ends[0])||tile.includes(ends[1]));
        if(active){const handBox=document.createElement('div');handBox.className='board-domino-hand';hand.forEach((tile,index)=>{const sides=ends?['left','right']:['right'];sides.forEach(side=>{const button=document.createElement('button');button.type='button';button.textContent=`${tile[0]} | ${tile[1]}${ends?'  '+side:''}`;button.disabled=ends&&!tile.includes(ends[side==='left'?0:1]);button.onclick=()=>send({type:'play',tile:index,end:side});handBox.append(button);});});board.append(handBox);
            if(!canPlay&&(state.stock?.length||(state.discard||[]).length>1)){const draw=document.createElement('button');draw.type='button';draw.className='table-match__start';draw.textContent=`Draw from stock (${state.stock.length})`;draw.onclick=()=>send({type:'draw'});board.append(draw);}else if(!canPlay){const pass=document.createElement('button');pass.type='button';pass.className='table-match__leave';pass.textContent='Pass';pass.onclick=()=>send({type:'pass'});board.append(pass);}
        }else{const counts=document.createElement('p');counts.textContent=(state.hand_counts||[]).map((count,index)=>`Player ${index+1}: ${count} tiles`).join('  ');board.append(counts);}
    } else if (type === 'oware') {
        const scorebar=document.createElement('div');scorebar.className='board-scorebar';(state.scores||[]).forEach((score,index)=>{const label=document.createElement('span');label.textContent="Player "+(index+1)+": "+score;scorebar.append(label);});const pits=document.createElement('div');pits.className='board-pits';const seat=latest.players.find(p=>p.id===userId)?.seat||0;const order=seat===0?[11,10,9,8,7,6,0,1,2,3,4,5]:[5,4,3,2,1,0,6,7,8,9,10,11];
        order.forEach(index=>{const button=document.createElement('button');button.type='button';button.className='board-pit';button.textContent=state.pits?.[index]??0;const own=seat===0?index<6:index>=6;button.disabled=!active||!own||!state.pits?.[index];button.onclick=()=>send({pit:index});pits.append(button);});board.append(scorebar,pits);
    } else if (type === 'snakes_and_ladders') {
        const track=document.createElement('div');track.className='board-track';for(let index=100;index>=1;index--){const cell=document.createElement('div');cell.className='board-square';cell.textContent=`${index}${Object.hasOwn(state.ladders||{},index)?'+':Object.hasOwn(state.snakes||{},index)?'v':''}`;state.positions?.forEach((position,seat)=>{if(position===index){const token=document.createElement('i');token.className='board-token';token.style.background=seat===0?'#00ef87':'#ffcc70';cell.append(token);}});track.append(cell);}board.append(track);
        if(active){const roll=document.createElement('button');roll.type='button';roll.className='table-match__start';roll.textContent='Roll server die';roll.onclick=()=>send({roll:true});board.append(roll);}
    } else if (type === 'ludo') {
        const seat=latest.players.find(p=>p.id===userId)?.seat||0;
        if(active&&state.pending_roll===null){const roll=document.createElement('button');roll.type='button';roll.className='table-match__start';roll.textContent='Roll server die';roll.onclick=()=>send({roll:true});board.append(roll);}
        if(active&&state.pending_roll!==null){const rollRow=document.createElement('div');rollRow.className='dice-roll-row';rollRow.append(diceFace(state.pending_roll));const label=document.createElement('span');label.textContent='Choose a movable token';rollRow.append(label);board.append(rollRow);const tokens=document.createElement('div');tokens.className='board-grid board-grid--ludo';state.tokens?.[seat]?.forEach((position,index)=>{const token=document.createElement('button');token.type='button';token.className='board-token-button';token.textContent=position===-1?'Home':position===58?'Finished':`Token ${index+1}  ${position}`;token.disabled=position===58||(position===-1&&state.pending_roll!==6)||(position>=0&&position+state.pending_roll>57);token.onclick=()=>send({token:index});tokens.append(token);});board.append(tokens);}
        if(!active){const positions=document.createElement('p');positions.textContent=state.tokens?.map((tokens,index)=>`Player ${index+1}: ${tokens.filter(p=>p===58).length}/4 finished`).join('  ')||'';board.append(positions);}
    } else if (type === 'chess' || type === 'draughts') {
        const seat=latest.players.find(p=>p.id===userId)?.seat||0;const white=seat===0;const grid=document.createElement('div');grid.className='board-grid board-grid--chess';
        const symbols=type==='chess'?{K:'♔',Q:'♕',R:'♖',B:'♗',N:'♘',P:'♙',k:'♚',q:'♛',r:'♜',b:'♝',n:'♞',p:'♟'}:{w:'⛀',W:'⛁',b:'⛂',B:'⛃'};
        const lastMove=data.last_move;
        for(let viewRow=0;viewRow<8;viewRow++)for(let viewCol=0;viewCol<8;viewCol++){const row=white?viewRow:7-viewRow,col=white?viewCol:7-viewCol,piece=state.board?.[row]?.[col]??null,square=String.fromCharCode(97+col)+(8-row);const own=piece&&(type==='chess'?(seat===0?piece===piece.toUpperCase():piece===piece.toLowerCase()):(seat===0?piece.toLowerCase()==='w':piece.toLowerCase()==='b'));const isLightPiece=piece&&(type==='chess'?piece===piece.toUpperCase():piece[0].toLowerCase()==='w');const isLastMove=lastMove&&(square===lastMove.from||square===lastMove.to);const cell=document.createElement('button');cell.type='button';cell.className=`board-cell board-cell--chess${(row+col)%2?' is-dark':''}${selectedSquare===square?' board-cell--selected':''}${isLastMove?' board-cell--last-move':''}${piece?(isLightPiece?' piece-light':' piece-dark'):''}${piece&&own?' is-own-piece':''}`;cell.textContent=piece?symbols[piece]||piece:'';cell.disabled=!active;cell.onclick=()=>{if(!selectedSquare){if(own)selectedSquare=square;}else{const from=selectedSquare;selectedSquare=null;if(from!==square)send({from,to:square,promotion:'q'});else if(own)selectedSquare=square;}renderBoard(latest);};grid.append(cell);}board.append(grid);
        if(type==='chess'){const actions=makeActionRow();if(state.draw_offer!==null&&state.draw_offer!==seat){const accept=document.createElement('button');accept.textContent='Accept draw';accept.onclick=()=>send({type:'accept_draw'});const decline=document.createElement('button');decline.textContent='Decline draw';decline.onclick=()=>send({type:'decline_draw'});actions.append(accept,decline);}else if(active){const offer=document.createElement('button');offer.textContent='Offer draw';offer.onclick=()=>send({type:'offer_draw'});const resign=document.createElement('button');resign.textContent='Resign';resign.onclick=()=>send({type:'resign'});actions.append(offer,resign);}if(actions.childNodes.length)board.append(actions);}
    }
}
async function refresh(){if(polling)return;polling=true;try{const url=new URL(stateUrl,location.href);if(latest)url.searchParams.set('since',latest.version);const response=await fetch(url,{headers:{'Accept':'application/json'},cache:'no-store'});if(!response.ok)throw new Error('Could not reconnect to this table.');const data=await response.json();if(!data.unchanged)gameState(data);}catch(e){error.textContent=e.message;}finally{polling=false;}}
const applaudBtn=document.querySelector('#match-applaud');if(applaudBtn)applaudBtn.onclick=()=>{applaudBtn.disabled=true;applaudBtn.classList.add('is-popping');setTimeout(()=>applaudBtn.classList.remove('is-popping'),350);window.TableSound?.play?.('click');fetch(root.dataset.applaud,{method:'POST',headers:{'X-CSRF-TOKEN':root.dataset.csrf,'Accept':'application/json'}}).finally(()=>{applaudBtn.disabled=false;});};
(()=>{
    const openBtn=document.querySelector('#match-gift-open');const drawer=document.querySelector('#gift-drawer');if(!openBtn||!drawer)return;
    const backdrop=document.querySelector('#gift-drawer-backdrop'),closeBtn=document.querySelector('#gift-drawer-close'),backBtn=document.querySelector('#gift-drawer-back');
    const pickStep=document.querySelector('#gift-drawer-pick'),sendStep=document.querySelector('#gift-drawer-send');
    const recipientsEl=document.querySelector('#gift-drawer-recipients'),quantityEl=document.querySelector('#gift-drawer-quantity'),errorEl=document.querySelector('#gift-drawer-error'),sendBtn=document.querySelector('#gift-drawer-send-btn');
    const previewImg=document.querySelector('#gift-drawer-preview-img'),previewName=document.querySelector('#gift-drawer-preview-name');
    let selectedGiftId=null,selectedRecipientId=null;
    function closeDrawer(){drawer.hidden=true;pickStep.hidden=false;sendStep.hidden=true;errorEl.textContent='';}
    function renderRecipients(){
        recipientsEl.replaceChildren();selectedRecipientId=null;
        const seated=(latest?.players||[]).filter(player=>player.id!==userId&&player.status==='seated');
        if(!seated.length){recipientsEl.innerHTML='<span class="gift-drawer__error">No other players are seated yet.</span>';sendBtn.disabled=true;return;}
        seated.forEach((player,index)=>{
            const btn=document.createElement('button');btn.type='button';btn.className='gift-drawer__recipient';btn.textContent=player.name||`Player ${player.seat+1}`;
            btn.onclick=()=>{recipientsEl.querySelectorAll('.gift-drawer__recipient').forEach(el=>el.classList.remove('is-selected'));btn.classList.add('is-selected');selectedRecipientId=player.id;};
            recipientsEl.append(btn);
            if(index===0){btn.classList.add('is-selected');selectedRecipientId=player.id;}
        });
        sendBtn.disabled=false;
    }
    openBtn.onclick=()=>{drawer.hidden=false;pickStep.hidden=false;sendStep.hidden=true;window.TableSound?.play?.('click');};
    closeBtn.onclick=closeDrawer;backdrop.onclick=closeDrawer;
    document.querySelectorAll('.gift-drawer__item').forEach(item=>{
        item.onclick=()=>{
            selectedGiftId=item.dataset.giftId;previewImg.src=item.dataset.giftImage;previewImg.alt=item.dataset.giftName;previewName.textContent=item.dataset.giftName;
            quantityEl.value=1;errorEl.textContent='';renderRecipients();
            pickStep.hidden=true;sendStep.hidden=false;
        };
    });
    backBtn.onclick=()=>{pickStep.hidden=false;sendStep.hidden=true;};
    sendBtn.onclick=()=>{
        if(!selectedGiftId||!selectedRecipientId)return;
        const quantity=Math.max(1,Math.min(99,parseInt(quantityEl.value,10)||1));
        sendBtn.disabled=true;errorEl.textContent='';
        const url=root.dataset.giftSendTemplate.replace('__GIFT__',selectedGiftId);
        fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':root.dataset.csrf},body:JSON.stringify({recipient_id:selectedRecipientId,quantity})})
            .then(async response=>{const data=await response.json();if(!response.ok)throw new Error(data.message||'The gift could not be sent.');closeDrawer();})
            .catch(e=>{errorEl.textContent=e.message;})
            .finally(()=>{sendBtn.disabled=false;});
    };
})();
window.spawnGiftBurst=function(giftImage,text){
    const burst=document.querySelector('#gift-burst');if(!burst)return;
    document.querySelector('#gift-burst-img').src=giftImage;
    document.querySelector('#gift-burst-text').textContent=text;
    const sparkles=document.querySelector('#gift-burst-sparkles');sparkles.replaceChildren();
    for(let i=0;i<14;i++){
        const sparkle=document.createElement('i');sparkle.className='las la-star';
        sparkle.style.left=(10+Math.random()*80)+'%';sparkle.style.animationDelay=(Math.random()*0.4)+'s';sparkle.style.color=['#ffd76a','#00ef87','#7fd9ff','#ff8aa3'][i%4];
        sparkles.append(sparkle);
    }
    burst.hidden=false;window.TableSound?.play?.('win');
    clearTimeout(window.__giftBurstTimer);
    window.__giftBurstTimer=setTimeout(()=>{burst.hidden=true;},2400);
}
const startBtn=document.querySelector('#match-start');if(startBtn)startBtn.onclick=()=>{window.TableSound?.play?.('click');fetch(api.start,{method:'POST',headers:{'X-CSRF-TOKEN':root.dataset.csrf,'Accept':'application/json'}}).then(refresh).catch(()=>{});};const leaveBtn=document.querySelector('#match-leave');if(leaveBtn)leaveBtn.onclick=()=>{window.TableSound?.play?.('leave');const creator=latest?.status==='waiting'&&latest.players.some(player=>player.id===userId&&player.seat===0);const prompt=creator?'Cancel this waiting table? All player stakes will be refunded to their wallets and invitations will stop working.':latest?.status==='waiting'?'Leave this waiting table? Your own stake will be refunded.':root.dataset.friendly==='1'?'Leave this friendly game? No money is involved.':'Stand up from this active game? Any stake already in play will stay in the pot.';if(confirm(prompt))fetch(api.leave,{method:'POST',headers:{'X-CSRF-TOKEN':root.dataset.csrf,'Accept':'application/json'}}).then(refresh);};const inviteForm=document.querySelector('#match-invite');if(inviteForm){const input=inviteForm.querySelector('[name=username]'),suggestions=document.querySelector('#invite-suggestions'),message=document.querySelector('#invite-message'),button=inviteForm.querySelector('button');let searchTimer,searchController;function closeSuggestions(){suggestions.replaceChildren();suggestions.hidden=true;input.setAttribute('aria-expanded','false');}input.setAttribute('aria-expanded','false');input.addEventListener('input',()=>{clearTimeout(searchTimer);searchController?.abort();const query=input.value.trim();message.textContent='';if(!query){closeSuggestions();return;}suggestions.hidden=false;suggestions.innerHTML='<span class="table-match__suggestion-note">Searching players…</span>';input.setAttribute('aria-expanded','true');searchTimer=setTimeout(async()=>{searchController=new AbortController();try{const url=new URL(root.dataset.playerSearch,location.href);url.searchParams.set('q',query);const response=await fetch(url,{headers:{Accept:'application/json'},signal:searchController.signal});if(!response.ok)throw new Error('Player search is unavailable.');const result=await response.json();if(input.value.trim()!==query)return;suggestions.replaceChildren();if(!result.users?.length){suggestions.innerHTML='<span class="table-match__suggestion-note">No matching players found</span>';return;}result.users.forEach(player=>{const option=document.createElement('button');option.type='button';option.className='table-match__suggestion';option.setAttribute('role','option');option.textContent=player.username;option.addEventListener('click',()=>{input.value=player.username;closeSuggestions();input.focus();});suggestions.append(option);});}catch(error){if(error.name!=='AbortError'){closeSuggestions();message.textContent=error.message;}}},180);});document.addEventListener('click',event=>{if(!inviteForm.contains(event.target))closeSuggestions();});inviteForm.addEventListener('submit',async event=>{event.preventDefault();const username=input.value.trim();if(!username)return;button.disabled=true;message.textContent='';try{const response=await fetch(inviteForm.action,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':root.dataset.csrf,'Content-Type':'application/json'},body:JSON.stringify({username})});const result=await response.json();if(!response.ok)throw new Error(result.message||'Could not send invitation.');message.textContent=result.message;input.value='';closeSuggestions();}catch(error){message.textContent=error.message;}finally{button.disabled=false;}});}refresh();const schedule=()=>{if(latest&&['finished','cancelled','force_closed'].includes(latest.status))return;pollTimer=setTimeout(async()=>{await refresh();schedule();},document.hidden?3500:1000);};document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});schedule();})();
</script>
@endpush
@push('script')
<script>
(()=>{
const root=document.querySelector('#table-match');if(!root)return;
const list=document.querySelector('#chat-messages'),empty=document.querySelector('#chat-empty'),error=document.querySelector('#chat-error'),user=Number(root.dataset.user);
const quickReactions=['👍','❤️','😂','👏','😮','🎉','👎'];
let cursor=0,busy=false,recorder=null,chunks=[],replyToId=null,lastRenderedDate=null,lastRenderedUser=null,isAtBottom=true,unseenCount=0,recordTimer=null,recordStart=0,recordAnalyser=null,recordRaf=null,chatHistoryLoaded=false;
const headers={'Accept':'application/json','X-CSRF-TOKEN':root.dataset.csrf};

/* ---- Emoji picker (shared by the composer button and per-message quick-react) ---- */
const EMOJI={Smileys:['😀','😃','😄','😁','😆','😅','🤣','😂','🙂','🙃','😉','😊','😇','🥰','😍','🤩','😘','😗','😚','😙','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🫡','🤐','🤨','😐','😑','😶','😏','😒','🙄','😬','🤥','😌','😔','😪','🤤','😴'],Gestures:['👍','👎','👏','🙌','👋','🤝','🙏','💪','👊','✊','🤞','✌️','🤟','🤙','👌','🫶','💅','👉','👈','👆','👇','✋','🤚'],Hearts:['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝'],Game:['🎲','♟️','🃏','🀄','🎯','🏆','🥇','🥈','🥉','🎉','🎊','🔥','⚡','💰','💎','👑','🚀','⭐','✨','💯'],Faces2:['😎','🤓','🧐','🥳','😡','😠','🤬','😢','😭','😤','😱','😨','😰','😓','🤒','🤕','🤢','🥶','🥵','😵','🤯','😷']};
const EMOJI_KEYWORDS={'👍':'thumbs up good yes','👎':'thumbs down bad no','❤️':'heart love','🔥':'fire hot','🎉':'party celebrate','🏆':'trophy win winner','😂':'laugh funny lol','😢':'sad cry','🤔':'thinking hmm','👏':'clap applause','🚀':'rocket fast','💰':'money cash','🎲':'dice game'};
let emojiRecentKey='tableChatRecentEmoji';
function recentEmoji(){try{return JSON.parse(localStorage.getItem(emojiRecentKey)||'[]');}catch(e){return [];}}
function pushRecentEmoji(e){const list=[e,...recentEmoji().filter(x=>x!==e)].slice(0,16);try{localStorage.setItem(emojiRecentKey,JSON.stringify(list));}catch(err){}}

const picker=document.querySelector('#emoji-picker'),pickerTabs=document.querySelector('#emoji-tabs'),pickerGrid=document.querySelector('#emoji-grid'),pickerSearch=document.querySelector('#emoji-search');
let pickerTarget=null;
function buildPickerTabs(){const cats=['Recent',...Object.keys(EMOJI)];pickerTabs.replaceChildren();cats.forEach((cat,i)=>{const btn=document.createElement('button');btn.type='button';btn.textContent=cat;btn.className=i===0?'is-active':'';btn.onclick=()=>{pickerTabs.querySelectorAll('button').forEach(b=>b.classList.remove('is-active'));btn.classList.add('is-active');renderPickerGrid(cat);};pickerTabs.append(btn);});}
function renderPickerGrid(cat){pickerGrid.replaceChildren();const items=cat==='Recent'?recentEmoji():(EMOJI[cat]||[]);items.forEach(e=>pickerGrid.append(makeEmojiButton(e)));}
function makeEmojiButton(e){const btn=document.createElement('button');btn.type='button';btn.textContent=e;btn.onclick=()=>{pushRecentEmoji(e);pickerTarget?.(e);closePicker();};return btn;}
function openPicker(anchorBtn,onPick){pickerTarget=onPick;picker.hidden=false;pickerSearch.value='';buildPickerTabs();renderPickerGrid('Recent');pickerSearch.focus();}
function closePicker(){picker.hidden=true;pickerTarget=null;}
/* Reactions are validated server-side against a fixed set of 7 emoji, so the react button
   opens this small dedicated popover rather than the full emoji picker - picking anything
   outside quickReactions would just fail server validation. */
let quickReactPopover=null;
function closeQuickReact(){quickReactPopover?.remove();quickReactPopover=null;}
function openQuickReact(anchorBtn,onPick){if(quickReactPopover){closeQuickReact();return;}const pop=document.createElement('div');pop.className='quick-react-popover';quickReactions.forEach(e=>{const btn=document.createElement('button');btn.type='button';btn.textContent=e;btn.onclick=ev=>{ev.stopPropagation();onPick(e);closeQuickReact();};pop.append(btn);});anchorBtn.after(pop);quickReactPopover=pop;}
document.addEventListener('click',e=>{if(quickReactPopover&&!quickReactPopover.contains(e.target))closeQuickReact();});
/* ---- Report message / block player ---- */
let messageMenu=null;
function closeMessageMenu(){messageMenu?.remove();messageMenu=null;}
function openMessageMenu(anchorBtn,m){if(messageMenu){closeMessageMenu();return;}const menu=document.createElement('div');menu.className='quick-react-popover message-menu';
    const report=document.createElement('button');report.type='button';report.innerHTML='<i class="las la-flag"></i> Report message';report.onclick=async ev=>{ev.stopPropagation();closeMessageMenu();if(!confirm('Report this message to our moderation team?'))return;try{const url=root.dataset.reportTemplate.replace('__MESSAGE__',m.id);const response=await fetch(url,{method:'POST',headers:{...headers,'Content-Type':'application/json'},body:JSON.stringify({})});const data=await response.json();if(!response.ok)throw new Error(data.message||'Could not report this message.');error.textContent=data.message;error.style.color='var(--tm-green,#00ef87)';}catch(e){error.textContent=e.message;error.style.color='';}};
    const block=document.createElement('button');block.type='button';block.innerHTML='<i class="las la-user-slash"></i> Block '+(m.name||'player');block.onclick=async ev=>{ev.stopPropagation();closeMessageMenu();if(!confirm(`Block ${m.name||'this player'}? You will no longer see their messages here or at future tables. Never pay or trade with anyone outside the platform.`))return;try{const url=root.dataset.blockTemplate.replace('__USER__',m.user_id);const response=await fetch(url,{method:'POST',headers:{...headers,'Content-Type':'application/json'},body:JSON.stringify({})});const data=await response.json();if(!response.ok)throw new Error(data.message||'Could not block this player.');error.textContent=data.message;error.style.color='var(--tm-green,#00ef87)';list.replaceChildren();cursor=0;lastRenderedDate=null;lastRenderedUser=null;getMessages();}catch(e){error.textContent=e.message;error.style.color='';}};
    menu.append(report,block);anchorBtn.after(menu);messageMenu=menu;}
document.addEventListener('click',e=>{if(messageMenu&&!messageMenu.contains(e.target))closeMessageMenu();});
pickerSearch?.addEventListener('input',()=>{const term=pickerSearch.value.trim().toLowerCase();if(!term){pickerTabs.hidden=false;renderPickerGrid('Recent');return;}pickerTabs.hidden=true;pickerGrid.replaceChildren();Object.values(EMOJI).flat().filter(e=>(EMOJI_KEYWORDS[e]||'').includes(term)).forEach(e=>pickerGrid.append(makeEmojiButton(e)));});
document.querySelector('#chat-emoji-open')?.addEventListener('click',e=>{e.stopPropagation();if(!picker.hidden){closePicker();return;}openPicker(null,emoji=>{const start=composer.selectionStart||composer.value.length;composer.value=composer.value.slice(0,start)+emoji+composer.value.slice(start);composer.focus();syncComposerState();});});
document.addEventListener('click',e=>{if(!picker.hidden&&!picker.contains(e.target)&&e.target.id!=='chat-emoji-open')closePicker();});

/* ---- Attach menu ---- */
const attachMenu=document.querySelector('#attach-menu'),fileInput=document.querySelector('#chat-file');
document.querySelector('#chat-attach-open')?.addEventListener('click',e=>{e.stopPropagation();attachMenu.hidden=!attachMenu.hidden;});
attachMenu?.querySelectorAll('button').forEach(btn=>btn.addEventListener('click',()=>{fileInput.accept=btn.dataset.accept||'';if(btn.dataset.capture)fileInput.capture=btn.dataset.capture;else fileInput.removeAttribute('capture');attachMenu.hidden=true;fileInput.click();}));
document.addEventListener('click',e=>{if(!attachMenu.hidden&&!attachMenu.contains(e.target)&&e.target.id!=='chat-attach-open')attachMenu.hidden=true;});

/* ---- Reply-to ---- */
const replyBar=document.querySelector('#chat-reply');
function startReply(id,name,preview){replyToId=id;document.querySelector('#chat-reply-name').textContent='Replying to '+(name||'Player');document.querySelector('#chat-reply-preview').textContent=preview||'';replyBar.hidden=false;composer.focus();}
document.querySelector('#chat-reply-cancel')?.addEventListener('click',()=>{replyToId=null;replyBar.hidden=true;});

/* ---- Message rendering ---- */
function formatTime(iso){return new Date(iso).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});}
function formatDateLabel(iso){const d=new Date(iso),today=new Date();const sameDay=a=>a.toDateString()===d.toDateString();if(sameDay(today))return 'Today';const yesterday=new Date(today);yesterday.setDate(yesterday.getDate()-1);if(sameDay(yesterday))return 'Yesterday';return d.toLocaleDateString([],{month:'short',day:'numeric'});}
function buildReactionBar(container,m){container.replaceChildren();const counted=quickReactions.map(choice=>({choice,item:(m.reactions||[]).find(r=>r.emoji===choice)})).filter(x=>x.item);counted.forEach(({choice,item})=>{const button=document.createElement('button');button.type='button';button.className=item.mine?'is-mine':'';button.textContent=`${choice} ${item.count}`;button.setAttribute('aria-label',`${choice} reaction`);button.onclick=()=>react(m.id,choice);container.append(button);});}
const voicePlayers=[];
function buildVoicePlayer(url){const wrap=document.createElement('div');wrap.className='table-chat__voice-player';
    const playBtn=document.createElement('button');playBtn.type='button';const playIcon=document.createElement('i');playIcon.className='las la-play';playBtn.append(playIcon);
    const track=document.createElement('div');track.className='table-chat__voice-track';
    const bars=document.createElement('div');bars.className='table-chat__voice-bars';const barHeights=Array.from({length:28},()=>18+Math.random()*82);barHeights.forEach(h=>{const b=document.createElement('span');b.style.height=h+'%';bars.append(b);});
    const progress=document.createElement('div');progress.className='table-chat__voice-progress';const progressBars=document.createElement('div');progressBars.className='table-chat__voice-bars';barHeights.forEach(h=>{const b=document.createElement('span');b.style.height=h+'%';progressBars.append(b);});progress.append(progressBars);
    track.append(bars,progress);
    const time=document.createElement('span');time.className='table-chat__voice-time';time.textContent='0:00';
    const speed=document.createElement('button');speed.type='button';speed.className='table-chat__voice-speed';speed.textContent='1x';
    const audio=new Audio(url);audio.preload='none';let rate=1;
    const fmt=s=>{s=Math.max(0,Math.floor(s));return Math.floor(s/60)+':'+String(s%60).padStart(2,'0');};
    voicePlayers.push({audio,playIcon});
    playBtn.onclick=()=>{if(audio.paused){voicePlayers.forEach(p=>{if(p.audio!==audio&&!p.audio.paused){p.audio.pause();p.playIcon.className='las la-play';}});window.TableSound?.unlock?.();audio.play().catch(()=>{error.textContent='Could not play this voice note.';});playIcon.className='las la-pause';}else{audio.pause();playIcon.className='las la-play';}};
    audio.addEventListener('timeupdate',()=>{const pct=audio.duration?(audio.currentTime/audio.duration)*100:0;progress.style.inset='0 '+(100-pct)+'% 0 0';time.textContent=fmt(audio.duration&&!isFinite(audio.duration)?0:audio.duration-audio.currentTime);});
    audio.addEventListener('loadedmetadata',()=>{if(isFinite(audio.duration))time.textContent=fmt(audio.duration);});
    audio.addEventListener('ended',()=>{playIcon.className='las la-play';progress.style.inset='0 100% 0 0';});
    track.addEventListener('click',e=>{if(!audio.duration)return;const rect=track.getBoundingClientRect();audio.currentTime=((e.clientX-rect.left)/rect.width)*audio.duration;});
    speed.onclick=()=>{rate=rate===1?1.5:rate===1.5?2:1;audio.playbackRate=rate;speed.textContent=rate+'x';};
    wrap.append(playBtn,track,time,speed);return wrap;}
function renderMessage(m){
    const existing=document.querySelector(`[data-chat-message="${m.id}"]`);
    if(existing){buildReactionBar(existing.querySelector('.table-chat__reactions'),m);return;}
    empty?.remove();
    const dateLabel=formatDateLabel(m.time);
    if(dateLabel!==lastRenderedDate){const sep=document.createElement('div');sep.className='table-chat__date-sep';sep.textContent=dateLabel;list.append(sep);lastRenderedDate=dateLabel;lastRenderedUser=null;}
    const mine=Number(m.user_id)===user;
    if(m.kind==='applause'){
        lastRenderedUser=null;
        const rowEl=document.createElement('div');rowEl.className='table-chat__row is-applause'+(mine?' is-mine':'');
        const bubble=document.createElement('article');bubble.className='table-chat__message';bubble.dataset.chatMessage=m.id;
        const icon=document.createElement('i');icon.className='las la-thumbs-up';
        bubble.append(icon,document.createTextNode(' '+(mine?'You':(m.name||'Someone'))+' applauded'));
        rowEl.append(bubble);list.append(rowEl);
        cursor=Math.max(cursor,Number(m.id));
        if(mine||isAtBottom){list.scrollTo({top:list.scrollHeight,behavior:'smooth'});unseenCount=0;jumpBtn.hidden=true;}
        return;
    }
    if(m.kind==='gift'){
        lastRenderedUser=null;
        let gift=null;try{gift=JSON.parse(m.body);}catch(e){}
        const rowEl=document.createElement('div');rowEl.className='table-chat__row is-applause'+(mine?' is-mine':'');
        const bubble=document.createElement('article');bubble.className='table-chat__message';bubble.dataset.chatMessage=m.id;
        if(gift?.gift_image){const img=document.createElement('img');img.src=gift.gift_image;img.alt=gift.gift_name||'';img.style.cssText='width:16px;height:16px;vertical-align:-3px;margin-right:4px';bubble.append(img);}
        const senderName=mine?'You':(m.name||'Someone');
        const recipientName=gift?(Number(gift.recipient_id)===user?'you':(gift.recipient_name||'a player')):'a player';
        bubble.append(document.createTextNode(`${senderName} sent ${gift?.quantity>1?gift.quantity+'x ':''}${gift?.gift_name||'a gift'} to ${recipientName}`));
        rowEl.append(bubble);list.append(rowEl);
        cursor=Math.max(cursor,Number(m.id));
        if(mine||isAtBottom){list.scrollTo({top:list.scrollHeight,behavior:'smooth'});unseenCount=0;jumpBtn.hidden=true;}
        if(chatHistoryLoaded&&gift?.gift_image){
            window.spawnGiftBurst?.(gift.gift_image,`${senderName} sent ${gift.quantity>1?gift.quantity+'x ':''}${gift.gift_name} to ${recipientName}!`);
        }
        return;
    }
    const grouped=!mine&&lastRenderedUser===m.user_id;
    lastRenderedUser=mine?null:m.user_id;
    const rowEl=document.createElement('div');rowEl.className='table-chat__row'+(mine?' is-mine':'')+(grouped?' is-grouped':'');
    if(!mine){const avatar=document.createElement('span');avatar.className='table-chat__avatar';if(m.avatar){const img=document.createElement('img');img.src=m.avatar;img.alt=m.name||'';avatar.append(img);}else{avatar.textContent=(m.name||'P').slice(0,1).toUpperCase();}rowEl.append(avatar);}
    const bubble=document.createElement('article');bubble.className='table-chat__message'+(mine?' is-mine':'');bubble.dataset.chatMessage=m.id;
    if(!grouped){const meta=document.createElement('div');meta.className='table-chat__meta';const name=document.createElement('strong');name.textContent=m.name||'Player';const time=document.createElement('time');time.textContent=formatTime(m.time);meta.append(name,time);bubble.append(meta);}
    if(m.reply_to){const quote=document.createElement('div');quote.className='table-chat__reply-quote';const who=document.createElement('strong');who.textContent=m.reply_to.name||'Player';const text=document.createElement('span');text.textContent=m.reply_to.preview||'';quote.append(who,text);bubble.append(quote);}
    if(m.body){const body=document.createElement('p');body.className='table-chat__body';body.textContent=m.body;bubble.append(body);}
    if(m.attachment_url){
        if(m.kind==='voice'){bubble.append(buildVoicePlayer(m.attachment_url));}
        else if((m.attachment_mime||'').startsWith('image/')){const image=document.createElement('img');image.className='table-chat__image';image.src=m.attachment_url;image.alt=m.attachment_name||'Shared image';image.loading='lazy';bubble.append(image);}
        else{const link=document.createElement('a');link.className='table-chat__attachment';link.href=m.attachment_url;link.textContent='📎 '+(m.attachment_name||'Open shared file');link.target='_blank';link.rel='noopener';bubble.append(link);}
    }
    const footer=document.createElement('div');footer.style.display='flex';footer.style.alignItems='center';
    const reactionBar=document.createElement('div');reactionBar.className='table-chat__reactions';buildReactionBar(reactionBar,m);footer.append(reactionBar);
    const reactBtn=document.createElement('button');reactBtn.type='button';reactBtn.className='table-chat__react-btn';reactBtn.innerHTML='<i class="las la-smile-beam"></i>';reactBtn.setAttribute('aria-label','React');reactBtn.onclick=e=>{e.stopPropagation();openQuickReact(reactBtn,emoji=>react(m.id,emoji));};
    const replyBtn=document.createElement('button');replyBtn.type='button';replyBtn.className='table-chat__react-btn';replyBtn.innerHTML='<i class="las la-reply"></i>';replyBtn.setAttribute('aria-label','Reply');replyBtn.onclick=()=>startReply(m.id,m.name,m.body||(m.kind==='voice'?'Voice note':(m.attachment_name||'Attachment')));
    footer.append(reactBtn,replyBtn);
    if(!mine){const moreBtn=document.createElement('button');moreBtn.type='button';moreBtn.className='table-chat__react-btn';moreBtn.innerHTML='<i class="las la-ellipsis-v"></i>';moreBtn.setAttribute('aria-label','More');moreBtn.onclick=e=>{e.stopPropagation();openMessageMenu(moreBtn,m);};footer.append(moreBtn);}
    if(mine&&m.status){const ticks=document.createElement('span');ticks.className='table-chat__ticks'+(m.status==='read'?' is-read':'');ticks.innerHTML=m.status==='read'?'<i class="las la-check-double"></i>':'<i class="las la-check"></i>';footer.append(ticks);}
    bubble.append(footer);
    rowEl.append(bubble);list.append(rowEl);
    cursor=Math.max(cursor,Number(m.id));
    if(mine||isAtBottom){list.scrollTo({top:list.scrollHeight,behavior:'smooth'});unseenCount=0;jumpBtn.hidden=true;}
    else{unseenCount++;document.querySelector('#chat-jump-count').textContent=unseenCount;jumpBtn.hidden=false;}
}
const jumpBtn=document.querySelector('#chat-jump');
list.addEventListener('scroll',()=>{isAtBottom=list.scrollHeight-list.scrollTop-list.clientHeight<60;if(isAtBottom){unseenCount=0;jumpBtn.hidden=true;}});
jumpBtn.addEventListener('click',()=>{list.scrollTo({top:list.scrollHeight,behavior:'smooth'});unseenCount=0;jumpBtn.hidden=true;});

async function getMessages(){if(busy||document.hidden)return;busy=true;try{const url=new URL(root.dataset.chat,location.href);url.searchParams.set('after',cursor);const response=await fetch(url,{headers,cache:'no-store'});if(!response.ok)throw new Error('Chat could not reconnect.');const data=await response.json();(data.messages||[]).forEach(renderMessage);error.textContent='';}catch(e){error.textContent=e.message;}finally{busy=false;}}
async function react(id,emoji){try{const url=root.dataset.reactionTemplate.replace('__MESSAGE__',id);const response=await fetch(url,{method:'POST',headers:{...headers,'Content-Type':'application/json'},body:JSON.stringify({emoji})});if(!response.ok)throw new Error('Reaction could not be saved.');const data=await response.json();const card=document.querySelector(`[data-chat-message="${id}"]`),bar=card?.querySelector('.table-chat__reactions');if(!bar)return;bar.replaceChildren();quickReactions.forEach(choice=>{const item=data.reactions.find(r=>r.emoji===choice);if(!item)return;const button=document.createElement('button');button.type='button';button.className=item.mine?'is-mine':'';button.textContent=`${choice} ${item.count}`;button.onclick=()=>react(id,choice);bar.append(button);});}catch(e){error.textContent=e.message;}}

/* ---- Composer ---- */
const composer=document.querySelector('#chat-input'),chatForm=document.querySelector('#chat-form');
function resizeComposer(){composer.style.height='auto';composer.style.height=Math.min(composer.scrollHeight,160)+'px';}
function syncComposerState(){resizeComposer();chatForm.classList.toggle('has-text',composer.value.trim().length>0);}
resizeComposer();composer.addEventListener('input',syncComposerState);
async function send(form){error.textContent='';try{if(replyToId)form.append('reply_to_id',replyToId);const response=await fetch(root.dataset.chatSend,{method:'POST',headers,body:form});const data=await response.json();if(!response.ok)throw new Error(data.message||'Message could not be sent.');renderMessage(data.message);window.TableSound?.play?.('click');composer.value='';syncComposerState();fileInput.value='';replyToId=null;replyBar.hidden=true;}catch(e){error.textContent=e.message;}}
chatForm.addEventListener('submit',event=>{event.preventDefault();const text=composer.value.trim();if(!text)return;const data=new FormData();data.append('body',text);data.append('kind','text');send(data);});
composer.addEventListener('keydown',event=>{if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();chatForm.requestSubmit();}});
fileInput.addEventListener('change',event=>{const file=event.target.files?.[0];if(!file)return;const data=new FormData();data.append('attachment',file);data.append('kind','file');send(data);});

/* ---- Voice recording ---- */
const recordBar=document.querySelector('#chat-record'),recordMeter=document.querySelector('#chat-record-meter'),recordTime=document.querySelector('#chat-record-time');
for(let i=0;i<24;i++){const bar=document.createElement('span');bar.style.height='10%';recordMeter.append(bar);}
function tickRecordTimer(){const elapsed=Math.floor((Date.now()-recordStart)/1000);recordTime.textContent=Math.floor(elapsed/60)+':'+String(elapsed%60).padStart(2,'0');}
function animateMeter(){if(!recordAnalyser)return;const data=new Uint8Array(recordAnalyser.frequencyBinCount);recordAnalyser.getByteFrequencyData(data);const bars=recordMeter.children;for(let i=0;i<bars.length;i++){const v=data[Math.floor(i*data.length/bars.length)]||0;bars[i].style.height=Math.max(10,(v/255)*100)+'%';}recordRaf=requestAnimationFrame(animateMeter);}
let recordStream=null;
async function startRecording(){if(!navigator.mediaDevices?.getUserMedia||!window.MediaRecorder){error.textContent='Voice recording is not supported by this browser.';return;}
    try{
        recordStream=await navigator.mediaDevices.getUserMedia({audio:true});
        window.TableSound?.unlock?.();
        chunks=[];recorder=new MediaRecorder(recordStream);
        recorder.ondataavailable=event=>{if(event.data.size)chunks.push(event.data);};
        recorder.onstop=()=>{recordStream.getTracks().forEach(track=>track.stop());cancelAnimationFrame(recordRaf);clearInterval(recordTimer);recordBar.hidden=true;recordAnalyser=null;};
        recorder.start();
        const AudioCtx=window.AudioContext||window.webkitAudioContext;
        if(AudioCtx){const ctx=new AudioCtx();const source=ctx.createMediaStreamSource(recordStream);recordAnalyser=ctx.createAnalyser();recordAnalyser.fftSize=64;source.connect(recordAnalyser);animateMeter();}
        recordStart=Date.now();recordTime.textContent='0:00';recordTimer=setInterval(tickRecordTimer,1000);recordBar.hidden=false;
    }catch(e){error.textContent='Allow microphone access to record a voice note.';}
}
function finishRecording(shouldSend){if(recorder?.state!=='recording'){return;}
    const onstopOriginal=recorder.onstop;
    recorder.onstop=()=>{onstopOriginal();
        if(shouldSend){const blob=new Blob(chunks,{type:recorder.mimeType||'audio/webm'});if(blob.size>10*1024*1024){error.textContent='Voice notes must be under 10 MB.';return;}
            const mime=blob.type.split(';')[0],extension=mime==='audio/webm'?'webm':mime==='audio/mp4'?'m4a':(mime.split('/')[1]||'ogg');
            const data=new FormData();data.append('attachment',blob,`voice-note.${extension}`);data.append('kind','voice');send(data);
        }
    };
    recorder.stop();
}
document.querySelector('#chat-voice')?.addEventListener('click',startRecording);
document.querySelector('#chat-record-stop')?.addEventListener('click',()=>finishRecording(true));
document.querySelector('#chat-record-cancel')?.addEventListener('click',()=>finishRecording(false));

getMessages().then(()=>{chatHistoryLoaded=true;});const poll=()=>setTimeout(async()=>{await getMessages();poll();},document.hidden?7000:2500);poll();document.addEventListener('visibilitychange',()=>{if(!document.hidden){getMessages();}});
})();
</script>
@endpush
