@php
    $bigWinLogs = collect();
    if (\Illuminate\Support\Facades\Schema::hasTable('game_logs')) {
        $bigWinQuery = \App\Models\GameLog::with(['user:id,username', 'game:id,name'])
            ->where('win_amo', '>', 0)->orderByDesc('win_amo');
        if (\Illuminate\Support\Facades\Schema::hasColumn('game_logs', 'demo_play')) $bigWinQuery->where('demo_play', 0);
        if (\Illuminate\Support\Facades\Schema::hasColumn('game_logs', 'status')) $bigWinQuery->where('status', \App\Constants\Status::GAME_FINISHED);
        $bigWinLogs = $bigWinQuery->limit(6)->get();
    }
@endphp
<section class="nocturne-community-section">
    <div class="nocturne-community-heading"><div><span class="nocturne-community-kicker">@lang('MOMENTS THAT MATTER')</span><h2><i class="ph-fill ph-trophy"></i>@lang('Recent Big Wins')</h2><p>@lang('A snapshot of recent wins recorded on Winxpot.')</p></div><span class="nocturne-community-heading__badge"><i class="ph-fill ph-sparkle"></i>@lang('RECENT')</span></div>
    @if($bigWinLogs->isNotEmpty())
        <div class="nocturne-big-wins-grid">
            @foreach($bigWinLogs as $log)
                @php $winner = $log->user?->username ?: __('Player'); $maskedWinner = mb_substr($winner, 0, 2) . str_repeat('•', max(2, min(6, mb_strlen($winner) - 2))); @endphp
                <article class="nocturne-big-win-card"><span class="nocturne-big-win-card__icon"><i class="ph-fill ph-currency-circle-dollar"></i></span><div><small>{{ __($log->game?->name ?? __('Winxpot game')) }} · {{ $maskedWinner }}</small><strong>{{ showAmount($log->win_amo) }}</strong><span>{{ $log->created_at?->diffForHumans() ?? __('Recently') }}</span></div><i class="ph-fill ph-confetti nocturne-big-win-card__spark"></i></article>
            @endforeach
        </div>
    @else
        <div class="nocturne-community-empty"><i class="ph-fill ph-trophy"></i><span>@lang('Big wins will appear here as they are recorded.')</span></div>
    @endif
</section>
