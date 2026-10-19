@php
    $latestGameLogs = collect();
    if (\Illuminate\Support\Facades\Schema::hasTable('game_logs')) {
        $latestQuery = \App\Models\GameLog::with(['user:id,username', 'game:id,name'])->latest('id');
        if (\Illuminate\Support\Facades\Schema::hasColumn('game_logs', 'demo_play')) $latestQuery->where('demo_play', 0);
        if (\Illuminate\Support\Facades\Schema::hasColumn('game_logs', 'status')) $latestQuery->where('status', \App\Constants\Status::GAME_FINISHED);
        $latestGameLogs = $latestQuery->limit(10)->get();
    }
@endphp
<section class="nocturne-community-section">
    <div class="nocturne-community-heading"><div><span class="nocturne-community-kicker">@lang('THE LATEST ACTION')</span><h2><i class="ph-fill ph-dice-five"></i>@lang('Last Games')</h2><p>@lang('Recent game activity from real recorded plays.')</p></div><span class="nocturne-community-heading__badge"><i class="ph-fill ph-users-three"></i>{{ number_format(\App\Models\User::count()) }} @lang('players')</span></div>
    @if($latestGameLogs->isNotEmpty())
        <div class="nocturne-last-games-table" role="table" aria-label="@lang('Last games')">
            <div class="nocturne-last-games-table__head" role="row"><span role="columnheader">@lang('Game')</span><span role="columnheader">@lang('Player')</span><span role="columnheader">@lang('Wager')</span><span role="columnheader">@lang('Result')</span></div>
            @foreach($latestGameLogs as $log)
                @php $player = $log->user?->username ?: __('Player'); $maskedPlayer = mb_substr($player, 0, 2) . str_repeat('•', max(2, min(6, mb_strlen($player) - 2))); @endphp
                <div class="nocturne-last-games-table__row" role="row"><span class="nocturne-last-games-table__game" role="cell"><i class="ph-fill ph-dice-five"></i>{{ __($log->game?->name ?? __('Winxpot game')) }}</span><span class="nocturne-last-games-table__player" role="cell">{{ $maskedPlayer }}<small>{{ $log->created_at?->diffForHumans() ?? __('Recently') }}</small></span><strong role="cell">{{ showAmount($log->invest) }}</strong><strong class="{{ (float) $log->win_amo > 0 ? 'is-win' : 'is-loss' }}" role="cell">{{ showAmount($log->win_amo) }}</strong></div>
            @endforeach
        </div>
    @else
        <div class="nocturne-community-empty"><i class="ph-fill ph-dice-five"></i><span>@lang('Recent games will appear here as activity is recorded.')</span></div>
    @endif
</section>
