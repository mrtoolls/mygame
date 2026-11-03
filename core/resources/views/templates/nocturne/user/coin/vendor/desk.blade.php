@extends('Template::layouts.master')
@section('content')
    <link rel="stylesheet" href="{{ asset('assets/global/css/coin-marketplace.css') }}?v={{ @filemtime('assets/global/css/coin-marketplace.css') }}">
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content coin-marketplace-content">
            <h1 class="nocturne-page-title">@lang('Vendor Desk')</h1>

            <div class="coin-page">
                <div class="coin-hero">
                    <div class="coin-hero__copy">
                        <span class="coin-hero__eyebrow"><i class="ph-fill ph-storefront"></i> @lang('Coin economy · Vendor account')</span>
                        <p style="margin:8px 0 0;color:var(--nocturne-muted);font-size:13.5px;line-height:1.55;max-width:46ch">@lang('Manage your coin availability, pricing, payment details and trades. Changes to your rate apply to new trades only.')</p>
                        <form method="post" action="{{ route('user.coin-vendor.selling') }}" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:16px">
                            @csrf
                            <button type="submit" class="coin-btn coin-btn--primary"><i class="ph-bold ph-storefront"></i> {{ $vendor->is_selling ? __('Pause new sales') : __('Start selling coins') }}</button>
                            <span style="color:var(--nocturne-muted);font-size:12.5px">{{ $vendor->is_selling ? __('Buyers can see your offer.') : __('Your offer is hidden from buyers.') }}</span>
                        </form>
                    </div>
                    <span class="coin-hero__icon"><i class="ph-fill ph-storefront"></i></span>
                </div>

                <div class="coin-stats">
                    <div class="coin-stat"><span>@lang('Available coins')</span><strong>{{ showAmount($wallet->available_balance,0) }}</strong></div>
                    <div class="coin-stat"><span>@lang('Trade escrow')</span><strong>{{ showAmount(bcsub((string) $wallet->held_balance,(string) $wallet->vendor_bond_balance,8),0) }}</strong></div>
                    <div class="coin-stat"><span>@lang('Security bond')</span><strong>{{ showAmount($wallet->vendor_bond_balance,0) }}</strong></div>
                    <div class="coin-stat"><span>@lang('Open trades')</span><strong>{{ $openTrades }}</strong></div>
                    <div class="coin-stat"><span>@lang('Completed trades')</span><strong>{{ $vendor->completed_trades }}</strong></div>
                    <div class="coin-stat"><span>@lang('Reliability')</span><strong>{{ number_format($vendor->reliability_score,0) }}%</strong></div>
                    <div class="coin-stat"><span>@lang('Avg. release')</span><strong>{{ $vendor->average_release_minutes }} @lang('min')</strong></div>
                    <div class="coin-stat"><span>@lang('Daily limit used')</span><strong>{{ showAmount($dailyUsed,0) }} / {{ showAmount($vendor->daily_limit,0) }}</strong></div>
                </div>

                <div class="coin-section">
                    <div class="coin-section__head">
                        <div>
                            <h2>@lang('Your sell rate')</h2>
                            <p>@lang('Allowed range'): {{ $band ? number_format($band['min'],4) : '—' }} – {{ $band ? number_format($band['max'],4) : '—' }} {{ $vendor->currency?->code }} @lang('per coin'). @lang('Platform sell reference is a hard floor.')</p>
                        </div>
                        <span class="coin-section__icon"><i class="ph-fill ph-tag"></i></span>
                    </div>
                    <form method="post" action="{{ route('user.coin-vendor.rate') }}" class="coin-form">
                        @csrf
                        <div class="coin-field coin-field--grow">
                            <label>@lang('Rate per coin') ({{ $vendor->currency?->code }})</label>
                            <input type="number" name="sell_rate" step="0.00000001" min="0.00000001" value="{{ old('sell_rate',$vendor->sell_rate) }}" required>
                        </div>
                        <button type="submit" class="coin-btn coin-btn--primary">@lang('Save rate')</button>
                    </form>
                    <p style="margin:12px 0 0;color:var(--nocturne-muted);font-size:12.5px">@lang('Per 100'): {{ $vendor->currency?->code }} {{ showAmount((float) $vendor->sell_rate * 100,2) }} · @lang('Per 1,000'): {{ $vendor->currency?->code }} {{ showAmount((float) $vendor->sell_rate * 1000,2) }}</p>
                </div>

                <div class="coin-section">
                    <div class="coin-section__head">
                        <div>
                            <h2>@lang('Payment methods')</h2>
                            <p>@lang('Details are encrypted and may be shared with your trade partner from the trade chat.')</p>
                        </div>
                        <span class="coin-section__icon"><i class="ph-fill ph-credit-card"></i></span>
                    </div>
                    <form method="post" action="{{ route('user.coin-vendor.payment-methods') }}">
                        @csrf
                        @foreach(($vendor->encrypted_payment_methods ?: [['type' => 'bank', 'label' => '', 'details' => '']]) as $index => $method)
                            <div class="coin-pm-row">
                                <div class="coin-field">
                                    <label>@lang('Type')</label>
                                    <select name="payment_methods[{{ $index }}][type]">
                                        <option value="bank" @selected(($method['type'] ?? '') === 'bank')>@lang('Bank transfer')</option>
                                        <option value="mobile_money" @selected(($method['type'] ?? '') === 'mobile_money')>@lang('Mobile money')</option>
                                        <option value="crypto" @selected(($method['type'] ?? '') === 'crypto')>@lang('Crypto')</option>
                                    </select>
                                </div>
                                <div class="coin-field coin-field--grow">
                                    <label>@lang('Display name')</label>
                                    <input name="payment_methods[{{ $index }}][label]" placeholder="@lang('Display name')" value="{{ $method['label'] ?? '' }}">
                                </div>
                                <div class="coin-field coin-field--grow">
                                    <label>@lang('Payment instructions')</label>
                                    <input name="payment_methods[{{ $index }}][details]" placeholder="@lang('Payment instructions')" value="{{ $method['details'] ?? '' }}">
                                </div>
                            </div>
                        @endforeach
                        <button type="submit" class="coin-btn coin-btn--primary" style="margin-top:14px">@lang('Save payment method')</button>
                    </form>
                </div>

                <div class="coin-section">
                    <div class="coin-section__head">
                        <div>
                            <h2>@lang('Buy from member listings')</h2>
                            <p>@lang('These member listings use their locked coin sell reference. Your own listings are hidden.')</p>
                        </div>
                        <span class="coin-section__icon"><i class="ph-fill ph-list-bullets"></i></span>
                    </div>
                    @forelse($listings as $listing)
                        <div class="coin-list">
                            <div class="coin-list__row">
                                <span class="coin-list__avatar"><i class="ph-fill ph-coin"></i></span>
                                <div class="coin-list__body">
                                    <span class="coin-list__title">{{ showAmount($listing->amount,0) }} @lang('coins')</span>
                                    <span class="coin-list__subtitle">@lang('Listing') #{{ $listing->id }} · {{ $listing->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="coin-list__trailing">
                                    <form method="post" action="{{ route('user.coin-vendor.listings.claim',$listing->id) }}">
                                        @csrf
                                        <button type="submit" class="coin-btn coin-btn--outline coin-btn--sm">@lang('Buy this listing')</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="coin-empty"><i class="ph-fill ph-coin"></i><p>@lang('No member listings are available right now.')</p></div>
                    @endforelse
                    <div class="mt-3">{{ $listings->links() }}</div>
                </div>

                <div class="coin-section">
                    <div class="coin-section__head">
                        <div><h2>@lang('Your trades')</h2></div>
                        <span class="coin-section__icon"><i class="ph-fill ph-arrows-left-right"></i></span>
                    </div>
                    @forelse($trades as $trade)
                        <div class="coin-list">
                            <a href="{{ route('user.coin.trade',$trade->id) }}" class="coin-list__row">
                                <span class="coin-list__avatar"><i class="ph-fill ph-arrows-left-right"></i></span>
                                <div class="coin-list__body">
                                    <span class="coin-list__title">@lang('Trade') #{{ $trade->id }}</span>
                                    <span class="coin-list__subtitle">{{ $trade->coin_seller_id == (int) auth()->id() ? __('Selling') : __('Buying') }} · {{ $trade->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="coin-list__trailing">
                                    <span class="coin-list__amount">{{ showAmount($trade->amount,0) }}<small>@lang('coins')</small></span>
                                    <span class="coin-badge coin-badge--{{ $trade->status }}">{{ str_replace('_',' ',ucfirst($trade->status)) }}</span>
                                </div>
                            </a>
                        </div>
                    @empty
                        <div class="coin-empty"><i class="ph-fill ph-arrows-left-right"></i><p>@lang('No trades yet.')</p></div>
                    @endforelse
                    <div class="mt-3">{{ $trades->links() }}</div>
                </div>

                <div class="coin-section">
                    <div class="coin-section__head">
                        <div>
                            <h2>@lang('Close your vendor account')</h2>
                            <p>@lang('Resigning stops new offers. Open trades must still be resolved. Your bond stays reserved for :days days and requires admin approval to release.', ['days' => (int) (gs('coin_vendor_bond_cooldown_days') ?? 14)])</p>
                        </div>
                        <span class="coin-section__icon"><i class="ph-fill ph-x-circle"></i></span>
                    </div>
                    <form method="post" action="{{ route('user.coin-vendor.resign') }}" onsubmit="return confirm('{{ __('Stop taking new vendor trades and begin the bond cooldown?') }}')">
                        @csrf
                        <button type="submit" class="coin-btn coin-btn--danger">@lang('Resign as vendor')</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
