@extends('Template::layouts.frontend')
@section('content')
    <div class="nocturne-arena-page">
        <div class="nocturne-arena-crumb"><a href="{{ route('arena.index') }}">@lang('Arena')</a><i class="ph-bold ph-caret-right"></i><span>{{ $profileUser->username }}</span></div>

        <div class="nocturne-arena-profile-card">
            <span class="nocturne-arena-post__avatar nocturne-arena-profile-card__avatar">@if($profileUser->avatarUrl())<img src="{{ $profileUser->avatarUrl() }}" alt="">@else{{ mb_strtoupper(mb_substr($profileUser->username, 0, 1)) }}@endif</span>
            <div class="nocturne-arena-profile-card__info">
                <h1>{{ $profileUser->username }}</h1>
                @if($currentTier)<span class="nocturne-arena-profile-card__tier"><i class="ph-fill ph-crown-simple"></i>{{ __($currentTier->name) }}</span>@endif
                <div class="nocturne-arena-profile-card__stats">
                    <span><strong id="arena-followers-count">{{ $followers }}</strong> @lang('Followers')</span>
                    <span><strong>{{ $following }}</strong> @lang('Following')</span>
                </div>
            </div>
            @auth
                @if(auth()->id() !== $profileUser->id)
                    <button type="button" class="nocturne-arena-follow-btn {{ $isFollowing ? 'is-following' : '' }}" id="arena-profile-follow-btn" data-user-id="{{ $profileUser->id }}" data-following="{{ $isFollowing ? '1' : '0' }}">
                        <span class="label-follow"><i class="ph-bold ph-user-plus"></i>@lang('Follow')</span>
                        <span class="label-following"><i class="ph-bold ph-check"></i>@lang('Following')</span>
                    </button>
                @endif
            @endauth
        </div>

        <div class="nocturne-arena-list" id="arena-feed">
            @if($posts->isEmpty())
                <div class="nocturne-arena-empty"><i class="ph-fill ph-chats-circle"></i><h3>@lang('No posts yet')</h3></div>
            @else
                @include('Template::partials.arena_post_cards')
            @endif
        </div>

        @if($posts->hasMorePages())
            <div class="nocturne-arena-loadmore">
                <button type="button" id="arena-load-more" data-next-page="{{ $posts->currentPage() + 1 }}" data-url="{{ route('arena.profile', $profileUser->username) }}">@lang('Load More')</button>
            </div>
        @endif
    </div>
@endsection

@push('style')
<style>
:root{--arena-accent:#00e78a;--arena-bg:#10152a;--arena-panel:#151a31;--arena-raised:#1b2140;--arena-muted:#858eaf;--arena-line:rgba(133,142,175,.18);--arena-text:#f5f6ff}
{{-- Shared post-card styling (card shell, vote/reaction/comment bar, media, lightbox, menu) -
     this page renders the same arena_post_cards partial as the main feed but previously had
     none of the matching CSS, so posts here showed up completely unstyled. --}}
@include('Template::partials.arena_post_card_styles')
.nocturne-arena-list{display:grid;gap:14px}
.nocturne-arena-page{max-width:760px;margin:0 auto;padding:24px 20px 60px}
.nocturne-arena-crumb{display:flex;align-items:center;gap:7px;margin-bottom:18px;color:var(--arena-muted);font-size:13px;font-weight:700}
.nocturne-arena-crumb a{color:var(--arena-muted);text-decoration:none}
.nocturne-arena-crumb span{color:var(--arena-text)}
.nocturne-arena-crumb i{font-size:11px;opacity:.6}

.nocturne-arena-profile-card{display:flex;align-items:center;gap:16px;margin-bottom:22px;padding:20px;border:1px solid var(--arena-line);border-radius:20px;background:linear-gradient(155deg,#151a31,#10152a 78%);box-shadow:0 14px 34px rgba(0,0,0,.16)}
.nocturne-arena-profile-card__avatar{flex:0 0 64px;width:64px;height:64px;font-size:24px}
.nocturne-arena-profile-card__info{flex:1;min-width:0}
.nocturne-arena-profile-card__info h1{margin:0 0 5px;color:var(--arena-text);font-size:19px;font-weight:850}
.nocturne-arena-profile-card__tier{display:inline-flex;align-items:center;gap:5px;margin-bottom:8px;padding:4px 10px;border-radius:999px;background:rgba(0,231,138,.12);color:var(--arena-accent);font-size:11px;font-weight:800}
.nocturne-arena-profile-card__stats{display:flex;gap:16px}
.nocturne-arena-profile-card__stats span{color:var(--arena-muted);font-size:12.5px}
.nocturne-arena-profile-card__stats strong{color:var(--arena-text);font-weight:800}

.nocturne-arena-follow-btn{flex:0 0 auto;padding:10px 18px;border:0;border-radius:11px;background:var(--arena-accent);color:#062a23;font-size:13px;font-weight:800;cursor:pointer}
.nocturne-arena-follow-btn .label-following{display:none}
.nocturne-arena-follow-btn.is-following{background:var(--arena-bg);color:var(--arena-text);border:1px solid var(--arena-line)}
.nocturne-arena-follow-btn.is-following .label-follow{display:none}
.nocturne-arena-follow-btn.is-following .label-following{display:inline-flex;align-items:center;gap:6px}
.nocturne-arena-follow-btn .label-follow{display:inline-flex;align-items:center;gap:6px}

.nocturne-arena-post__avatar{display:grid;place-items:center;border-radius:50%;background:rgba(0,231,138,.12);color:var(--arena-accent);font-weight:800;overflow:hidden}
.nocturne-arena-post__avatar img{width:100%;height:100%;object-fit:cover}

.nocturne-arena-empty{display:grid;justify-items:center;gap:8px;padding:50px 20px;border:1px dashed var(--arena-line);border-radius:18px;color:var(--arena-muted);text-align:center}
.nocturne-arena-empty i{color:var(--arena-accent);font-size:28px}
.nocturne-arena-empty h3{margin:0;color:var(--arena-text);font-size:16px}
.nocturne-arena-loadmore{display:flex;justify-content:center;margin-top:20px}
#arena-load-more{padding:12px 26px;border:1px solid var(--arena-line);border-radius:14px;background:var(--arena-panel);color:var(--arena-text);font-size:13px;font-weight:800;cursor:pointer}
#arena-load-more:hover{border-color:rgba(0,231,138,.4);color:var(--arena-accent)}
#arena-load-more:disabled{opacity:.5;cursor:default}

@media(max-width:575.98px){
    /* This page had no mobile padding override at all, so it fell back to the desktop
       20px side padding on top of the shell's own 12px - 32px total, the worst case of
       this bug on any Arena page. */
    .nocturne-arena-page{padding:16px 4px 40px}
    .nocturne-arena-profile-card{padding:16px}
    .nocturne-arena-post{padding:14px}
}
</style>
@endpush

@push('script')
<script>
"use strict";
(function($) {
    $('#arena-profile-follow-btn').on('click', function() {
        var btn = $(this);
        var userId = btn.data('user-id');
        var following = btn.data('following') == '1';
        var url = "{{ route('user.arena.follow.store', ['user' => '__ID__']) }}".replace('__ID__', userId);
        $.ajax({
            url: url,
            method: following ? 'DELETE' : 'POST',
            data: { _token: "{{ csrf_token() }}" }
        }).done(function(res) {
            btn.toggleClass('is-following', res.following).data('following', res.following ? '1' : '0');
            $('#arena-followers-count').text(res.followers);
        });
    });

    $('#arena-load-more').on('click', function() {
        var btn = $(this);
        var page = btn.data('next-page');
        btn.prop('disabled', true).text("{{ __('Loading…') }}");
        $.ajax({ url: btn.data('url'), data: { page: page }, headers: { 'Accept': 'application/json' } })
            .done(function(res) {
                $('#arena-feed').append(res.html);
                setupSeeMore(document.getElementById('arena-feed'));
                window.arenaObserveFeedVideos && window.arenaObserveFeedVideos(document.getElementById('arena-feed'));
                if (res.hasMore) { btn.data('next-page', page + 1).prop('disabled', false).text("{{ __('Load More') }}"); }
                else { btn.remove(); }
            })
            .fail(function() { btn.prop('disabled', false).text("{{ __('Load More') }}"); });
    });

    @include('Template::partials.arena_post_card_scripts')
})(jQuery);
</script>
@endpush
