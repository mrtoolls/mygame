@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content nocturne-wallet-page nocturne-wallet-page--withdraw">
    <h1 class="nocturne-page-title">@lang('Withdraw')</h1>
    <section class="nocturne-wallet-hero">
        <div class="nocturne-wallet-hero__copy">
            <span class="nocturne-wallet-hero__eyebrow"><i class="ph-fill ph-shield-check"></i> @lang('Secure withdrawals')</span>
            <h2>@lang('Your winnings, on the way')</h2>
            <p>@lang('Choose where to receive your funds, enter an amount and review the payout details.')</p>
        </div>
        <div class="nocturne-wallet-hero__balance">
            <span>@lang('Available balance')</span>
            <strong>{{ gs('cur_sym') }}{{ showAmount(auth()->user()->balance, currencyFormat: false) }} <small>{{ __(gs('cur_text')) }}</small></strong>
            <i class="ph-fill ph-wallet"></i>
        </div>
    </section>
    @php
        $cryptoCurrencies = ['BTC', 'XBT', 'ETH', 'LTC', 'BCH', 'USDT', 'USDC', 'BNB', 'TRX', 'XRP', 'DOGE', 'SOL', 'TON', 'ADA', 'DOT', 'XMR', 'DASH'];
        $cryptoMethodCount = $withdrawMethod->filter(fn($method) => in_array(strtoupper((string) $method->currency), $cryptoCurrencies, true))->count();
        $winxpayMethodCount = $withdrawMethod->count() - $cryptoMethodCount;
        $hasCryptoWithdraw = $cryptoMethodCount > 0;
        $hasWinxpayWithdraw = $winxpayMethodCount > 0;
    @endphp

    <form action="{{ route('user.withdraw.money') }}" method="post" class="withdraw-form" id="withdraw-form" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="currency">
        <div class="nocturne-wallet-card">
            <h3 class="nocturne-wallet-card__heading"><span>1</span> @lang('Select a withdraw method')</h3>

            <div class="nocturne-wallet-switch">
                <button type="button" class="nocturne-wallet-switch__opt @if ($hasCryptoWithdraw && !$hasWinxpayWithdraw) is-active @endif" data-filter="crypto" @disabled(!$hasCryptoWithdraw)>
                    <span class="nocturne-wallet-switch__icons"><i class="lab la-bitcoin"></i><i class="lab la-ethereum"></i><i class="las la-coins"></i></span>
                    <strong>@lang('Crypto')</strong>
                    <small>{{ $cryptoMethodCount }} @lang('coins')</small>
                </button>
                <button type="button" class="nocturne-wallet-switch__opt @if ($hasWinxpayWithdraw && !$hasCryptoWithdraw) is-active @endif" data-filter="winxpay" @disabled(!$hasWinxpayWithdraw)>
                    <span class="nocturne-wallet-switch__icons"><i class="las la-user-tie"></i><i class="lab la-cc-mastercard"></i><i class="lab la-cc-visa"></i></span>
                    <strong>@lang('WinxPay')</strong>
                    <small>{{ $winxpayMethodCount }} @lang('methods')</small>
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
                        @forelse ($withdrawMethod as $data)
                            <label for="{{ titleToKey($data->name) }}"
                                data-crypto="{{ in_array(strtoupper((string) $data->currency), $cryptoCurrencies, true) ? 1 : 0 }}"
                                data-category="{{ in_array(strtoupper((string) $data->currency), $cryptoCurrencies, true) ? 'crypto' : 'winxpay' }}"
                                data-payment-agent="{{ $data->supportAgent?->name }}"
                                data-search="{{ strtolower($data->name . ' ' . $data->currency) }}"
                                class="nocturne-wallet-option gateway-option">
                                <span class="nocturne-wallet-option__thumb">
                                    <img onerror="this.hidden=true;this.parentElement.classList.add('has-missing-image')" src="{{ getImage(getFilePath('withdrawMethod') . '/' . $data->image) }}" alt="">
                                </span>
                                <span class="nocturne-wallet-option__copy">
                                    <span class="nocturne-wallet-option__name">{{ __($data->name) }} <small>{{ $data->currency }}</small></span>
                                    @if($data->support_agent_id)<small class="nocturne-wallet-option__agent"><i class="ph-fill ph-user-check"></i> {{ $data->supportAgent?->name }}</small>@endif
                                </span>
                                <span class="nocturne-wallet-option__check"><i class="ph-bold ph-check"></i></span>
                                <input class="gateway-input" id="{{ titleToKey($data->name) }}" hidden
                                    data-gateway='@json($data)' type="radio" name="method_code" value="{{ $data->id }}"
                                    @if (old('method_code')) @checked(old('method_code') == $data->id) @else @checked($loop->first) @endif
                                    data-min-amount="{{ showAmount($data->min_limit) }}"
                                    data-max-amount="{{ showAmount($data->max_limit) }}">
                            </label>
                        @empty
                            <div class="nocturne-wallet-empty"><i class="ph-fill ph-wallet"></i><strong>@lang('No withdrawal methods available')</strong><span>@lang('Please contact support or try again later.')</span></div>
                        @endforelse
                    </div>
                </div>
            </div>

            @if ($withdrawMethod->isNotEmpty())
                <h3 class="nocturne-wallet-card__heading nocturne-wallet-card__heading--amount"><span>2</span> @lang('Payout details')</h3>
                <div class="nocturne-wallet-fields">
                    @foreach ($withdrawMethod as $method)
                        <fieldset class="withdraw-method-fields" data-method-id="{{ $method->id }}" @if (!$loop->first) disabled hidden @endif>
                            <x-viser-form identifier="id" identifierValue="{{ $method->form_id }}" />
                        </fieldset>
                    @endforeach
                </div>
            @endif

            <h3 class="nocturne-wallet-card__heading nocturne-wallet-card__heading--amount"><span>{{ $withdrawMethod->isNotEmpty() ? 3 : 2 }}</span> @lang('Enter withdrawal amount')</h3>
            <div class="nocturne-wallet-amount nocturne-wallet-amount--withdraw">
                <span class="nocturne-wallet-amount__prefix">{{ gs('cur_sym') }}</span>
                <input type="number" inputmode="decimal" min="0" step="any" class="amount" name="amount" placeholder="0.00" value="{{ old('amount') }}" autocomplete="off" aria-label="@lang('Withdrawal amount')">
                <button type="button" class="nocturne-wallet-max-btn" id="withdraw-max-btn">@lang('Max')</button>
            </div>

            <div class="nocturne-wallet-charges d-none">
                <div><span>@lang('Fee')</span><strong><span class="processing-fee-label">@lang('No Commission')</span></strong></div>
            </div>
            <div class="nocturne-wallet-conversion d-none gateway-conversion"></div>
            <div class="nocturne-wallet-conversion d-none conversion-currency"><span>@lang('Amount received in')</span> <span class="gateway-currency"></span>: <span class="in-currency"></span></div>

            <button type="submit" class="nocturne-wallet-cta" id="withdraw-cta" disabled><i class="ph-bold ph-paper-plane-tilt"></i><span>@lang('Request Withdraw')</span></button>

            <div class="nocturne-wallet-footrow">
                <div><span>@lang('Minimum Withdraw')</span><strong class="gateway-limit">—</strong></div>
                <div><span>@lang('Fee')</span><strong class="processing-fee-label">@lang('No Commission')</strong></div>
            </div>
        </div>
    </form>

    <section class="nocturne-wallet-history" aria-labelledby="withdraw-history-title">
        <div class="nocturne-wallet-history__heading"><div><span class="nocturne-wallet-kicker">@lang('YOUR ACTIVITY')</span><h2 id="withdraw-history-title">@lang('Withdrawal History')</h2></div><a href="{{ route('user.withdraw.history') }}">@lang('View all') <i class="ph-bold ph-arrow-right"></i></a></div>
        <div class="nocturne-wallet-history__list">
            @forelse($withdraws as $withdraw)
                <div class="nocturne-wallet-history__row">
                    <span class="nocturne-wallet-history__icon"><i class="ph-fill ph-paper-plane-tilt"></i></span>
                    <div class="nocturne-wallet-history__mid"><strong>{{ __($withdraw->method->name ?? 'Payout') }}</strong><small>{{ $withdraw->trx }} · {{ showDateTime($withdraw->created_at, 'M d, Y') }}</small></div>
                    <div class="nocturne-wallet-history__end"><strong>{{ showAmount($withdraw->amount) }}</strong>{!! $withdraw->statusBadge !!}</div>
                </div>
            @empty
                <div class="nocturne-wallet-empty"><i class="ph-fill ph-paper-plane-tilt"></i><strong>@lang('No withdrawals yet')</strong><span>@lang('Your completed and pending withdrawals will appear here.')</span></div>
            @endforelse
        </div>
        <div class="nocturne-wallet-history__pagination">{{ paginateLinks($withdraws) }}</div>
    </section>
        </div>
    </div>
@endsection

@push('style')
<style>
:root{--wallet-accent:#00e78a;--wallet-bg:#10152a;--wallet-panel:#151a31;--wallet-raised:#1b2140;--wallet-muted:#858eaf;--wallet-line:rgba(133,142,175,.18);--wallet-text:#f5f6ff}
.nocturne-wallet-page{max-width:900px;margin-inline:auto}
.nocturne-wallet-page .nocturne-page-title{margin-bottom:16px;font-size:26px}
.nocturne-wallet-hero{position:relative;display:flex;align-items:center;justify-content:space-between;gap:20px;overflow:hidden;margin:0 0 22px;padding:24px 28px;border:1px solid var(--wallet-line);border-radius:24px;background:radial-gradient(ellipse at 80% 0,rgba(0,231,138,.2),transparent 36%),linear-gradient(120deg,#171d37,#11162b 70%);box-shadow:0 18px 50px rgba(0,0,0,.18)}
.nocturne-wallet-hero__eyebrow{display:inline-flex;align-items:center;gap:7px;color:var(--wallet-accent);font-size:11.5px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}
.nocturne-wallet-hero__copy h2{margin:8px 0 4px;color:var(--wallet-text);font-size:clamp(20px,2.4vw,28px);font-weight:850;letter-spacing:-.03em}
.nocturne-wallet-hero__copy p{margin:0;color:var(--wallet-muted);font-size:13.5px}
.nocturne-wallet-hero__balance{position:relative;min-width:190px;padding:14px 46px 14px 16px;border:1px solid rgba(255,255,255,.08);border-radius:16px;background:rgba(7,11,27,.5)}
.nocturne-wallet-hero__balance>span{display:block;color:var(--wallet-muted);font-size:11px;font-weight:700}
.nocturne-wallet-hero__balance strong{display:block;margin-top:3px;color:#fff;font-size:19px;font-weight:800;white-space:nowrap}
.nocturne-wallet-hero__balance small{color:var(--wallet-muted);font-size:11px}
.nocturne-wallet-hero__balance>i{position:absolute;right:13px;top:50%;transform:translateY(-50%);color:var(--wallet-accent);font-size:22px}

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

.nocturne-wallet-fields{display:grid;gap:14px;margin-bottom:18px;padding:16px;border:1px solid var(--wallet-line);border-radius:18px;background:rgba(7,11,27,.42)}
.nocturne-wallet-fields .withdraw-method-fields{min-width:0;margin:0;padding:0;border:0}
.nocturne-wallet-fields .form-group{margin-bottom:12px}
.nocturne-wallet-fields .form-group:last-child{margin-bottom:0}
.nocturne-wallet-fields .form-control,.nocturne-wallet-fields .form--control{min-height:48px;border:1px solid var(--wallet-line)!important;border-radius:12px;background:var(--wallet-bg)!important;color:var(--wallet-text)!important}
.nocturne-wallet-fields label{color:var(--wallet-muted);font-size:12.5px;font-weight:700}

.nocturne-wallet-amount{display:flex;align-items:center;min-height:62px;border:1px solid var(--wallet-line);border-radius:16px;background:var(--wallet-bg);overflow:hidden}
.nocturne-wallet-amount__prefix{display:grid;place-items:center;flex:0 0 auto;min-width:52px;height:100%;color:var(--wallet-accent);font-size:19px;font-weight:800;border-right:1px solid var(--wallet-line)}
.nocturne-wallet-amount .amount{flex:1;min-width:0;height:60px;padding:0 16px;border:0;background:transparent;color:#fff;font-size:22px;font-weight:800;outline:0}
.nocturne-wallet-amount .amount::placeholder{color:#606986}
.nocturne-wallet-amount--withdraw .amount{padding-right:4px}
.nocturne-wallet-max-btn{flex:0 0 auto;margin-right:8px;padding:9px 15px;border:0;border-radius:10px;background:rgba(0,231,138,.14);color:var(--wallet-accent);font-size:12px;font-weight:800;cursor:pointer}
.nocturne-wallet-max-btn:hover{background:rgba(0,231,138,.22)}

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
.nocturne-wallet-history__heading h2{margin:0;color:var(--wallet-text);font-size:18px;font-weight:850}
.nocturne-wallet-kicker{display:block;margin-bottom:3px;color:var(--wallet-accent);font-size:9px;font-weight:850;letter-spacing:.14em}
.nocturne-wallet-history__heading>a{display:inline-flex;align-items:center;gap:6px;padding:9px 13px;border-radius:11px;background:var(--wallet-raised);color:var(--wallet-text);font-size:12px;font-weight:750;text-decoration:none}
.nocturne-wallet-history__list{display:grid;gap:9px}
.nocturne-wallet-history__row{display:flex;align-items:center;gap:12px;padding:12px;border:1px solid var(--wallet-line);border-radius:14px;background:rgba(9,13,28,.5)}
.nocturne-wallet-history__icon{display:grid;place-items:center;flex:0 0 38px;width:38px;height:38px;border-radius:11px;background:rgba(0,231,138,.1);color:var(--wallet-accent)}
.nocturne-wallet-history__mid{flex:1;min-width:0;display:grid;gap:2px}
.nocturne-wallet-history__mid strong{overflow:hidden;color:var(--wallet-text);font-size:13.5px;text-overflow:ellipsis;white-space:nowrap}
.nocturne-wallet-history__mid small{color:var(--wallet-muted);font-size:11px}
.nocturne-wallet-history__end{display:grid;justify-items:end;gap:4px;flex:0 0 auto}
.nocturne-wallet-history__end strong{color:var(--wallet-text);font-size:13.5px}
.nocturne-wallet-history__pagination{margin-top:14px}

@media(max-width:575.98px){
    .nocturne-wallet-hero{flex-direction:column;align-items:stretch;padding:18px}
    .nocturne-wallet-hero__balance{width:100%}
    .nocturne-wallet-switch__opt{min-height:76px;padding:10px}
    .nocturne-wallet-footrow{flex-direction:column;gap:10px}
    .nocturne-wallet-history__row{flex-wrap:wrap}
    .nocturne-wallet-max-btn{padding:8px 11px;font-size:11px}
}
</style>
@endpush

@push('script')
<script>
"use strict";
(function($) {
    var amount = parseFloat($('.amount').val() || 0);
    var gateway, minAmount, maxAmount;

    $('.amount').on('input', function() {
        amount = parseFloat($(this).val()) || 0;
        calculation();
    });

    $('.gateway-input').on('change', gatewayChange);

    function gatewayChange() {
        let el = $('.gateway-input:checked');
        if (!el.length) {
            gateway = null;
            $('.nocturne-wallet-pick__thumb').attr('src', '').hide();
            $('#pay-dropdown-name').text("{{ __('Choose an option') }}");
            $('#withdraw-cta').prop('disabled', true);
            $('.withdraw-method-fields').prop('disabled', true).prop('hidden', true);
            $('.gateway-limit').text('—');
            $('.processing-fee-label').text("{{ __('No Commission') }}");
            return;
        }
        gateway = el.data('gateway');
        minAmount = el.data('min-amount');
        maxAmount = el.data('max-amount');

        $('.withdraw-method-fields').prop('disabled', true).prop('hidden', true);
        $('.withdraw-method-fields[data-method-id="' + el.val() + '"]').prop('disabled', false).prop('hidden', false);

        let selectedImage = el.closest('.nocturne-wallet-option').find('img').attr('src');
        let selectedName = el.closest('.nocturne-wallet-option').find('.nocturne-wallet-option__name').clone().children().remove().end().text().trim();
        if (selectedImage) {
            $('.nocturne-wallet-pick__thumb').attr('src', selectedImage).prop('hidden', false).show();
            $('#pay-selected-fallback-icon').hide();
        }
        $('#pay-dropdown-name').text(selectedName + (gateway.currency ? ' ' + gateway.currency : ''));

        calculation();
    }

    gatewayChange();

    $('.nocturne-wallet-switch__opt').on('click', function() {
        if (this.disabled) return;
        let filter = String($(this).data('filter'));
        $('.nocturne-wallet-switch__opt').removeClass('is-active');
        $(this).addClass('is-active');
        $('.gateway-option').each(function() {
            $(this).toggle(String($(this).data('category')) === filter);
        });
        $('#pay-dropdown-search').val('').trigger('input');
        let visibleChecked = $('.gateway-option:visible .gateway-input:checked');
        if (!visibleChecked.length) {
            $('.gateway-input').prop('checked', false);
            let firstVisible = $('.gateway-option:visible').first();
            if (firstVisible.length) firstVisible.find('.gateway-input').prop('checked', true);
        }
        gatewayChange();
    });

    function calculation() {
        if (!gateway) return;
        $('.gateway-limit').text("{{ gs('cur_sym') }}" + minAmount);

        let percentCharge = parseFloat(gateway.percent_charge) || 0;
        let fixedCharge = parseFloat(gateway.fixed_charge) || 0;
        let totalPercentCharge = amount ? (amount / 100 * percentCharge) : 0;
        let totalCharge = totalPercentCharge + fixedCharge;
        let totalAmount = (amount || 0) - totalPercentCharge - fixedCharge;

        $('.processing-fee-label').text(totalCharge > 0 ? (totalCharge.toFixed(2) + ' {{ __(gs("cur_text")) }}') : "{{ __('No Commission') }}");
        $('.nocturne-wallet-charges').toggleClass('d-none', !(percentCharge > 0 || fixedCharge > 0));
        $('input[name=currency]').val(gateway.currency);
        $('.gateway-currency').text(gateway.currency);

        $('#withdraw-cta').prop('disabled', !(amount >= Number(gateway.min_limit) && amount <= Number(gateway.max_limit)));

        if (gateway.currency != "{{ gs('cur_text') }}") {
            $('.gateway-conversion, .conversion-currency').removeClass('d-none');
            $('.gateway-conversion').html(`1 {{ __(gs('cur_text')) }} = ${parseFloat(gateway.rate).toFixed(2)} ${gateway.currency}`);
            $('.in-currency').text((totalAmount * gateway.rate).toFixed(2));
        } else {
            $('.gateway-conversion, .conversion-currency').addClass('d-none');
        }
    }

    if ($('.nocturne-wallet-switch__opt').length) {
        let checkedCategory = $('.gateway-input:checked').closest('.gateway-option').data('category');
        let initialFilter = checkedCategory === undefined ? $('.nocturne-wallet-switch__opt:not(:disabled)').first().data('filter') : checkedCategory;
        $('.nocturne-wallet-switch__opt').filter(`[data-filter="${initialFilter}"]`).trigger('click');
    } else {
        gatewayChange();
    }

    // Max: fills the amount field with balance minus the estimated flat+percent fee
    $('#withdraw-max-btn').on('click', function() {
        if (!gateway) return;
        const balance = {{ (float) auth()->user()->balance }};
        const percentCharge = parseFloat(gateway.percent_charge) || 0;
        const fixedCharge = parseFloat(gateway.fixed_charge) || 0;
        let max = (balance - fixedCharge) / (1 + percentCharge / 100);
        max = Math.max(0, max);
        $('.amount').val(max.toFixed(2)).trigger('input');
    });

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
        $('.gateway-option').each(function() {
            let matches = !term || String($(this).data('search') || '').includes(term);
            $(this).toggle(matches);
        });
    });
})(jQuery);
</script>
@endpush
