@php
    $homeNews = \App\Models\NewsPost::published()->latest()->limit(8)->get();
@endphp
@if ($homeNews->count())
    <section class="nocturne-game-row">
        <div class="nocturne-game-row__head">
            <h2><i class="ph-bold ph-newspaper"></i> @lang('Latest News')</h2>
            <a href="{{ route('blog') }}">@lang('View all') <i class="ph-bold ph-caret-right"></i></a>
        </div>
        <div class="nocturne-game-scroller nocturne-news-scroller">
            @foreach ($homeNews as $post)
                <a href="{{ route('blog.details', $post->slug) }}" class="nocturne-news-card">
                    <span class="nocturne-news-card__image"><img src="{{ $post->imageUrl() }}" alt="{{ __($post->title) }}" loading="lazy" decoding="async"></span>
                    <span class="nocturne-news-card__body">
                        <strong>{{ __($post->title) }}</strong>
                        @if($post->excerpt)<small>{{ __($post->excerpt) }}</small>@endif
                    </span>
                </a>
            @endforeach
        </div>
    </section>
@endif
