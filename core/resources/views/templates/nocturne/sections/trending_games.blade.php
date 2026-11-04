@php
    $content = getContent('trending_games.content', true);
    $trendingGames = \App\Models\Game::active()->where('trending', \App\Constants\Status::YES)->get();
@endphp
{{-- Fantasy Football always guarantees at least one card here, so this section is no longer
conditional on $trendingGames being non-empty. --}}
<section class="nocturne-game-row">
    <div class="nocturne-game-row__head">
        <h2><i class="ph-bold ph-fire"></i> {{ __(@$content->data_values->heading ?: 'Trending Now') }}</h2>
        <a href="{{ route('games') }}">@lang('View all') <i class="ph-bold ph-caret-right"></i></a>
    </div>
    <div class="nocturne-game-scroller">
        <div class="game-item nocturne-game-scroller__item">
            <div class="game-item__image">
                <img src="{{ asset('assets/images/frontend/fantasy-football/thumbnail.jpg') }}" alt="{{ __('Fantasy Football') }}" loading="lazy" decoding="async">
                <div class="game-item__play">
                    <a href="{{ route('user.fantasy.team') }}" class="nocturne-play-btn nocturne-play-btn--play" aria-label="@lang('Play')"><i class="ph-bold ph-play" aria-hidden="true"></i><span>@lang('Play')</span></a>
                    <a href="{{ route('user.fantasy.team') }}" class="nocturne-play-btn nocturne-play-btn--demo" aria-label="@lang('Demo')"><i class="ph-bold ph-eye" aria-hidden="true"></i><span>@lang('Demo')</span></a>
                </div>
            </div>
            <h4 class="game-item__title">@lang('Fantasy Football')</h4>
            <span class="game-item__tag">@lang('Originals')</span>
        </div>
        @foreach ($trendingGames as $game)
            <div class="game-item nocturne-game-scroller__item">
                <div class="game-item__image">
                    <img src="{{ getImage(getFilePath('game') . '/' . $game->image, getFileSize('game')) }}" alt="{{ __($game->name) }}" loading="lazy" decoding="async">
                    <div class="game-item__play">
                        <a href="{{ route('user.play.game', $game->alias) }}" class="nocturne-play-btn nocturne-play-btn--play" aria-label="@lang('Play')"><i class="ph-bold ph-play" aria-hidden="true"></i><span>@lang('Play')</span></a>
                        <a href="{{ route('user.play.game', [$game->alias, 'demo']) }}" class="nocturne-play-btn nocturne-play-btn--demo" aria-label="@lang('Demo')"><i class="ph-bold ph-eye" aria-hidden="true"></i><span>@lang('Demo')</span></a>
                    </div>
                </div>
                <h4 class="game-item__title">{{ __($game->name) }}</h4>
                <span class="game-item__tag">@lang('Originals')</span>
            </div>
        @endforeach
    </div>
</section>
