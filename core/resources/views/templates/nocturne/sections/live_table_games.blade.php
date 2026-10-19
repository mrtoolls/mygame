@php
    $liveGameCatalog = collect();
    if (\Illuminate\Support\Facades\Schema::hasTable('table_games') && \Illuminate\Support\Facades\Schema::hasTable('table_game_settings')) {
        $liveGameCatalog = collect(app(\App\Games\TableGames\TableGameService::class)->catalog())
            ->where('available', true)
            ->where('enabled', true)
            ->values();
    }
@endphp
@if ($liveGameCatalog->isNotEmpty())
    <section class="nocturne-live-tables">
        <div class="nocturne-live-tables__head">
            <div><span>@lang('LIVE GAMES')</span><h2>@lang('Have fun with friends')</h2><p>@lang('Pick a game, invite your friends, and play for free or choose a stake together.')</p></div>
            <a href="{{ auth()->check() ? route('user.tables.index') : route('user.login') }}">@lang('Open live games') <i class="ph-bold ph-arrow-right"></i></a>
        </div>
        <div class="nocturne-live-tables__grid">
            @foreach ($liveGameCatalog as $game)
                <article class="nocturne-live-table">
                    <div class="nocturne-live-table__top"><span class="nocturne-live-table__game">{{ strtoupper(substr($game['name'], 0, 2)) }}</span><span class="nocturne-live-table__status"><i></i>@lang('PLAY WITH FRIENDS')</span></div>
                    <h3>{{ __($game['name']) }}</h3>
                    <div class="nocturne-live-table__meta"><span>{{ $game['min_players'] }}–{{ $game['max_players'] }} @lang('players')</span><strong class="nocturne-live-table__free">@lang('FREE PLAY')</strong></div>
                    <a class="nocturne-live-table__button" href="{{ auth()->check() ? route('user.tables.index') : route('user.login') }}">@lang('Play for fun') <i class="ph-bold ph-arrow-right"></i></a>
                </article>
            @endforeach
        </div>
    </section>
@endif
<style>
.nocturne-live-tables{margin:26px 0;padding:clamp(16px,3vw,24px);border:1px solid var(--nocturne-line);border-radius:22px;background:linear-gradient(140deg,rgba(24,30,55,.96),rgba(15,19,37,.97))}.nocturne-live-tables__head{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:15px}.nocturne-live-tables__head span{color:var(--nocturne-green);font-size:9px;font-weight:950;letter-spacing:.14em}.nocturne-live-tables__head h2{margin:3px 0;font-size:clamp(19px,3vw,25px);font-weight:950;text-transform:uppercase}.nocturne-live-tables__head p{margin:0;color:var(--nocturne-muted);font-size:11px}.nocturne-live-tables__head>a{display:inline-flex;align-items:center;gap:6px;padding:9px 12px;border-radius:11px;background:var(--nocturne-raised);color:var(--nocturne-text);font-size:10px;font-weight:850;text-decoration:none;white-space:nowrap}.nocturne-live-tables__grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.nocturne-live-table{min-width:0;padding:13px;border:1px solid rgba(145,156,205,.13);border-radius:15px;background:rgba(35,42,71,.4)}.nocturne-live-table__top{display:flex;justify-content:space-between;gap:7px}.nocturne-live-table__game{display:grid;width:35px;height:35px;place-items:center;border-radius:11px;background:rgba(0,239,135,.12);color:var(--nocturne-green);font-size:10px;font-weight:950}.nocturne-live-table__status{display:flex;align-items:center;gap:5px;color:#a8b5d3;font-size:8px;letter-spacing:.05em}.nocturne-live-table__status i{width:6px;height:6px;border-radius:50%;background:var(--nocturne-green);box-shadow:0 0 8px var(--nocturne-green)}.nocturne-live-table h3{overflow:hidden;margin:10px 0;font-size:12px;font-weight:900;text-overflow:ellipsis;white-space:nowrap}.nocturne-live-table__meta{display:flex;align-items:center;justify-content:space-between;gap:5px;color:var(--nocturne-muted);font-size:9px}.nocturne-live-table__meta strong{color:var(--nocturne-green);font-size:9px}.nocturne-live-table__button{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;min-height:34px;margin-top:10px;border-radius:10px;background:var(--nocturne-green);color:#062b1d;font-size:9px;font-weight:950;text-decoration:none}@media(max-width:800px){.nocturne-live-tables__grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:420px){.nocturne-live-tables{padding:14px;border-radius:18px}.nocturne-live-tables__grid{gap:8px}.nocturne-live-table{padding:10px}.nocturne-live-table h3{font-size:11px}}
</style>
