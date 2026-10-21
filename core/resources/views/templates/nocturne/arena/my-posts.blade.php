@extends('Template::layouts.frontend')
@section('content')
    <div class="nocturne-arena-page nocturne-arena-page--narrow">
        <div class="nocturne-arena-crumb"><a href="{{ route('arena.index') }}">@lang('Arena')</a><i class="ph-bold ph-caret-right"></i><span>@lang('My Posts')</span></div>

        <div class="nocturne-arena-list" id="arena-feed">
            @if($posts->isEmpty())
                <div class="nocturne-arena-empty"><i class="ph-fill ph-chats-circle"></i><h3>@lang("You haven't posted yet")</h3><p><a href="{{ route('arena.index') }}">@lang('Go to the Arena to post something')</a></p></div>
            @else
                @include('Template::partials.arena_my_post_cards')
            @endif
        </div>

        @if($posts->hasMorePages())
            <div class="nocturne-arena-loadmore">
                <button type="button" id="arena-load-more" data-next-page="{{ $posts->currentPage() + 1 }}" data-url="{{ route('user.arena.my-posts') }}">@lang('Load More')</button>
            </div>
        @endif
    </div>
@endsection

@push('style')
<style>
:root{--arena-accent:#00e78a;--arena-bg:#10152a;--arena-panel:#151a31;--arena-raised:#1b2140;--arena-muted:#858eaf;--arena-line:rgba(133,142,175,.18);--arena-text:#f5f6ff}
.nocturne-arena-page--narrow{max-width:720px;margin:0 auto;padding:24px 20px 60px}
.nocturne-arena-crumb{display:flex;align-items:center;gap:7px;margin-bottom:18px;color:var(--arena-muted);font-size:13px;font-weight:700}
.nocturne-arena-crumb a{color:var(--arena-muted);text-decoration:none}
.nocturne-arena-crumb a:hover{color:var(--arena-text)}
.nocturne-arena-crumb span{color:var(--arena-text)}
.nocturne-arena-crumb i{font-size:11px;opacity:.6}

.nocturne-arena-list{display:grid;gap:14px}
.nocturne-arena-post{padding:18px;border:1px solid var(--arena-line);border-radius:20px;background:linear-gradient(155deg,#151a31,#10152a 78%);box-shadow:0 14px 34px rgba(0,0,0,.14)}
.nocturne-arena-post__head{display:flex;align-items:center;gap:10px;margin-bottom:12px;flex-wrap:wrap}
.nocturne-arena-post__type{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:999px;background:rgba(133,142,175,.12);color:var(--arena-muted);font-size:10.5px;font-weight:800}
.nocturne-arena-post__body{margin:0 0 4px;color:#d7dafa;font-size:14px;line-height:1.65;white-space:pre-wrap;overflow-wrap:anywhere}
.nocturne-arena-post__image{margin-top:10px;border-radius:14px;overflow:hidden}
.nocturne-arena-post__image img{display:block;width:100%;max-height:420px;object-fit:cover}
.nocturne-arena-post__colored{display:grid;place-items:center;min-height:140px;padding:24px;border-radius:16px;text-align:center}
.nocturne-arena-post__colored p{margin:0;color:#fff;font-size:17px;font-weight:800;line-height:1.5;overflow-wrap:anywhere}
.nocturne-arena-post__colored--violet{background:linear-gradient(135deg,#7c3aed,#4c1d95)}
.nocturne-arena-post__colored--sunset{background:linear-gradient(135deg,#f97316,#be123c)}
.nocturne-arena-post__colored--forest{background:linear-gradient(135deg,#00e78a,#065f46)}
.nocturne-arena-post__colored--ocean{background:linear-gradient(135deg,#0ea5e9,#1e3a8a)}
.nocturne-arena-post__colored--rose{background:linear-gradient(135deg,#f472b6,#9d174d)}
.nocturne-arena-post__win{display:flex;align-items:center;gap:12px;margin-top:12px;padding:13px 15px;border:1px solid rgba(0,231,138,.22);border-radius:14px;background:rgba(0,231,138,.07)}
.nocturne-arena-post__win i{color:var(--arena-accent);font-size:22px}
.nocturne-arena-post__win strong{display:block;color:var(--arena-text);font-size:13.5px}
.nocturne-arena-post__win small{color:var(--arena-muted);font-size:11.5px}

.nocturne-arena-my-status{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:999px;font-size:10.5px;font-weight:800}
.nocturne-arena-my-status--pending{background:rgba(255,181,71,.14);color:#ffb547}
.nocturne-arena-my-status--success{background:rgba(0,231,138,.14);color:var(--arena-accent)}
.nocturne-arena-my-status--reject{background:rgba(255,92,122,.14);color:#ff5c7a}

.nocturne-arena-my-reason{display:flex;gap:10px;margin-top:12px;padding:13px 15px;border:1px solid rgba(255,92,122,.22);border-radius:14px;background:rgba(255,92,122,.07)}
.nocturne-arena-my-reason i{color:#ff5c7a;font-size:17px;margin-top:2px}
.nocturne-arena-my-reason span{display:block;margin-bottom:3px;color:#ff5c7a;font-size:10.5px;font-weight:800;text-transform:uppercase}
.nocturne-arena-my-reason p{margin:0;color:var(--arena-text);font-size:13px}
.nocturne-arena-my-note{margin:12px 0 0;color:var(--arena-muted);font-size:12px;font-style:italic}

.nocturne-arena-empty{display:grid;justify-items:center;gap:8px;padding:50px 20px;border:1px dashed var(--arena-line);border-radius:18px;color:var(--arena-muted);text-align:center}
.nocturne-arena-empty i{color:var(--arena-accent);font-size:28px}
.nocturne-arena-empty h3{margin:0;color:var(--arena-text);font-size:16px}
.nocturne-arena-empty a{color:var(--arena-accent);text-decoration:none;font-size:13px}

.nocturne-arena-loadmore{display:flex;justify-content:center;margin-top:20px}
#arena-load-more{padding:12px 26px;border:1px solid var(--arena-line);border-radius:14px;background:var(--arena-panel);color:var(--arena-text);font-size:13px;font-weight:800;cursor:pointer}
#arena-load-more:hover{border-color:rgba(0,231,138,.4);color:var(--arena-accent)}
#arena-load-more:disabled{opacity:.5;cursor:default}

@media(max-width:575.98px){
    .nocturne-arena-page--narrow{padding:16px 14px 40px}
    .nocturne-arena-post{padding:14px}
}
</style>
@endpush

@push('script')
<script>
"use strict";
(function($) {
    $('#arena-load-more').on('click', function() {
        var btn = $(this);
        var page = btn.data('next-page');
        btn.prop('disabled', true).text("{{ __('Loading…') }}");
        $.ajax({ url: btn.data('url'), data: { page: page }, headers: { 'Accept': 'application/json' } })
            .done(function(res) {
                $('#arena-feed').append(res.html);
                if (res.hasMore) { btn.data('next-page', page + 1).prop('disabled', false).text("{{ __('Load More') }}"); }
                else { btn.remove(); }
            })
            .fail(function() { btn.prop('disabled', false).text("{{ __('Load More') }}"); });
    });
})(jQuery);
</script>
@endpush
