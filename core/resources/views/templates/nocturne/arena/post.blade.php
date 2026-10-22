@extends('Template::layouts.frontend')
@section('content')
    @php
        $typeMeta = [
            'general'   => ['label' => 'General', 'icon' => 'ph-chat-circle-text'],
            'win'       => ['label' => 'Win', 'icon' => 'ph-trophy'],
            'challenge' => ['label' => 'Challenge', 'icon' => 'ph-sword'],
            'question'  => ['label' => 'Question', 'icon' => 'ph-question'],
        ];
        $meta = $typeMeta[$post->type] ?? $typeMeta['general'];
    @endphp
    <div class="nocturne-arena-page nocturne-arena-page--narrow">
        <div class="nocturne-arena-crumb"><a href="{{ route('arena.index') }}">@lang('Arena')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Post')</span></div>

        <article class="nocturne-arena-post">
            <div class="nocturne-arena-post__head">
                <a href="{{ route('arena.profile', $post->user->username ?? '') }}" class="nocturne-arena-post__avatar">@if($post->user?->avatarUrl())<img src="{{ $post->user->avatarUrl() }}" alt="">@else{{ mb_strtoupper(mb_substr($post->user->username ?? '?', 0, 1)) }}@endif</a>
                <div class="nocturne-arena-post__identity">
                    <a href="{{ route('arena.profile', $post->user->username ?? '') }}"><strong>{{ $post->user->username ?? 'Deleted user' }}</strong></a>
                    <small>{{ diffForHumans($post->created_at) }}</small>
                </div>
                <span class="nocturne-arena-post__type"><i class="ph-fill {{ $meta['icon'] }}"></i>{{ __($meta['label']) }}</span>
            </div>

            @if($post->body)
                @if(!$post->image && !$post->video && $post->background_style)
                    <div class="nocturne-arena-post__colored nocturne-arena-post__colored--{{ $post->background_style }}"><p>{{ $post->body }}</p></div>
                @else
                    <p class="nocturne-arena-post__body">{{ $post->body }}</p>
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
                    <div><strong>{{ $post->linkedGameLog->game->name ?? 'Game' }}</strong><small>{{ showAmount($post->linkedGameLog->win_amo) }}</small></div>
                </div>
            @endif
        </article>

        <a href="{{ route('arena.index') }}" class="nocturne-arena-open-btn"><i class="ph-bold ph-chats-circle"></i>@lang('Open in Arena to react and comment')</a>
    </div>
@endsection

@push('style')
<style>
:root{--arena-accent:#00e78a;--arena-bg:#10152a;--arena-panel:#151a31;--arena-raised:#1b2140;--arena-muted:#858eaf;--arena-line:rgba(133,142,175,.18);--arena-text:#f5f6ff}
.nocturne-arena-page--narrow{max-width:640px;margin:0 auto;padding:24px 20px 60px}
.nocturne-arena-crumb{display:flex;align-items:center;gap:7px;margin-bottom:18px;color:var(--arena-muted);font-size:13px;font-weight:700}
.nocturne-arena-crumb a{color:var(--arena-muted);text-decoration:none}
.nocturne-arena-crumb a:hover{color:var(--arena-text)}
.nocturne-arena-crumb span{color:var(--arena-text)}
.nocturne-arena-crumb i{font-size:11px;opacity:.6}

.nocturne-arena-post{padding:18px;border:1px solid var(--arena-line);border-radius:20px;background:linear-gradient(155deg,#151a31,#10152a 78%);box-shadow:0 14px 34px rgba(0,0,0,.14)}
.nocturne-arena-post__head{display:flex;align-items:center;gap:10px;margin-bottom:12px;flex-wrap:wrap}
.nocturne-arena-post__avatar{display:grid;flex:0 0 42px;width:42px;height:42px;place-items:center;overflow:hidden;border-radius:50%;background:var(--arena-raised);color:var(--arena-text);font-weight:800;text-decoration:none}
.nocturne-arena-post__avatar img{width:100%;height:100%;object-fit:cover}
.nocturne-arena-post__identity a{text-decoration:none}
.nocturne-arena-post__identity strong{color:var(--arena-text);font-size:13.5px;font-weight:800}
.nocturne-arena-post__identity small{display:block;color:var(--arena-muted);font-size:11px}
.nocturne-arena-post__type{display:inline-flex;align-items:center;gap:5px;margin-left:auto;padding:5px 11px;border-radius:999px;background:rgba(133,142,175,.12);color:var(--arena-muted);font-size:10.5px;font-weight:800}
.nocturne-arena-post__body{margin:0 0 4px;color:#d7dafa;font-size:14.5px;line-height:1.7;white-space:pre-wrap;overflow-wrap:anywhere}
.nocturne-arena-post__image,.nocturne-arena-post__video{margin-top:10px;border-radius:14px;overflow:hidden;background:#000}
.nocturne-arena-post__image img{display:block;width:100%;max-height:480px;object-fit:cover}
.nocturne-arena-post__video video{display:block;width:100%;max-height:480px}
.nocturne-arena-post__colored{display:grid;place-items:center;min-height:160px;padding:26px;border-radius:16px;text-align:center}
.nocturne-arena-post__colored p{margin:0;color:#fff;font-size:19px;font-weight:800;line-height:1.5;overflow-wrap:anywhere}
.nocturne-arena-post__colored--violet{background:linear-gradient(135deg,#7c3aed,#4c1d95)}
.nocturne-arena-post__colored--sunset{background:linear-gradient(135deg,#f97316,#be123c)}
.nocturne-arena-post__colored--forest{background:linear-gradient(135deg,#00e78a,#065f46)}
.nocturne-arena-post__colored--ocean{background:linear-gradient(135deg,#0ea5e9,#1e3a8a)}
.nocturne-arena-post__colored--rose{background:linear-gradient(135deg,#f472b6,#9d174d)}
.nocturne-arena-post__win{display:flex;align-items:center;gap:12px;margin-top:12px;padding:13px 15px;border:1px solid rgba(0,231,138,.22);border-radius:14px;background:rgba(0,231,138,.07)}
.nocturne-arena-post__win i{color:var(--arena-accent);font-size:22px}
.nocturne-arena-post__win strong{display:block;color:var(--arena-text);font-size:13.5px}
.nocturne-arena-post__win small{color:var(--arena-muted);font-size:11.5px}

.nocturne-arena-open-btn{display:flex;align-items:center;justify-content:center;gap:9px;margin-top:16px;min-height:50px;border-radius:14px;background:var(--arena-accent);color:#062a1d;text-decoration:none;font-size:13.5px;font-weight:850}
.nocturne-arena-open-btn:hover{color:#062a1d;filter:brightness(1.05)}

@media(max-width:575.98px){ .nocturne-arena-page--narrow{padding:16px 14px 40px} .nocturne-arena-post{padding:14px} }
</style>
@endpush
