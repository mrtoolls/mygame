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
                                <div class="gift-card">
                                    <img src="{{ $item->gift->imageUrl() }}" alt="{{ $item->gift->name }}">
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
                    <div class="gift-grid">
                        @forelse ($catalog as $gift)
                            <div class="gift-card">
                                <img src="{{ $gift->imageUrl() }}" alt="{{ $gift->name }}">
                                <strong>{{ $gift->name }}</strong>
                                <span>${{ showAmount($gift->buy_price, currencyFormat: false) }}</span>
                                <form method="post" action="{{ route('user.gifts.buy', $gift->id) }}" class="gift-card__form">
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
