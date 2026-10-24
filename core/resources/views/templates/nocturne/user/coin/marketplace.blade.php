@extends('Template::layouts.master')
@section('content')
    <link rel="stylesheet" href="{{ asset('assets/global/css/coin-marketplace.css') }}?v={{ @filemtime('assets/global/css/coin-marketplace.css') }}">
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content">
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

                <div class="coin-section">
                    <div class="coin-section__head">
                        <div>
                            <h2>@lang('Buy coins from an agent')</h2>
                            <p>@lang('Pick a verified agent who is currently selling.')</p>
                        </div>
                        <span class="coin-section__icon"><i class="ph-fill ph-user-circle-plus"></i></span>
                    </div>
                    @if($agents->count())
                        <form method="post" action="{{ route('user.coin.buy') }}" class="coin-form mb-3">
                            @csrf
                            <div class="coin-field coin-field--grow">
                                <label>@lang('Agent')</label>
                                <select name="support_agent_id" required>
                                    @foreach($agents as $agent)
                                        <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="coin-field">
                                <label>@lang('Amount (coins)')</label>
                                <input type="number" step="1" min="1" name="amount" required>
                            </div>
                            <button type="submit" class="coin-btn coin-btn--primary"><i class="ph-bold ph-shopping-cart-simple"></i> @lang('Buy')</button>
                        </form>
                        <div class="coin-agent-grid">
                            @foreach($agents as $agent)
                                <div class="coin-agent-card">
                                    <span class="coin-agent-card__avatar">{{ strtoupper(substr($agent->name, 0, 1)) }}</span>
                                    <strong>{{ $agent->name }}</strong>
                                    <span class="coin-badge coin-badge--completed"><i class="ph-fill ph-check-circle"></i> @lang('Selling')</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="coin-empty">
                            <i class="ph-fill ph-smiley-sad"></i>
                            <p>@lang('No agents are currently selling coins. Check back soon.')</p>
                        </div>
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
