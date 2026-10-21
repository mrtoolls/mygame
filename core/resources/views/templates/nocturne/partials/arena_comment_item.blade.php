@php $isReply = (bool) $comment->parent_id; @endphp
<div class="nocturne-arena-comment {{ $isReply ? 'nocturne-arena-comment--reply' : '' }}" data-comment-id="{{ $comment->id }}">
    <span class="nocturne-arena-comment__avatar">@if($comment->user?->avatarUrl())<img src="{{ $comment->user->avatarUrl() }}" alt="">@else{{ mb_strtoupper(mb_substr($comment->user->username ?? '?', 0, 1)) }}@endif</span>
    <div class="nocturne-arena-comment__body">
        <div class="nocturne-arena-comment__bubble">
            <strong>{{ $comment->user->username ?? 'Deleted user' }}</strong>
            <p>{{ $comment->body }}</p>
        </div>
        <div class="nocturne-arena-comment__meta">
            <span>{{ diffForHumans($comment->created_at) }}</span>
            @if(!$isReply)
                <button type="button" class="nocturne-arena-comment__reply-btn" data-reply-to="{{ $comment->id }}">@lang('Reply')</button>
            @endif
        </div>
        @if(!$isReply)
            <div class="nocturne-arena-comment__replies" data-replies-for="{{ $comment->id }}">
                @foreach($comment->replies ?? [] as $reply)
                    @include('Template::partials.arena_comment_item', ['comment' => $reply, 'post' => $post])
                @endforeach
            </div>
        @endif
    </div>
</div>
