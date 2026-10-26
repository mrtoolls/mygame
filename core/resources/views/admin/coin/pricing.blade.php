@extends('admin.layouts.app')

@section('panel')
@php
    $staleHours = (int) ($general->coin_rate_stale_hours ?? 48);
    $pricedCount = $currencies->filter(function ($currency) use ($rates, $staleHours) {
        $rate = $rates->get($currency->id);
        return $rate && $rate->status === 'active' && $rate->buy_price !== null && $rate->sell_price !== null
            && $currency->rate_to_usd && $currency->last_updated_at
            && $currency->last_updated_at->gte(now()->subHours($staleHours));
    })->count();
@endphp

<div class="coin-pricing-page">
    <section class="cp-hero">
        <div class="cp-hero__copy">
            <span class="cp-eyebrow"><i class="las la-coins"></i> Coin Economy <span>/</span> Pricing</span>
            <h1>Coin Pricing</h1>
            <p>Set the price players pay and the price vendors receive in every enabled currency.</p>
            <div class="cp-peg"><span class="cp-peg__icon"><i class="las la-link"></i></span><span><strong>1 coin = ${{ number_format((float) ($general->coin_peg_usd ?? 0.01), 2) }}</strong><small>Local prices use the exchange rate and your margins.</small></span></div>
        </div>
        <div class="cp-hero__side">
            <div class="cp-health"><span class="cp-health__dot"></span><div><strong>{{ $pricedCount }} / {{ $currencies->count() }} currencies priced</strong><small>{{ $currencies->count() - $pricedCount }} need attention or a rate refresh</small></div></div>
            <a class="cp-back" href="{{ route('admin.coin.settings') }}"><i class="las la-arrow-left"></i> Coin Settings</a>
        </div>
    </section>

    @if($errors->any())
        <div class="alert alert--danger cp-alert" role="alert">{{ $errors->first() }}</div>
    @endif
    @if(session('coin_refresh_error'))<div class="alert alert--danger cp-alert" role="alert">{{ session('coin_refresh_error') }}</div>@endif
    @if(session('coin_refresh_result'))
        @php($refreshResult = session('coin_refresh_result'))
        <div class="alert alert--success cp-alert" role="status">Rates refreshed using {{ $refreshResult['source'] }}; {{ $refreshResult['updated'] }} currency rates checked and prices recomputed. <a href="{{ route('admin.currency-country.index') }}">Rate source details</a>.</div>
    @endif
    @if(session('coin_pricing_results'))
        @php($pricingResults = session('coin_pricing_results'))
        @php($pausedCount = collect($pricingResults)->where('ok', false)->count())
        <div class="alert alert--{{ $pausedCount ? 'warning' : 'success' }} cp-alert" role="status">{{ $pausedCount ? $pausedCount . ' currencies need attention. See their rate status below.' : 'Prices recomputed for all enabled currencies.' }}</div>
    @endif

    <section class="cp-controls">
        <div class="cp-section-heading"><div><span class="cp-step">01</span><div><h2>Default pricing margins</h2><p>These margins apply to every currency without its own override.</p></div></div></div>
        <div class="cp-controls__body">
            <form method="post" action="{{ route('admin.coin.pricing.margins') }}" class="cp-global-form">
                @csrf
                <label><span>Buy margin <small>Added to the base coin price</small></span><div class="cp-input-wrap"><input type="number" name="buy_margin_percent" min="0" max="100" step="0.01" value="{{ old('buy_margin_percent', $general->coin_default_buy_margin_percent ?? 5) }}" required><b>%</b></div></label>
                <label><span>Sell margin <small>Deducted from the base coin price</small></span><div class="cp-input-wrap"><input type="number" name="sell_margin_percent" min="0" max="100" step="0.01" value="{{ old('sell_margin_percent', $general->coin_default_sell_margin_percent ?? 2) }}" required><b>%</b></div></label>
                <button class="cp-button cp-button--primary" type="submit"><i class="las la-check"></i> Save margins &amp; update prices</button>
            </form>
            <div class="cp-refresh-box"><span class="cp-refresh-box__icon"><i class="las la-sync-alt"></i></span><div><strong>Exchange rates changed?</strong><small>Refresh rates and recalculate every currency price.</small></div><form method="post" action="{{ route('admin.coin.pricing.refresh') }}">@csrf<button class="cp-button cp-button--secondary" type="submit">Refresh &amp; recompute</button></form></div>
        </div>
        <div class="cp-how"><i class="las la-info-circle"></i><span><strong>How to set a price:</strong> change these defaults or enter a currency-specific margin below. Save to apply immediately. Buy price is the player’s cost; sell price is the platform/vendor buyback reference. Prices are calculated from the peg and FX rate, rather than entered as arbitrary amounts.</span></div>
    </section>

    <section class="cp-currencies">
        <div class="cp-section-heading cp-section-heading--currencies"><div><span class="cp-step">02</span><div><h2>Prices by currency</h2><p>Review the live rate, current coin prices, and local margin controls.</p></div></div><span class="cp-count">{{ $currencies->count() }} currencies</span></div>

        @forelse($currencies as $currency)
            @php
                $rate = $rates->get($currency->id);
                $fxUsdish = $currency->rate_to_usd && (float) $currency->rate_to_usd > 0 ? 1 / (float) $currency->rate_to_usd : null;
                $isFresh = $currency->rate_to_usd && $currency->last_updated_at && $currency->last_updated_at->gte(now()->subHours($staleHours));
                $isPriced = $isFresh && $rate && $rate->status === 'active' && $rate->buy_price !== null && $rate->sell_price !== null;
            @endphp
            <article class="cp-currency-card {{ $isPriced ? '' : 'is-paused' }}">
                <header class="cp-currency-card__head">
                    <div class="cp-currency-id"><span class="cp-currency-symbol">{{ $currency->code }}</span><div><h3>{{ $currency->name }}</h3><small>{{ $currency->decimals }} decimal places <span>·</span> {{ $currency->symbol }}</small></div></div>
                    @if($isPriced)<span class="cp-status cp-status--active"><i class="las la-check-circle"></i> Active</span>@else<span class="cp-status cp-status--paused"><i class="las la-exclamation-circle"></i> Paused</span>@endif
                </header>

                <div class="cp-fx-line">
                    <div><span class="cp-label">Exchange rate</span><strong>{{ $fxUsdish === null ? 'No rate available' : '1 USD = ' . number_format($fxUsdish, max(2, min(6, $currency->decimals + 2)), '.', ',') . ' ' . $currency->code }}</strong></div>
                    <div class="cp-rate-meta"><span class="cp-source {{ $currency->rate_source === 'manual' ? 'cp-source--manual' : '' }}"><i class="las {{ $currency->rate_source === 'manual' ? 'la-user-edit' : 'la-cloud' }}"></i> {{ $currency->rate_source === 'manual' ? 'Manual rate' : 'ExchangeRate-API' }}</span><small>Updated {{ $currency->last_updated_at?->format('M j, Y H:i') ?? 'Never' }}</small></div>
                </div>

                @if(!$isPriced)
                    <div class="cp-paused-note"><i class="las la-info-circle"></i><span>@if(!$currency->rate_to_usd)No exchange rate is set. Refresh rates or add a manual rate below.@elseif(!$currency->last_updated_at)The rate has no update time. Refresh rates to restore pricing.@elseif(!$isFresh)The rate is older than {{ $staleHours }} hours. Refresh rates to restore pricing.@else{{ $rate?->pause_reason ?? 'Price not computed. Save margins or refresh rates.' }}@endif</span></div>
                @endif

                <div class="cp-prices">
                    <div class="cp-price cp-price--buy"><span><i class="las la-arrow-down"></i> Buy price <small>Player pays</small></span><strong>{{ $rate?->buy_price === null ? '—' : $currency->symbol . number_format((float) $rate->buy_price, $currency->decimals, '.', ',') }}<small>/ coin</small></strong></div>
                    <div class="cp-price cp-price--sell"><span><i class="las la-arrow-up"></i> Sell price <small>Buyback reference</small></span><strong>{{ $rate?->sell_price === null ? '—' : $currency->symbol . number_format((float) $rate->sell_price, $currency->decimals, '.', ',') }}<small>/ coin</small></strong></div>
                </div>

                <div class="cp-preview">
                    <span class="cp-label">Player price preview</span>
                    <div class="cp-preview__items">
                        @foreach([100, 1000, 10000] as $coinAmount)
                            <div><small>{{ number_format($coinAmount) }} coins</small><strong>{{ $rate?->buy_price === null ? '—' : $currency->symbol . number_format((float) $rate->buy_price * $coinAmount, $currency->decimals, '.', ',') }}</strong></div>
                        @endforeach
                    </div>
                </div>

                <form method="post" action="{{ route('admin.coin.pricing.currency.margins', $currency->id) }}" class="cp-override-form">
                    @csrf
                    <div class="cp-override-heading"><span><i class="las la-sliders-h"></i> Currency-specific margins</span><small>Leave blank to use defaults ({{ $general->coin_default_buy_margin_percent ?? 5 }}% buy / {{ $general->coin_default_sell_margin_percent ?? 2 }}% sell)</small></div>
                    <label>Buy override %<input type="number" name="buy_margin_override" min="0" max="100" step="0.01" value="{{ $rate?->buy_margin_override }}" placeholder="Use default" aria-label="{{ $currency->code }} buy margin override"></label>
                    <label>Sell override %<input type="number" name="sell_margin_override" min="0" max="100" step="0.01" value="{{ $rate?->sell_margin_override }}" placeholder="Use default" aria-label="{{ $currency->code }} sell margin override"></label>
                    <button class="cp-button cp-button--small" type="submit"><i class="las la-save"></i> Save &amp; update</button>
                </form>

                <details class="cp-manual-rate">
                    <summary><i class="las la-edit"></i> Set a manual exchange rate <i class="las la-angle-down cp-chevron"></i></summary>
                    <form method="post" action="{{ route('admin.currency-country.currency.manual-rate', $currency) }}" class="cp-manual-form">
                        @csrf
                        <label>Value of 1 {{ $currency->code }} in USD<input type="number" name="rate_to_usd" min="0.000000000001" step="0.000000000001" value="{{ $currency->rate_to_usd }}" required></label>
                        <label>Reason (required)<input name="reason" maxlength="500" minlength="5" placeholder="Why is this rate being overridden?" required></label>
                        <button class="cp-button cp-button--secondary" type="submit">Save manual rate</button>
                        <small>The change is logged, and automatic updates for this currency are paused until re-enabled.</small>
                    </form>
                </details>
            </article>
        @empty
            <div class="cp-empty"><i class="las la-coins"></i><strong>No enabled currencies</strong><span>Enable currencies in Currencies &amp; Countries to price coins.</span><a href="{{ route('admin.currency-country.index') }}">Open currency settings</a></div>
        @endforelse
    </section>
</div>

<style>
.coin-pricing-page{--cp-bg:#11172a;--cp-panel:#161e35;--cp-panel2:#1d2742;--cp-line:rgba(151,169,218,.15);--cp-text:#f3f5ff;--cp-muted:#9ca8cb;--cp-green:#00e58b;--cp-warn:#ffbd63;color:var(--cp-text);display:grid;gap:22px}
.coin-pricing-page *{box-sizing:border-box}.cp-hero,.cp-controls,.cp-currency-card{border:1px solid var(--cp-line);border-radius:20px;background:linear-gradient(140deg,rgba(28,39,67,.98),rgba(17,23,42,.98));box-shadow:0 14px 34px rgba(3,7,18,.18)}
.cp-hero{display:flex;justify-content:space-between;gap:22px;align-items:center;padding:clamp(20px,3vw,32px);position:relative;overflow:hidden}.cp-hero:after{content:"";position:absolute;right:-85px;top:-170px;width:390px;height:390px;border:1px solid rgba(0,229,139,.15);border-radius:50%;box-shadow:0 0 0 42px rgba(0,229,139,.025),0 0 0 88px rgba(0,229,139,.02);pointer-events:none}.cp-hero__copy,.cp-hero__side{position:relative;z-index:1}.cp-eyebrow{display:flex;align-items:center;gap:7px;color:var(--cp-green);font-size:11px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}.cp-eyebrow span{color:#687493}.cp-hero h1{margin:8px 0 5px;color:var(--cp-text);font-size:clamp(25px,3vw,34px);font-weight:900;line-height:1.12;letter-spacing:-.035em}.cp-hero__copy>p{margin:0;color:var(--cp-muted);font-size:14px}.cp-peg{display:flex;align-items:center;gap:11px;margin-top:18px}.cp-peg__icon{display:grid;place-items:center;width:36px;height:36px;border-radius:11px;background:rgba(0,229,139,.11);color:var(--cp-green);font-size:17px}.cp-peg strong,.cp-peg small{display:block}.cp-peg strong{font-size:14px}.cp-peg small{margin-top:2px;color:var(--cp-muted);font-size:11px}.cp-hero__side{display:grid;justify-items:end;gap:18px}.cp-health{display:flex;align-items:center;gap:10px;padding:12px 15px;border:1px solid var(--cp-line);border-radius:14px;background:rgba(7,13,27,.35)}.cp-health__dot{width:9px;height:9px;border-radius:50%;background:var(--cp-green);box-shadow:0 0 12px rgba(0,229,139,.45)}.cp-health strong,.cp-health small{display:block}.cp-health strong{font-size:12px}.cp-health small{margin-top:3px;color:var(--cp-muted);font-size:10px}.cp-back{color:#c1cce9;font-size:12px;font-weight:800;text-decoration:none}.cp-back:hover{color:var(--cp-green)}
.cp-alert{margin:0;border-radius:12px}.cp-controls{overflow:hidden}.cp-section-heading{padding:20px 22px;border-bottom:1px solid var(--cp-line)}.cp-section-heading>div{display:flex;align-items:center;gap:12px}.cp-step{display:grid;place-items:center;flex:none;width:34px;height:34px;border-radius:11px;background:rgba(0,229,139,.11);color:var(--cp-green);font-size:11px;font-weight:900}.cp-section-heading h2{margin:0;color:var(--cp-text);font-size:17px;font-weight:850}.cp-section-heading p{margin:3px 0 0;color:var(--cp-muted);font-size:12px}.cp-controls__body{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(280px,.85fr);gap:18px;padding:20px 22px}.cp-global-form{display:flex;align-items:end;gap:12px;flex-wrap:wrap}.cp-global-form>label{display:grid;gap:7px;min-width:150px;flex:1;color:#dce4fc;font-size:12px;font-weight:800}.cp-global-form>label small{display:block;margin-top:2px;color:var(--cp-muted);font-size:10px;font-weight:500}.cp-input-wrap{display:flex;align-items:center;height:42px;border:1px solid var(--cp-line);border-radius:11px;background:rgba(7,13,27,.48)}.cp-input-wrap input{width:100%;min-width:0;height:40px;padding:0 12px;border:0;outline:0;background:transparent;color:var(--cp-text);font-size:14px;font-weight:800}.cp-input-wrap b{padding-inline:12px;color:var(--cp-green);font-size:13px}.cp-button{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:40px;padding:0 14px;border:1px solid transparent;border-radius:11px;font-size:11px;font-weight:900;white-space:nowrap;cursor:pointer;text-decoration:none;transition:transform .16s ease,filter .16s ease}.cp-button:hover{filter:brightness(1.08);transform:translateY(-1px)}.cp-button--primary{background:var(--cp-green);color:#06291f}.cp-button--secondary{border-color:var(--cp-line);background:#202b48;color:#e9eeff}.cp-button--small{min-height:38px;background:#202b48;color:#eaf0ff}.cp-refresh-box{display:flex;align-items:center;gap:10px;padding:12px;border:1px solid var(--cp-line);border-radius:14px;background:rgba(7,13,27,.31)}.cp-refresh-box__icon{display:grid;place-items:center;flex:none;width:35px;height:35px;border-radius:10px;background:rgba(0,229,139,.1);color:var(--cp-green);font-size:16px}.cp-refresh-box>div{min-width:0;flex:1}.cp-refresh-box strong,.cp-refresh-box small{display:block}.cp-refresh-box strong{font-size:11px}.cp-refresh-box small{margin-top:3px;color:var(--cp-muted);font-size:10px}.cp-refresh-box form{margin:0}.cp-how{display:flex;gap:9px;padding:13px 22px;border-top:1px solid var(--cp-line);background:rgba(0,229,139,.035);color:#b8c4e4;font-size:11px;line-height:1.55}.cp-how>i{flex:none;color:var(--cp-green);font-size:15px}.cp-how strong{color:var(--cp-text)}
.cp-currencies{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.cp-section-heading--currencies{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;padding:3px 2px 2px;border:0}.cp-count{padding:6px 10px;border:1px solid var(--cp-line);border-radius:999px;color:#cbd5ef;font-size:10px;font-weight:800}.cp-currency-card{min-width:0;overflow:hidden}.cp-currency-card__head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:16px 17px;border-bottom:1px solid var(--cp-line);background:linear-gradient(110deg,rgba(40,53,88,.58),rgba(27,37,64,.42))}.cp-currency-id{display:flex;align-items:center;gap:11px;min-width:0}.cp-currency-symbol{display:grid;place-items:center;flex:none;width:42px;height:42px;border:1px solid rgba(0,229,139,.22);border-radius:13px;background:rgba(0,229,139,.09);color:var(--cp-green);font-size:12px;font-weight:950}.cp-currency-id h3{margin:0;color:var(--cp-text);font-size:14px;font-weight:850}.cp-currency-id small{display:block;margin-top:3px;color:var(--cp-muted);font-size:10px}.cp-currency-id small span{padding-inline:3px;color:#65708e}.cp-status{display:inline-flex;align-items:center;gap:4px;padding:6px 9px;border-radius:999px;font-size:10px;font-weight:900;white-space:nowrap}.cp-status--active{background:rgba(0,229,139,.1);color:var(--cp-green)}.cp-status--paused{background:rgba(255,189,99,.1);color:var(--cp-warn)}.cp-fx-line{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:13px 17px}.cp-label{display:block;margin-bottom:4px;color:#8f9bbb;font-size:9px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.cp-fx-line>div:first-child strong{font-size:12px}.cp-rate-meta{display:grid;justify-items:end;gap:4px}.cp-source{color:#aeb9d7;font-size:9px;font-weight:800}.cp-source i{color:#64afff}.cp-source--manual i{color:var(--cp-warn)}.cp-rate-meta small{color:#8995b5;font-size:9px}.cp-paused-note{display:flex;align-items:flex-start;gap:7px;margin:0 16px 12px;padding:9px 10px;border:1px solid rgba(255,189,99,.15);border-radius:10px;background:rgba(255,189,99,.055);color:#ffd48e;font-size:10px;line-height:1.4}.cp-paused-note i{flex:none;margin-top:1px}.cp-prices{display:grid;grid-template-columns:1fr 1fr;gap:9px;padding:0 16px 14px}.cp-price{display:grid;gap:10px;padding:12px;border:1px solid var(--cp-line);border-radius:12px;background:rgba(8,14,28,.34)}.cp-price>span{color:#b2bdd9;font-size:10px;font-weight:850}.cp-price>span i{margin-inline-end:3px;color:var(--cp-green)}.cp-price>span small{display:block;margin:3px 0 0;color:#808dae;font-size:9px;font-weight:500}.cp-price>strong{color:var(--cp-text);font-size:19px;font-weight:900;letter-spacing:-.03em;overflow-wrap:anywhere}.cp-price>strong small{margin-inline-start:4px;color:#8591b0;font-size:9px;font-weight:600;letter-spacing:0}.cp-price--sell>span i{color:#70aafa}.cp-preview{padding:0 16px 14px}.cp-preview__items{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px}.cp-preview__items>div{min-width:0;padding:9px;border:1px solid var(--cp-line);border-radius:10px;background:rgba(255,255,255,.025)}.cp-preview__items small,.cp-preview__items strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.cp-preview__items small{color:#8e9abc;font-size:9px}.cp-preview__items strong{margin-top:5px;color:#e6ecff;font-size:11px}.cp-override-form{display:grid;grid-template-columns:1fr 1fr auto;align-items:end;gap:8px;padding:13px 16px;border-top:1px solid var(--cp-line);background:rgba(7,12,25,.25)}.cp-override-heading{grid-column:1/-1;display:flex;justify-content:space-between;gap:8px;align-items:baseline}.cp-override-heading>span{color:#dce5ff;font-size:10px;font-weight:900}.cp-override-heading>span i{margin-inline-end:4px;color:var(--cp-green)}.cp-override-heading>small{color:#8490b0;font-size:9px;text-align:end}.cp-override-form label,.cp-manual-form label{display:grid;gap:5px;color:#9eabc9;font-size:9px;font-weight:800}.cp-override-form input,.cp-manual-form input{width:100%;min-width:0;height:36px;padding:0 9px;border:1px solid var(--cp-line);border-radius:9px;outline:0;background:#10172a;color:var(--cp-text);font-size:11px}.cp-override-form input:focus,.cp-manual-form input:focus,.cp-input-wrap:focus-within{border-color:rgba(0,229,139,.55);box-shadow:0 0 0 3px rgba(0,229,139,.08)}.cp-manual-rate{border-top:1px solid var(--cp-line)}.cp-manual-rate summary{display:flex;align-items:center;gap:7px;padding:12px 16px;color:#aab6d3;font-size:10px;font-weight:850;cursor:pointer;list-style:none}.cp-manual-rate summary::-webkit-details-marker{display:none}.cp-manual-rate summary>i:first-child{color:var(--cp-warn)}.cp-manual-rate summary .cp-chevron{margin-inline-start:auto;transition:transform .2s}.cp-manual-rate[open] summary .cp-chevron{transform:rotate(180deg)}.cp-manual-form{display:grid;grid-template-columns:1fr 1fr auto;align-items:end;gap:9px;padding:0 16px 15px}.cp-manual-form small{grid-column:1/-1;color:#8591b0;font-size:9px}.cp-empty{grid-column:1/-1;display:grid;justify-items:center;gap:9px;padding:42px 20px;border:1px dashed var(--cp-line);border-radius:18px;color:var(--cp-muted);text-align:center}.cp-empty>i{color:var(--cp-green);font-size:28px}.cp-empty strong{color:var(--cp-text)}.cp-empty a{color:var(--cp-green);font-size:12px;font-weight:850}
@media(max-width:1100px){.cp-controls__body{grid-template-columns:1fr}.cp-refresh-box{max-width:none}}
@media(max-width:760px){.coin-pricing-page{gap:15px}.cp-hero{align-items:flex-start;flex-direction:column}.cp-hero__side{width:100%;justify-items:stretch;grid-template-columns:1fr auto;align-items:center}.cp-health{padding:10px}.cp-controls__body{padding:15px;gap:13px}.cp-global-form{display:grid;grid-template-columns:1fr 1fr}.cp-global-form>label{min-width:0}.cp-global-form>.cp-button{grid-column:1/-1;width:100%}.cp-section-heading{padding:16px}.cp-how{padding:12px 16px}.cp-currencies{grid-template-columns:1fr}.cp-section-heading--currencies{grid-column:1}.cp-currency-card__head{padding:14px}.cp-fx-line{padding:12px 14px}.cp-prices{padding-inline:13px}.cp-preview{padding-inline:13px}.cp-override-form{padding-inline:13px}.cp-manual-form{padding-inline:13px}}
@media(max-width:420px){.cp-hero__side{grid-template-columns:1fr;justify-items:start}.cp-health{width:100%}.cp-global-form{grid-template-columns:1fr}.cp-global-form>.cp-button{grid-column:auto}.cp-refresh-box{align-items:flex-start;flex-wrap:wrap}.cp-refresh-box>div{padding-top:3px}.cp-refresh-box form{width:100%}.cp-refresh-box form .cp-button{width:100%}.cp-currency-card__head{align-items:flex-start}.cp-currency-symbol{width:38px;height:38px}.cp-status{padding:5px 7px;font-size:9px}.cp-prices{gap:7px}.cp-price{padding:10px}.cp-price>strong{font-size:16px}.cp-override-form{grid-template-columns:1fr 1fr}.cp-override-form .cp-button{grid-column:1/-1;width:100%}.cp-override-heading{align-items:flex-start;flex-direction:column}.cp-override-heading>small{text-align:start}.cp-manual-form{grid-template-columns:1fr}.cp-manual-form small{grid-column:auto}}
@media(prefers-reduced-motion:reduce){.cp-button{transition:none}}
</style>
@endsection
