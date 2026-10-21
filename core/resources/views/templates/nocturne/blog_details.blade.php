@extends('Template::layouts.frontend')
@section('content')
    <div class="nocturne-news-page nocturne-news-detail">
        <div class="nocturne-news-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><a href="{{ route('blog') }}">@lang('News')</a><i class="ph-bold ph-caret-right"></i><span>{{ __($blog->title) }}</span></div>

        <div class="nocturne-news-detail__grid">
            <article class="nocturne-news-detail__main">
                <div class="nocturne-news-detail__thumb"><img src="{{ $blog->imageUrl() }}" alt="{{ __($blog->title) }}"></div>
                @if($blog->badge_label)<span class="nocturne-news-detail__badge"><i class="ph-fill ph-seal-check"></i>{{ __($blog->badge_label) }}</span>@endif
                <h1 class="nocturne-news-detail__title">{{ __($blog->title) }}</h1>
                <div class="nocturne-news-detail__meta"><i class="ph-bold ph-calendar"></i>{{ showDateTime($blog->created_at, 'M d, Y') }}<span>&middot;</span><i class="ph-bold ph-eye"></i>{{ number_format($blog->views) }} @lang('views')</div>
                <div class="nocturne-news-detail__body">{!! $blog->posted_by_type === 'agent' ? nl2br(e($blog->body)) : $blog->body !!}</div>

                <div class="nocturne-news-detail__share">
                    <span>@lang('Share this')</span>
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}" target="_blank" rel="noopener"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://x.com/intent/tweet?text={{ urlencode(__($blog->title)) }}&url={{ urlencode(url()->current()) }}" target="_blank" rel="noopener"><i class="fa-brands fa-x-twitter"></i></a>
                    <a href="https://www.linkedin.com/shareArticle?mini=true&url={{ urlencode(url()->current()) }}&title={{ urlencode(__($blog->title)) }}" target="_blank" rel="noopener"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </article>

            <aside class="nocturne-news-detail__side">
                <h2>@lang('Latest News')</h2>
                <div class="nocturne-news-recent nocturne-news-recent--stack">
                    @forelse($latestBlogs as $post)
                        <a href="{{ route('blog.details', $post->slug) }}" class="nocturne-news-recent__item">
                            <span class="nocturne-news-recent__thumb"><img src="{{ $post->imageUrl() }}" alt="{{ __($post->title) }}" loading="lazy"></span>
                            <span class="nocturne-news-recent__body">
                                <strong>{{ __($post->title) }}</strong>
                                <small>{{ showDateTime($post->created_at, 'M d, Y') }}</small>
                            </span>
                        </a>
                    @empty
                        <p style="color:var(--news-muted);font-size:13px">@lang('No other posts yet.')</p>
                    @endforelse
                </div>
            </aside>
        </div>
    </div>
@endsection

@push('style')
<style>
:root{--news-accent:#00e78a;--news-bg:#10152a;--news-panel:#151a31;--news-muted:#858eaf;--news-line:rgba(133,142,175,.18);--news-text:#f5f6ff}
.nocturne-news-page{max-width:1240px;margin:0 auto;padding:24px 20px 60px}
.nocturne-news-crumb{display:flex;align-items:center;gap:7px;margin-bottom:18px;color:var(--news-muted);font-size:13px;font-weight:700;flex-wrap:wrap}
.nocturne-news-crumb a{color:var(--news-muted);text-decoration:none}
.nocturne-news-crumb a:hover{color:var(--news-text)}
.nocturne-news-crumb span{color:var(--news-text)}
.nocturne-news-crumb i{font-size:11px;opacity:.6}

.nocturne-news-detail__grid{display:grid;grid-template-columns:2fr 1fr;gap:28px;align-items:start}
.nocturne-news-detail__main{padding:26px;border:1px solid var(--news-line);border-radius:24px;background:linear-gradient(155deg,#151a31,#10152a 78%);box-shadow:0 18px 44px rgba(0,0,0,.18)}
.nocturne-news-detail__thumb{border-radius:18px;overflow:hidden;aspect-ratio:16/8;background:#0d1225}
.nocturne-news-detail__thumb img{width:100%;height:100%;object-fit:cover}
.nocturne-news-detail__badge{display:inline-flex;align-items:center;gap:6px;margin-top:16px;padding:7px 13px;border-radius:999px;background:rgba(0,231,138,.14);color:var(--news-accent);font-size:11.5px;font-weight:800}
.nocturne-news-detail__title{margin:18px 0 10px;color:var(--news-text);font-size:clamp(22px,2.6vw,30px);font-weight:850;letter-spacing:-.02em;line-height:1.3}
.nocturne-news-detail__meta{display:flex;align-items:center;gap:8px;margin-bottom:20px;padding-bottom:20px;border-bottom:1px dashed var(--news-line);color:var(--news-muted);font-size:12.5px;font-weight:700}
.nocturne-news-detail__meta i{color:var(--news-accent)}
.nocturne-news-detail__body{color:#d7dafa;font-size:15px;line-height:1.85}
.nocturne-news-detail__body p{margin:0 0 16px}
.nocturne-news-detail__body img{max-width:100%;border-radius:14px}

.nocturne-news-detail__share{display:flex;align-items:center;gap:10px;margin-top:28px;padding-top:22px;border-top:1px solid var(--news-line)}
.nocturne-news-detail__share span{color:var(--news-muted);font-size:12.5px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
.nocturne-news-detail__share a{display:grid;place-items:center;width:36px;height:36px;border-radius:50%;background:var(--news-bg);color:var(--news-text);text-decoration:none}
.nocturne-news-detail__share a:hover{color:var(--news-accent)}

.nocturne-news-detail__side h2{margin:0 0 14px;color:var(--news-text);font-size:17px;font-weight:850}

.nocturne-news-recent{display:grid;gap:14px}
.nocturne-news-recent__item{display:flex;gap:14px;padding:14px;border:1px solid var(--news-line);border-radius:18px;background:linear-gradient(155deg,#151a31,#10152a 78%);text-decoration:none;transition:border-color .18s,transform .18s}
.nocturne-news-recent__item:hover{border-color:rgba(0,231,138,.4);transform:translateY(-2px)}
.nocturne-news-recent__thumb{flex:0 0 72px;width:72px;height:60px;border-radius:12px;overflow:hidden;background:#0d1225}
.nocturne-news-recent__thumb img{width:100%;height:100%;object-fit:cover}
.nocturne-news-recent__body{flex:1;min-width:0;display:grid;gap:4px;align-content:center}
.nocturne-news-recent__body strong{color:var(--news-text);font-size:13.5px;font-weight:800;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.nocturne-news-recent__body small{color:var(--news-muted);font-size:11.5px}

@media(max-width:991.98px){
    .nocturne-news-detail__grid{grid-template-columns:1fr}
}
@media(max-width:575.98px){
    .nocturne-news-page{padding:18px 14px 40px}
    .nocturne-news-detail__main{padding:18px;border-radius:18px}
}
</style>
@endpush
