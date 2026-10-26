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
    <section class="nocturne-live-tables" data-live-carousel aria-labelledby="live-table-games-title">
        <div class="nocturne-live-tables__hero">
            <div class="nocturne-live-tables__copy">
                <span class="nocturne-live-tables__eyebrow"><i class="ph-fill ph-broadcast" aria-hidden="true"></i>@lang('LIVE GAMES / HAVE FUN WITH FRIENDS')</span>
                <h2 id="live-table-games-title">@lang('Your table, your people')</h2>
                <p>@lang('Pick your game, bring your friends, and make the next move together. Play for fun or agree on a stake before you start.')</p>
                <div class="nocturne-live-tables__promises" aria-label="@lang('Live game options')">
                    <span><i class="ph-fill ph-users-three" aria-hidden="true"></i>@lang('Play with friends')</span>
                    <span><i class="ph-fill ph-sparkle" aria-hidden="true"></i>@lang('Free play available')</span>
                    <span><i class="ph-fill ph-lock-key" aria-hidden="true"></i>@lang('Private tables')</span>
                </div>
            </div>
            <div class="nocturne-live-tables__visual" aria-hidden="true">
                <span class="nocturne-live-tables__orbit nocturne-live-tables__orbit--outer"></span>
                <span class="nocturne-live-tables__orbit nocturne-live-tables__orbit--inner"></span>
                <span class="nocturne-live-tables__visual-die"><i class="ph-fill ph-dice-five"></i></span>
                <span class="nocturne-live-tables__visual-token nocturne-live-tables__visual-token--one"><i class="ph-fill ph-chess-knight"></i></span>
                <span class="nocturne-live-tables__visual-token nocturne-live-tables__visual-token--two"><i class="ph-fill ph-cards"></i></span>
                <span class="nocturne-live-tables__visual-spark"><i class="ph-fill ph-sparkle"></i></span>
            </div>
        </div>
        <div class="nocturne-live-tables__rail-head">
            <div><span>@lang('PICK YOUR GAME')</span><strong>@lang('Find your next table')</strong></div>
            <div class="nocturne-live-tables__controls">
                <a href="{{ auth()->check() ? route('user.tables.index') : route('user.login') }}">@lang('Explore live games') <i class="ph-bold ph-arrow-right"></i></a>
                <button type="button" data-carousel-prev aria-label="@lang('Previous live games')"><i class="ph-bold ph-caret-left"></i></button>
                <button type="button" data-carousel-next aria-label="@lang('Next live games')"><i class="ph-bold ph-caret-right"></i></button>
            </div>
        </div>
        <div class="nocturne-live-tables__rail" data-carousel-track tabindex="0" aria-label="@lang('Available live games')">
            @foreach ($liveGameCatalog as $index => $game)
                <article class="nocturne-live-table nocturne-live-table--{{ $index % 5 }}">
                    <div class="nocturne-live-table__art">
                        @if (!empty($game['card_image']))
                            <img src="{{ getImage(getFilePath('game') . '/' . $game['card_image'], getFileSize('game')) }}" alt="{{ __($game['name']) }}" loading="lazy" decoding="async">
                        @else
                            <span class="nocturne-live-table__art-glow"></span>
                            <i class="ph-fill {{ ['ph-dice-five', 'ph-cards', 'ph-spade', 'ph-circles-three-plus', 'ph-game-controller'][($index % 5)] }}"></i>
                        @endif
                        <span class="nocturne-live-table__live"><i></i>@lang('LIVE TABLES')</span>
                    </div>
                    <div class="nocturne-live-table__body">
                        <div class="nocturne-live-table__title"><div><small>@lang('PLAY TOGETHER')</small><h3>{{ __($game['name']) }}</h3></div><span class="nocturne-live-table__players"><i class="ph-fill ph-users-three"></i>{{ $game['min_players'] }}-{{ $game['max_players'] }}</span></div>
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
                    const previous = carousel.querySelector('[data-carousel-prev]');
                    const next = carousel.querySelector('[data-carousel-next]');
                    const step = () => {
                        const first = items[0];
                        return first ? first.getBoundingClientRect().width + parseFloat(getComputedStyle(track).columnGap || getComputedStyle(track).gap || 0) : 0;
                    };
                    const update = () => {
                        const max = track.scrollWidth - track.clientWidth;
                        progress.style.width = max > 0 ? `${Math.min(100, (track.scrollLeft / max) * 100)}%` : '100%';
                        previous.disabled = track.scrollLeft <= 2;
                        next.disabled = max <= 0 || track.scrollLeft >= max - 2;
                    };
                    previous.addEventListener('click', () => track.scrollBy({ left: -step(), behavior: 'smooth' }));
                    next.addEventListener('click', () => track.scrollBy({ left: step(), behavior: 'smooth' }));
                    track.addEventListener('scroll', update, { passive: true });
                    window.addEventListener('resize', update, { passive: true });
                    update();
                });
            })();
        </script>
    @endpush
@endif
