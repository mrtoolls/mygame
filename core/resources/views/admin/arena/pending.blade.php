@extends('admin.layouts.app')
@section('panel')
    <div class="nocturne-admin-wrap">
        @forelse($posts as $post)
            <div class="nocturne-admin-card nocturne-arena-pending-card">
                <div class="nocturne-arena-pending-card__head">
                    <span class="nocturne-admin-avatar">{{ mb_strtoupper(mb_substr($post->user->username ?? '?', 0, 1)) }}</span>
                    <div>
                        <strong>{{ $post->user->username ?? 'Deleted user' }}</strong>
                        <small>{{ ucfirst($post->type) }} &middot; {{ showDateTime($post->created_at) }}</small>
                    </div>
                </div>
                @if($post->body)<p class="nocturne-arena-pending-card__body">{{ $post->body }}</p>@endif
                @if($post->image)<img class="nocturne-arena-pending-card__image" src="{{ $post->imageUrl() }}" alt="">@endif
                <div class="nocturne-arena-pending-card__actions">
                    <form method="post" action="{{ route('admin.arena.approve', $post->id) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="nocturne-admin-btn nocturne-admin-btn--success"><i class="las la-check"></i> @lang('Approve')</button>
                    </form>
                    <button type="button" class="nocturne-admin-btn nocturne-admin-btn--danger" data-open-modal="rejectModal-{{ $post->id }}"><i class="las la-ban"></i> @lang('Reject')</button>
                </div>
            </div>

            <div class="nocturne-admin-modal" id="rejectModal-{{ $post->id }}" hidden>
                <div class="nocturne-admin-modal__backdrop" data-close-modal></div>
                <div class="nocturne-admin-modal__sheet">
                    <form action="{{ route('admin.arena.reject') }}" method="post">
                        @csrf
                        <input type="hidden" name="id" value="{{ $post->id }}">
                        <div class="nocturne-admin-modal__head">
                            <span class="nocturne-admin-modal__icon nocturne-admin-modal__icon--danger"><i class="las la-ban"></i></span>
                            <h3>@lang('Reject this post?')</h3>
                            <button type="button" class="nocturne-admin-modal__close" data-close-modal><i class="las la-times"></i></button>
                        </div>
                        <div class="nocturne-admin-modal__body">
                            <p>@lang('Rejecting notifies') <strong>{{ $post->user->username ?? 'the author' }}</strong> @lang('with your reason below.')</p>
                            <label>@lang('Reason for rejection')</label>
                            <textarea name="message" maxlength="255" rows="3" required placeholder="@lang('e.g. Violates community guidelines')"></textarea>
                        </div>
                        <div class="nocturne-admin-modal__foot">
                            <button type="button" class="nocturne-admin-btn nocturne-admin-btn--ghost" data-close-modal>@lang('Cancel')</button>
                            <button type="submit" class="nocturne-admin-btn nocturne-admin-btn--danger"><i class="las la-ban"></i> @lang('Confirm rejection')</button>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            <div class="nocturne-admin-card" style="text-align:center;color:var(--na-muted)">@lang('No pending Arena posts right now.')</div>
        @endforelse

        {{ paginateLinks($posts) }}
    </div>
@endsection

@push('style')
<style>
:root{--na-accent:#00e78a;--na-bg:#0d1225;--na-panel:#151a31;--na-raised:#1b2140;--na-muted:#858eaf;--na-line:rgba(133,142,175,.18);--na-text:#f5f6ff;--na-danger:#ff5c7a;--na-warning:#ffb547}
.nocturne-admin-wrap{max-width:900px;margin:0 auto 40px;display:grid;gap:16px}
.nocturne-admin-card{padding:22px;border:1px solid var(--na-line);border-radius:22px;background:linear-gradient(160deg,#171d36,#11152a 78%);box-shadow:0 14px 38px rgba(0,0,0,.16)}
.nocturne-admin-avatar{display:grid;place-items:center;flex:0 0 42px;width:42px;height:42px;border-radius:14px;background:linear-gradient(145deg,rgba(0,231,138,.25),rgba(0,231,138,.08));color:var(--na-accent);font-size:17px;font-weight:850}
.nocturne-arena-pending-card__head{display:flex;align-items:center;gap:12px;margin-bottom:12px}
.nocturne-arena-pending-card__head strong{display:block;color:var(--na-text);font-size:14px}
.nocturne-arena-pending-card__head small{color:var(--na-muted);font-size:11.5px}
.nocturne-arena-pending-card__body{margin:0 0 12px;color:#d7dafa;font-size:14px;line-height:1.6;white-space:pre-wrap;overflow-wrap:anywhere}
.nocturne-arena-pending-card__image{display:block;max-width:320px;max-height:220px;border-radius:14px;margin-bottom:12px;object-fit:cover}
.nocturne-arena-pending-card__actions{display:flex;gap:10px}

.nocturne-admin-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:40px;padding:0 18px;border:0;border-radius:11px;font-size:13px;font-weight:800;cursor:pointer}
.nocturne-admin-btn--success{background:var(--na-accent);color:#062a23}
.nocturne-admin-btn--danger{background:var(--na-danger);color:#2a0712}
.nocturne-admin-btn--ghost{background:rgba(255,255,255,.06);color:var(--na-text)}

.nocturne-admin-modal{position:fixed;inset:0;z-index:1200;display:flex;align-items:flex-end;justify-content:center}
.nocturne-admin-modal__backdrop{position:absolute;inset:0;background:rgba(5,8,18,.72);backdrop-filter:blur(3px)}
.nocturne-admin-modal__sheet{position:relative;z-index:1;width:100%;max-width:460px;margin:0 16px 16px;border:1px solid var(--na-line);border-radius:22px;background:linear-gradient(165deg,#171d36,#11152a 80%);box-shadow:0 30px 70px rgba(0,0,0,.5);animation:na-modal-up .22s ease}
@keyframes na-modal-up{from{transform:translateY(24px);opacity:0}to{transform:translateY(0);opacity:1}}
.nocturne-admin-modal__head{display:flex;align-items:center;gap:12px;padding:20px 22px 6px}
.nocturne-admin-modal__icon{display:grid;place-items:center;flex:0 0 40px;width:40px;height:40px;border-radius:12px;font-size:19px}
.nocturne-admin-modal__icon--danger{background:rgba(255,92,122,.14);color:var(--na-danger)}
.nocturne-admin-modal__head h3{flex:1;margin:0;color:var(--na-text);font-size:16px;font-weight:800}
.nocturne-admin-modal__close{flex:0 0 auto;border:0;background:transparent;color:var(--na-muted);font-size:18px;cursor:pointer}
.nocturne-admin-modal__body{padding:12px 22px}
.nocturne-admin-modal__body p{margin:0 0 14px;color:var(--na-muted);font-size:13.5px;line-height:1.6}
.nocturne-admin-modal__body p strong{color:var(--na-text)}
.nocturne-admin-modal__body label{display:block;margin-bottom:7px;color:var(--na-muted);font-size:11.5px;font-weight:800;text-transform:uppercase}
.nocturne-admin-modal__body textarea{width:100%;padding:11px 13px;border:1px solid var(--na-line);border-radius:12px;background:var(--na-bg);color:var(--na-text);font:inherit;font-size:13.5px;resize:vertical;outline:0}
.nocturne-admin-modal__foot{display:flex;justify-content:flex-end;gap:10px;padding:16px 22px 22px}

@media(max-width:575.98px){
    .nocturne-admin-card{padding:16px}
    .nocturne-arena-pending-card__actions{flex-direction:column}
    .nocturne-arena-pending-card__actions form,.nocturne-arena-pending-card__actions .nocturne-admin-btn{width:100%}
    .nocturne-admin-modal__sheet{max-width:none}
}
</style>
@endpush

@push('script')
<script>
"use strict";
(function($) {
    $('[data-open-modal]').on('click', function() {
        $('#' + $(this).data('open-modal')).prop('hidden', false);
        $('body').css('overflow', 'hidden');
    });
    $('[data-close-modal]').on('click', function() {
        $(this).closest('.nocturne-admin-modal').prop('hidden', true);
        $('body').css('overflow', '');
    });
})(jQuery);
</script>
@endpush
