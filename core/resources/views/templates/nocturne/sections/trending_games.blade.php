@php
    $content = getContent('trending_games.content', true);
    $trendingGames = \App\Models\Game::active()->where('trending', \App\Constants\Status::YES)->get();
@endphp
@if ($trendingGames->count())
    <section class="nocturne-game-row">
        <div class="nocturne-game-row__head">
            <h2><i class="ph-bold ph-fire"></i> {{ __(@$content->data_values->heading ?: 'Trending Now') }}</h2>
            <a href="{{ route('games') }}">@lang('View all') <i class="ph-bold ph-caret-right"></i></a>
        </div>
        <div class="nocturne-game-scroller">
            @foreach ($trendingGames as $game)
                <div class="game-item nocturne-game-scroller__item">
                    <div class="game-item__image">
                        <img src="{{ getImage(getFilePath('game') . '/' . $game->image, getFileSize('game')) }}" alt="{{ __($game->name) }}">
                        <div class="game-item__play">
                            <a href="{{ route('user.play.game', $game->alias) }}" class="btn btn--gradient btn--sm">@lang('Play')</a>
                            <a href="{{ route('user.play.game', [$game->alias, 'demo']) }}" class="btn btn--gradient btn--sm">@lang('Demo')</a>
                        </div>
                    </div>
                    <h4 class="game-item__title">{{ __($game->name) }}</h4>
                    <span class="game-item__tag">@lang('Originals')</span>
                </div>
            @endforeach
        </div>
    </section>
@endif
