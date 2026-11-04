{{-- Fantasy Football is a standalone season-long game, not a row in the games table (no
single-round stake, so no separate demo mode is needed - the whole game is free to play; only
its optional coin contests involve a stake), but it's shown first here and everywhere else the
original games are listed, matching their card style exactly. --}}
<div class="game-item">
    <div class="game-item__image">
        <img src="{{ asset('assets/images/frontend/fantasy-football/thumbnail.jpg') }}" alt="{{ __('Fantasy Football') }}" loading="lazy" decoding="async">
        <div class="game-item__play">
            <a href="{{ route('user.fantasy.team') }}" class="nocturne-play-btn nocturne-play-btn--play" aria-label="@lang('Play')"><i class="ph-bold ph-play" aria-hidden="true"></i><span>@lang('Play')</span></a>
            <a href="{{ route('user.fantasy.team') }}" class="nocturne-play-btn nocturne-play-btn--demo" aria-label="@lang('Demo')"><i class="ph-bold ph-eye" aria-hidden="true"></i><span>@lang('Demo')</span></a>
        </div>
    </div>
    <h4 class="game-item__title">@lang('Fantasy Football')</h4>
</div>
@foreach ($games as $game)
    <div class="game-item">
        <div class="game-item__image">
            <img src="{{ getImage(getFilePath('game') . '/' . $game->image, getFileSize('game')) }}" alt="{{ __($game->name) }}" loading="lazy" decoding="async">
            <div class="game-item__play">
                <a href="{{ route('user.play.game', $game->alias) }}" class="nocturne-play-btn nocturne-play-btn--play" aria-label="@lang('Play')"><i class="ph-bold ph-play" aria-hidden="true"></i><span>@lang('Play')</span></a>
                <a href="{{ route('user.play.game', [$game->alias, 'demo']) }}" class="nocturne-play-btn nocturne-play-btn--demo" aria-label="@lang('Demo')"><i class="ph-bold ph-eye" aria-hidden="true"></i><span>@lang('Demo')</span></a>
            </div>
        </div>
        <h4 class="game-item__title">{{ __($game->name) }}</h4>
    </div>
@endforeach
