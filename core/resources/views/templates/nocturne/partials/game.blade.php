@foreach ($games as $game)
    <div class="game-item">
        <div class="game-item__image">
            <img src="{{ getImage(getFilePath('game') . '/' . $game->image, getFileSize('game')) }}" alt="{{ __($game->name) }}" width="400" height="534" loading="lazy" decoding="async">
            <div class="game-item__play">
                <a href="{{ route('user.play.game', $game->alias) }}" class="nocturne-play-btn nocturne-play-btn--play" aria-label="@lang('Play')"><i class="ph-bold ph-play" aria-hidden="true"></i><span>@lang('Play')</span></a>
                <a href="{{ route('user.play.game', [$game->alias, 'demo']) }}" class="nocturne-play-btn nocturne-play-btn--demo" aria-label="@lang('Demo')"><i class="ph-bold ph-eye" aria-hidden="true"></i><span>@lang('Demo')</span></a>
            </div>
        </div>
        <h4 class="game-item__title">{{ __($game->name) }}</h4>
    </div>
@endforeach
