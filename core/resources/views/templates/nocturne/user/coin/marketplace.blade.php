@extends('Template::layouts.master')
@section('content')
    <link rel="stylesheet" href="{{ asset('assets/global/css/coin-marketplace.css') }}?v={{ @filemtime('assets/global/css/coin-marketplace.css') }}">
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content coin-marketplace-content">
            <h1 class="nocturne-page-title">@lang('Coin Marketplace')</h1>

            <div class="coin-page">
                <div class="coin-hero">
                    <div class="coin-hero__copy">
                        <span class="coin-hero__eyebrow"><i class="ph-fill ph-coins"></i> @lang('Your coin wallet')</span>
                        <div class="coin-hero__amount">{{ number_format($wallet->available_balance, 0) }} <small>@lang('coins available')</small></div>
                        <div class="coin-hero__held">@lang('Held in open listings/trades'): <b>{{ number_format($wallet->held_balance, 0) }}</b></div>
                    </div>
                    <span class="coin-hero__icon"><i class="ph-fill ph-coin-vertical"></i></span>
                </div>

                <div class="coin-section">
                    <div class="coin-section__head">
                        <div>
                            <h2>@lang('Sell my coins')</h2>
                            <p>@lang('List coins for sale — verified agents will buy them and pay you directly.')</p>
                        </div>
                        <span class="coin-section__icon"><i class="ph-fill ph-tag"></i></span>
                    </div>
                    <form method="post" action="{{ route('user.coin.listings.store') }}" class="coin-form">
                        @csrf
                        <div class="coin-field coin-field--grow">
                            <label>@lang('Amount (whole coins)')</label>
                            <input type="number" step="1" min="1" name="amount" required>
                        </div>
                        <button type="submit" class="coin-btn coin-btn--primary"><i class="ph-bold ph-tag"></i> @lang('List for sale')</button>
                    </form>
                </div>

                @if($myListings->count())
                    <div class="coin-section">
                        <div class="coin-section__head">
                            <div><h2>@lang('Your open listings')</h2></div>
                            <span class="coin-section__icon"><i class="ph-fill ph-list-bullets"></i></span>
                        </div>
                        <div class="coin-list">
                            @foreach($myListings as $listing)
                                <div class="coin-list__row">
                                    <span class="coin-list__avatar"><i class="ph-fill ph-coin"></i></span>
                                    <div class="coin-list__body">
                                        <span class="coin-list__title">{{ number_format($listing->amount, 0) }} @lang('coins')</span>
                                        <span class="coin-list__subtitle">@lang('Listing') #{{ $listing->id }}</span>
                                    </div>
                                    <div class="coin-list__trailing">
                                        <span class="coin-badge coin-badge--{{ $listing->status }}">{{ str_replace('_', ' ', $listing->status) }}</span>
                                        @if($listing->status === 'on_sale')
                                            <form method="post" action="{{ route('user.coin.listings.cancel', $listing->id) }}">
                                                @csrf
                                                <button type="submit" class="coin-btn coin-btn--danger coin-btn--sm">@lang('Cancel')</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="coin-section coin-vendor-market">
                    <div class="coin-section__head">
                        <div>
                            <span class="coin-market-kicker"><i class="ph-fill ph-storefront"></i> @lang('Coin marketplace')</span>
                            <h2>@lang('Offers from agents and vendors')</h2>
                            <p>@lang('Compare agent and vendor offers with individual member listings. Payment totals are locked when each trade starts.')</p>
                        </div>
                        <span class="coin-section__icon"><i class="ph-fill ph-storefront"></i></span>
                    </div>

                    @if(count($vendors) || count($agents) || $listings->isNotEmpty())
                        <div class="coin-vendor-grid">
                            @foreach($listings as $listing)
                                @php
                                    $seller = $listing->seller;
                                    $isVendorListing = in_array((int) $listing->seller_user_id, $listingVendorIds, true);
                                    $listingRate = $seller ? $listingRates->get($seller->currency_id) : null;
                                @endphp
                                @if($seller && $listingRate?->sell_price)
                                    <article class="coin-vendor-card">
                                        <div class="coin-vendor-card__top">
                                            <span class="coin-vendor-card__avatar coin-vendor-card__avatar--listing" aria-hidden="true"><i class="ph-fill {{ $isVendorListing ? 'ph-storefront' : 'ph-tag' }}"></i></span>
                                            <div class="coin-vendor-card__identity">
                                                <strong>{{ $seller->username }}</strong>
                                                <span><i class="ph-fill {{ $isVendorListing ? 'ph-seal-check' : 'ph-user-circle' }}"></i> {{ $isVendorListing ? __('Verified vendor listing') : __('Member listing') }}</span>
                                            </div>
                                            <span class="coin-seller-tag {{ $isVendorListing ? 'is-vendor' : 'is-listing' }}">{{ $isVendorListing ? __('Vendor') : __('Listed') }}</span>
                                        </div>
                                        <div class="coin-vendor-card__rate">
                                            <span>@lang('Listing amount')</span>
                                            <strong><i class="ph-fill ph-coins"></i> {{ number_format($listing->amount, 0) }} <small>@lang('coins')</small></strong>
                                            <span>@lang('Listing') #{{ $listing->id }} · {{ $listing->created_at->diffForHumans() }}</span>
                                        </div>
                                        <div class="coin-vendor-card__meta">
                                            <span><i class="ph-fill ph-currency-circle-dollar"></i> {{ $listingRate->sell_price }} {{ $seller->currency->code }} @lang('per coin')</span>
                                            <span><i class="ph-fill ph-shield-check"></i> @lang('Escrow protected')</span>
                                        </div>
                                        <form method="post" action="{{ route('user.coin.listings.buy', $listing->id) }}" class="coin-vendor-card__form">
                                            @csrf
                                            <button type="submit" class="coin-btn coin-btn--primary"><i class="ph-bold ph-arrow-up-right"></i> @lang('Buy listing')</button>
                                        </form>
                                    </article>
                                @endif
                            @endforeach

                            @foreach($vendors as $vendor)
                                @php
                                    $vendorMax = (int) min($vendor['available_coins'], $vendor['per_trade_limit']);
                                    $paymentLabels = collect($vendor['payment_methods'] ?? [])->map(fn ($method) => ucwords(str_replace('_', ' ', $method)))->take(3);
                                @endphp
                                <article class="coin-vendor-card">
                                    <div class="coin-vendor-card__top">
                                        @if(!empty($vendor['avatar_url']))<img class="coin-vendor-card__avatar" src="{{ $vendor['avatar_url'] }}" alt="">@else<span class="coin-vendor-card__avatar coin-vendor-card__avatar--initial">{{ strtoupper(substr($vendor['name'], 0, 1)) }}</span>@endif
                                        <div class="coin-vendor-card__identity"><strong>{{ $vendor['name'] }}</strong><span><i class="ph-fill ph-seal-check"></i> @lang('Verified coin vendor')</span></div>
                                        <span class="coin-seller-tag is-vendor">@lang('Vendor offer')</span>
                                    </div>
                                    <div class="coin-vendor-card__rate"><span>@lang('Vendor rate')</span><strong>{{ number_format($vendor['rate_per_100'], 2) }} <small>{{ $vendor['currency_code'] }} / 100 @lang('coins')</small></strong><span>{{ number_format($vendor['rate_per_coin'], 4) }} {{ $vendor['currency_code'] }} @lang('per coin')</span></div>
                                    <div class="coin-vendor-card__meta"><span><i class="ph-fill ph-coins"></i> {{ number_format($vendor['available_coins'], 0) }} @lang('coins available')</span><span><i class="ph-fill ph-lightning"></i> @lang('Up to') {{ number_format($vendorMax, 0) }} @lang('per trade')</span></div>
                                    @if($paymentLabels->isNotEmpty())<div class="coin-vendor-card__methods">@foreach($paymentLabels as $label)<span>{{ $label }}</span>@endforeach</div>@endif
                                    <form method="post" action="{{ route('user.coin-vendor.buy') }}" class="coin-vendor-card__form">@csrf<input type="hidden" name="vendor_id" value="{{ $vendor['id'] }}"><label class="coin-field"><span>@lang('Coins to buy')</span><input type="number" name="amount" min="{{ $minimumTradeAmount }}" max="{{ $vendorMax }}" step="1" placeholder="{{ number_format($minimumTradeAmount) }}" required></label><button type="submit" class="coin-btn coin-btn--primary"><i class="ph-bold ph-arrow-up-right"></i> @lang('Start trade')</button></form>
                                </article>
                            @endforeach

                            @foreach($agents as $agent)
                                @php $agentMax = (int) min($agent['available_coins'], $agent['per_trade_limit'], $maximumTradeAmount); @endphp
                                <article class="coin-vendor-card">
                                    <div class="coin-vendor-card__top">
                                        @if(!empty($agent['avatar_url']))<img class="coin-vendor-card__avatar" src="{{ $agent['avatar_url'] }}" alt="">@else<span class="coin-vendor-card__avatar coin-vendor-card__avatar--initial">{{ strtoupper(substr($agent['name'], 0, 1)) }}</span>@endif
                                        <div class="coin-vendor-card__identity"><strong>{{ $agent['name'] }}</strong><span><i class="ph-fill ph-shield-check"></i> @lang('Verified payment agent')</span></div>
                                        <span class="coin-seller-tag is-agent">@lang('Agent offer')</span>
                                    </div>
                                    <div class="coin-vendor-card__rate"><span>@lang('Coin rate')</span><strong>{{ number_format($agent['rate_per_100'], 2) }} <small>{{ $agent['currency_code'] }} / 100 @lang('coins')</small></strong></div>
                                    <div class="coin-vendor-card__meta"><span><i class="ph-fill ph-coins"></i> {{ number_format($agent['available_coins'], 0) }} @lang('coins available')</span><span><i class="ph-fill ph-shield-check"></i> @lang('Protected agent trade')</span></div>
                                    <form method="post" action="{{ route('user.coin.buy') }}" class="coin-vendor-card__form">@csrf<input type="hidden" name="support_agent_id" value="{{ $agent['id'] }}"><label class="coin-field"><span>@lang('Coins to buy')</span><input type="number" name="amount" min="{{ $minimumTradeAmount }}" max="{{ $agentMax }}" step="1" placeholder="{{ number_format($minimumTradeAmount) }}" required></label><button type="submit" class="coin-btn coin-btn--primary"><i class="ph-bold ph-arrow-up-right"></i> @lang('Start trade')</button></form>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="coin-empty coin-empty--vendor"><span><i class="ph-fill ph-storefront"></i></span><div><strong>@lang('No coin offers are available right now')</strong><p>@lang('New agent offers, vendor offers, and eligible member listings will appear here.')</p></div></div>
                    @endif
                </div>

                <div class="coin-section">
                    <div class="coin-section__head">
                        <div><h2>@lang('Your trades')</h2></div>
                        <span class="coin-section__icon"><i class="ph-fill ph-arrows-left-right"></i></span>
                    </div>
                    @if($myTrades->count())
                        <div class="coin-list">
                            @foreach($myTrades as $trade)
                                <a href="{{ route('user.coin.trade', $trade->id) }}" class="coin-list__row">
                                    <span class="coin-list__avatar"><i class="ph-fill ph-arrows-left-right"></i></span>
                                    <div class="coin-list__body">
                                        <span class="coin-list__title">@lang('Trade') #{{ $trade->id }}</span>
                                        <span class="coin-list__subtitle">{{ ucfirst(str_replace('_', ' ', $trade->direction)) }}</span>
                                    </div>
                                    <div class="coin-list__trailing">
                                        <span class="coin-list__amount">{{ number_format($trade->amount, 0) }}<small>@lang('coins')</small></span>
                                        <span class="coin-badge coin-badge--{{ $trade->status }}">{{ str_replace('_', ' ', $trade->status) }}</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                        <div class="mt-3">{{ $myTrades->links() }}</div>
                    @else
                        <div class="coin-empty">
                            <i class="ph-fill ph-arrows-left-right"></i>
                            <p>@lang("No trades yet — list some coins for sale or buy from an agent above to get started.")</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
