@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content nocturne-wallet-page">
    <h1 class="nocturne-page-title">{{ ($coinMode ?? false) ? __('Buy Coins') : __('Deposit') }}</h1>
    <section class="nocturne-wallet-hero">
        <div class="nocturne-wallet-hero__copy">
            <span class="nocturne-wallet-hero__eyebrow"><i class="ph-fill ph-shield-check"></i> @lang('Secure payments')</span>
            <h2>{{ ($coinMode ?? false) ? __('Buy coins for your wallet') : __('Power up your wallet') }}</h2>
            <p>{{ ($coinMode ?? false) ? __('Pick a method, review the details and buy coins in a few taps.') : __('Pick a method, review the details and send your deposit in a few taps.') }}</p>
        </div>
        @if($coinMode ?? false)
            <div class="nocturne-wallet-hero__balance">
                <span>@lang('Available balance')</span>
                <strong>{!! showUserBalance(auth()->user()) !!}</strong>
                <i class="ph-fill ph-wallet"></i>
            </div>
        @else
            <div class="nocturne-wallet-hero__balance">
                <span>@lang('Available balance')</span>
                <strong>{{ gs('cur_sym') }}{{ showAmount(auth()->user()->balance, currencyFormat: false) }} <small>{{ __(gs('cur_text')) }}</small></strong>
                <i class="ph-fill ph-wallet"></i>
            </div>
        @endif
    </section>

    @if($coinMode ?? false)
        @if($coinPricingError)
            <div class="nocturne-wallet-coin-notice nocturne-wallet-coin-notice--error" id="coin-preview-box" data-coin-buy-price="0">
                <i class="ph-fill ph-warning-circle"></i>
                <span>
                    {{ __($coinPricingError) }}
                    <br><small>@lang('USD peg'): <strong>1 {{ __('Coin') }} = ${{ showAmount($coinPegUsd, currencyFormat: false) }}</strong> @lang('(reference only - your currency price is not available)')</small>
                </span>
            </div>
        @else
            <div class="nocturne-wallet-coin-notice" id="coin-preview-box" data-coin-buy-price="{{ $coinBuyPrice }}">
                <i class="ph-fill ph-coins"></i>
                <span>@lang('Current rate'): <strong>1 {{ __('Coin') }} = {{ gs('cur_sym') }}{{ showAmount($coinBuyPrice, currencyFormat: false) }}</strong> &middot; @lang("You'll receive"): <strong id="coin-preview-amount">0 @lang('Coins')</strong></span>
            </div>
        @endif
    @endif
    @php
        $cryptoCount = $gatewayCurrency->filter(fn($g) => (int) $g->method->crypto === 1)->count();
        $winxpayCount = $gatewayCurrency->filter(fn($g) => (int) $g->method->crypto !== 1)->count() + count($vendorOffers ?? []);
        $hasWinxpay = $winxpayCount > 0;
        $hasCrypto = $cryptoCount > 0;
        $preselectAgentId = request()->integer('agent') ?: null;
        $preselectGateway = $preselectAgentId ? $gatewayCurrency->firstWhere('support_agent_id', $preselectAgentId) : null;
    @endphp

    <form action="{{ route('user.deposit.insert') }}" method="post" class="deposit-form" id="deposit-form" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="currency">
        <div class="nocturne-wallet-card">
            <h3 class="nocturne-wallet-card__heading"><span>1</span> @lang('Select a deposit method')</h3>

            <div class="nocturne-wallet-switch">
                <button type="button" class="nocturne-wallet-switch__opt @if ($hasCrypto && !$hasWinxpay) is-active @endif" data-filter="crypto" @disabled(!$hasCrypto)>
                    <span class="nocturne-wallet-switch__icons"><i class="lab la-bitcoin"></i><i class="lab la-ethereum"></i><i class="las la-coins"></i></span>
                    <strong>@lang('Crypto')</strong>
                    <small>{{ $cryptoCount }} @lang('coins')</small>
                </button>
                <button type="button" class="nocturne-wallet-switch__opt @if ($hasWinxpay && !$hasCrypto) is-active @endif" data-filter="winxpay" @disabled(!$hasWinxpay)>
                    <span class="nocturne-wallet-switch__icons"><i class="las la-user-tie"></i><i class="lab la-cc-mastercard"></i><i class="lab la-cc-visa"></i></span>
                    <strong>@lang('WinxPay')</strong>
                    <small>{{ $winxpayCount }} @lang('methods')</small>
                </button>
            </div>

            <div class="nocturne-wallet-pick">
                <button type="button" class="nocturne-wallet-pick__toggle" id="pay-dropdown-toggle">
                    <span class="nocturne-wallet-pick__selected">
                        <span class="nocturne-wallet-pick__thumb-wrap"><img class="nocturne-wallet-pick__thumb" src="" alt="" hidden><i class="ph-fill ph-wallet" id="pay-selected-fallback-icon"></i></span>
                        <span class="nocturne-wallet-pick__name" id="pay-dropdown-name">@lang('Choose an option')</span>
                    </span>
                    <i class="ph-bold ph-caret-down"></i>
                </button>
                <div class="nocturne-wallet-pick__panel" id="pay-dropdown-panel" hidden>
                    <div class="nocturne-wallet-pick__search"><i class="ph-bold ph-magnifying-glass"></i><input type="text" id="pay-dropdown-search" placeholder="@lang('Search')" autocomplete="off"></div>
                    <div class="nocturne-wallet-pick__list gateway-option-list">
                        @forelse ($gatewayCurrency as $data)
                            <label for="{{ titleToKey($data->name) }}"
                                data-crypto="{{ $data->method->crypto }}"
                                data-category="{{ (int) $data->method->crypto === 1 ? 'crypto' : 'winxpay' }}"
                                data-payment-agent="{{ $data->supportAgent?->name }}"
                                data-payment-agent-id="{{ $data->support_agent_id }}"
                                data-search="{{ strtolower($data->name . ' ' . $data->currency) }}"
                                class="nocturne-wallet-option gateway-option">
                                <span class="nocturne-wallet-option__thumb">
                                    <img onerror="this.hidden=true;this.parentElement.classList.add('has-missing-image')" src="{{ getImage(getFilePath('gateway') . '/' . $data->method->image) }}" alt="">
                                </span>
                                <span class="nocturne-wallet-option__copy">
                                    <span class="nocturne-wallet-option__name">{{ __($data->name) }} <small>{{ $data->currency }}</small></span>
                                    @if($data->support_agent_id)<small class="nocturne-wallet-option__agent"><i class="ph-fill ph-user-check"></i> {{ $data->supportAgent?->name }}</small>@endif
                                </span>
                                <span class="nocturne-wallet-option__check"><i class="ph-bold ph-check"></i></span>
                                <input class="gateway-input" id="{{ titleToKey($data->name) }}" hidden
                                    data-gateway='@json($data)' type="radio" name="gateway" value="{{ $data->method_code }}"
                                    @checked(old('gateway', $preselectGateway ? $preselectGateway->method_code : ($loop->first ? $data->method_code : null)) == $data->method_code)
                                    data-min-amount="{{ showAmount($data->min_amount) }}"
                                    data-max-amount="{{ showAmount($data->max_amount) }}">
                            </label>
                        @empty
                            <div class="nocturne-wallet-empty"><i class="ph-fill ph-wallet"></i><strong>@lang('No deposit methods available')</strong><span>@lang('Please contact support or try again later.')</span></div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Inline wallet reveal: QR + address, populated live from the selected crypto method -->
            <div class="nocturne-wallet-reveal" id="wallet-reveal" hidden>
                <div class="nocturne-wallet-reveal__qr"><canvas id="wallet-qr"></canvas></div>
                <div class="nocturne-wallet-reveal__info">
                    <span class="nocturne-wallet-reveal__label">@lang('Wallet address')</span>
                    <div class="nocturne-wallet-reveal__address">
                        <code id="wallet-address-text"></code>
                        <button type="button" class="nocturne-wallet-copy-btn" id="wallet-copy-btn"><i class="ph-bold ph-copy"></i></button>
                    </div>
                    <p class="nocturne-wallet-reveal__note" id="wallet-instructions"></p>
                </div>
            </div>

            <h3 class="nocturne-wallet-card__heading nocturne-wallet-card__heading--amount"><span>2</span> @lang('Enter deposit amount')</h3>
            <div class="nocturne-wallet-amount">
                <span class="nocturne-wallet-amount__prefix">{{ gs('cur_sym') }}</span>
                <input type="number" inputmode="decimal" min="0" step="any" class="amount" name="amount" placeholder="0.00" value="{{ old('amount') }}" autocomplete="off" aria-label="@lang('Deposit amount')">
            </div>

            <div class="nocturne-wallet-charges d-none">
                <div><span>@lang('Processing charge')</span><strong><span class="processing-fee">0.00</span> {{ __(gs('cur_text')) }}</strong></div>
                <div><span>@lang('Total')</span><strong><span class="final-amount">0.00</span> {{ __(gs('cur_text')) }}</strong></div>
            </div>
            <div class="nocturne-wallet-conversion d-none gateway-conversion"></div>
            <div class="nocturne-wallet-conversion d-none conversion-currency"><span>@lang('In')</span> <span class="gateway-currency"></span>: <span class="in-currency"></span></div>

            <button type="submit" class="nocturne-wallet-cta" id="deposit-cta" disabled><i class="ph-bold ph-arrow-circle-right"></i><span id="deposit-cta-label">{{ ($coinMode ?? false) ? __('Buy Coins') : __('Deposit Confirm') }}</span></button>

            <div class="nocturne-wallet-footrow">
                <div><span>@lang('Minimum Deposit')</span><strong class="gateway-limit">—</strong></div>
                <div><span>@lang('Network')</span><strong class="gateway-network">—</strong></div>
            </div>
        </div>
    </form>

    @if(($coinMode ?? false) && count($vendorOffers ?? []))
        <section class="nocturne-vendor-market" id="coin-vendor-directory" hidden aria-labelledby="coin-vendor-title">
            <div class="nocturne-wallet-history__heading"><div><span class="nocturne-wallet-kicker">WINXPAY · PERSON TO PERSON</span><h2 id="coin-vendor-title">Buy from a verified coin vendor</h2><p>Compare today's rate, availability, and payment methods. Your rate is locked when a trade opens.</p></div></div>
            <div class="nocturne-vendor-tools"><label><i class="ph-bold ph-magnifying-glass"></i><input id="vendor-search" type="search" placeholder="Search vendors or payment methods" aria-label="Search vendors"></label><select id="vendor-sort" aria-label="Sort vendors"><option value="rate">Best rate</option><option value="fastest">Fastest release</option><option value="reliable">Most reliable</option></select></div>
            <div class="nocturne-vendor-grid" id="vendor-grid">
                @foreach($vendorOffers as $offer)
                    <article class="nocturne-vendor-card" data-rate="{{ $offer['rate_per_coin'] }}" data-release="{{ $offer['average_release_minutes'] ?: 9999 }}" data-score="{{ $offer['reliability_score'] }}" data-search="{{ strtolower($offer['name'].' '.implode(' ', $offer['payment_methods'])) }}">
                        <div class="nocturne-vendor-card__head"><span class="nocturne-vendor-avatar">@if($offer['avatar_url'])<img src="{{ $offer['avatar_url'] }}" alt="">@else{{ strtoupper(substr($offer['name'],0,1)) }}@endif</span><div class="nocturne-vendor-id"><strong>{{ $offer['name'] }} <i class="ph-fill ph-seal-check" title="Verified vendor"></i></strong><small><span class="nocturne-presence {{ $offer['online'] ? 'is-online' : '' }}"></span>{{ $offer['online'] ? 'Online' : 'Verified vendor' }} · {{ number_format($offer['reliability_score'], 0) }}% reliability</small></div></div>
                        <div class="nocturne-vendor-prices"><div><small>Per 100 coins</small><strong>{{ $offer['currency_code'] }} {{ number_format($offer['rate_per_100'], 2) }}</strong></div><div><small>Per 1,000 coins</small><strong>{{ $offer['currency_code'] }} {{ number_format($offer['rate_per_1000'], 2) }}</strong></div></div>
                        <div class="nocturne-vendor-meta"><span><i class="ph-fill ph-check-circle"></i> {{ number_format($offer['completed_trades']) }} completed</span><span><i class="ph-fill ph-clock"></i> {{ $offer['average_release_minutes'] ? $offer['average_release_minutes'].' min avg' : 'New vendor' }}</span></div>
                        @if($offer['payment_methods'])<div class="nocturne-vendor-methods">@foreach($offer['payment_methods'] as $method)<span>{{ $method }}</span>@endforeach</div>@endif
                        <form method="post" action="{{ route('user.coin-vendor.buy') }}" class="nocturne-vendor-buy">@csrf<input type="hidden" name="vendor_id" value="{{ $offer['id'] }}"><label>Coins<input type="number" name="amount" min="10" max="{{ min($offer['per_trade_limit'], $offer['available_coins']) }}" step="1" required placeholder="Amount"></label><button type="submit"><i class="ph-bold ph-arrow-right"></i> Buy coins</button><small>Up to {{ number_format(min($offer['per_trade_limit'], $offer['available_coins'])) }} available</small></form>
                    </article>
                @endforeach
            </div>
            <div class="nocturne-wallet-empty" id="vendor-empty" hidden><i class="ph-fill ph-storefront"></i><strong>No vendors match that search</strong><span>Try another name or payment method.</span></div>
        </section>
    @elseif(($coinMode ?? false) && gs('coin_vendor_enabled'))
        <section class="nocturne-vendor-market" id="coin-vendor-directory" hidden><div class="nocturne-wallet-empty"><i class="ph-fill ph-storefront"></i><strong>No coin vendors are available right now</strong><span>Try another WinxPay option or check back later.</span></div></section>
    @endif

    @php
        $txnStatusMeta = fn ($status) => match ((int) $status) {
            Status::PAYMENT_PENDING => ['label' => 'Pending', 'icon' => 'ph-hourglass-medium', 'cls' => 'pending'],
            Status::PAYMENT_SUCCESS => ['label' => 'Approved', 'icon' => 'ph-check-circle', 'cls' => 'success'],
            Status::PAYMENT_REJECT => ['label' => 'Rejected', 'icon' => 'ph-x-circle', 'cls' => 'reject'],
            default => ['label' => 'Initiated', 'icon' => 'ph-circle-dashed', 'cls' => 'initiate'],
        };
    @endphp
    <section class="nocturne-wallet-history" aria-labelledby="deposit-history-title">
        <div class="nocturne-wallet-history__heading"><div><span class="nocturne-wallet-kicker">@lang('YOUR ACTIVITY')</span><h2 id="deposit-history-title">@lang('Deposit History')</h2></div><a href="{{ route('user.deposit.history') }}">@lang('View all') <i class="ph-bold ph-arrow-right"></i></a></div>
        <div class="nocturne-wallet-history__list">
            @forelse($deposits as $deposit)
                @php $meta = $txnStatusMeta($deposit->status); @endphp
                <div class="nocturne-wallet-history__row">
                    <span class="nocturne-wallet-history__icon"><i class="ph-fill ph-wallet"></i></span>
                    <div class="nocturne-wallet-history__mid"><strong>{{ __($deposit->gateway->name ?? 'Payment') }}</strong><small>{{ $deposit->trx }} · {{ showDateTime($deposit->created_at, 'M d, Y') }}</small></div>
                    <div class="nocturne-wallet-history__end">
                        <strong class="nocturne-wallet-history__amount">{{ $deposit->coin_mode_purchase ? coinAmountText($deposit->coin_amount) : showAmount($deposit->amount) }}</strong>
                        <span class="nocturne-wallet-history__chip nocturne-wallet-history__chip--{{ $meta['cls'] }}"><i class="ph-fill {{ $meta['icon'] }}"></i> {{ __($meta['label']) }}</span>
                        <span class="nocturne-wallet-history__time"><i class="ph-bold ph-clock"></i> {{ diffForHumans($deposit->created_at) }}</span>
                    </div>
                </div>
            @empty
                <div class="nocturne-wallet-empty"><i class="ph-fill ph-wallet"></i><strong>@lang('No deposits yet')</strong><span>@lang('Your completed and pending deposits will appear here.')</span></div>
            @endforelse
        </div>
        <div class="nocturne-wallet-history__pagination">{{ paginateLinks($deposits) }}</div>
    </section>
        </div>
    </div>

    @include('Template::user.payment._confirmation_modal')
@endsection

@push('style')
<style>
:root{--wallet-accent:#00e78a;--wallet-bg:#10152a;--wallet-panel:#151a31;--wallet-raised:#1b2140;--wallet-muted:#858eaf;--wallet-line:rgba(133,142,175,.18);--wallet-text:#f5f6ff}
.nocturne-wallet-page{max-width:900px;margin-inline:auto}
.nocturne-wallet-page .nocturne-page-title{margin:0 0 16px;font-size:clamp(23px,2.2vw,29px);font-weight:750;letter-spacing:-.035em;line-height:1.15;text-transform:none}
.nocturne-vendor-market{margin:22px 0;padding:clamp(16px,2.5vw,24px);border:1px solid rgba(0,231,138,.2);border-radius:22px;background:radial-gradient(ellipse at 95% 0,rgba(0,231,138,.09),transparent 34%),var(--wallet-panel)}.nocturne-vendor-market[hidden]{display:none}.nocturne-vendor-market .nocturne-wallet-history__heading{margin-bottom:18px}.nocturne-vendor-market .nocturne-wallet-history__heading h2{margin:5px 0;font-size:22px}.nocturne-vendor-market .nocturne-wallet-history__heading p{margin:0;color:var(--wallet-muted);font-size:12px}.nocturne-vendor-tools{display:flex;gap:10px;margin-bottom:15px}.nocturne-vendor-tools label{display:flex;align-items:center;gap:9px;flex:1;min-width:0;padding-inline:12px;border:1px solid var(--wallet-line);border-radius:12px;color:var(--wallet-muted);background:#11162a}.nocturne-vendor-tools input,.nocturne-vendor-tools select{min-height:42px;border:0;outline:0;color:var(--wallet-text);background:transparent}.nocturne-vendor-tools input{width:100%}.nocturne-vendor-tools select{padding:0 12px;border:1px solid var(--wallet-line);border-radius:12px;background:#11162a}.nocturne-vendor-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,280px),1fr));gap:12px}.nocturne-vendor-card{display:grid;gap:14px;min-width:0;padding:16px;border:1px solid var(--wallet-line);border-radius:17px;background:linear-gradient(150deg,rgba(32,42,72,.8),rgba(18,24,43,.9))}.nocturne-vendor-card__head{display:flex;align-items:center;gap:11px;min-width:0}.nocturne-vendor-avatar{display:grid;place-items:center;overflow:hidden;flex:0 0 46px;width:46px;height:46px;border-radius:50%;background:rgba(0,231,138,.13);color:var(--wallet-accent);font-weight:900}.nocturne-vendor-avatar img{width:100%;height:100%;object-fit:cover}.nocturne-vendor-id{min-width:0}.nocturne-vendor-id strong{display:block;overflow:hidden;color:var(--wallet-text);text-overflow:ellipsis;white-space:nowrap}.nocturne-vendor-id strong i{color:var(--wallet-accent)}.nocturne-vendor-id small{display:block;margin-top:4px;color:var(--wallet-muted);font-size:10px}.nocturne-presence{display:inline-block;width:7px;height:7px;margin-inline-end:5px;border-radius:50%;background:#69718e}.nocturne-presence.is-online{background:var(--wallet-accent);box-shadow:0 0 8px var(--wallet-accent)}.nocturne-vendor-prices{display:grid;grid-template-columns:1fr 1fr;gap:8px}.nocturne-vendor-prices>div{padding:10px;border-radius:11px;background:rgba(255,255,255,.035)}.nocturne-vendor-prices small,.nocturne-vendor-prices strong{display:block}.nocturne-vendor-prices small{margin-bottom:5px;color:var(--wallet-muted);font-size:9px}.nocturne-vendor-prices strong{font-size:12px}.nocturne-vendor-meta{display:flex;justify-content:space-between;gap:8px;color:var(--wallet-muted);font-size:10px}.nocturne-vendor-meta i{color:var(--wallet-accent)}.nocturne-vendor-methods{display:flex;flex-wrap:wrap;gap:5px}.nocturne-vendor-methods span{padding:4px 8px;border-radius:999px;background:rgba(0,231,138,.08);color:var(--wallet-accent);font-size:9px;font-weight:800}.nocturne-vendor-buy{display:grid;grid-template-columns:1fr auto;gap:8px;align-items:end}.nocturne-vendor-buy label{display:grid;gap:5px;color:var(--wallet-muted);font-size:10px}.nocturne-vendor-buy input{min-width:0;min-height:42px;padding:0 10px;border:1px solid var(--wallet-line);border-radius:10px;background:#10162a;color:var(--wallet-text)}.nocturne-vendor-buy button{min-height:42px;padding:0 13px;border:0;border-radius:10px;background:var(--wallet-accent);color:#062a23;font-weight:900}.nocturne-vendor-buy small{grid-column:1/-1;color:var(--wallet-muted);font-size:9px}.nocturne-vendor-market .nocturne-wallet-empty{margin-top:10px}
.nocturne-wallet-hero{position:relative;isolation:isolate;display:flex;align-items:center;justify-content:space-between;gap:20px;overflow:hidden;margin:0 0 22px;padding:clamp(20px,3vw,34px);border:1px solid rgba(133,148,192,.22);border-radius:24px;background:radial-gradient(ellipse at 90% 0,rgba(0,231,138,.18),transparent 37%),radial-gradient(ellipse at 52% 110%,rgba(45,83,145,.2),transparent 47%),linear-gradient(125deg,#171d37,#11162b 72%);box-shadow:0 18px 50px rgba(0,0,0,.18),inset 0 1px rgba(255,255,255,.035)}
.nocturne-wallet-hero::before{position:absolute;z-index:-1;top:50%;right:clamp(210px,28%,390px);width:180px;aspect-ratio:1;transform:translateY(-50%);border:1px solid rgba(0,231,138,.13);border-radius:50%;box-shadow:0 0 0 18px rgba(0,231,138,.025),inset 0 0 34px rgba(0,231,138,.035);content:"";pointer-events:none}
.nocturne-wallet-hero__copy,.nocturne-wallet-hero__balance{position:relative;z-index:1}
.nocturne-wallet-hero__eyebrow{display:inline-flex;align-items:center;gap:7px;color:var(--wallet-accent);font-size:11.5px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}
.nocturne-wallet-hero__copy h2{margin:8px 0 5px;color:var(--wallet-text);font-size:clamp(21px,2.4vw,29px);font-weight:740;letter-spacing:-.035em;line-height:1.18}
.nocturne-wallet-hero__copy p{max-width:620px;margin:0;color:#a4afcd;font-size:13px;line-height:1.6}
.nocturne-wallet-hero__balance{position:relative;min-width:190px;padding:14px 46px 14px 16px;border:1px solid rgba(255,255,255,.08);border-radius:16px;background:rgba(7,11,27,.5)}
.nocturne-wallet-hero__balance>span{display:block;color:var(--wallet-muted);font-size:11px;font-weight:700}
.nocturne-wallet-hero__balance strong{display:block;margin-top:3px;color:#fff;font-size:19px;font-weight:800;white-space:nowrap}
.nocturne-wallet-hero__balance small{color:var(--wallet-muted);font-size:11px}
.nocturne-wallet-hero__balance>i{position:absolute;right:13px;top:50%;transform:translateY(-50%);color:var(--wallet-accent);font-size:22px}

.nocturne-wallet-coin-notice{display:flex;align-items:center;gap:10px;margin:0 0 18px;padding:14px 18px;border:1px solid var(--wallet-line);border-radius:16px;background:rgba(0,231,138,.08);color:var(--wallet-text);font-size:13px}
.nocturne-wallet-coin-notice i{color:var(--wallet-accent);font-size:18px;flex:0 0 auto}
.nocturne-wallet-coin-notice--error{background:rgba(255,92,122,.1);border-color:rgba(255,92,122,.25)}
.nocturne-wallet-coin-notice--error i{color:#ff5c7a}

.nocturne-wallet-card{padding:clamp(18px,3vw,28px);border:1px solid var(--wallet-line);border-radius:26px;background:linear-gradient(160deg,#171d36,#11152a 75%);box-shadow:0 20px 55px rgba(0,0,0,.2)}
.nocturne-wallet-card__heading{display:flex;align-items:center;gap:10px;margin:0 0 16px;color:var(--wallet-text);font-size:15px;font-weight:800}
.nocturne-wallet-card__heading span{display:grid;place-items:center;flex:0 0 26px;width:26px;height:26px;border-radius:9px;background:rgba(0,231,138,.14);color:var(--wallet-accent);font-size:12px}
.nocturne-wallet-card__heading--amount{margin-top:22px}

.nocturne-wallet-switch{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:18px}
.nocturne-wallet-switch__opt{display:grid;place-items:center;gap:7px;min-height:88px;padding:14px;border:1.5px solid var(--wallet-line);border-radius:18px;background:var(--wallet-bg);color:var(--wallet-text);transition:border-color .18s,background .18s,transform .18s}
.nocturne-wallet-switch__opt:not(:disabled):hover{transform:translateY(-2px)}
.nocturne-wallet-switch__opt.is-active{border-color:var(--wallet-accent);background:linear-gradient(140deg,rgba(0,231,138,.14),var(--wallet-raised) 80%);box-shadow:0 0 0 1px rgba(0,231,138,.2)}
.nocturne-wallet-switch__opt:disabled{opacity:.4;cursor:not-allowed}
.nocturne-wallet-switch__icons{display:flex;align-items:center;gap:7px;color:var(--wallet-muted);font-size:18px}
.nocturne-wallet-switch__opt.is-active .nocturne-wallet-switch__icons{color:var(--wallet-accent)}
.nocturne-wallet-switch__opt strong{font-size:14px;font-weight:850}
.nocturne-wallet-switch__opt small{color:var(--wallet-muted);font-size:10.5px}

.nocturne-wallet-pick{position:relative;margin-bottom:18px}
.nocturne-wallet-pick__toggle{display:flex;align-items:center;justify-content:space-between;gap:10px;width:100%;min-height:64px;padding:10px 16px;border:1px solid var(--wallet-line);border-radius:16px;background:var(--wallet-bg);color:var(--wallet-text);cursor:pointer}
.nocturne-wallet-pick__toggle:hover{border-color:rgba(0,231,138,.4)}
.nocturne-wallet-pick__toggle>i{color:var(--wallet-muted)}
.nocturne-wallet-pick__selected{display:flex;align-items:center;gap:11px;min-width:0}
.nocturne-wallet-pick__thumb-wrap{display:grid;place-items:center;flex:0 0 36px;width:36px;height:36px;border-radius:50%;background:rgba(0,231,138,.1);color:var(--wallet-accent);overflow:hidden}
.nocturne-wallet-pick__thumb{width:100%;height:100%;object-fit:cover}
.nocturne-wallet-pick__name{overflow:hidden;font-size:15px;font-weight:800;text-overflow:ellipsis;white-space:nowrap}
.nocturne-wallet-pick__panel{position:absolute;z-index:30;top:calc(100% + 8px);left:0;right:0;padding:12px;border:1px solid var(--wallet-line);border-radius:18px;background:#11162b;box-shadow:0 24px 60px rgba(0,0,0,.45)}
.nocturne-wallet-pick__search{display:flex;align-items:center;gap:8px;margin-bottom:10px;padding:10px 12px;border:1px solid var(--wallet-line);border-radius:12px;background:#0d1225}
.nocturne-wallet-pick__search i{color:var(--wallet-muted);font-size:14px}
.nocturne-wallet-pick__search input{flex:1;min-width:0;border:0;background:transparent;color:var(--wallet-text);font:inherit;outline:0}
.nocturne-wallet-pick__search input::placeholder{color:#606986}
.nocturne-wallet-pick__list{display:grid;gap:4px;max-height:280px;overflow:auto}
.nocturne-wallet-option{position:relative;display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:12px;cursor:pointer}
.nocturne-wallet-option:hover{background:#171d36}
.nocturne-wallet-option:has(.gateway-input:checked){background:rgba(0,231,138,.1)}
.nocturne-wallet-option__thumb{display:grid;place-items:center;flex:0 0 32px;width:32px;height:32px;border-radius:50%;background:#fff;overflow:hidden}
.nocturne-wallet-option__thumb img{width:100%;height:100%;object-fit:cover}
.nocturne-wallet-option__copy{display:grid;min-width:0;gap:2px;flex:1}
.nocturne-wallet-option__name{overflow:hidden;font-size:13.5px;font-weight:750;text-overflow:ellipsis;white-space:nowrap;color:var(--wallet-text)}
.nocturne-wallet-option__name small{margin-left:5px;color:var(--wallet-muted);font-weight:700}
.nocturne-wallet-option__agent{color:var(--wallet-accent);font-size:10px;font-weight:700}
.nocturne-wallet-option__check{display:none;flex:0 0 auto;color:var(--wallet-accent)}
.nocturne-wallet-option:has(.gateway-input:checked) .nocturne-wallet-option__check{display:block}

.nocturne-wallet-reveal{display:flex;flex-wrap:wrap;align-items:center;gap:18px;margin-bottom:18px;padding:18px;border:1px solid var(--wallet-line);border-radius:18px;background:rgba(7,11,27,.42)}
.nocturne-wallet-reveal__qr{flex:0 0 auto;display:grid;place-items:center;padding:9px;background:#fff;border-radius:14px;box-shadow:0 10px 26px rgba(0,0,0,.3)}
.nocturne-wallet-reveal__qr canvas{display:block;width:120px;height:120px}
.nocturne-wallet-reveal__info{flex:1;min-width:200px}
.nocturne-wallet-reveal__label{display:block;margin-bottom:6px;color:var(--wallet-muted);font-size:11px;text-transform:uppercase;letter-spacing:.05em;font-weight:800}
.nocturne-wallet-reveal__address{display:flex;align-items:center;gap:8px}
.nocturne-wallet-reveal__address code{flex:1;min-width:0;overflow-wrap:anywhere;padding:9px 11px;border-radius:10px;background:rgba(0,0,0,.28);color:var(--wallet-text);font-size:12.5px}
.nocturne-wallet-copy-btn{flex:0 0 auto;display:grid;place-items:center;width:38px;height:38px;border:0;border-radius:10px;background:rgba(0,231,138,.14);color:var(--wallet-accent);cursor:pointer}
.nocturne-wallet-copy-btn:hover{background:rgba(0,231,138,.22)}
.nocturne-wallet-copy-btn.is-copied{background:var(--wallet-accent);color:#062a23}
.nocturne-wallet-reveal__note{margin:10px 0 0;color:var(--wallet-muted);font-size:12px;line-height:1.6}

.nocturne-wallet-amount{display:flex;align-items:center;min-height:62px;border:1px solid var(--wallet-line);border-radius:16px;background:var(--wallet-bg);overflow:hidden}
.nocturne-wallet-amount__prefix{display:grid;place-items:center;flex:0 0 auto;min-width:52px;height:100%;color:var(--wallet-accent);font-size:19px;font-weight:800;border-right:1px solid var(--wallet-line)}
.nocturne-wallet-amount .amount{flex:1;min-width:0;height:60px;padding:0 16px;border:0;background:transparent;color:#fff;font-size:22px;font-weight:800;outline:0}
.nocturne-wallet-amount .amount::placeholder{color:#606986}

.nocturne-wallet-charges{display:grid;gap:8px;margin-top:14px;padding:12px 14px;border-radius:13px;background:rgba(255,255,255,.03)}
.nocturne-wallet-charges>div{display:flex;justify-content:space-between;font-size:12.5px;color:var(--wallet-muted)}
.nocturne-wallet-charges strong{color:var(--wallet-text)}
.nocturne-wallet-conversion{margin-top:10px;font-size:12px;color:var(--wallet-muted)}

.nocturne-wallet-cta{display:flex;align-items:center;justify-content:center;gap:9px;width:100%;min-height:54px;margin-top:18px;border:0;border-radius:15px;background:var(--wallet-accent);color:#062a23;font-size:15px;font-weight:850;cursor:pointer;box-shadow:0 12px 30px rgba(0,231,138,.18)}
.nocturne-wallet-cta:disabled{opacity:.45;cursor:not-allowed;box-shadow:none}

.nocturne-wallet-footrow{display:flex;justify-content:space-between;gap:12px;margin-top:16px;padding-top:14px;border-top:1px dashed var(--wallet-line);flex-wrap:wrap}
.nocturne-wallet-footrow div{display:grid;gap:3px}
.nocturne-wallet-footrow span{color:var(--wallet-muted);font-size:10.5px;text-transform:uppercase;letter-spacing:.04em}
.nocturne-wallet-footrow strong{color:var(--wallet-text);font-size:13px}

.nocturne-wallet-empty{display:grid;justify-items:center;gap:8px;padding:28px;border:1px dashed var(--wallet-line);border-radius:16px;color:var(--wallet-muted);text-align:center}
.nocturne-wallet-empty i{color:var(--wallet-accent);font-size:26px}

.nocturne-wallet-history{margin-top:24px;padding:22px;border:1px solid var(--wallet-line);border-radius:22px;background:linear-gradient(150deg,#151a31,#10152a)}
.nocturne-wallet-history__heading{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:16px}
.nocturne-wallet-history__heading h2{margin:0;color:var(--wallet-text);font-size:16px;font-weight:850}
.nocturne-wallet-kicker{display:block;margin-bottom:3px;color:var(--wallet-accent);font-size:9px;font-weight:850;letter-spacing:.14em}
.nocturne-wallet-history__heading>a{display:inline-flex;align-items:center;gap:6px;padding:9px 13px;border-radius:11px;background:var(--wallet-raised);color:var(--wallet-text);font-size:12px;font-weight:750;text-decoration:none}
.nocturne-wallet-history__list{display:grid;gap:9px}
.nocturne-wallet-history__row{display:flex;min-width:0;align-items:center;gap:14px;padding:14px;border:1px solid var(--wallet-line);border-radius:16px;background:linear-gradient(125deg,rgba(23,30,53,.86),rgba(9,13,28,.62));flex-wrap:wrap;transition:border-color .18s,transform .18s}
.nocturne-wallet-history__row:hover{border-color:rgba(0,231,138,.2);transform:translateY(-1px)}
.nocturne-wallet-history__icon{display:grid;place-items:center;flex:0 0 42px;width:42px;height:42px;border-radius:12px;background:rgba(0,231,138,.1);color:var(--wallet-accent);font-size:18px}
.nocturne-wallet-history__mid{flex:1 1 160px;min-width:0;display:grid;gap:3px}
.nocturne-wallet-history__mid strong{overflow:hidden;color:var(--wallet-text);font-size:13.5px;text-overflow:ellipsis;white-space:nowrap}
.nocturne-wallet-history__mid small{overflow:hidden;color:var(--wallet-muted);font-size:10.5px;text-overflow:ellipsis;white-space:nowrap}
.nocturne-wallet-history__end{display:flex;min-width:0;align-items:center;gap:10px;flex:0 0 auto;margin-inline-start:auto}
.nocturne-wallet-history__amount{min-width:0;color:var(--wallet-text);font-size:14px;font-weight:750;white-space:nowrap}
.nocturne-wallet-history__chip{display:inline-flex;align-items:center;gap:5px;padding:6px 11px;border-radius:999px;font-size:11px;font-weight:800;white-space:nowrap}
.nocturne-wallet-history__chip--pending{background:rgba(255,181,71,.14);color:#ffb547}
.nocturne-wallet-history__chip--success{background:rgba(0,231,138,.14);color:var(--wallet-accent)}
.nocturne-wallet-history__chip--reject{background:rgba(255,92,122,.14);color:#ff5c7a}
.nocturne-wallet-history__chip--initiate{background:rgba(133,142,175,.16);color:var(--wallet-muted)}
.nocturne-wallet-history__time{display:none;align-items:center;gap:5px;color:var(--wallet-muted);font-size:11px;white-space:nowrap}
.nocturne-wallet-history__pagination{margin-top:14px}

@media(min-width:850px){
    .nocturne-wallet-history__time{display:inline-flex}
}

@media(max-width:575.98px){
    .nocturne-wallet-page{width:100%;max-width:none}
    .nocturne-wallet-page .nocturne-page-title{margin-bottom:12px;font-size:23px}
    .nocturne-wallet-hero{flex-direction:column;align-items:stretch;gap:14px;margin-bottom:16px;padding:17px;border-radius:19px}
    .nocturne-wallet-hero::before{top:auto;right:-20px;bottom:-88px;width:165px;transform:none}
    .nocturne-wallet-hero__copy h2{max-width:23ch;font-size:clamp(20px,5.7vw,25px);font-weight:720}
    .nocturne-wallet-hero__copy p{max-width:42ch;font-size:12px}
    .nocturne-wallet-hero__balance{width:100%;min-width:0;padding:12px 44px 12px 14px;border-radius:14px}
    .nocturne-wallet-hero__balance strong{font-size:17px}
    .nocturne-wallet-history{margin-top:17px;padding:13px;border-radius:18px}
    .nocturne-wallet-history__heading{align-items:center;gap:8px;margin-bottom:12px}
    .nocturne-wallet-history__heading h2{font-size:15px;font-weight:750}
    .nocturne-wallet-history__heading>a{flex:0 0 auto;padding:8px 10px;font-size:10.5px}
    .nocturne-wallet-history__row{display:grid;grid-template-columns:38px minmax(0,1fr);align-items:center;gap:9px 10px;padding:12px;border-radius:14px}
    .nocturne-wallet-history__icon{width:38px;height:38px;flex-basis:38px;border-radius:11px;font-size:16px}
    .nocturne-wallet-history__mid{grid-column:2;grid-row:1;flex:initial;min-width:0;gap:4px}
    .nocturne-wallet-history__mid strong{font-size:12.5px}
    .nocturne-wallet-history__mid small{font-size:9.5px}
    .nocturne-wallet-history__end{grid-column:1/-1;grid-row:2;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:7px 8px;align-items:center;width:100%;margin:0}
    .nocturne-wallet-history__amount{overflow:hidden;font-size:13px;text-overflow:ellipsis}
    .nocturne-wallet-history__chip{justify-self:end;padding:5px 8px;font-size:9.5px}
    .nocturne-wallet-history__time{grid-column:1/-1;justify-self:end;font-size:10px}
    .nocturne-wallet-switch__opt{min-height:76px;padding:10px}
    .nocturne-vendor-tools{flex-direction:column}.nocturne-vendor-tools select{min-height:42px}.nocturne-vendor-buy{grid-template-columns:1fr}.nocturne-vendor-buy button{width:100%}
    .nocturne-wallet-reveal{flex-direction:column;align-items:stretch;text-align:center}
    .nocturne-wallet-reveal__qr{justify-self:center;margin:0 auto}
    .nocturne-wallet-footrow{flex-direction:column;gap:10px}
    .nocturne-wallet-history__row{align-items:center}
    .nocturne-wallet-history__time{display:inline-flex}
}
@media(max-width:359.98px){.nocturne-wallet-history{padding:10px}.nocturne-wallet-history__row{padding:10px;gap:8px}.nocturne-wallet-history__end{grid-template-columns:minmax(0,1fr);justify-items:start}.nocturne-wallet-history__chip,.nocturne-wallet-history__time{grid-column:1;justify-self:start}.nocturne-wallet-hero{padding:14px}}
</style>
@endpush

@push('script')
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
<script>
"use strict";
(function($) {
    var amount = parseFloat($('.amount').val() || 0);
    var gateway, minAmount, maxAmount;
    var coinMode = {{ ($coinMode ?? false) ? 'true' : 'false' }};
    var coinPricingBlocked = {{ (($coinMode ?? false) && $coinPricingError) ? 'true' : 'false' }};
    var coinBuyPrice = parseFloat($('#coin-preview-box').data('coin-buy-price')) || 0;

    function updateCoinPreview() {
        if (!coinMode || !coinBuyPrice) return;
        // Matches PaymentController::userDataUpdate()'s bcdiv($lockedDeposit->amount, $buyPrice, 0)
        // - a plain truncating integer division, same as Math.floor for non-negative numbers.
        var coins = amount > 0 ? Math.floor(amount / coinBuyPrice) : 0;
        $('#coin-preview-amount').text(coins.toLocaleString() + ' {{ __('Coins') }}');
    }

    $('.amount').on('input', function() {
        amount = parseFloat($(this).val()) || 0;
        calculation();
        updateCoinPreview();
    });

    $('.gateway-input').on('change', gatewayChange);

    function renderWallet() {
        var reveal = $('#wallet-reveal');
        if (!gateway || gateway.method.crypto != 1 || !gateway.wallet_address) {
            reveal.prop('hidden', true);
            return;
        }
        reveal.prop('hidden', false);
        $('#wallet-address-text').text(gateway.wallet_address);
        $('#wallet-instructions').text(gateway.method.description ? gateway.method.description.replace(/<[^>]+>/g, ' ').trim() : "{{ __('Send only') }} " + gateway.currency + " {{ __('to this address.') }}");
        var canvas = document.getElementById('wallet-qr');
        if (canvas && window.QRCode) {
            QRCode.toCanvas(canvas, gateway.wallet_address, { width: 120, margin: 1 }, function() {});
        }
    }

    function gatewayChange() {
        let el = $('.gateway-input:checked');
        if (!el.length) {
            gateway = null;
            $('.nocturne-wallet-pick__thumb').attr('src', '').hide();
            $('#pay-dropdown-name').text("{{ __('Choose an option') }}");
            $('#deposit-cta').prop('disabled', true);
            $('#wallet-reveal').prop('hidden', true);
            return;
        }
        gateway = el.data('gateway');
        minAmount = el.data('min-amount');
        maxAmount = el.data('max-amount');

        let selectedImage = el.closest('.nocturne-wallet-option').find('img').attr('src');
        let selectedName = el.closest('.nocturne-wallet-option').find('.nocturne-wallet-option__name').clone().children().remove().end().text().trim();
        if (selectedImage) {
            $('.nocturne-wallet-pick__thumb').attr('src', selectedImage).prop('hidden', false).show();
            $('#pay-selected-fallback-icon').hide();
        }
        $('#pay-dropdown-name').text(selectedName + (gateway.currency ? ' ' + gateway.currency : ''));
        if (coinMode) {
            $('#deposit-cta-label').text("{{ __('Buy Coins') }}");
        } else {
            $('#deposit-cta-label').text(gateway.method.crypto == 1 ? "{{ __('Confirm Deposit') }}" : "{{ __('Deposit Confirm') }}");
        }

        renderWallet();
        calculation();
        updateCoinPreview();
    }

    gatewayChange();

    $('.nocturne-wallet-switch__opt').on('click', function() {
        if (this.disabled) return;
        let filter = String($(this).data('filter'));
        $('#coin-vendor-directory').prop('hidden', filter !== 'winxpay');
        $('.nocturne-wallet-switch__opt').removeClass('is-active');
        $(this).addClass('is-active');
        $('.gateway-option').each(function() {
            $(this).toggle(String($(this).data('category')) === filter);
        });
        $('#pay-dropdown-search').val('').trigger('input');
        let firstVisible = $('.gateway-option:visible').first();
        if (firstVisible.length) firstVisible.find('.gateway-input').prop('checked', true);
        else $('.gateway-input').prop('checked', false);
        gatewayChange();
    });

    $('#vendor-search').on('input', function() {
        let term = String($(this).val() || '').toLowerCase().trim();
        let visible = 0;
        $('.nocturne-vendor-card').each(function() {
            let matches = String($(this).data('search') || '').includes(term);
            $(this).toggle(matches);
            if (matches) visible++;
        });
        $('#vendor-empty').prop('hidden', visible > 0);
    });
    $('#vendor-sort').on('change', function() {
        const key = String($(this).val());
        const attr = key === 'fastest' ? 'release' : (key === 'reliable' ? 'score' : 'rate');
        const direction = key === 'reliable' ? -1 : 1;
        const cards = $('.nocturne-vendor-card').get().sort((a, b) => direction * ((Number($(a).data(attr)) || 0) - (Number($(b).data(attr)) || 0)));
        $('#vendor-grid').append(cards);
    });

    function calculation() {
        if (!gateway) return;
        $('.gateway-limit').text(minAmount);
        $('.gateway-network').text(gateway.currency || '{{ __(gs("cur_text")) }}');

        let percentCharge = parseFloat(gateway.percent_charge) || 0;
        let fixedCharge = parseFloat(gateway.fixed_charge) || 0;
        let totalPercentCharge = amount ? (amount / 100 * percentCharge) : 0;
        let totalCharge = totalPercentCharge + fixedCharge;
        let totalAmount = (amount || 0) + totalPercentCharge + fixedCharge;

        $('.final-amount').text(totalAmount.toFixed(2));
        $('.processing-fee').text(totalCharge.toFixed(2));
        $('input[name=currency]').val(gateway.currency);
        $('.gateway-currency').text(gateway.currency);
        $('.nocturne-wallet-charges').toggleClass('d-none', !(percentCharge > 0 || fixedCharge > 0));

        $('#deposit-cta').prop('disabled', coinPricingBlocked || !(amount >= Number(gateway.min_amount) && amount <= Number(gateway.max_amount)));

        if (gateway.currency != "{{ gs('cur_text') }}" && gateway.method.crypto != 1) {
            $('.gateway-conversion, .conversion-currency').removeClass('d-none');
            $('.gateway-conversion').html(`1 {{ __(gs('cur_text')) }} = ${parseFloat(gateway.rate).toFixed(2)} ${gateway.currency}`);
            $('.in-currency').text((totalAmount * gateway.rate).toFixed(gateway.method.crypto == 1 ? 8 : 2));
        } else {
            $('.gateway-conversion, .conversion-currency').addClass('d-none');
        }
    }

    if ($('.nocturne-wallet-switch__opt').length) {
        let checkedCategory = $('.gateway-input:checked').closest('.gateway-option').data('category');
        let initialFilter = checkedCategory === undefined ? $('.nocturne-wallet-switch__opt:not(:disabled)').first().data('filter') : checkedCategory;
        $('.nocturne-wallet-switch__opt').filter(`[data-filter="${initialFilter}"]`).trigger('click');
    }

    // Dropdown open/close + search
    $('#pay-dropdown-toggle').on('click', function(e) {
        e.stopPropagation();
        $('#pay-dropdown-panel').prop('hidden', function(i, hidden) { return !hidden; });
        if (!$('#pay-dropdown-panel').prop('hidden')) $('#pay-dropdown-search').trigger('focus');
    });
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.nocturne-wallet-pick').length) $('#pay-dropdown-panel').prop('hidden', true);
    });
    $('.gateway-option-list').on('click', '.nocturne-wallet-option', function() {
        $('#pay-dropdown-panel').prop('hidden', true);
    });
    $('#pay-dropdown-search').on('input', function() {
        let term = $(this).val().toLowerCase().trim();
        $('.gateway-option:visible, .gateway-option').each(function() {
            if ($(this).css('display') === 'none' && !term) return;
        });
        $('.gateway-option').each(function() {
            let matches = !term || String($(this).data('search') || '').includes(term);
            $(this).toggle(matches);
        });
    });

    // Copy wallet address
    $('#wallet-copy-btn').on('click', function() {
        let text = $('#wallet-address-text').text();
        if (!text) return;
        navigator.clipboard?.writeText(text).then(() => {
            $(this).addClass('is-copied').find('i').removeClass('ph-copy').addClass('ph-check');
            setTimeout(() => { $(this).removeClass('is-copied').find('i').removeClass('ph-check').addClass('ph-copy'); }, 1500);
        });
    });

    // Intercept submit only for crypto manual deposits -> open modal instead of navigating
    $('#deposit-form').on('submit', function(e) {
        if (!gateway || gateway.method.crypto != 1) return; // let WinxPay/automatic submit natively
        e.preventDefault();
        if (window.WalletModal) window.WalletModal.startDeposit($(this), gateway);
    });
})(jQuery);
</script>
@endpush
