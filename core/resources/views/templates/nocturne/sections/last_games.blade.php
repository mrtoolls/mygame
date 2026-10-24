@php
    $normalFeed = collect();
    if (\Illuminate\Support\Facades\Schema::hasTable('game_logs')) {
        $normalQuery = \App\Models\GameLog::with(['user:id,username', 'game:id,name'])->latest('id')->limit(6);
        if (\Illuminate\Support\Facades\Schema::hasColumn('game_logs', 'demo_play')) $normalQuery->where('demo_play', 0);
        if (\Illuminate\Support\Facades\Schema::hasColumn('game_logs', 'status')) $normalQuery->where('status', \App\Constants\Status::GAME_FINISHED);
        foreach ($normalQuery->get() as $log) {
            $normalFeed->push((object) [
                'game_name' => $log->game?->name ?? __('Winxpot game'),
                'username' => $log->user?->username,
                'wager' => (float) $log->invest,
                'wager_rate_to_usd' => null,
                'result' => (float) $log->win_amo,
                'currency_id' => $log->currency_id ?? null,
                'rate_to_usd' => null,
                'at' => $log->created_at,
            ]);
        }
    }

    $tableFeed = collect();
    if (\Illuminate\Support\Facades\Schema::hasTable('table_game_players') && \Illuminate\Support\Facades\Schema::hasTable('table_games')) {
        $tableQuery = \App\Models\TableGamePlayer::with(['user:id,username', 'table:id,game_type,status'])
            ->whereIn('settlement_type', ['payout', 'loss'])
            ->whereHas('table', fn ($q) => $q->where('status', 'finished'))
            ->latest('id')->limit(4);
        foreach ($tableQuery->get() as $player) {
            $tableFeed->push((object) [
                'game_name' => \App\Games\TableGames\TableGameRules::GAMES[$player->table?->game_type]['name'] ?? __('Table game'),
                'username' => $player->user?->username,
                // 'stake' is in the TABLE's own currency (table_rate_to_usd), not the player's
                // wallet currency - unlike settlement_amount, which creditPlayer() already
                // converts into the player's wallet currency (currency_id/wallet_rate_to_usd).
                // Each needs its own rate for an accurate coin conversion.
                'wager' => (float) $player->stake,
                'wager_rate_to_usd' => $player->table_rate_to_usd,
                'result' => (float) ($player->settlement_amount ?? 0),
                'currency_id' => $player->currency_id,
                'rate_to_usd' => $player->wallet_rate_to_usd,
                'at' => $player->updated_at,
            ]);
        }
    }

    // Reserving fixed slots per source (6 normal + 4 table), rather than pooling everything
    // and taking the latest 10 overall, guarantees table games show up here whenever any
    // exist - a plain time-sort would let the usually-far-more-numerous normal games crowd
    // them out entirely. The reserved set is then sorted by time just for display order.
    $latestGameLogs = $normalFeed->merge($tableFeed)->sortByDesc('at')->values();
@endphp
<section class="nocturne-community-section">
    <div class="nocturne-community-heading"><div><span class="nocturne-community-kicker">@lang('THE LATEST ACTION')</span><h2><i class="ph-fill ph-dice-five"></i>@lang('Last Games')</h2><p>@lang('Recent game activity from real recorded plays.')</p></div><span class="nocturne-community-heading__badge"><i class="ph-fill ph-users-three"></i>{{ number_format(\App\Models\User::count()) }} @lang('players')</span></div>
    @if($latestGameLogs->isNotEmpty())
        <div class="nocturne-last-games-table" role="table" aria-label="@lang('Last games')">
            <div class="nocturne-last-games-table__head" role="row"><span role="columnheader">@lang('Game')</span><span role="columnheader">@lang('Player')</span><span role="columnheader">@lang('Wager')</span><span role="columnheader">@lang('Result')</span></div>
            @foreach($latestGameLogs as $log)
                @php $player = $log->username ?: __('Player'); $maskedPlayer = mb_substr($player, 0, 2) . str_repeat('•', max(2, min(6, mb_strlen($player) - 2))); @endphp
                <div class="nocturne-last-games-table__row" role="row"><span class="nocturne-last-games-table__game" role="cell"><i class="ph-fill ph-dice-five"></i>{{ __($log->game_name) }}</span><span class="nocturne-last-games-table__player" role="cell">{{ $maskedPlayer }}<small>{{ $log->at?->diffForHumans() ?? __('Recently') }}</small></span><strong role="cell">{{ formatHistoricalAmountAsCoins($log->wager, $log->currency_id, $log->wager_rate_to_usd) }}</strong><strong class="{{ $log->result > 0 ? 'is-win' : 'is-loss' }}" role="cell">{{ formatHistoricalAmountAsCoins($log->result, $log->currency_id, $log->rate_to_usd) }}</strong></div>
            @endforeach
        </div>
    @else
        <div class="nocturne-community-empty"><i class="ph-fill ph-dice-five"></i><span>@lang('Recent games will appear here as activity is recorded.')</span></div>
    @endif
</section>
