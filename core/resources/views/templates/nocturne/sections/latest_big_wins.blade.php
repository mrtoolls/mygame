@php
    $palette = ['t1', 't2', 't3', 't4', 't5'];

    $normalWins = collect();
    if (\Illuminate\Support\Facades\Schema::hasTable('game_logs')) {
        $normalQuery = \App\Models\GameLog::with(['user:id,username', 'game:id,name,image'])
            ->where('win_amo', '>', 0)->latest('id')->limit(16);
        if (\Illuminate\Support\Facades\Schema::hasColumn('game_logs', 'demo_play')) $normalQuery->where('demo_play', 0);
        if (\Illuminate\Support\Facades\Schema::hasColumn('game_logs', 'status')) $normalQuery->where('status', \App\Constants\Status::GAME_FINISHED);
        foreach ($normalQuery->get() as $log) {
            $normalWins->push((object) [
                'game_name' => $log->game?->name ?? __('Winxpot game'),
                'image' => $log->game?->image ? getImage(getFilePath('game') . '/' . $log->game->image, getFileSize('game')) : null,
                'icon' => 'la-dice-five',
                'tag' => __('Originals'),
                'username' => $log->user?->username,
                'amount' => (float) $log->win_amo,
                'at' => $log->created_at,
            ]);
        }
    }

    $tableWins = collect();
    if (\Illuminate\Support\Facades\Schema::hasTable('table_game_players') && \Illuminate\Support\Facades\Schema::hasTable('table_games')) {
        $tableQuery = \App\Models\TableGamePlayer::with(['user:id,username', 'table:id,game_type,status'])
            ->where('settlement_type', 'payout')->where('settlement_amount', '>', 0)
            ->whereHas('table', fn ($q) => $q->where('status', 'finished'))
            ->latest('id')->limit(10);
        $cardImages = \App\Models\TableGameSetting::pluck('card_image', 'game_type');
        foreach ($tableQuery->get() as $player) {
            $type = $player->table?->game_type;
            $cardImage = $cardImages[$type] ?? null;
            $tableWins->push((object) [
                'game_name' => \App\Games\TableGames\TableGameRules::GAMES[$type]['name'] ?? __('Table game'),
                'image' => $cardImage ? getImage(getFilePath('game') . '/' . $cardImage, getFileSize('game')) : null,
                'icon' => \App\Games\TableGames\TableGameRules::GAME_ICONS[$type] ?? 'la-dice',
                'tag' => __('Live Table'),
                'username' => $player->user?->username,
                'amount' => (float) $player->settlement_amount,
                'at' => $player->updated_at,
            ]);
        }
    }

    // Same fixed-slot reasoning as last_games/recent_big_wins: reserve room for each source so
    // normal games (almost always far more numerous) can't crowd table-game wins out of a
    // continuous ticker that's supposed to represent the whole site, not just one game type.
    $bigWins = $normalWins->take(16)->merge($tableWins->take(8))->sortByDesc('at')->values();
@endphp
@if($bigWins->isNotEmpty())
    <section class="nocturne-ticker-wins">
        <div class="nocturne-ticker-wins__head">
            <h2><i class="ph-fill ph-lightning"></i>@lang('Latest Big Wins')</h2>
            <span class="nocturne-ticker-wins__live"><i></i>@lang('LIVE')</span>
        </div>
        <div class="nocturne-ticker-wins__viewport">
            <div class="nocturne-ticker-wins__track" style="--ticker-count:{{ $bigWins->count() }}">
                @for($pass = 0; $pass < 2; $pass++)
                    @foreach($bigWins as $i => $win)
                        @php
                            $player = $win->username ?: __('Player');
                            $maskedPlayer = mb_substr($player, 0, 2) . str_repeat('•', max(2, min(5, mb_strlen($player) - 2)));
                            $tint = $palette[$i % count($palette)];
                        @endphp
                        <article class="nocturne-win-card nocturne-win-card--{{ $tint }}" aria-hidden="{{ $pass === 1 ? 'true' : 'false' }}">
                            <span class="nocturne-win-card__tag">{{ $win->tag }}</span>
                            <div class="nocturne-win-card__art">
                                @if($win->image)
                                    <img src="{{ $win->image }}" alt="" loading="lazy" decoding="async">
                                @else
                                    <i class="las {{ $win->icon ?? 'la-dice-five' }}"></i>
                                @endif
                            </div>
                            <div class="nocturne-win-card__body">
                                <strong class="nocturne-win-card__game">{{ __($win->game_name) }}</strong>
                                <span class="nocturne-win-card__player">{{ $maskedPlayer }}</span>
                                <span class="nocturne-win-card__amount"><i class="ph-fill ph-trophy"></i>{{ showAmount($win->amount) }}</span>
                            </div>
                        </article>
                    @endforeach
                @endfor
            </div>
        </div>
    </section>
@endif
