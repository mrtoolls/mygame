@php
    $normalWins = collect();
    if (\Illuminate\Support\Facades\Schema::hasTable('game_logs')) {
        $bigWinQuery = \App\Models\GameLog::with(['user:id,username', 'game:id,name'])
            ->where('win_amo', '>', 0)->orderByDesc('win_amo')->limit(4);
        if (\Illuminate\Support\Facades\Schema::hasColumn('game_logs', 'demo_play')) $bigWinQuery->where('demo_play', 0);
        if (\Illuminate\Support\Facades\Schema::hasColumn('game_logs', 'status')) $bigWinQuery->where('status', \App\Constants\Status::GAME_FINISHED);
        foreach ($bigWinQuery->get() as $log) {
            $normalWins->push((object) [
                'game_name' => $log->game?->name ?? __('Winxpot game'),
                'username' => $log->user?->username,
                'amount' => (float) $log->win_amo,
                'at' => $log->created_at,
            ]);
        }
    }

    $tableWins = collect();
    if (\Illuminate\Support\Facades\Schema::hasTable('table_game_players') && \Illuminate\Support\Facades\Schema::hasTable('table_games')) {
        $tableWinQuery = \App\Models\TableGamePlayer::with(['user:id,username', 'table:id,game_type,status'])
            ->where('settlement_type', 'payout')->where('settlement_amount', '>', 0)
            ->whereHas('table', fn ($q) => $q->where('status', 'finished'))
            ->orderByDesc('settlement_amount')->limit(2);
        foreach ($tableWinQuery->get() as $player) {
            $tableWins->push((object) [
                'game_name' => \App\Games\TableGames\TableGameRules::GAMES[$player->table?->game_type]['name'] ?? __('Table game'),
                'username' => $player->user?->username,
                'amount' => (float) $player->settlement_amount,
                'at' => $player->updated_at,
            ]);
        }
    }

    // Fixed slots per source (4 normal + 2 table), same reasoning as last_games.blade.php -
    // a pure "top 6 by amount overall" ranking would usually let normal-game wins (far more
    // numerous) push table-game wins out entirely.
    $bigWinLogs = $normalWins->merge($tableWins)->sortByDesc('amount')->values();
@endphp
<section class="nocturne-community-section">
    <div class="nocturne-community-heading"><div><span class="nocturne-community-kicker">@lang('MOMENTS THAT MATTER')</span><h2><i class="ph-fill ph-trophy"></i>@lang('Recent Big Wins')</h2><p>@lang('A snapshot of recent wins recorded on Winxpot.')</p></div><span class="nocturne-community-heading__badge"><i class="ph-fill ph-sparkle"></i>@lang('RECENT')</span></div>
    @if($bigWinLogs->isNotEmpty())
        <div class="nocturne-big-wins-grid">
            @foreach($bigWinLogs as $log)
                @php $winner = $log->username ?: __('Player'); $maskedWinner = mb_substr($winner, 0, 2) . str_repeat('•', max(2, min(6, mb_strlen($winner) - 2))); @endphp
                <article class="nocturne-big-win-card"><span class="nocturne-big-win-card__icon"><i class="ph-fill ph-currency-circle-dollar"></i></span><div><small>{{ __($log->game_name) }} · {{ $maskedWinner }}</small><strong>{{ showAmount($log->amount) }}</strong><span>{{ $log->at?->diffForHumans() ?? __('Recently') }}</span></div><i class="ph-fill ph-confetti nocturne-big-win-card__spark"></i></article>
            @endforeach
        </div>
    @else
        <div class="nocturne-community-empty"><i class="ph-fill ph-trophy"></i><span>@lang('Big wins will appear here as they are recorded.')</span></div>
    @endif
</section>
