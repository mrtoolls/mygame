@php
    $typeMeta = [
        'general'   => ['label' => 'General', 'icon' => 'ph-chat-circle-text'],
        'win'       => ['label' => 'Win', 'icon' => 'ph-trophy'],
        'challenge' => ['label' => 'Challenge', 'icon' => 'ph-sword'],
        'question'  => ['label' => 'Question', 'icon' => 'ph-question'],
    ];
@endphp
@foreach ($posts as $post)
    @php $meta = $typeMeta[$post->type] ?? $typeMeta['general']; @endphp
    <article class="nocturne-arena-post">
        <div class="nocturne-arena-post__head">
            <span class="nocturne-arena-post__avatar">@if($post->user?->avatarUrl())<img src="{{ $post->user->avatarUrl() }}" alt="">@else{{ mb_strtoupper(mb_substr($post->user->username ?? '?', 0, 1)) }}@endif</span>
            <div class="nocturne-arena-post__identity">
                <strong>{{ $post->user->username ?? 'Deleted user' }}</strong>
                <small>{{ diffForHumans($post->created_at) }}</small>
            </div>
            <span class="nocturne-arena-post__type"><i class="ph-fill {{ $meta['icon'] }}"></i>{{ __($meta['label']) }}</span>
        </div>

        @if($post->body)
            @if(!$post->image && $post->background_style)
                <div class="nocturne-arena-post__colored nocturne-arena-post__colored--{{ $post->background_style }}"><p>{{ $post->body }}</p></div>
            @else
                <p class="nocturne-arena-post__body">{{ $post->body }}</p>
            @endif
        @endif

        @if($post->image)
            <div class="nocturne-arena-post__image"><img src="{{ $post->imageUrl() }}" alt="" loading="lazy"></div>
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
    </article>
@endforeach
