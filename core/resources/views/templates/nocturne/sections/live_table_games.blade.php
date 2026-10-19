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
    <section class="nocturne-live-tables" data-live-carousel>
        <div class="nocturne-live-tables__head">
            <div><span>@lang('LIVE GAMES · HAVE FUN WITH FRIENDS')</span><h2>@lang('Your table, your people')</h2><p>@lang('Pick a game, invite your friends, and play for free or choose a stake together.')</p></div>
            <div class="nocturne-live-tables__controls">
                <a href="{{ auth()->check() ? route('user.tables.index') : route('user.login') }}">@lang('Open live games') <i class="ph-bold ph-arrow-right"></i></a>
                <button type="button" data-carousel-prev aria-label="@lang('Previous live games')"><i class="ph-bold ph-caret-left"></i></button>
                <button type="button" data-carousel-next aria-label="@lang('Next live games')"><i class="ph-bold ph-caret-right"></i></button>
            </div>
        </div>
        <div class="nocturne-live-tables__rail" data-carousel-track tabindex="0" aria-label="@lang('Available live games')">
            @foreach ($liveGameCatalog as $index => $game)
                <article class="nocturne-live-table nocturne-live-table--{{ $index % 5 }}">
                    <div class="nocturne-live-table__art" aria-label="@lang('Game image placeholder')">
                        <span class="nocturne-live-table__art-glow"></span>
                        <i class="ph-fill {{ ['ph-dice-five', 'ph-cards', 'ph-spade', 'ph-circles-three-plus', 'ph-game-controller'][($index % 5)] }}"></i>
                        <small>@lang('GAME IMAGE HERE')</small>
                        <span class="nocturne-live-table__live"><i></i>@lang('LIVE TABLES')</span>
                    </div>
                    <div class="nocturne-live-table__body">
                        <div class="nocturne-live-table__title"><div><small>@lang('PLAY TOGETHER')</small><h3>{{ __($game['name']) }}</h3></div><span class="nocturne-live-table__players"><i class="ph-fill ph-users-three"></i>{{ $game['min_players'] }}–{{ $game['max_players'] }}</span></div>
                        <p>@lang('Invite friends or find a table and play at your own pace.')</p>
                        <div class="nocturne-live-table__meta"><span><i class="ph-fill ph-sparkle"></i>@lang('Free play available')</span><span>@lang('Private tables')</span></div>
                        <a class="nocturne-live-table__button" href="{{ auth()->check() ? route('user.tables.index') : route('user.login') }}">@lang('Find a table') <i class="ph-bold ph-arrow-up-right"></i></a>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="nocturne-live-tables__progress" aria-hidden="true"><span></span></div>
    </section>
    @push('script')
        <script>
            (() => {
                document.querySelectorAll('[data-live-carousel]').forEach((carousel) => {
                    const track = carousel.querySelector('[data-carousel-track]');
                    const items = [...track.children];
                    const progress = carousel.querySelector('.nocturne-live-tables__progress span');
                    const step = () => {
                        const first = items[0];
                        return first ? first.getBoundingClientRect().width + parseFloat(getComputedStyle(track).columnGap || getComputedStyle(track).gap || 0) : 0;
                    };
                    const update = () => {
                        const max = track.scrollWidth - track.clientWidth;
                        progress.style.width = max > 0 ? `${Math.min(100, (track.scrollLeft / max) * 100)}%` : '100%';
                    };
                    carousel.querySelector('[data-carousel-prev]').addEventListener('click', () => track.scrollBy({ left: -step(), behavior: 'smooth' }));
                    carousel.querySelector('[data-carousel-next]').addEventListener('click', () => track.scrollBy({ left: step(), behavior: 'smooth' }));
                    track.addEventListener('scroll', update, { passive: true });
                    let timer;
                    const start = () => {
                        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || items.length < 2) return;
                        timer = window.setInterval(() => {
                            if (carousel.matches(':hover') || carousel.contains(document.activeElement)) return;
                            const max = track.scrollWidth - track.clientWidth;
                            track.scrollTo({ left: track.scrollLeft >= max - 4 ? 0 : track.scrollLeft + step(), behavior: 'smooth' });
                        }, 4800);
                    };
                    const stop = () => window.clearInterval(timer);
                    carousel.addEventListener('mouseenter', stop);
                    carousel.addEventListener('mouseleave', start);
                    carousel.addEventListener('focusin', stop);
                    carousel.addEventListener('focusout', start);
                    window.addEventListener('resize', update, { passive: true });
                    update(); start();
                });
            })();
        </script>
    @endpush
@endif
