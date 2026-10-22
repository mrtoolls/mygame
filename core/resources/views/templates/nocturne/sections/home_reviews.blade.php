@php
    $homeReviews = \Illuminate\Support\Facades\Schema::hasTable('user_feedback')
        ? \App\Models\UserFeedback::approved()->with('user:id,username,image')->latest('approved_at')->limit(8)->get()
        : collect();
@endphp
@if ($homeReviews->count())
    <section class="nocturne-game-row">
        <div class="nocturne-game-row__head">
            <h2><i class="ph-bold ph-chats-teardrop"></i> @lang('Player Reviews')</h2>
            <a href="{{ route('feedback') }}">@lang('View all') <i class="ph-bold ph-caret-right"></i></a>
        </div>
        <div class="nocturne-game-scroller nocturne-review-scroller">
            @foreach ($homeReviews as $review)
                <article class="nocturne-review-card">
                    <div class="nocturne-review-card__head">
                        <span class="nocturne-review-card__avatar">
                            @if($review->user?->avatarUrl())
                                <img src="{{ $review->user->avatarUrl() }}" alt="" loading="lazy" decoding="async" width="40" height="40">
                            @else
                                <i class="ph-fill ph-user"></i>
                            @endif
                        </span>
                        <span>
                            <strong>{{ $review->display_name }}</strong>
                            <small>{{ $review->player_since ? __('Player since :year', ['year' => $review->player_since]) : __('Winxpot player') }}</small>
                        </span>
                    </div>
                    <div class="nocturne-review-card__stars" aria-label="{{ $review->rating }} @lang('out of 5 stars')">{{ str_repeat('★', (int) $review->rating) }}<span>{{ str_repeat('★', 5 - (int) $review->rating) }}</span></div>
                    <p>{{ \Illuminate\Support\Str::limit($review->message, 140) }}</p>
                </article>
            @endforeach
        </div>
    </section>
@endif
