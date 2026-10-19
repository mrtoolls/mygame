@php
    $homeTableGames = \Illuminate\Support\Facades\Schema::hasTable('table_games')
        ? \App\Models\TableGame::with('players.user')->whereIn('status', ['waiting', 'active'])->latest()->limit(4)->get()
        : collect();
@endphp
@if ($homeTableGames->isNotEmpty())
    <section class="nocturne-live-tables">
        <div class="nocturne-live-tables__head"><div><span>@lang('PLAY TOGETHER')</span><h2>@lang('Live Tables')</h2><p>@lang('Join a table and play against other players.')</p></div><a href="{{ auth()->check() ? route('user.tables.index') : route('user.login') }}">@lang('View all') <i class="ph-bold ph-arrow-right"></i></a></div>
        <div class="nocturne-live-tables__grid">
            @foreach ($homeTableGames as $table)
                <article class="nocturne-live-table"><div class="nocturne-live-table__top"><span class="nocturne-live-table__game">{{ strtoupper(substr($table->game_type, 0, 2)) }}</span><span class="nocturne-live-table__status {{ $table->status === 'active' ? 'is-playing' : '' }}"><i></i>{{ $table->status === 'active' ? __('In progress') : __('Waiting') }}</span></div><h3>{{ __(\App\Games\TableGames\TableGameRules::GAMES[$table->game_type]['name'] ?? $table->game_type) }}</h3><div class="nocturne-live-table__meta"><span>{{ $table->players->where('status', 'seated')->count() }}/{{ $table->player_limit }} @lang('seats')</span><strong>{{ gs('cur_sym') }}{{ showAmount($table->stake) }} <small>@lang('each')</small></strong></div>
                    @if ($table->status === 'waiting' && auth()->check() && !$table->players->contains('user_id', auth()->id()))
                        <form method="post" action="{{ route('user.tables.join', $table->id) }}">@csrf<button type="submit">@lang('Join table') <i class="ph-bold ph-arrow-right"></i></button></form>
                    @elseif ($table->status === 'waiting' && !auth()->check())
                        <a class="nocturne-live-table__button" href="{{ route('user.login') }}">@lang('Sign in to join') <i class="ph-bold ph-arrow-right"></i></a>
                    @elseif (auth()->check() && $table->players->contains('user_id', auth()->id()))
                        <a class="nocturne-live-table__button" href="{{ route('user.tables.show', $table->id) }}">@lang('Open table') <i class="ph-bold ph-arrow-right"></i></a>
                    @else
                        <a class="nocturne-live-table__button" href="{{ auth()->check() ? route('user.tables.index') : route('user.login') }}">@lang('Browse tables') <i class="ph-bold ph-arrow-right"></i></a>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
    <style>
        .nocturne-live-tables{margin:26px 0;padding:clamp(16px,3vw,24px);border:1px solid var(--nocturne-line);border-radius:22px;background:linear-gradient(140deg,rgba(24,30,55,.96),rgba(15,19,37,.97))}.nocturne-live-tables__head{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:15px}.nocturne-live-tables__head span{color:var(--nocturne-green);font-size:9px;font-weight:950;letter-spacing:.14em}.nocturne-live-tables__head h2{margin:3px 0;font-size:clamp(19px,3vw,25px);font-weight:950}.nocturne-live-tables__head p{margin:0;color:var(--nocturne-muted);font-size:11px}.nocturne-live-tables__head>a{display:inline-flex;align-items:center;gap:6px;padding:9px 12px;border-radius:11px;background:var(--nocturne-raised);color:var(--nocturne-text);font-size:10px;font-weight:850;text-decoration:none;white-space:nowrap}.nocturne-live-tables__grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.nocturne-live-table{min-width:0;padding:13px;border:1px solid rgba(145,156,205,.13);border-radius:15px;background:rgba(35,42,71,.4)}.nocturne-live-table__top{display:flex;justify-content:space-between;gap:7px}.nocturne-live-table__game{display:grid;width:35px;height:35px;place-items:center;border-radius:11px;background:rgba(0,239,135,.12);color:var(--nocturne-green);font-size:10px;font-weight:950}.nocturne-live-table__status{display:flex;align-items:center;gap:5px;color:#a8b5d3;font-size:8px}.nocturne-live-table__status i{width:6px;height:6px;border-radius:50%;background:var(--nocturne-green);box-shadow:0 0 8px var(--nocturne-green)}.nocturne-live-table__status.is-playing i{background:#f4b95e;box-shadow:0 0 8px #f4b95e}.nocturne-live-table h3{overflow:hidden;margin:10px 0;font-size:12px;font-weight:900;text-overflow:ellipsis;white-space:nowrap}.nocturne-live-table__meta{display:flex;align-items:center;justify-content:space-between;gap:5px;color:var(--nocturne-muted);font-size:9px}.nocturne-live-table__meta strong{color:var(--nocturne-text);font-size:10px}.nocturne-live-table__meta small{color:var(--nocturne-muted);font-size:8px}.nocturne-live-table form{margin:10px 0 0}.nocturne-live-table form button,.nocturne-live-table__button{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;min-height:34px;margin-top:10px;border:0;border-radius:10px;background:var(--nocturne-green);color:#062b1d;font-size:9px;font-weight:950;text-decoration:none}.nocturne-live-table form button{margin:0}@media(max-width:800px){.nocturne-live-tables__grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:420px){.nocturne-live-tables{padding:14px;border-radius:18px}.nocturne-live-tables__grid{gap:8px}.nocturne-live-table{padding:10px}.nocturne-live-table h3{font-size:11px}}
    </style>
@endif
