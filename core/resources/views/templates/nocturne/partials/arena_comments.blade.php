@auth
    <form class="nocturne-arena-comment-form" data-post-id="{{ $post->id }}">
        <span class="nocturne-arena-comment__avatar">@if(auth()->user()->avatarUrl())<img src="{{ auth()->user()->avatarUrl() }}" alt="">@else{{ mb_strtoupper(mb_substr(auth()->user()->username, 0, 1)) }}@endif</span>
        <input type="text" name="body" maxlength="1000" placeholder="@lang('Write a comment...')" required>
        <button type="submit"><i class="ph-bold ph-paper-plane-tilt"></i></button>
    </form>
@endauth
<div class="nocturne-arena-comment-list" data-post-id="{{ $post->id }}">
    @forelse($comments as $comment)
        @include('Template::partials.arena_comment_item', ['comment' => $comment, 'post' => $post])
    @empty
        <p class="nocturne-arena-comment-empty">@lang('No comments yet. Be the first to say something.')</p>
    @endforelse
</div>
