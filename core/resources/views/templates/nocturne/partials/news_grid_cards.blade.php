@foreach ($blogs as $post)
    <article class="nocturne-news-card">
        <a href="{{ route('blog.details', $post->slug) }}" class="nocturne-news-card__thumb">
            <img src="{{ $post->imageUrl() ?? getImage('assets/images/news/placeholder.jpg') }}" alt="{{ __($post->title) }}" loading="lazy">
        </a>
        <div class="nocturne-news-card__body">
            <h3><a href="{{ route('blog.details', $post->slug) }}">{{ __($post->title) }}</a></h3>
            @if($post->excerpt)<p>{{ __($post->excerpt) }}</p>@endif
            <a class="nocturne-news-card__more" href="{{ route('blog.details', $post->slug) }}">@lang('Read More')</a>
        </div>
    </article>
@endforeach
