@php
    $palette = ['t1', 't2', 't3', 't4', 't5'];
    $liveWins = collect();
    if (\Illuminate\Support\Facades\Schema::hasTable('table_game_players') && \Illuminate\Support\Facades\Schema::hasTable('table_games')) {
        $liveWinQuery = \App\Models\TableGamePlayer::with(['user:id,username', 'table:id,game_type,status'])
            ->where('settlement_type', 'payout')->where('settlement_amount', '>', 0)
            ->whereHas('table', fn ($q) => $q->where('status', 'finished'))
            ->latest('id')->limit(14);
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
                'at' => $player->updated_at,
            ]);
        }
    }
@endphp
@if($liveWins->isNotEmpty())
    <section class="nocturne-ticker-wins nocturne-ticker-wins--static">
        <div class="nocturne-ticker-wins__head">
            <h2><i class="ph-fill ph-trophy"></i>@lang('Recent Wins')</h2>
            <span class="nocturne-ticker-wins__live"><i></i>@lang('LIVE TABLES')</span>
        </div>
        <div class="nocturne-game-scroller">
            @foreach($liveWins as $i => $win)
                @php
                    $player = $win->username ?: __('Player');
                    $maskedPlayer = mb_substr($player, 0, 2) . str_repeat('•', max(2, min(5, mb_strlen($player) - 2)));
                    $tint = $palette[$i % count($palette)];
                @endphp
                <article class="nocturne-win-card nocturne-win-card--{{ $tint }} nocturne-game-scroller__item">
                    <span class="nocturne-win-card__tag">{{ __('Live Table') }}</span>
                    <div class="nocturne-win-card__art">
                        @if($win->image)
                            <img src="{{ $win->image }}" alt="" loading="lazy" decoding="async">
                        @else
                            <i class="las {{ $win->icon }}"></i>
                        @endif
                    </div>
                    <div class="nocturne-win-card__body">
                        <strong class="nocturne-win-card__game">{{ __($win->game_name) }}</strong>
                        <span class="nocturne-win-card__player">{{ $maskedPlayer }}</span>
                        <span class="nocturne-win-card__amount"><i class="ph-fill ph-trophy"></i>{{ showAmount($win->amount) }}</span>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
