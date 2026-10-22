@php
    $typeMeta = [
        'general'   => ['label' => 'General', 'icon' => 'ph-chat-circle-text'],
        'win'       => ['label' => 'Win', 'icon' => 'ph-trophy'],
        'challenge' => ['label' => 'Challenge', 'icon' => 'ph-sword'],
        'question'  => ['label' => 'Question', 'icon' => 'ph-question'],
    ];

    // Batch the current viewer's votes, follows and every post's reaction counts
    // into a handful of queries total, instead of per-post N+1 queries.
    $postIds = $posts->pluck('id');
    $myVotes = auth()->check()
        ? \App\Models\ArenaPostVote::whereIn('post_id', $postIds)->where('user_id', auth()->id())->pluck('value', 'post_id')
        : collect();
    $reactionRows = \App\Models\ArenaReaction::where('reactable_type', 'post')->whereIn('reactable_id', $postIds)->get()->groupBy('reactable_id');
    $authorIds = $posts->pluck('user_id')->unique();
    $myFollowedIds = auth()->check()
        ? \App\Models\ArenaFollow::where('follower_id', auth()->id())->whereIn('followed_id', $authorIds)->pluck('followed_id')->all()
        : [];
@endphp
@foreach ($posts as $post)
    @php
        $meta = $typeMeta[$post->type] ?? $typeMeta['general'];
        $myVote = $myVotes[$post->id] ?? null;
        $rows = $reactionRows->get($post->id, collect());
        $reactionCounts = $rows->groupBy('emoji')->map->count();
        $myReaction = auth()->check() ? $rows->firstWhere('user_id', auth()->id())?->emoji : null;
        $isOwnPost = auth()->check() && auth()->id() === $post->user_id;
        $isFollowingAuthor = in_array($post->user_id, $myFollowedIds, true);
    @endphp
    <article class="nocturne-arena-post" data-post-id="{{ $post->id }}">
        <div class="nocturne-arena-post__head">
            <a href="{{ route('arena.profile', $post->user->username ?? '') }}" class="nocturne-arena-post__avatar">@if($post->user?->avatarUrl())<img src="{{ $post->user->avatarUrl() }}" alt="">@else{{ mb_strtoupper(mb_substr($post->user->username ?? '?', 0, 1)) }}@endif</a>
            <div class="nocturne-arena-post__identity">
                <a href="{{ route('arena.profile', $post->user->username ?? '') }}"><strong>{{ $post->user->username ?? 'Deleted user' }}</strong></a>
                <small>{{ diffForHumans($post->created_at) }}</small>
            </div>
            @if(auth()->check() && !$isOwnPost && $post->user)
                <button type="button" class="nocturne-arena-mini-follow {{ $isFollowingAuthor ? 'is-following' : '' }}" data-user-id="{{ $post->user_id }}" data-following="{{ $isFollowingAuthor ? '1' : '0' }}">
                    <span class="label-follow">@lang('Follow')</span><span class="label-following">@lang('Following')</span>
                </button>
            @endif
            <span class="nocturne-arena-post__type"><i class="ph-fill {{ $meta['icon'] }}"></i>{{ __($meta['label']) }}</span>
        </div>

        @if($post->body)
            @if(!$post->image && !$post->video && $post->background_style)
                <div class="nocturne-arena-post__colored nocturne-arena-post__colored--{{ $post->background_style }}"><p>{{ $post->body }}</p></div>
            @else
                <div class="nocturne-arena-post__body-wrap"><p class="nocturne-arena-post__body" data-seemore>{{ $post->body }}</p><button type="button" class="nocturne-arena-seemore" hidden>@lang('See more')</button></div>
            @endif
        @endif

        @if($post->image)
            <div class="nocturne-arena-post__image"><img src="{{ $post->imageUrl() }}" alt="" loading="lazy"></div>
        @elseif($post->video)
            <div class="nocturne-arena-post__video"><video src="{{ $post->videoUrl() }}" controls preload="metadata" playsinline></video></div>
        @endif

        @if($post->linkedGameLog)
            <div class="nocturne-arena-post__win">
                <i class="ph-fill ph-trophy"></i>
                <div>
                    <strong>{{ showAmount($post->linkedGameLog->win_amo) }} {{ __('won on') }} {{ __($post->linkedGameLog->game->name ?? 'a game') }}</strong>
                    <small>{{ __('Staked') }} {{ showAmount($post->linkedGameLog->invest) }}</small>
                </div>
            </div>
        @endif

        <div class="nocturne-arena-post__bar">
            <div class="nocturne-arena-vote {{ auth()->check() ? '' : 'is-disabled' }}">
                <button type="button" class="nocturne-arena-vote__btn nocturne-arena-vote__btn--up {{ $myVote === 1 ? 'is-active' : '' }}" data-vote="1" {{ auth()->check() ? '' : 'disabled' }}><i class="ph-fill ph-arrow-fat-up"></i></button>
                <span class="nocturne-arena-vote__score">{{ $post->upvotes_count - $post->downvotes_count }}</span>
                <button type="button" class="nocturne-arena-vote__btn nocturne-arena-vote__btn--down {{ $myVote === -1 ? 'is-active' : '' }}" data-vote="-1" {{ auth()->check() ? '' : 'disabled' }}><i class="ph-fill ph-arrow-fat-down"></i></button>
            </div>

            <div class="nocturne-arena-reactions {{ auth()->check() ? '' : 'is-disabled' }}" data-reactable-type="post" data-reactable-id="{{ $post->id }}">
                @foreach(\App\Models\ArenaReaction::EMOJIS as $emoji)
                    <button type="button" class="nocturne-arena-reaction {{ $myReaction === $emoji ? 'is-active' : '' }}" data-emoji="{{ $emoji }}" {{ auth()->check() ? '' : 'disabled' }}>{{ $emoji }}<small class="{{ ($reactionCounts[$emoji] ?? 0) > 0 ? '' : 'is-zero' }}">{{ $reactionCounts[$emoji] ?? 0 }}</small></button>
                @endforeach
            </div>

            <button type="button" class="nocturne-arena-comment-toggle" data-post-id="{{ $post->id }}"><i class="ph-bold ph-chat-circle"></i><span class="nocturne-arena-comment-count">{{ $post->comments_count }}</span></button>
        </div>

        <div class="nocturne-arena-comments-wrap" hidden></div>
    </article>
@endforeach
