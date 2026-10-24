@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card mb-3">
                <div class="card-body">
                    <p class="text-muted mb-0">@lang('Shop idle/hover/purchase animations below are live - hover or tap a card. Use "Play send overlay" to preview the live-game send animation for that gift\'s tier. Nothing here spends money or sends a real gift.')</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="gift-preview-grid" id="gift-preview-grid">
                        @forelse ($gifts as $gift)
                            <div class="gift-preview-card gift-fx" data-gift-fx="{{ $gift->slug }}" data-gift-price="{{ $gift->buy_price }}">
                                <span class="gift-fx__media">
                                    @if ($gift->webpImageUrl())
                                        <picture><source srcset="{{ $gift->webpImageUrl() }}" type="image/webp"><img src="{{ $gift->imageUrl() }}" alt="{{ $gift->name }}" width="72" height="72" loading="lazy"></picture>
                                    @else
                                        <img src="{{ $gift->imageUrl() }}" alt="{{ $gift->name }}" width="72" height="72" loading="lazy">
                                    @endif
                                </span>
                                <strong>{{ $gift->name }}</strong>
                                <span class="text-muted">@if(gs('coin_mode')) {{ formatCoinOrUsd($gift->buy_price) }} @else ${{ showAmount($gift->buy_price, currencyFormat: false) }} @endif</span>
                                <div class="gift-preview-card__actions">
                                    <button type="button" class="btn btn-sm btn-outline--success" data-preview-pop data-gift-id="{{ $gift->id }}">@lang('Play pop')</button>
                                    <button type="button" class="btn btn-sm btn-outline--primary" data-preview-send data-gift-name="{{ $gift->name }}" data-gift-slug="{{ $gift->slug }}" data-gift-price="{{ $gift->buy_price }}" data-gift-image="{{ $gift->imageUrl() }}">@lang('Play send overlay')</button>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted">@lang('No gifts configured yet.')</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="gift-fx-overlay" id="gift-fx-overlay" hidden aria-live="polite">
        <div class="gift-fx-overlay__card">
            <span class="gift-fx-overlay__extra"></span>
            <img class="gift-fx-overlay__img" data-gift-fx-img src="" alt="">
            <span class="gift-fx-overlay__qty" data-gift-fx-qty hidden></span>
            <p class="gift-fx-overlay__text" data-gift-fx-text></p>
        </div>
    </div>
@endsection

@push('breadcrumb-plugins')
    <x-back route="{{ route('admin.gift.index') }}" />
@endpush

@push('style')
    <link rel="stylesheet" href="{{ asset('assets/global/css/gift-effects.css') }}?v={{ @filemtime(public_path('assets/global/css/gift-effects.css')) }}">
    <style>
        .gift-preview-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px}
        .gift-preview-card{display:grid;justify-items:center;gap:8px;padding:18px 12px;border:1px solid #2a2f45;border-radius:14px;background:rgba(255,255,255,.02);text-align:center}
        .gift-preview-card strong{font-size:14px}
        .gift-preview-card__actions{display:flex;flex-direction:column;gap:6px;width:100%;margin-top:6px}
    </style>
@endpush

@push('script')
    <script src="{{ asset('assets/global/js/gift-effects.js') }}?v={{ @filemtime(public_path('assets/global/js/gift-effects.js')) }}"></script>
    <script>
        (function () {
            if (!window.GiftEffects) return;
            var shopApi = window.GiftEffects.initShop(document.getElementById('gift-preview-grid'));
            var overlay = window.GiftEffects.initLiveOverlay(document.getElementById('gift-fx-overlay'));

            document.querySelectorAll('[data-preview-pop]').forEach(function (btn) {
                btn.addEventListener('click', function () { shopApi && shopApi.pop(btn.dataset.giftId); });
            });
            document.querySelectorAll('[data-preview-send]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    overlay.queue({
                        key: btn.dataset.giftSlug,
                        price: btn.dataset.giftPrice,
                        image: btn.dataset.giftImage,
                        giftName: btn.dataset.giftName,
                        senderName: 'Admin',
                        recipientName: 'you',
                        quantity: Math.floor(Math.random() * 4) + 1,
                    });
                });
            });
        })();
    </script>
@endpush
