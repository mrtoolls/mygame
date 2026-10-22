@extends('Template::layouts.frontend')
@section('content')
    <div class="nocturne-news-page">
        <div class="nocturne-news-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><span>@lang('News')</span></div>

        @if($featured)
            <section class="nocturne-news-top">
                <a href="{{ route('blog.details', $featured->slug) }}" class="nocturne-news-featured">
                    <img src="{{ $featured->imageUrl() }}" alt="{{ __($featured->title) }}" fetchpriority="high">
                    <div class="nocturne-news-featured__shade"></div>
                    @if($featured->badge_label)<span class="nocturne-news-featured__badge"><i class="ph-fill ph-seal-check"></i>{{ __($featured->badge_label) }}</span>@endif
                    <h2 class="nocturne-news-featured__title">{{ __($featured->title) }}</h2>
                </a>

                @if($recentList->isNotEmpty())
                    <div class="nocturne-news-recent">
                        @foreach($recentList as $post)
                            <a href="{{ route('blog.details', $post->slug) }}" class="nocturne-news-recent__item">
                                <span class="nocturne-news-recent__thumb"><img src="{{ $post->imageUrl() }}" alt="{{ __($post->title) }}" loading="lazy"></span>
                                <span class="nocturne-news-recent__body">
                                    <strong>{{ __($post->title) }}</strong>
                                    @if($post->excerpt)<small>{{ __($post->excerpt) }}</small>@endif
                                    <b>@lang('Read More')</b>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        <section class="nocturne-news-grid-section">
            <h2 class="nocturne-news-grid-heading"><i class="ph-fill ph-newspaper-clipping"></i>@lang('Trendy News')</h2>

            @if($blogs->isEmpty() && !$featured)
                <div class="nocturne-vip-empty"><i class="ph-fill ph-newspaper"></i><h3>@lang('No news yet')</h3><p>@lang('Check back soon for updates.')</p></div>
            @else
                <div class="nocturne-news-grid" id="news-grid">
                    @include('Template::partials.news_grid_cards')
                </div>
                @if($blogs->hasMorePages())
                    <div class="nocturne-news-loadmore">
                        <button type="button" id="news-load-more" data-next-page="{{ $blogs->currentPage() + 1 }}" data-url="{{ route('blog') }}">@lang('Load More')</button>
                    </div>
                @endif
            @endif
        </section>
    </div>

    @if (@$sections->secs != null)
        @foreach (json_decode($sections->secs) as $sec)
            @include('Template::sections.' . $sec)
        @endforeach
    @endif
@endsection

@push('style')
<style>
:root{--news-accent:#00e78a;--news-bg:#10152a;--news-panel:#151a31;--news-muted:#858eaf;--news-line:rgba(133,142,175,.18);--news-text:#f5f6ff}
.nocturne-news-page{max-width:1240px;margin:0 auto;padding:24px 20px 60px}
.nocturne-news-crumb{display:flex;align-items:center;gap:7px;margin-bottom:18px;color:var(--news-muted);font-size:13px;font-weight:700}
.nocturne-news-crumb a{color:var(--news-muted);text-decoration:none}
.nocturne-news-crumb a:hover{color:var(--news-text)}
.nocturne-news-crumb span{color:var(--news-text)}
.nocturne-news-crumb i{font-size:11px;opacity:.6}

.nocturne-news-top{display:grid;grid-template-columns:1.6fr 1fr;gap:20px;margin-bottom:34px}
.nocturne-news-featured{position:relative;display:block;min-height:420px;border-radius:24px;overflow:hidden;text-decoration:none;background:var(--news-panel);box-shadow:0 20px 50px rgba(0,0,0,.22)}
.nocturne-news-featured img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.nocturne-news-featured__shade{position:absolute;inset:0;background:linear-gradient(0deg,rgba(5,8,18,.88) 0%,rgba(5,8,18,.15) 55%,transparent 80%)}
.nocturne-news-featured__badge{position:relative;z-index:1;display:inline-flex;align-items:center;gap:6px;margin:20px 0 0 20px;padding:8px 14px;border-radius:999px;background:rgba(0,231,138,.16);backdrop-filter:blur(6px);color:var(--news-accent);font-size:12px;font-weight:800}
.nocturne-news-featured__title{position:relative;z-index:1;margin:260px 24px 24px;color:#fff;font-size:clamp(20px,2.4vw,30px);font-weight:850;letter-spacing:-.02em;line-height:1.25}

.nocturne-news-recent{display:grid;gap:14px}
.nocturne-news-recent__item{display:flex;gap:14px;padding:14px;border:1px solid var(--news-line);border-radius:18px;background:linear-gradient(155deg,#151a31,#10152a 78%);text-decoration:none;transition:border-color .18s,transform .18s}
.nocturne-news-recent__item:hover{border-color:rgba(0,231,138,.4);transform:translateY(-2px)}
.nocturne-news-recent__thumb{flex:0 0 96px;width:96px;height:80px;border-radius:13px;overflow:hidden;background:#0d1225}
.nocturne-news-recent__thumb img{width:100%;height:100%;object-fit:cover}
.nocturne-news-recent__body{flex:1;min-width:0;display:grid;gap:6px;align-content:center}
.nocturne-news-recent__body strong{color:var(--news-text);font-size:14.5px;font-weight:800;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.nocturne-news-recent__body small{color:var(--news-muted);font-size:12px;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.nocturne-news-recent__body b{color:var(--news-accent);font-size:12px;font-weight:800}

.nocturne-news-grid-heading{display:flex;align-items:center;gap:10px;margin:0 0 20px;color:var(--news-text);font-size:22px;font-weight:850}
.nocturne-news-grid-heading i{color:var(--news-accent)}

.nocturne-news-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px 20px;align-items:start}
.nocturne-news-card{border:1px solid var(--news-line);border-radius:20px;overflow:hidden;background:linear-gradient(155deg,#151a31,#10152a 78%);box-shadow:0 14px 34px rgba(0,0,0,.16);transition:transform .18s,border-color .18s}
.nocturne-news-card:hover{transform:translateY(-3px);border-color:rgba(0,231,138,.35)}
.nocturne-news-card__thumb{display:block;aspect-ratio:16/10;background:#0d1225}
.nocturne-news-card__thumb img{width:100%;height:100%;object-fit:cover}
.nocturne-news-card__body{padding:18px}
.nocturne-news-card__body h3{margin:0 0 8px;font-size:15.5px;font-weight:800;line-height:1.35}
.nocturne-news-card__body h3 a{color:var(--news-text);text-decoration:none;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.nocturne-news-card__body p{margin:0 0 12px;color:var(--news-muted);font-size:13px;line-height:1.6;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.nocturne-news-card__more{color:var(--news-accent);font-size:12.5px;font-weight:800;text-decoration:none}
.nocturne-news-card__more:hover{text-decoration:underline}

.nocturne-news-loadmore{display:flex;justify-content:center;margin-top:28px}
#news-load-more{padding:13px 28px;border:1px solid var(--news-line);border-radius:14px;background:var(--news-panel);color:var(--news-text);font-size:13.5px;font-weight:800;cursor:pointer}
#news-load-more:hover{border-color:rgba(0,231,138,.4);color:var(--news-accent)}
#news-load-more:disabled{opacity:.5;cursor:default}

.nocturne-vip-empty{display:grid;justify-items:center;gap:8px;padding:60px 20px;border:1px dashed var(--news-line);border-radius:18px;color:var(--news-muted);text-align:center}
.nocturne-vip-empty i{color:var(--news-accent);font-size:30px}
.nocturne-vip-empty h3{margin:0;color:var(--news-text);font-size:17px}

@media(max-width:991.98px){
    .nocturne-news-top{grid-template-columns:1fr}
    .nocturne-news-featured{min-height:300px}
    .nocturne-news-featured__title{margin-top:160px}
    .nocturne-news-recent{grid-template-columns:repeat(3,minmax(0,1fr))}
    .nocturne-news-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:767.98px){
    .nocturne-news-recent{grid-template-columns:1fr}
}
@media(max-width:575.98px){
    .nocturne-news-page{padding:18px 14px 40px}
    .nocturne-news-featured{min-height:260px;border-radius:18px}
    .nocturne-news-featured__title{margin:140px 16px 18px;font-size:19px}
    .nocturne-news-featured__badge{margin:14px 0 0 14px;font-size:11px;padding:6px 11px}
    .nocturne-news-grid{grid-template-columns:1fr}
    .nocturne-news-grid-heading{font-size:19px}
}
</style>
@endpush

@push('script')
<script>
"use strict";
(function($) {
    $('#news-load-more').on('click', function() {
        var btn = $(this);
        var page = btn.data('next-page');
        var url = btn.data('url');
        btn.prop('disabled', true).text("{{ __('Loading…') }}");
        $.ajax({
            url: url,
            data: { page: page },
            headers: { 'Accept': 'application/json' },
        }).done(function(res) {
            $('#news-grid').append(res.html);
            if (res.hasMore) {
                btn.data('next-page', page + 1).prop('disabled', false).text("{{ __('Load More') }}");
            } else {
                btn.remove();
            }
        }).fail(function() {
            btn.prop('disabled', false).text("{{ __('Load More') }}");
        });
    });
})(jQuery);
</script>
@endpush
