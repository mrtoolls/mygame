@props(['available' => 0, 'held' => null, 'approx' => false, 'showHeld' => false, 'currency' => null, 'size' => 'md'])
@php
    $approxText = null;
    if ($approx) {
        try {
            $currency = $currency ?: auth()->user()?->currency;
            if ($currency && $currency->enabled) {
                $rate = \App\Models\CoinRate::where('currency_id', $currency->id)->first();
                if ($rate?->sell_price) {
                    $minor = app(\App\Support\Money::class)->toMinor(bcmul((string) $available, (string) $rate->sell_price, 2), min(3, (int) $currency->decimals));
                    $approxText = $currency->formatMinor($minor, false);
                }
            }
        } catch (\Throwable) {
            $approxText = null;
        }
    }
@endphp
<span class="coin-chip coin-chip--{{ $size }}">
    <i class="ph-fill ph-coin coin-chip__icon" aria-hidden="true"></i>
    <span class="coin-chip__value">{{ number_format((float) $available, 0) }}</span>
    <span class="coin-chip__label">@lang('Coins')</span>
    @if($showHeld && $held !== null && (float) $held > 0)
        <span class="coin-chip__held">+{{ number_format((float) $held, 0) }} @lang('held')</span>
    @endif
    @if($approxText)
        <span class="coin-chip__approx">@lang('approx.') {{ $approxText }}</span>
    @endif
</span>
