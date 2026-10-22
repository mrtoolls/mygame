@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content">
            <h1 class="nocturne-page-title">@lang('My Gifts')</h1>

            <div class="card custom--card gift-section">
                <div class="card-body">
                    <h2 class="gift-section__title">@lang('Your Inventory')</h2>
                    <p class="gift-section__sub">@lang("Gifts you've bought or received during live games. Cash any of them out to your wallet balance any time.")</p>
                    @if ($inventory->isEmpty())
                        <p class="gift-empty">@lang("You don't have any gifts yet - buy one below, or receive one while watching or playing a live game.")</p>
                    @else
                        <div class="gift-grid">
                            @foreach ($inventory as $item)
                                <div class="gift-card gift-fx" data-gift-fx="{{ $item->gift->slug }}" data-gift-price="{{ $item->gift->buy_price }}">
                                    <span class="gift-fx__media">
                                        @if ($item->gift->webpImageUrl())
                                            <picture>
                                                <source srcset="{{ $item->gift->webpImageUrl() }}" type="image/webp">
                                                <img src="{{ $item->gift->imageUrl() }}" alt="{{ $item->gift->name }}" width="56" height="56" loading="lazy">
                                            </picture>
                                        @else
                                            <img src="{{ $item->gift->imageUrl() }}" alt="{{ $item->gift->name }}" width="56" height="56" loading="lazy">
                                        @endif
                                    </span>
                                    <strong>{{ $item->gift->name }}</strong>
                                    <span>x{{ $item->quantity }}</span>
                                    <form method="post" action="{{ route('user.gifts.cashout', $item->gift_id) }}" class="gift-card__form">
                                        @csrf
                                        <input type="number" name="quantity" min="1" max="{{ $item->quantity }}" value="1">
                                        <button type="submit">@lang('Cash out')</button>
                                    </form>
                                    <small>${{ showAmount($item->gift->cashout_value, currencyFormat: false) }} @lang('each')</small>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="card custom--card gift-section">
                <div class="card-body">
                    <h2 class="gift-section__title">@lang('Gift Shop')</h2>
                    <p class="gift-section__sub">@lang('Buy gifts here to have them ready to send next time you join a live game.')</p>
                    <div class="gift-grid" id="gift-shop-grid">
                        @forelse ($catalog as $gift)
                            <div class="gift-card gift-fx" data-gift-fx="{{ $gift->slug }}" data-gift-price="{{ $gift->buy_price }}" data-gift-id="{{ $gift->id }}">
                                <span class="gift-fx__media">
                                    @if ($gift->webpImageUrl())
                                        <picture>
                                            <source srcset="{{ $gift->webpImageUrl() }}" type="image/webp">
                                            <img src="{{ $gift->imageUrl() }}" alt="{{ $gift->name }}" width="56" height="56" loading="lazy">
                                        </picture>
                                    @else
                                        <img src="{{ $gift->imageUrl() }}" alt="{{ $gift->name }}" width="56" height="56" loading="lazy">
                                    @endif
                                </span>
                                <strong>{{ $gift->name }}</strong>
                                <span>${{ showAmount($gift->buy_price, currencyFormat: false) }}</span>
                                <form method="post" action="{{ route('user.gifts.buy', $gift->id) }}" class="gift-card__form" data-gift-buy-form data-gift-id="{{ $gift->id }}">
                                    @csrf
                                    <input type="number" name="quantity" min="1" max="999" value="1">
                                    <button type="submit">@lang('Buy')</button>
                                </form>
                            </div>
                        @empty
                            <p class="gift-empty">@lang('No gifts are available right now.')</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('style')
    <link rel="stylesheet" href="{{ asset('assets/global/css/gift-effects.css') }}?v={{ @filemtime(public_path('assets/global/css/gift-effects.css')) }}">
    <style>
        .gift-section { margin-bottom: 20px; }
        .gift-section__title { margin: 0 0 4px; font-size: 16px; font-weight: 900; }
        .gift-section__sub { margin: 0 0 18px; color: var(--nocturne-muted, #929abc); font-size: 12.5px; }
        .gift-empty { margin: 0; color: var(--nocturne-muted, #929abc); font-size: 13px; }
        .gift-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 14px; }
        .gift-card {
            display: grid; justify-items: center; gap: 6px; padding: 16px 10px; border: 1px solid var(--nocturne-line, rgba(145, 156, 205, .16));
            border-radius: 15px; background: var(--nocturne-raised, #232a47); text-align: center;
        }
        .gift-fx__media { width: 56px; height: 56px; }
        .gift-card img { width: 56px; height: 56px; object-fit: contain; }
        .gift-card strong { color: var(--nocturne-text, #f5f6ff); font-size: 13px; font-weight: 900; }
        .gift-card > span { color: var(--nocturne-muted, #929abc); font-size: 11px; }
        .gift-card small { color: var(--nocturne-muted, #929abc); font-size: 10px; }
        .gift-card__form { display: flex; width: 100%; gap: 6px; margin-top: 2px; }
        .gift-card__form input {
            width: 50px; min-height: 34px; padding: 0 6px; border: 1px solid var(--nocturne-line, rgba(145, 156, 205, .16));
            border-radius: 8px; background: #11162c; color: var(--nocturne-text, #f5f6ff); font-size: 12px; text-align: center;
        }
        .gift-card__form button {
            flex: 1; min-height: 34px; border: 0; border-radius: 8px; background: var(--nocturne-green, #00ef87);
            color: #062b1d; font-size: 11px; font-weight: 900; cursor: pointer;
        }
    </style>
@endpush

@push('script')
    <script src="{{ asset('assets/global/js/gift-effects.js') }}?v={{ @filemtime(public_path('assets/global/js/gift-effects.js')) }}"></script>
    <script>
        (function () {
            var shopApi = window.GiftEffects && window.GiftEffects.initShop(document.getElementById('gift-shop-grid'));

            // The buy form is a normal (non-AJAX) POST so purchase logic is never touched here -
            // we just remember which gift was being bought, then check the real flash-notify
            // success array after the page reloads to decide whether to play the pop animation.
            document.querySelectorAll('[data-gift-buy-form]').forEach(function (form) {
                form.addEventListener('submit', function () {
                    try { sessionStorage.setItem('gift-shop-last-buy', form.dataset.giftId); } catch (e) {}
                });
            });

            if (shopApi) {
                var notifications = @json(session('notify', []));
                var hadSuccess = notifications.some(function (n) { return n[0] === 'success'; });
                var pendingGiftId = null;
                try { pendingGiftId = sessionStorage.getItem('gift-shop-last-buy'); } catch (e) {}
                if (pendingGiftId && hadSuccess) shopApi.pop(pendingGiftId);
                try { sessionStorage.removeItem('gift-shop-last-buy'); } catch (e) {}
            }
        })();
    </script>
@endpush
