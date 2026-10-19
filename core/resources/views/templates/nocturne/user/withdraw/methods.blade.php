@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content nocturne-payment-page nocturne-withdraw-page">
    <h1 class="nocturne-page-title">@lang('Withdraw')</h1>
    <section class="nocturne-payment-hero">
        <div class="nocturne-payment-hero__copy">
            <span class="nocturne-payment-hero__eyebrow"><i class="las la-shield-alt"></i> @lang('Secure withdrawals')</span>
            <h2>@lang('Your winnings, on the way')</h2>
            <p>@lang('Choose where to receive your funds, enter an amount and review the payout details.')</p>
        </div>
        <div class="nocturne-payment-hero__balance">
            <span>@lang('Available balance')</span>
            <strong>{{ gs('cur_sym') }}{{ showAmount(auth()->user()->balance, currencyFormat: false) }} <small>{{ __(gs('cur_text')) }}</small></strong>
            <i class="las la-wallet"></i>
        </div>
    </section>
    <div class="row justify-content-center">
        <div class="col-12">
            <form action="{{ route('user.withdraw.money') }}" method="post" class="withdraw-form">
                @csrf
                <div class="gateway-card">
                    <div class="row justify-content-center gy-sm-4 gy-3 payment-workspace">
                        <div class="col-lg-5 payment-method-column">
                            <div class="card custom--card payment-method-panel">
                                <div class="card-body p-0">
                                    <div class="nocturne-pay-list-heading"><div><span class="nocturne-pay-kicker">@lang('STEP 01')</span><h4 class="nocturne-pay-step">@lang('Select a withdrawal method')</h4></div><span class="nocturne-pay-secure"><i class="las la-lock"></i> @lang('Secure')</span></div>
                                    <div class="payment-system-list is-scrollable gateway-option-list">
                                        @foreach ($withdrawMethod as $data)
                                            <label for="{{ titleToKey($data->name) }}"
                                                class="payment-item @if ($loop->index > 4) d-none @endif gateway-option">
                                                <div class="payment-item__info">
                                                    <span class="payment-item__check"></span>
                                                    <span class="payment-item__name">{{ __($data->name) }}</span>
                                                </div>
                                                <div class="payment-item__thumb">
                                                    <img class="payment-item__thumb-img"
                                                        src="{{ getImage(getFilePath('withdrawMethod') . '/' . $data->image) }}"
                                                        alt="@lang('payment-thumb')">
                                                </div>
                                                <input class="payment-item__radio gateway-input"
                                                    id="{{ titleToKey($data->name) }}" hidden
                                                    data-gateway='@json($data)' type="radio"
                                                    name="method_code" value="{{ $data->id }}"
                                                    @if (old('method_code')) @checked(old('method_code') == $data->id) @else @checked($loop->first) @endif
                                                    data-min-amount="{{ showAmount($data->min_limit) }}"
                                                    data-max-amount="{{ showAmount($data->max_limit) }}">
                                            </label>
                                        @endforeach
                                        @if ($withdrawMethod->count() > 4)
                                            <button type="button" class="payment-item__btn more-gateway-option">
                                                <p class="payment-item__btn-text">@lang('Show All Payment Options')</p>
                                                <span class="payment-item__btn__icon"><i
                                                        class="fas fa-chevron-down"></i></i></span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-7 payment-summary-column">
                            <div class="card custom--card payment-summary-panel">
                                <div class="card-body p-0">
                                    <div class="nocturne-pay-detail-heading"><div><span class="nocturne-pay-kicker">@lang('PAYOUT DETAILS')</span><h3>@lang('Where should we send it?')</h3><p>@lang('Choose a method, provide your receiving details, then enter an amount.')</p></div><span class="nocturne-pay-detail-icon"><i class="las la-money-check-alt"></i></span></div>
                                    <div class="nocturne-pay-selected">
                                        <img class="nocturne-pay-selected__thumb" src="" alt="">
                                        <span class="nocturne-pay-selected__name"></span>
                                        <span class="nocturne-pay-selected__badge">@lang('Selected')</span>
                                    </div>
                                    <div class="payment-system-list p-3">
                                        <div class="deposit-info">
                                            <div class="deposit-info__title">
                                                <p class="text mb-0">@lang('Amount')</p>
                                            </div>
                                            <div class="deposit-info__input">
                                                <div class="deposit-info__input-group input-group">
                                                    <span class="deposit-info__input-group-text">{{ gs('cur_sym') }}</span>
                                                    <input type="text" class="form-control form--control amount"
                                                        name="amount" placeholder="@lang('00.00')"
                                                        value="{{ old('amount') }}" autocomplete="off">
                                                </div>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="deposit-info">
                                            <div class="deposit-info__title">
                                                <p class="text has-icon"> @lang('Limit')</p>
                                            </div>
                                            <div class="deposit-info__input">
                                                <p class="text"><span class="gateway-limit">@lang('0.00')</span> </p>
                                            </div>
                                        </div>
                                        <div class="deposit-info">
                                            <div class="deposit-info__title">
                                                <p class="text has-icon">@lang('Processing Charge')
                                                    <span data-bs-toggle="tooltip" title="@lang('Processing charge for withdraw method')"
                                                        class="proccessing-fee-info"><i class="las la-info-circle"></i>
                                                    </span>
                                                </p>
                                            </div>
                                            <div class="deposit-info__input">
                                                <p class="text">{{ gs('cur_sym') }}<span
                                                        class="processing-fee">@lang('0.00')</span>
                                                    {{ __(gs('cur_text')) }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="deposit-info total-amount pt-3">
                                            <div class="deposit-info__title">
                                                <p class="text">@lang('Receivable')</p>
                                            </div>
                                            <div class="deposit-info__input">
                                                <p class="text">{{ gs('cur_sym') }}<span
                                                        class="final-amount">@lang('0.00')</span>
                                                    {{ __(gs('cur_text')) }}</p>
                                            </div>
                                        </div>

                                        <div class="deposit-info gateway-conversion d-none total-amount pt-2">
                                            <div class="deposit-info__title">
                                                <p class="text">@lang('Conversion')
                                                </p>
                                            </div>
                                            <div class="deposit-info__input">
                                                <p class="text"></p>
                                            </div>
                                        </div>
                                        <div class="deposit-info conversion-currency d-none total-amount pt-2">
                                            <div class="deposit-info__title">
                                                <p class="text">
                                                    @lang('In') <span class="gateway-currency"></span>
                                                </p>
                                            </div>
                                            <div class="deposit-info__input">
                                                <p class="text">
                                                    <span class="in-currency"></span>
                                                </p>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn--base w-100 mt-3"
                                            disabled><i class="ph-bold ph-money-wavy"></i>@lang('Confirm Withdraw')</button>
                                        <div class="info-text pt-3">
                                            <p class="text">@lang('Safely withdraw your funds using our highly secure process and various withdrawal method')</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <a href="{{ route('user.withdraw.history') }}" class="nocturne-pay-history-link"><i class="ph-bold ph-clock-counter-clockwise"></i>@lang('View Withdraw History')<i class="ph-bold ph-caret-right"></i></a>
        </div>
    </div>
        </div>
    </div>
@endsection
@push('style')
    <style>
        .nocturne-payment-page{--pay-accent:#00e78a;--pay-panel:#151a31;--pay-soft:#202744;--pay-muted:#858eaf;max-width:1480px;margin-inline:auto}
        .nocturne-payment-page .nocturne-page-title{margin-bottom:18px}
        .nocturne-payment-hero{position:relative;display:flex;align-items:center;justify-content:space-between;gap:24px;overflow:hidden;margin:0 0 24px;padding:26px 30px;border:1px solid rgba(133,142,175,.14);border-radius:24px;background:radial-gradient(ellipse at 78% 0,rgba(0,231,138,.2),transparent 34%),linear-gradient(120deg,#171d37,#11162b 68%);box-shadow:0 18px 50px rgba(0,0,0,.18)}
        .nocturne-payment-hero:after{content:"";position:absolute;width:240px;height:240px;right:22%;top:-150px;border:1px solid rgba(0,231,138,.15);border-radius:50%;box-shadow:0 0 0 22px rgba(0,231,138,.025),0 0 0 46px rgba(0,231,138,.02);pointer-events:none}
        .nocturne-payment-hero__copy,.nocturne-payment-hero__balance{position:relative;z-index:1}
        .nocturne-payment-hero__eyebrow{display:inline-flex;align-items:center;gap:7px;color:var(--pay-accent);font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}
        .nocturne-payment-hero__copy h2{margin:8px 0 5px;color:#f5f6ff;font-size:clamp(22px,2.2vw,32px);font-weight:800;letter-spacing:-.04em}
        .nocturne-payment-hero__copy p{margin:0;color:var(--pay-muted);font-size:14px}
        .nocturne-payment-hero__balance{min-width:225px;padding:16px 48px 16px 18px;border:1px solid rgba(255,255,255,.07);border-radius:18px;background:rgba(7,11,27,.48)}
        .nocturne-payment-hero__balance>span{display:block;color:var(--pay-muted);font-size:12px;font-weight:700}
        .nocturne-payment-hero__balance strong{display:block;margin-top:3px;color:#fff;font-size:21px;font-weight:800;white-space:nowrap}
        .nocturne-payment-hero__balance small{color:var(--pay-muted);font-size:12px}
        .nocturne-payment-hero__balance>i{position:absolute;right:15px;top:50%;transform:translateY(-50%);color:var(--pay-accent);font-size:25px}
        .nocturne-payment-page .payment-workspace{align-items:stretch}
        .nocturne-payment-page .payment-method-panel,.nocturne-payment-page .payment-summary-panel{height:100%;padding:22px;border:1px solid rgba(133,142,175,.12);border-radius:22px;background:var(--pay-panel);box-shadow:0 15px 40px rgba(0,0,0,.15)}
        .nocturne-payment-page .nocturne-pay-step{display:flex;align-items:center;gap:10px;margin:0 0 15px;color:#f3f4ff;font-size:15px;font-weight:800}
        .nocturne-payment-page .nocturne-pay-step:before{display:grid;place-items:center;width:27px;height:27px;border-radius:9px;background:rgba(0,231,138,.13);color:var(--pay-accent);font-size:12px;content:counter(paystep);counter-increment:paystep}
        .nocturne-payment-page .payment-method-panel .card-body{counter-reset:paystep;padding:0!important}
        .nocturne-payment-page .nocturne-pay-tabs{display:flex;gap:8px;margin:0 0 20px;padding:5px;border-radius:13px;background:#0d1225}
        .nocturne-payment-page .nocturne-pay-tab{flex:1;min-height:40px;border:0;border-radius:10px;background:transparent;color:var(--pay-muted);font-size:13px;font-weight:800;transition:.2s}
        .nocturne-payment-page .nocturne-pay-tab.is-active{background:var(--pay-soft);color:#fff;box-shadow:0 3px 12px rgba(0,0,0,.18)}
        .nocturne-payment-page .payment-system-list.is-scrollable{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;max-height:410px;overflow:auto;padding:0 3px 4px}
        .nocturne-payment-page .payment-item{position:relative;display:flex;align-items:center;justify-content:space-between;gap:8px;min-height:68px;margin:0;padding:12px;border:1px solid rgba(133,142,175,.16);border-radius:15px;background:#11162b;cursor:pointer;transition:transform .18s,border-color .18s,background .18s}
        .nocturne-payment-page .payment-item:hover{transform:translateY(-2px);border-color:rgba(0,231,138,.45)}
        .nocturne-payment-page .payment-item:has(.gateway-input:checked){border-color:var(--pay-accent);background:linear-gradient(135deg,rgba(0,231,138,.13),#151a31 75%);box-shadow:0 0 0 1px rgba(0,231,138,.13)}
        .nocturne-payment-page .payment-item__info{display:flex;align-items:center;gap:9px;min-width:0}
        .nocturne-payment-page .payment-item__name{overflow:hidden;color:#f2f3fc;font-size:13px;font-weight:750;text-overflow:ellipsis;white-space:nowrap}
        .nocturne-payment-page .payment-item__check{flex:none;width:16px;height:16px;border:1.5px solid #606987;border-radius:50%}
        .nocturne-payment-page .payment-item:has(.gateway-input:checked) .payment-item__check{border:5px solid var(--pay-accent)}
        .nocturne-payment-page .payment-item__thumb{flex:none;width:38px;height:30px;display:grid;place-items:center;border-radius:8px;background:#fff;padding:3px}
        .nocturne-payment-page .payment-item__thumb-img{max-width:100%;max-height:100%;object-fit:contain}
        .nocturne-payment-page .payment-item__btn{grid-column:1/-1;display:flex;justify-content:center;align-items:center;gap:8px;min-height:42px;border:0;border-radius:12px;background:var(--pay-soft);color:#fff}
        .nocturne-payment-page .payment-item__btn-text{margin:0;font-size:12px;font-weight:700}
        .nocturne-payment-page .nocturne-pay-selected{display:flex;align-items:center;gap:10px;margin:0 0 18px;padding:12px 14px;border:1px solid rgba(0,231,138,.16);border-radius:15px;background:rgba(0,231,138,.07)}
        .nocturne-payment-page .nocturne-pay-selected__thumb{width:32px;height:26px;object-fit:contain;border-radius:6px;background:#fff;padding:2px}
        .nocturne-payment-page .nocturne-pay-selected__name{color:#f4f5fd;font-weight:800}
        .nocturne-payment-page .nocturne-pay-selected__badge{margin-left:auto;color:var(--pay-accent);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.08em}
        .nocturne-payment-page .payment-summary-panel .payment-system-list{padding:0!important}
        .nocturne-payment-page .deposit-info__title .text{color:var(--pay-muted);font-size:13px;font-weight:650}
        .nocturne-payment-page .deposit-info__input .text{color:#f5f6ff;font-size:13px;font-weight:750}
        .nocturne-payment-page .deposit-info__input-group{min-height:62px;align-items:center;border:1px solid rgba(133,142,175,.2);border-radius:15px;background:#10152a;overflow:hidden}
        .nocturne-payment-page .deposit-info__input-group-text{padding-left:17px;color:var(--pay-accent);font-size:20px;font-weight:800}
        .nocturne-payment-page .deposit-info__input-group .amount{height:58px;border:0!important;color:#fff!important;font-size:24px;font-weight:800;box-shadow:none!important}
        .nocturne-payment-page .deposit-info__input-group .amount::placeholder{color:#606986}
        .nocturne-payment-page hr{border-color:rgba(133,142,175,.16);opacity:1}
        .nocturne-payment-page .total-amount{padding:11px 13px;border-radius:12px;background:rgba(255,255,255,.035)}
        .nocturne-payment-page .total-amount .deposit-info__title .text,.nocturne-payment-page .total-amount .deposit-info__input .text{margin:0}
        .nocturne-payment-page .deposit-info.total-amount:last-of-type{margin-top:10px;border:1px solid rgba(0,231,138,.2);background:rgba(0,231,138,.08)}
        .nocturne-payment-page .withdraw-form button[type=submit]{min-height:54px;border-radius:14px;font-size:15px;font-weight:850;box-shadow:0 10px 25px rgba(0,231,138,.15)}
        .nocturne-payment-page .info-text{color:var(--pay-muted);font-size:12px;line-height:1.55}
        .nocturne-payment-page .info-text p{margin:0}
        .nocturne-payment-page .nocturne-pay-history-link{display:flex;align-items:center;gap:9px;width:max-content;max-width:100%;margin:16px 0 0 auto;padding:12px 16px;border:1px solid rgba(133,142,175,.15);border-radius:13px;background:var(--pay-panel);color:#dfe2f4;font-weight:750;text-decoration:none}
        .nocturne-payment-page .nocturne-pay-history-link i:last-child{margin-left:5px;color:var(--pay-accent)}
        @media(max-width:767.98px){.nocturne-payment-page{padding-bottom:calc(24px + env(safe-area-inset-bottom))}.nocturne-payment-page .nocturne-page-title{font-size:24px}.nocturne-payment-hero{align-items:flex-start;flex-direction:column;gap:17px;margin-bottom:16px;padding:20px;border-radius:20px}.nocturne-payment-hero__balance{width:100%;min-width:0;padding:12px 48px 12px 14px}.nocturne-payment-hero__balance strong{font-size:19px}.nocturne-payment-page .payment-method-panel,.nocturne-payment-page .payment-summary-panel{padding:17px;border-radius:19px}.nocturne-payment-page .payment-system-list.is-scrollable{grid-template-columns:repeat(2,minmax(0,1fr));max-height:265px;gap:8px}.nocturne-payment-page .payment-item{min-height:62px;padding:9px}.nocturne-payment-page .payment-item__thumb{width:30px;height:25px}.nocturne-payment-page .payment-item__name{font-size:12px}.nocturne-payment-page .deposit-info__input-group{min-height:58px}.nocturne-payment-page .deposit-info__input-group .amount{height:54px;font-size:22px}.nocturne-payment-page .nocturne-pay-history-link{width:100%;justify-content:center;margin-top:12px}.nocturne-payment-page .nocturne-pay-step{margin-bottom:12px}}
        @media(max-width:360px){.nocturne-payment-page .payment-system-list.is-scrollable{grid-template-columns:1fr}.nocturne-payment-page .payment-item{min-height:58px}}
        .nocturne-payment-page .payment-method-panel,.nocturne-payment-page .payment-summary-panel{min-height:100%;padding:26px;background:linear-gradient(155deg,#171d36 0%,#13182e 72%);border-color:rgba(133,142,175,.18)}
        .nocturne-payment-page .payment-method-column{flex:0 0 auto;width:41.666667%}
        .nocturne-payment-page .payment-summary-column{flex:0 0 auto;width:58.333333%}
        .nocturne-payment-page .nocturne-pay-list-heading{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px}
        .nocturne-payment-page .nocturne-pay-kicker{display:block;margin-bottom:5px;color:var(--pay-accent);font-size:9px;font-weight:850;letter-spacing:.15em}
        .nocturne-payment-page .nocturne-pay-list-heading .nocturne-pay-step{margin:0;font-size:17px}
        .nocturne-payment-page .nocturne-pay-list-heading .nocturne-pay-step:before{display:none}
        .nocturne-payment-page .nocturne-pay-secure{display:inline-flex;align-items:center;gap:5px;padding:7px 9px;border:1px solid rgba(0,231,138,.16);border-radius:10px;color:var(--pay-accent);font-size:10px;font-weight:750}
        .nocturne-payment-page .payment-system-list.is-scrollable{grid-template-columns:1fr;max-height:440px;gap:9px}
        .nocturne-payment-page .payment-item{min-height:72px;padding:13px 15px;border-radius:15px;background:linear-gradient(110deg,#10152a,#141a32)}
        .nocturne-payment-page .payment-item__check{width:18px;height:18px}
        .nocturne-payment-page .payment-item__name{font-size:14px}
        .nocturne-payment-page .payment-item__thumb{width:48px;height:38px;border-radius:10px}
        .nocturne-payment-page .nocturne-pay-detail-heading{display:flex;justify-content:space-between;align-items:center;gap:16px;margin:0 0 22px;padding:4px 2px 19px;border-bottom:1px solid rgba(133,142,175,.14)}
        .nocturne-payment-page .nocturne-pay-detail-heading h3{margin:0 0 6px;color:#f5f6ff;font-size:clamp(19px,2vw,25px);font-weight:850;letter-spacing:-.035em}
        .nocturne-payment-page .nocturne-pay-detail-heading p{max-width:500px;margin:0;color:var(--pay-muted);font-size:12px;line-height:1.55}
        .nocturne-payment-page .nocturne-pay-detail-icon{display:grid;place-items:center;flex:none;width:54px;height:54px;border-radius:17px;background:rgba(0,231,138,.1);color:var(--pay-accent);font-size:27px}
        .nocturne-payment-page .nocturne-pay-selected{min-height:58px}
        .nocturne-payment-page .payment-summary-panel .payment-system-list{padding:0!important}
        @media(max-width:991.98px){.nocturne-payment-page .payment-method-column,.nocturne-payment-page .payment-summary-column{width:100%}.nocturne-payment-page .payment-system-list.is-scrollable{max-height:360px}}
        @media(max-width:767.98px){.nocturne-payment-page .payment-method-panel,.nocturne-payment-page .payment-summary-panel{padding:17px}.nocturne-payment-page .payment-system-list.is-scrollable{grid-template-columns:1fr;max-height:300px}.nocturne-payment-page .nocturne-pay-detail-heading{align-items:flex-start;margin-bottom:17px;padding-bottom:15px}.nocturne-payment-page .nocturne-pay-detail-icon{width:44px;height:44px;border-radius:14px;font-size:22px}.nocturne-payment-page .nocturne-pay-list-heading .nocturne-pay-step{font-size:15px}}
        .btn:disabled {
            border: unset;
        }

        .form--control {
            background: unset !important;
        }
    </style>
@endpush



@push('script')
    <script>
        "use strict";
        (function($) {

            var amount = parseFloat($('.amount').val() || 0);
            var gateway, minAmount, maxAmount;


            $('.amount').on('input', function(e) {
                amount = parseFloat($(this).val());
                if (!amount) {
                    amount = 0;
                }
                calculation();
            });

            $('.gateway-input').on('change', function(e) {
                gatewayChange();
            });

            function gatewayChange() {
                let gatewayElement = $('.gateway-input:checked');
                let methodCode = gatewayElement.val();

                gateway = gatewayElement.data('gateway');
                minAmount = gatewayElement.data('min-amount');
                maxAmount = gatewayElement.data('max-amount');

                let processingFeeInfo =
                    `${parseFloat(gateway.percent_charge).toFixed(2)}% with ${parseFloat(gateway.fixed_charge).toFixed(2)} {{ __(gs('cur_text')) }} charge for processing fees`
                $(".proccessing-fee-info").attr("data-bs-original-title", processingFeeInfo);
                $(".nocturne-pay-selected__thumb").attr('src', gatewayElement.closest('.payment-item').find('.payment-item__thumb-img').attr('src'));
                $(".nocturne-pay-selected__name").text(gatewayElement.closest('.payment-item').find('.payment-item__name').text());

                calculation();
            }

            gatewayChange();

            $(".more-gateway-option").on("click", function(e) {
                let paymentList = $(".gateway-option-list");
                paymentList.find(".gateway-option").removeClass("d-none");
                $(this).addClass('d-none');
                paymentList.animate({
                    scrollTop: (paymentList.height() - 60)
                }, 'slow');
            });

            function calculation() {
                if (!gateway) return;
                $(".gateway-limit").text(minAmount + " - " + maxAmount);

                let percentCharge = 0;
                let fixedCharge = 0;
                let totalPercentCharge = 0;

                if (amount) {
                    percentCharge = parseFloat(gateway.percent_charge);
                    fixedCharge = parseFloat(gateway.fixed_charge);
                    totalPercentCharge = parseFloat(amount / 100 * percentCharge);
                }

                let totalCharge = parseFloat(totalPercentCharge + fixedCharge);
                let totalAmount = parseFloat((amount || 0) - totalPercentCharge - fixedCharge);

                $(".final-amount").text(totalAmount.toFixed(2));
                $(".processing-fee").text(totalCharge.toFixed(2));
                $("input[name=currency]").val(gateway.currency);
                $(".gateway-currency").text(gateway.currency);

                if (amount < Number(gateway.min_limit) || amount > Number(gateway.max_limit)) {
                    $(".withdraw-form button[type=submit]").attr('disabled', true);
                } else {
                    $(".withdraw-form button[type=submit]").removeAttr('disabled');
                }

                if (gateway.currency != "{{ gs('cur_text') }}") {
                    $('.withdraw-form').addClass('adjust-height')
                    $(".gateway-conversion, .conversion-currency").removeClass('d-none');
                    $(".gateway-conversion").find('.deposit-info__input .text').html(
                        `1 {{ __(gs('cur_text')) }} = <span class="rate">${parseFloat(gateway.rate).toFixed(2)}</span>  <span class="method_currency">${gateway.currency}</span>`
                    );
                    $('.in-currency').text(parseFloat(totalAmount * gateway.rate).toFixed(2))
                } else {
                    $(".gateway-conversion, .conversion-currency").addClass('d-none');
                    $('.withdraw-form').removeClass('adjust-height')
                }
            }

            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
            $('.gateway-input').change();
        })(jQuery);
    </script>
@endpush
