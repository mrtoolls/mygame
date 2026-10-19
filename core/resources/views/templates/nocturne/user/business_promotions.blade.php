@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account nocturne-business-promo-page">
    @include('Template::partials.account_nav')
    <main class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Promote a Business')</span></div>
        <section class="nocturne-business-promo-hero">
            <div><span class="nocturne-vip-kicker"><i class="ph-fill ph-megaphone-simple"></i> @lang('WINXPOT BUSINESS SPOTLIGHT')</span><h1>@lang('Put your business in the spotlight')</h1><p>@lang('Create a polished campaign for the Winxpot community. Choose how long it runs, pay the price set for your wallet currency, and submit it for review.')</p></div>
            <span class="nocturne-business-promo-hero__art"><i class="ph-fill ph-storefront"></i><i class="ph-fill ph-sparkle"></i></span>
        </section>

        <div class="nocturne-business-promo-layout">
            <section class="nocturne-business-promo-form-card">
                <div class="nocturne-vip-section-head"><div><span class="nocturne-vip-kicker">@lang('CREATE CAMPAIGN')</span><h2>@lang('Promotion details')</h2><p>@lang('Your campaign is published after admin approval.')</p></div></div>
                @if (!$currency || !$rates)
                    <div class="nocturne-business-promo-empty"><i class="ph-fill ph-wallet"></i><strong>@lang('Business promotions are not available yet')</strong><span>@lang('Pricing has not been configured for your wallet currency. Please check back later.')</span></div>
                @else
                    @if ($errors->any())
                        <div class="nocturne-business-promo-errors" role="alert"><strong>@lang('Please review the following')</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                    @endif
                    @php
                        $prices = [
                            'day' => (int) $rates->daily_price_minor,
                            'week' => (int) $rates->weekly_price_minor,
                            'month' => (int) $rates->monthly_price_minor,
                        ];
                        $enabledPrices = array_filter($prices, fn ($price) => $price > 0);
                    @endphp
                    @if (empty($enabledPrices))
                        <div class="nocturne-business-promo-empty"><i class="ph-fill ph-wallet"></i><strong>@lang('Business promotions are not available yet')</strong><span>@lang('Pricing has not been configured for your wallet currency. Please check back later.')</span></div>
                    @else
                        <form method="post" action="{{ route('user.business-promotions.store') }}" enctype="multipart/form-data" class="nocturne-business-promo-form" id="business-promotion-form" data-prices='@json($prices)' data-decimals="{{ $currency->decimals }}" data-symbol="{{ $currency->symbol }}" data-position="{{ $currency->symbol_position }}">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ $submissionKey }}">
                            <div class="nocturne-business-promo-fields">
                                <label><span>@lang('Business name')</span><input type="text" name="business_name" maxlength="120" value="{{ old('business_name') }}" placeholder="@lang('Your business or brand')" required></label>
                                <label><span>@lang('Promotion title')</span><input type="text" name="title" maxlength="160" value="{{ old('title') }}" placeholder="@lang('Give your offer a clear title')" required></label>
                                <label class="is-wide"><span>@lang('Description')</span><textarea name="description" rows="4" maxlength="1200" placeholder="@lang('Tell players what makes this business or offer special')" required>{{ old('description') }}</textarea></label>
                                <label class="is-wide"><span>@lang('Website or destination link')</span><input type="url" name="destination_url" value="{{ old('destination_url') }}" placeholder="https://example.com" required></label>
                                <label class="is-wide"><span>@lang('Campaign image') <small>@lang('JPG, PNG or WebP · up to 5 MB')</small></span><input type="file" name="image" accept="image/jpeg,image/png,image/webp" required></label>
                                <label><span>@lang('Campaign duration')</span><select name="duration" id="business-promotion-duration" required>@foreach ($enabledPrices as $duration => $price)<option value="{{ $duration }}" @selected(old('duration', array_key_first($enabledPrices)) === $duration)>{{ __($duration === 'day' ? '1 day' : ($duration === 'week' ? '1 week' : '1 month')) }} · {{ $currency->formatMinor($price, true) }}</option>@endforeach</select></label>
                                <div class="nocturne-business-promo-price"><span>@lang('Campaign total')</span><strong id="business-promotion-total">{{ $currency->formatMinor(reset($enabledPrices), true) }}</strong><small>@lang('Paid from your wallet now. Admin review is required before publishing.')</small></div>
                            </div>
                            <div class="nocturne-business-promo-consent"><i class="ph-fill ph-shield-check"></i><span>@lang('Campaign content must follow our policies. The selected duration starts when your promotion is approved. If an admin rejects it, the payment is refunded to your wallet.')</span></div>
                            <button type="submit" class="nocturne-business-promo-submit"><i class="ph-bold ph-lock-key"></i>@lang('Pay and submit for review')</button>
                        </form>
                    @endif
                @endif
            </section>

            <aside class="nocturne-business-promo-side">
                <article><i class="ph-fill ph-currency-circle-dollar"></i><strong>@lang('Clear pricing')</strong><p>@lang('Daily, weekly and monthly prices are set by the Winxpot admin and shown in your wallet currency before you pay.')</p></article>
                <article><i class="ph-fill ph-check-circle"></i><strong>@lang('Reviewed before publishing')</strong><p>@lang('Our team checks every submission. Approved promotions appear in the public Promotions page for the period you selected.')</p></article>
            </aside>
        </div>

        <section class="nocturne-business-promo-history">
            <div class="nocturne-vip-section-head"><div><span class="nocturne-vip-kicker">@lang('YOUR CAMPAIGNS')</span><h2>@lang('Promotion history')</h2><p>@lang('Track review status and active campaign dates.')</p></div></div>
            <div class="nocturne-business-promo-list">
                @forelse ($promotions as $promotion)
                    @php $displayStatus = $promotion->status === 'active' && $promotion->ends_at?->isPast() ? 'expired' : $promotion->status; @endphp
                    <article class="nocturne-business-promo-row">
                        <img src="{{ getImage('assets/images/business-promotions/' . $promotion->image) }}" alt="" loading="lazy">
                        <div class="nocturne-business-promo-row__copy"><strong>{{ $promotion->title }}</strong><span>{{ $promotion->business_name }} · {{ __(ucfirst($promotion->duration)) }} · {{ $promotion->currency->formatMinor($promotion->amount_minor, true) }}</span>@if($promotion->status === 'active')<small>{{ __('Runs :start to :end', ['start' => showDateTime($promotion->starts_at, 'M d, Y'), 'end' => showDateTime($promotion->ends_at, 'M d, Y')]) }}</small>@elseif($promotion->admin_note)<small>{{ $promotion->admin_note }}</small>@endif</div>
                        <span class="nocturne-business-promo-status is-{{ $displayStatus }}">{{ __(ucfirst($displayStatus)) }}</span>
                    </article>
                @empty
                    <div class="nocturne-business-promo-empty"><i class="ph-fill ph-megaphone-simple"></i><strong>@lang('Your campaigns will appear here')</strong><span>@lang('Submit your first business promotion to get started.')</span></div>
                @endforelse
            </div>
            @if ($promotions->hasPages())<div class="mt-3">{{ $promotions->links() }}</div>@endif
        </section>
    </main>
</div>
@endsection

@push('script')
<script>
(() => {
    const form = document.getElementById('business-promotion-form');
    if (!form) return;
    const duration = document.getElementById('business-promotion-duration');
    const output = document.getElementById('business-promotion-total');
    const prices = JSON.parse(form.dataset.prices || '{}');
    const decimals = Number(form.dataset.decimals || 2);
    const fmt = new Intl.NumberFormat(undefined, {minimumFractionDigits: decimals, maximumFractionDigits: decimals});
    const render = () => {
        const amount = Number(prices[duration.value] || 0) / (10 ** decimals);
        const value = fmt.format(amount);
        output.textContent = form.dataset.position === 'right' ? `${value} ${form.dataset.symbol}` : `${form.dataset.symbol}${value}`;
    };
    duration.addEventListener('change', render);
})();
</script>
@endpush
