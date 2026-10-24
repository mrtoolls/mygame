@php
    $liveWins = collect();
    if (\Illuminate\Support\Facades\Schema::hasTable('table_game_players') && \Illuminate\Support\Facades\Schema::hasTable('table_games')) {
        $liveWinQuery = \App\Models\TableGamePlayer::with(['user:id,username', 'table:id,game_type,status'])
            ->where('settlement_type', 'payout')->where('settlement_amount', '>', 0)
            ->whereHas('table', fn ($q) => $q->where('status', 'finished'))
            ->latest('id')->limit(20);
        $cardImages = \App\Models\TableGameSetting::pluck('card_image', 'game_type');
        foreach ($liveWinQuery->get() as $player) {
            $type = $player->table?->game_type;
            $cardImage = $cardImages[$type] ?? null;
            $liveWins->push((object) [
                'game_name' => \App\Games\TableGames\TableGameRules::GAMES[$type]['name'] ?? __('Table game'),
                'image' => $cardImage ? getImage(getFilePath('game') . '/' . $cardImage, getFileSize('game')) : null,
                'icon' => \App\Games\TableGames\TableGameRules::GAME_ICONS[$type] ?? 'la-dice',
                'username' => $player->user?->username,
                'amount' => (float) $player->settlement_amount,
                'currency_id' => $player->currency_id,
                'rate_to_usd' => $player->wallet_rate_to_usd,
                'at' => $player->updated_at,
            ]);
        }
    }
@endphp
<div class="recent-wins">
    <div class="recent-wins__head"><i class="las la-trophy"></i><span>@lang('Recent Wins')</span><small>@lang('Live tables')</small></div>
    @if($liveWins->isNotEmpty())
        <div class="recent-wins__viewport">
            <div class="recent-wins__track" style="--win-count:{{ $liveWins->count() }}">
                @for($pass = 0; $pass < 2; $pass++)
                    @foreach($liveWins as $win)
                        @php
                            $player = $win->username ?: __('Player');
                            $maskedPlayer = mb_substr($player, 0, 2) . str_repeat('•', max(2, min(5, mb_strlen($player) - 2)));
                        @endphp
                        <div class="recent-wins__item" aria-hidden="{{ $pass === 1 ? 'true' : 'false' }}">
                            <span class="recent-wins__badge">
                                @if($win->image)
                                    <img src="{{ $win->image }}" alt="" loading="lazy" decoding="async">
                                @else
                                    <i class="las {{ $win->icon }}"></i>
                                @endif
                            </span>
                            <div class="recent-wins__info">
                                <strong>{{ __($win->game_name) }}</strong>
                                <small>{{ $maskedPlayer }} · {{ $win->at?->diffForHumans() ?? __('Recently') }}</small>
                            </div>
                            <span class="recent-wins__amount">{{ formatHistoricalAmountAsCoins($win->amount, $win->currency_id, $win->rate_to_usd) }}</span>
                        </div>
                    @endforeach
                @endfor
            </div>
        </div>
    @else
        <div class="recent-wins__empty"><i class="las la-trophy"></i><span>@lang('Live table wins will appear here as games finish.')</span></div>
    @endif
</div>
