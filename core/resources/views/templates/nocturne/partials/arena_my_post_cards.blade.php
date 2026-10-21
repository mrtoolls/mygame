@php
    $typeMeta = [
        'general'   => ['label' => 'General', 'icon' => 'ph-chat-circle-text'],
        'win'       => ['label' => 'Win', 'icon' => 'ph-trophy'],
        'challenge' => ['label' => 'Challenge', 'icon' => 'ph-sword'],
        'question'  => ['label' => 'Question', 'icon' => 'ph-question'],
    ];
    $statusMeta = [
        'pending'  => ['label' => 'Pending review', 'icon' => 'ph-hourglass-medium', 'cls' => 'pending'],
        'approved' => ['label' => 'Live', 'icon' => 'ph-check-circle', 'cls' => 'success'],
        'rejected' => ['label' => 'Rejected', 'icon' => 'ph-x-circle', 'cls' => 'reject'],
    ];
@endphp
@foreach ($posts as $post)
    @php
        $meta = $typeMeta[$post->type] ?? $typeMeta['general'];
        $status = $statusMeta[$post->status] ?? $statusMeta['pending'];
    @endphp
    <article class="nocturne-arena-post">
        <div class="nocturne-arena-post__head">
            <span class="nocturne-arena-post__type"><i class="ph-fill {{ $meta['icon'] }}"></i>{{ __($meta['label']) }}</span>
            <small style="margin-left:auto;color:var(--arena-muted);font-size:11px">{{ diffForHumans($post->created_at) }}</small>
            <span class="nocturne-arena-my-status nocturne-arena-my-status--{{ $status['cls'] }}"><i class="ph-fill {{ $status['icon'] }}"></i>{{ __($status['label']) }}</span>
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

        @if($post->status === 'rejected' && $post->rejection_reason)
            <div class="nocturne-arena-my-reason"><i class="ph-fill ph-info"></i><div><span>@lang('Reason')</span><p>{{ $post->rejection_reason }}</p></div></div>
        @elseif($post->status === 'pending')
            <p class="nocturne-arena-my-note">@lang("This post is only visible to you until a moderator reviews it.")</p>
        @endif
    </article>
@endforeach
