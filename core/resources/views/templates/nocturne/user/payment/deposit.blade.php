@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content">
    <h1 class="nocturne-page-title">@lang('Deposit')</h1>
    @php
        $hasCrypto = $gatewayCurrency->contains(fn($g) => $g->method->crypto == 1);
        $hasCard   = $gatewayCurrency->contains(fn($g) => $g->method->crypto != 1);
    @endphp
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <form action="{{ route('user.deposit.insert') }}" method="post" class="deposit-form">
                @csrf
                <input type="hidden" name="currency">
                <div class="gateway-card">
                    <div class="row justify-content-center gy-sm-4 gy-3">
                        <div class="col-lg-6">
                            <div class="card custom--card">
                                <div class="card-body p-0">
                                    <h4 class="nocturne-pay-step">1. @lang('Select a Deposit Method')</h4>
                                    @if ($hasCrypto && $hasCard)
                                        <div class="nocturne-pay-tabs">
                                            <button type="button" class="nocturne-pay-tab is-active" data-filter="1">@lang('Crypto')</button>
                                            <button type="button" class="nocturne-pay-tab" data-filter="0">@lang('Bank Card')</button>
                                        </div>
                                    @endif
                                    <h4 class="nocturne-pay-step">2. @lang('Select Method to Deposit')</h4>
                                    <div class="payment-system-list is-scrollable gateway-option-list">
                                        @foreach ($gatewayCurrency as $data)
                                            <label for="{{ titleToKey($data->name) }}"
                                                data-crypto="{{ $data->method->crypto }}"
                                                class="payment-item @if ($loop->index > 4) d-none @endif gateway-option">
                                                <div class="payment-item__info">
                                                    <span class="payment-item__check"></span>
                                                    <span class="payment-item__name">{{ __($data->name) }}</span>
                                                </div>
                                                <div class="payment-item__thumb">
                                                    <img class="payment-item__thumb-img"
                                                        src="{{ getImage(getFilePath('gateway') . '/' . $data->method->image) }}"
                                                        alt="@lang('payment-thumb')">
                                                </div>
                                                <input class="payment-item__radio gateway-input"
                                                    id="{{ titleToKey($data->name) }}" hidden
                                                    data-gateway='@json($data)' type="radio"
                                                    name="gateway" value="{{ $data->method_code }}"
                                                    @checked(old('method_code', $loop->first) == $data->id)
                                                    data-min-amount="{{ showAmount($data->min_amount) }}"
                                                    data-max-amount="{{ showAmount($data->max_amount) }}">
                                            </label>
                                        @endforeach
                                        @if ($gatewayCurrency->count() > 4)
                                            <button type="button" class="payment-item__btn more-gateway-option">
                                                <p class="payment-item__btn-text">@lang('Show All Payment Options')</p>
                                                <span class="payment-item__btn__icon"><i
                                                        class="fas fa-chevron-down"></i></span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card custom--card">
                                <div class="card-body p-0">
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
                                                <p class="text has-icon"> @lang('Limit')
                                                    <span></span>
                                                </p>
                                            </div>
                                            <div class="deposit-info__input">
                                                <p class="text"><span class="gateway-limit">@lang('0.00')</span>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="deposit-info mb-3">
                                            <div class="deposit-info__title">
                                                <p class="text has-icon">@lang('Processing Charge')
                                                    <span data-bs-toggle="tooltip" title="@lang('Processing charge for payment gateways')"
                                                        class="proccessing-fee-info"><i class="las la-info-circle"></i>
                                                    </span>
                                                </p>
                                            </div>
                                            <div class="deposit-info__input">
                                                <p class="text"><span
                                                        class="processing-fee">@lang('0.00')</span>{{ __(gs('cur_text')) }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="deposit-info total-amount pt-3">
                                            <div class="deposit-info__title">
                                                <p class="text">@lang('Total')</p>
                                            </div>
                                            <div class="deposit-info__input">
                                                <p class="text"><span class="final-amount">@lang('0.00')</span>
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
                                        <div class="d-none crypto-message">
                                            @lang('Conversion with') <span class="gateway-currency"></span> @lang('and final value will Show on next step')
                                        </div>
                                        <button type="submit" class="btn btn--base w-100 mt-3"
                                            disabled><i class="ph-bold ph-wallet"></i>@lang('Deposit Confirm')</button>
                                        <div class="info-text pt-3">
                                            <p class="text">@lang('Ensuring your funds grow safely through our secure deposit process with world-class payment options.')</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <a href="{{ route('user.deposit.history') }}" class="nocturne-pay-history-link"><i class="ph-bold ph-clock-counter-clockwise"></i>@lang('View Deposit History')<i class="ph-bold ph-caret-right"></i></a>
        </div>
    </div>
        </div>
    </div>
@endsection

@push('style')
    <style>
        .btn:disabled {
            border: unset;
        }

        .form--control {
            background: unset !important;
        }

        .adjust-height .payment-system-list.is-scrollable {
            align-items: flex-start;
            justify-content: flex-start;
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
                    `${parseFloat(gateway.percent_charge).toFixed(2)}% with ${parseFloat(gateway.fixed_charge).toFixed(2)} {{ __(gs('cur_text')) }} charge for payment gateway processing fees`
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

            $(".nocturne-pay-tab").on("click", function(e) {
                let filter = $(this).data('filter');
                $(".nocturne-pay-tab").removeClass('is-active');
                $(this).addClass('is-active');
                $(".gateway-option").each(function() {
                    $(this).toggleClass('d-none', String($(this).data('crypto')) !== String(filter));
                });
                let firstVisible = $(".gateway-option:not(.d-none)").first();
                firstVisible.find('.gateway-input').prop('checked', true);
                gatewayChange();
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
                let totalAmount = parseFloat((amount || 0) + totalPercentCharge + fixedCharge);

                $(".final-amount").text(totalAmount.toFixed(2));
                $(".processing-fee").text(totalCharge.toFixed(2));
                $("input[name=currency]").val(gateway.currency);
                $(".gateway-currency").text(gateway.currency);

                if (amount < Number(gateway.min_amount) || amount > Number(gateway.max_amount)) {
                    $(".deposit-form button[type=submit]").attr('disabled', true);
                } else {
                    $(".deposit-form button[type=submit]").removeAttr('disabled');
                }

                if (gateway.currency != "{{ gs('cur_text') }}" && gateway.method.crypto != 1) {
                    $('.deposit-form').addClass('adjust-height')

                    $(".gateway-conversion, .conversion-currency").removeClass('d-none');
                    $(".gateway-conversion").find('.deposit-info__input .text').html(
                        `1 {{ __(gs('cur_text')) }} = <span class="rate">${parseFloat(gateway.rate).toFixed(2)}</span>  <span class="method_currency">${gateway.currency}</span>`
                    );
                    $('.in-currency').text(parseFloat(totalAmount * gateway.rate).toFixed(gateway.method.crypto == 1 ?
                        8 : 2))
                } else {
                    $(".gateway-conversion, .conversion-currency").addClass('d-none');
                    $('.deposit-form').removeClass('adjust-height')
                }

                if (gateway.method.crypto == 1) {
                    $('.crypto-message').removeClass('d-none');
                } else {
                    $('.crypto-message').addClass('d-none');
                }
            }

            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })

            if ($(".nocturne-pay-tab").length) {
                let checkedCrypto = $('.gateway-input:checked').closest('.gateway-option').data('crypto');
                $(".nocturne-pay-tab").filter(`[data-filter="${checkedCrypto}"]`).trigger('click');
            } else {
                $('.gateway-input').change();
            }
        })(jQuery);
    </script>
@endpush
