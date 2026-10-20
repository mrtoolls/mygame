@php
    $reviewContent = getContent('about_reviews.content', true)?->data_values;
    $reviews = getContent('about_reviews.element', orderById: true);
    $playerFeedback = \Illuminate\Support\Facades\Schema::hasTable('user_feedback') ? \App\Models\UserFeedback::approved()->with('user:id,username,image')->latest('approved_at')->take(6)->get() : collect();
@endphp
<section class="nocturne-about-section">
    <div class="nocturne-about-section-title nocturne-about-section-title--action"><span><i class="ph-fill ph-chats-teardrop"></i><h2>{{ __($reviewContent->heading ?? 'Reviews') }}</h2></span>@if(!empty($reviewContent->button_name))<a class="nocturne-about-view-all" href="{{ $reviewContent->button_url ?: '#' }}">{{ __($reviewContent->button_name) }} <i class="ph-bold ph-caret-right"></i></a>@endif</div>
    <div class="nocturne-about-review-grid">
        @foreach($reviews as $review)
            <article class="nocturne-about-review-card">
                <header>
                    <span class="nocturne-about-review-card__avatar">@if(!empty($review->data_values->avatar_image))<img src="{{ frontendImage('about_reviews', $review->data_values->avatar_image, '96x96') }}" alt="">@else<i class="ph-fill ph-user"></i>@endif</span>
                    <span><strong>{{ __($review->data_values->name) }}</strong><small>{{ __($review->data_values->player_since) }}</small></span>
                </header>
                <div class="nocturne-about-stars" aria-label="5 out of 5 stars">★★★★★</div>
                <p>{{ __($review->data_values->quote) }}</p>
                @if(!empty($review->data_values->review_image))<img class="nocturne-about-review-card__proof" src="{{ frontendImage('about_reviews', $review->data_values->review_image, '240x180') }}" alt="Player review attachment">@endif
            </article>
        @endforeach
        @foreach($playerFeedback as $review)
            <article class="nocturne-about-review-card"><header><span class="nocturne-about-review-card__avatar">@if($review->user?->avatarUrl())<img src="{{ $review->user->avatarUrl() }}" alt="">@else<i class="ph-fill ph-user"></i>@endif</span><span><strong>{{ $review->display_name }}</strong><small>{{ $review->player_since ? __('Player since :year',['year'=>$review->player_since]) : __('Winxpot player') }}</small></span></header><div class="nocturne-about-stars">{{ str_repeat('★',(int)$review->rating) }}{{ str_repeat('☆',5-(int)$review->rating) }}</div><p>{{ $review->message }}</p>@if($review->image)<img class="nocturne-about-review-card__proof" src="{{ getImage('assets/images/user-feedback/'.$review->image) }}" alt="@lang('Player review attachment')">@endif</article>
        @endforeach
    </div>
</section>
