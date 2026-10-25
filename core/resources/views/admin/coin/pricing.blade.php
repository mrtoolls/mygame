@extends('admin.layouts.app')
@section('panel')
<div class="coin-pricing-page">
    <div class="card mb-3"><div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3"><div><h5 class="mb-1">Coin Pricing</h5><p class="text-muted mb-0">Set the platform-wide margins and review every enabled currency's coin price.</p></div><a class="btn btn-sm btn-outline--dark" href="{{ route('admin.coin.settings') }}">Coin Settings</a></div>
        <p class="coin-pricing-peg">1 coin = ${{ number_format((float) ($general->coin_peg_usd ?? 0.01), 2) }}. Local prices come from the exchange rate plus your margin.</p>
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
            <form class="d-flex flex-wrap align-items-end gap-2" method="post" action="{{ route('admin.coin.pricing.margins') }}">@csrf
                <label class="coin-pricing-field">Buy margin %<input class="form-control" type="number" name="buy_margin_percent" min="0" max="100" step="0.01" value="{{ $general->coin_default_buy_margin_percent ?? 5 }}" required></label>
                <label class="coin-pricing-field">Sell margin %<input class="form-control" type="number" name="sell_margin_percent" min="0" max="100" step="0.01" value="{{ $general->coin_default_sell_margin_percent ?? 2 }}" required></label>
                <button class="btn btn--primary" type="submit">Save margins</button>
            </form>
            <form method="post" action="{{ route('admin.coin.pricing.refresh') }}">@csrf<button class="btn btn--success" type="submit"><i class="las la-sync"></i> Refresh rates and recompute prices</button></form>
        </div>
    </div></div>

    @if(session('coin_refresh_error'))<div class="alert alert--danger" role="alert">{{ session('coin_refresh_error') }}</div>@endif
    @if(session('coin_refresh_result'))
        @php($refreshResult = session('coin_refresh_result'))
        <div class="alert alert--success" role="status">Rates refreshed using the {{ $refreshResult['source'] }} endpoint. {{ $refreshResult['updated'] }} currency rates checked; prices recomputed. <a href="{{ route('admin.currency-country.index') }}">View rate source and currency details</a>.</div>
    @endif
    @if(session('coin_pricing_results'))
        @php($pricingResults = session('coin_pricing_results'))
        @php($pausedCount = collect($pricingResults)->where('ok', false)->count())
        <div class="alert alert--{{ $pausedCount ? 'warning' : 'success' }}" role="status">{{ $pausedCount ? $pausedCount . ' currencies need an exchange-rate fix; see the status column below.' : 'Coin prices recomputed for every enabled currency.' }}</div>
    @endif

    <div class="card"><div class="card-body">
        <h5 class="mb-1">Prices by currency</h5><p class="text-muted">Buy is the player price. Sell is the platform's coin buyback price. Preview totals use each row's rounded per-coin price.</p>
        <div class="table-responsive coin-pricing-table-wrap"><table class="table table--responsive--md align-middle coin-pricing-table"><thead><tr><th>Currency</th><th>1 USD =</th><th>Rate source / updated</th><th>Buy / coin</th><th>Sell / coin</th><th>Margin overrides</th><th>Status</th><th>Preview (buy)</th></tr></thead><tbody>
        @forelse($currencies as $currency)
            @php($rate = $rates->get($currency->id))
            @php($fxUsdish = $currency->rate_to_usd && (float) $currency->rate_to_usd > 0 ? 1 / (float) $currency->rate_to_usd : null)
            @php($isFresh = $currency->rate_to_usd && $currency->last_updated_at && $currency->last_updated_at->gte(now()->subHours((int) ($general->coin_rate_stale_hours ?? 48))))
            @php($isPriced = $isFresh && $rate && $rate->status === 'active' && $rate->buy_price !== null && $rate->sell_price !== null)
            <tr>
                <td><strong>{{ $currency->code }}</strong><small class="d-block text-muted">{{ $currency->name }}</small></td>
                <td>{{ $fxUsdish === null ? '—' : number_format($fxUsdish, max(2, min(6, $currency->decimals + 2)), '.', ',') . ' ' . $currency->code }}</td>
                <td>{{ $currency->rate_source === 'manual' ? 'Manual' : 'ExchangeRate-API' }}<small class="d-block text-muted">{{ $currency->last_updated_at?->format('Y-m-d H:i') ?? 'No update time' }}</small></td>
                <td>{{ $rate?->buy_price === null ? '—' : $currency->symbol . number_format((float) $rate->buy_price, $currency->decimals, '.', ',') }}</td>
                <td>{{ $rate?->sell_price === null ? '—' : $currency->symbol . number_format((float) $rate->sell_price, $currency->decimals, '.', ',') }}</td>
                <td><form method="post" action="{{ route('admin.coin.pricing.currency.margins', $currency->id) }}" class="coin-pricing-overrides">@csrf
                    <label>Buy <input class="form-control form-control-sm" type="number" name="buy_margin_override" min="0" max="100" step="0.01" value="{{ $rate?->buy_margin_override }}" placeholder="{{ $general->coin_default_buy_margin_percent ?? 5 }}% global"></label>
                    <label>Sell <input class="form-control form-control-sm" type="number" name="sell_margin_override" min="0" max="100" step="0.01" value="{{ $rate?->sell_margin_override }}" placeholder="{{ $general->coin_default_sell_margin_percent ?? 2 }}% global"></label>
                    <button class="btn btn-sm btn-outline--primary" type="submit">Save</button>
                </form></td>
                <td>@if($isPriced)<span class="badge badge--success">Active</span>@else<span class="badge badge--danger">Paused</span><small class="coin-pricing-reason d-block">@if(!$currency->rate_to_usd)No rate. Refresh rates or enter a manual rate.@elseif(!$currency->last_updated_at)Missing update time. Click Refresh rates.@elseif(!$isFresh)Rate is older than {{ (int) ($general->coin_rate_stale_hours ?? 48) }} hours. Click Refresh rates.@else{{ $rate?->pause_reason ?? 'Price not computed. Click Refresh rates and recompute prices.' }}@endif</small>@endif</td>
                <td>@if($rate?->buy_price !== null){{ $currency->symbol }}{{ number_format((float) $rate->buy_price * 100, $currency->decimals, '.', ',') }} / {{ $currency->symbol }}{{ number_format((float) $rate->buy_price * 1000, $currency->decimals, '.', ',') }} / {{ $currency->symbol }}{{ number_format((float) $rate->buy_price * 10000, $currency->decimals, '.', ',') }}<small class="d-block text-muted">100 / 1,000 / 10,000 coins</small>@else—@endif</td>
            </tr>
            <tr class="coin-pricing-rate-row"><td colspan="8"><details><summary>Manual rate override for {{ $currency->code }}</summary><form method="post" action="{{ route('admin.currency-country.currency.manual-rate', $currency) }}" class="coin-pricing-manual-rate">@csrf<label>Value of 1 {{ $currency->code }} in USD<input class="form-control" type="number" name="rate_to_usd" min="0.000000000001" step="0.000000000001" value="{{ $currency->rate_to_usd }}" required></label><label>Reason (required)<input class="form-control" name="reason" maxlength="500" required></label><button class="btn btn-sm btn-outline--warning" type="submit">Save manual rate</button><small>Manual overrides are logged and stop automatic refresh for this currency until enabled again.</small></form></details></td></tr>
        @empty<tr><td colspan="8" class="text-center">No enabled currencies.</td></tr>@endforelse
        </tbody></table></div>
    </div></div>
</div>
<style>
.coin-pricing-peg{padding:11px 14px;border-inline-start:3px solid #00c878;background:rgba(0,200,120,.07);border-radius:4px;color:#d8e0f5}
.coin-pricing-field{display:grid;gap:5px;min-width:130px;font-size:12px;font-weight:700}
.coin-pricing-overrides{display:grid;grid-template-columns:repeat(2,minmax(80px,1fr));gap:5px;min-width:180px}.coin-pricing-overrides label,.coin-pricing-manual-rate label{display:grid;gap:4px;font-size:11px}
.coin-pricing-reason{max-width:230px;color:#e6b45e}.coin-pricing-rate-row>td{padding-block:5px;border-top:0}.coin-pricing-manual-rate{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));align-items:end;gap:10px;padding:12px;background:rgba(20,26,45,.45);border-radius:9px}.coin-pricing-manual-rate small{grid-column:1/-1;color:#9aa4c2}
@media(max-width:767px){.coin-pricing-page .card-body{padding:14px}.coin-pricing-table{min-width:1000px}.coin-pricing-manual-rate{grid-template-columns:1fr}.coin-pricing-manual-rate small{grid-column:auto}}
</style>
@endsection
