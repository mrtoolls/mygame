@php
    $newsContent = getContent('about_news.content', true)?->data_values;
    $news = getContent('about_news.element', orderById: true)->take(3);
    // Admin-curated cards (above) are optional highlight picks - fall back to real published
    // posts so this section never just sits empty when no cards have been curated yet.
    $remaining = max(0, 3 - $news->count());
    $latestPosts = $remaining > 0 ? \App\Models\NewsPost::published()->latest()->limit($remaining)->get() : collect();
@endphp
<section class="nocturne-about-section">
    <div class="nocturne-about-section-title nocturne-about-section-title--action"><span><i class="ph-fill ph-newspaper"></i><h2>{{ __($newsContent->heading ?? 'Latest News') }}</h2></span>@if(!empty($newsContent->button_name))<a class="nocturne-about-view-all" href="{{ $newsContent->button_url ?: '#' }}">{{ __($newsContent->button_name) }} <i class="ph-bold ph-caret-right"></i></a>@endif</div>
    @if($news->isNotEmpty() || $latestPosts->isNotEmpty())
        <div class="nocturne-about-news-grid">
            @foreach($news as $item)
                <a class="nocturne-about-news-card" href="{{ $item->data_values->button_url ?: '#' }}">
                    <span class="nocturne-about-news-card__image">@if(!empty($item->data_values->news_image))<img src="{{ frontendImage('about_news', $item->data_values->news_image, '500x300') }}" alt="">@else<i class="ph-bold ph-image"></i><small>News image here</small>@endif</span>
                    <span class="nocturne-about-news-card__copy"><strong>{{ __($item->data_values->title) }}</strong><span>{{ __($item->data_values->description) }}</span><b>Read More <i class="ph-bold ph-arrow-right"></i></b></span>
                </a>
            @endforeach
            @foreach($latestPosts as $post)
                <a class="nocturne-about-news-card" href="{{ route('blog.details', $post->slug) }}">
                    <span class="nocturne-about-news-card__image"><img src="{{ $post->imageUrl() }}" alt="{{ __($post->title) }}" loading="lazy"></span>
                    <span class="nocturne-about-news-card__copy"><strong>{{ __($post->title) }}</strong><span>{{ __($post->excerpt) }}</span><b>Read More <i class="ph-bold ph-arrow-right"></i></b></span>
                </a>
            @endforeach
        </div>
    @endif
</section>
