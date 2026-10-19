@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content nocturne-payment-page nocturne-deposit-page">
    <h1 class="nocturne-page-title">@lang('Deposit')</h1>
    <section class="nocturne-payment-hero">
        <div class="nocturne-payment-hero__copy">
            <span class="nocturne-payment-hero__eyebrow"><i class="las la-shield-alt"></i> @lang('Secure payments')</span>
            <h2>@lang('Power up your wallet')</h2>
            <p>@lang('Choose a payment method, enter an amount and review the details before continuing.')</p>
        </div>
        <div class="nocturne-payment-hero__balance">
            <span>@lang('Available balance')</span>
            <strong>{{ gs('cur_sym') }}{{ showAmount(auth()->user()->balance, currencyFormat: false) }} <small>{{ __(gs('cur_text')) }}</small></strong>
            <i class="las la-wallet"></i>
        </div>
    </section>
    @php
        $cryptoCount = $gatewayCurrency->filter(fn($g) => (int) $g->method->crypto === 1)->count();
        $agentCount = $gatewayCurrency->filter(fn($g) => (int) $g->method->crypto !== 1 && $g->support_agent_id)->count();
        $otherCount = $gatewayCurrency->filter(fn($g) => (int) $g->method->crypto !== 1 && !$g->support_agent_id)->count();
        $hasOther = $agentCount + $otherCount > 0;
        $hasCrypto = $cryptoCount > 0;
    @endphp
    <div class="row justify-content-center">
        <div class="col-12">
            <form action="{{ route('user.deposit.insert') }}" method="post" class="deposit-form">
                @csrf
                <input type="hidden" name="currency">
                <div class="gateway-card">
                    <div class="row justify-content-center gy-sm-4 gy-3 payment-workspace">
                        <div class="col-lg-5 payment-method-column">
                            <div class="card custom--card payment-method-panel">
                                <div class="card-body p-0">
                                    <h4 class="nocturne-pay-step">1. @lang('Select a Deposit Method')</h4>
                                    <div class="nocturne-pay-tabs">
                                        <button type="button" class="nocturne-pay-tab @if ($hasCrypto && !$hasOther) is-active @endif" data-filter="crypto" @disabled(!$hasCrypto)><i class="lab la-bitcoin"></i><span><strong>@lang('Crypto')</strong><small>@lang('Choose a coin and network')</small></span><b>{{ $cryptoCount }}</b></button>
                                        <button type="button" class="nocturne-pay-tab @if ($agentCount && !$hasCrypto) is-active @endif" data-filter="agent" @disabled(!$agentCount)><i class="las la-user-tie"></i><span><strong>@lang('Payment Agents')</strong><small>@lang('Pay through an approved agent')</small></span><b>{{ $agentCount }}</b></button>
                                        <button type="button" class="nocturne-pay-tab @if ($otherCount && !$hasCrypto && !$agentCount) is-active @endif" data-filter="other" @disabled(!$otherCount)><i class="las la-wallet"></i><span><strong>@lang('Other Methods')</strong><small>@lang('Available supported gateways')</small></span><b>{{ $otherCount }}</b></button>
                                    </div>
                                    <div class="nocturne-pay-list-heading"><div><span class="nocturne-pay-kicker">@lang('STEP 02')</span><h4 class="nocturne-pay-step">@lang('Select a payment option')</h4></div><span class="nocturne-pay-secure"><i class="las la-lock"></i> @lang('Secure')</span></div>
                                    <div class="payment-system-list is-scrollable gateway-option-list">
                                        @forelse ($gatewayCurrency as $data)
                                            <label for="{{ titleToKey($data->name) }}"
                                                data-crypto="{{ $data->method->crypto }}"
                                                data-category="{{ (int) $data->method->crypto === 1 ? 'crypto' : ($data->support_agent_id ? 'agent' : 'other') }}"
                                                data-payment-agent="{{ $data->supportAgent?->name }}"
                                                class="payment-item gateway-option">
                                                <div class="payment-item__info">
                                                    <span class="payment-item__check"></span>
                                                    <span class="payment-item__copy"><span class="payment-item__name">{{ __($data->name) }}</span>
                                                    @if($data->support_agent_id)<small class="payment-item__agent"><i class="las la-user-check"></i> {{ $data->supportAgent?->name }}</small>@endif</span>
                                                </div>
                                                <div class="payment-item__thumb">
                                                <img class="payment-item__thumb-img" onerror="this.hidden=true;this.parentElement.classList.add('has-missing-image')"
                                                        src="{{ getImage(getFilePath('gateway') . '/' . $data->method->image) }}"
                                                        alt="@lang('payment-thumb')">
                                                </div>
                                                <input class="payment-item__radio gateway-input"
                                                    id="{{ titleToKey($data->name) }}" hidden
                                                    data-gateway='@json($data)' type="radio"
                                                    name="gateway" value="{{ $data->method_code }}"
                                                    @checked(old('gateway', $loop->first ? $data->method_code : null) == $data->method_code)
                                                    data-min-amount="{{ showAmount($data->min_amount) }}"
                                                    data-max-amount="{{ showAmount($data->max_amount) }}">
                                            </label>
                                        @empty
                                            <div class="nocturne-pay-empty"><i class="las la-wallet"></i><strong>@lang('No deposit methods available')</strong><span>@lang('Please contact support or try again later.')</span></div>
                                        @endforelse
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
                        <div class="col-lg-7 payment-summary-column">
                            <div class="card custom--card payment-summary-panel">
                                <div class="card-body p-0">
                                    <div class="nocturne-pay-detail-heading"><div><span class="nocturne-pay-kicker">@lang('PAYMENT DETAILS')</span><h3>@lang('Complete your deposit')</h3><p>@lang('Enter the amount you want to add. You will review the payment instructions on the next step.')</p></div><span class="nocturne-pay-detail-icon"><i class="las la-wallet"></i></span></div>
                                    <div class="nocturne-pay-selected">
                                        <img class="nocturne-pay-selected__thumb" src="" alt="" onerror="this.hidden=true">
                                        <span class="nocturne-pay-selected__name"></span>
                                        <span class="nocturne-pay-selected__agent" hidden></span>
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
                                                    <input type="number" inputmode="decimal" min="0" step="any" class="form-control form--control amount"
                                                        name="amount" placeholder="@lang('00.00')"
                                                        value="{{ old('amount') }}" autocomplete="off" aria-label="@lang('Deposit amount')">
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
            <section class="nocturne-payment-history" aria-labelledby="deposit-history-title">
                <div class="nocturne-payment-history__heading"><div><span class="nocturne-pay-kicker">@lang('YOUR ACTIVITY')</span><h2 id="deposit-history-title"><i class="las la-list-alt"></i> @lang('Deposit History')</h2></div><a href="{{ route('user.deposit.history') }}">@lang('View all') <i class="las la-arrow-right"></i></a></div>
                <div class="nocturne-payment-history__table-wrap"><table class="nocturne-payment-history__table"><thead><tr><th>@lang('Method / Reference')</th><th>@lang('Amount')</th><th>@lang('Status')</th><th>@lang('Date')</th></tr></thead><tbody>
                    @forelse($deposits as $deposit)
                        <tr><td data-label="@lang('Method / Reference')"><strong>{{ __($deposit->gateway->name ?? 'Payment') }}</strong><small>{{ $deposit->trx }}</small></td><td data-label="@lang('Amount')"><strong>{{ showAmount($deposit->amount) }}</strong>@if($deposit->charge > 0)<small>@lang('Fee'): {{ showAmount($deposit->charge) }}</small>@endif</td><td data-label="@lang('Status')"><span class="nocturne-history-status">{!! $deposit->statusBadge !!}</span></td><td data-label="@lang('Date')"><strong>{{ showDateTime($deposit->created_at, 'M d, Y') }}</strong><small>{{ diffForHumans($deposit->created_at) }}</small></td></tr>
                    @empty
                        <tr><td colspan="4"><div class="nocturne-history-empty"><i class="las la-wallet"></i><strong>@lang('No deposits yet')</strong><span>@lang('Your completed and pending deposits will appear here.')</span></div></td></tr>
                    @endforelse
                </tbody></table></div><div class="nocturne-payment-history__pagination">{{ paginateLinks($deposits) }}</div>
            </section>
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
        .nocturne-payment-page .payment-item__copy{display:grid;min-width:0;gap:3px}
        .nocturne-payment-page .payment-item__agent{color:var(--pay-accent);font-size:10px;font-weight:700;white-space:normal}
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
        .nocturne-payment-page .nocturne-pay-selected__agent{color:var(--pay-accent);font-size:11px;font-weight:750}
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
        .nocturne-payment-page .deposit-form button[type=submit]{min-height:54px;border-radius:14px;font-size:15px;font-weight:850;box-shadow:0 10px 25px rgba(0,231,138,.15)}
        .nocturne-payment-page .info-text{color:var(--pay-muted);font-size:12px;line-height:1.55}
        .nocturne-payment-page .info-text p{margin:0}
        .nocturne-payment-page .nocturne-pay-history-link{display:flex;align-items:center;gap:9px;width:max-content;max-width:100%;margin:16px 0 0 auto;padding:12px 16px;border:1px solid rgba(133,142,175,.15);border-radius:13px;background:var(--pay-panel);color:#dfe2f4;font-weight:750;text-decoration:none}
        .nocturne-payment-page .nocturne-pay-history-link i:last-child{margin-left:5px;color:var(--pay-accent)}
        @media(max-width:767.98px){.nocturne-payment-page{padding-bottom:calc(24px + env(safe-area-inset-bottom))}.nocturne-payment-page .nocturne-page-title{font-size:24px}.nocturne-payment-hero{align-items:flex-start;flex-direction:column;gap:17px;margin-bottom:16px;padding:20px;border-radius:20px}.nocturne-payment-hero__balance{width:100%;min-width:0;padding:12px 48px 12px 14px}.nocturne-payment-hero__balance strong{font-size:19px}.nocturne-payment-page .payment-method-panel,.nocturne-payment-page .payment-summary-panel{padding:17px;border-radius:19px}.nocturne-payment-page .payment-system-list.is-scrollable{grid-template-columns:repeat(2,minmax(0,1fr));max-height:265px;gap:8px}.nocturne-payment-page .payment-item{min-height:62px;padding:9px}.nocturne-payment-page .payment-item__thumb{width:30px;height:25px}.nocturne-payment-page .payment-item__name{font-size:12px}.nocturne-payment-page .deposit-info__input-group{min-height:58px}.nocturne-payment-page .deposit-info__input-group .amount{height:54px;font-size:22px}.nocturne-payment-page .nocturne-pay-history-link{width:100%;justify-content:center;margin-top:12px}.nocturne-payment-page .nocturne-pay-step{margin-bottom:12px}}
        @media(max-width:360px){.nocturne-payment-page .payment-system-list.is-scrollable{grid-template-columns:1fr}.nocturne-payment-page .payment-item{min-height:58px}}
        .nocturne-payment-page .payment-method-panel,.nocturne-payment-page .payment-summary-panel{min-height:100%;padding:26px;background:linear-gradient(155deg,#171d36 0%,#13182e 72%);border-color:rgba(133,142,175,.18)}
        .nocturne-payment-page .payment-item.d-none{display:none!important}
        .nocturne-payment-page .payment-item__thumb.has-missing-image{position:relative;background:rgba(0,231,138,.1)}
        .nocturne-payment-page .payment-item__thumb.has-missing-image:after{content:'◆';color:var(--pay-accent);font-size:15px}
        .nocturne-payment-page .nocturne-pay-empty{display:grid;justify-items:center;align-content:center;gap:8px;min-height:180px;padding:24px;border:1px dashed rgba(133,142,175,.25);border-radius:16px;text-align:center;color:var(--pay-muted)}
        .nocturne-payment-page .nocturne-pay-empty i{color:var(--pay-accent);font-size:27px}
        .nocturne-payment-page .nocturne-pay-empty strong{color:#f4f5fd;font-size:14px}
        .nocturne-payment-page .nocturne-pay-empty span{font-size:12px}
        .nocturne-payment-page .payment-method-column{flex:0 0 auto;width:41.666667%}
        .nocturne-payment-page .payment-summary-column{flex:0 0 auto;width:58.333333%}
        .nocturne-payment-page .nocturne-pay-tabs{display:grid;grid-template-columns:1fr 1fr;gap:10px;background:transparent;padding:0;margin-bottom:25px}
        .nocturne-payment-page .nocturne-pay-tab{display:flex;align-items:center;gap:11px;min-height:76px;padding:12px;border:1px solid rgba(133,142,175,.19);border-radius:16px;text-align:left;background:#10152a;color:#f4f5fd}
        .nocturne-payment-page .nocturne-pay-tab>i{display:grid;place-items:center;flex:none;width:38px;height:38px;border-radius:12px;background:rgba(0,231,138,.12);color:var(--pay-accent);font-size:23px}
        .nocturne-payment-page .nocturne-pay-tab>span{display:grid;gap:3px;min-width:0}
        .nocturne-payment-page .nocturne-pay-tab strong{font-size:13px;font-weight:850}
        .nocturne-payment-page .nocturne-pay-tab small{color:var(--pay-muted);font-size:10px;line-height:1.3}
        .nocturne-payment-page .nocturne-pay-tab>b{margin-left:auto;color:var(--pay-muted);font-size:10px}
        .nocturne-payment-page .nocturne-pay-tab.is-active{border-color:var(--pay-accent);background:linear-gradient(135deg,rgba(0,231,138,.13),#151a31 76%);color:#fff}
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
        @media(max-width:767.98px){.nocturne-payment-page .payment-method-panel,.nocturne-payment-page .payment-summary-panel{padding:17px}.nocturne-payment-page .nocturne-pay-tabs{grid-template-columns:1fr 1fr;gap:8px}.nocturne-payment-page .nocturne-pay-tab{min-height:70px;padding:9px}.nocturne-payment-page .nocturne-pay-tab>i{width:32px;height:32px;font-size:20px}.nocturne-payment-page .nocturne-pay-tab small{font-size:9px}.nocturne-payment-page .payment-system-list.is-scrollable{grid-template-columns:1fr;max-height:300px}.nocturne-payment-page .nocturne-pay-detail-heading{align-items:flex-start;margin-bottom:17px;padding-bottom:15px}.nocturne-payment-page .nocturne-pay-detail-icon{width:44px;height:44px;border-radius:14px;font-size:22px}.nocturne-payment-page .nocturne-pay-list-heading .nocturne-pay-step{font-size:15px}}
        .btn:disabled {
            border: unset;
        }

        .form--control {
            background: unset !important;
        }
        .nocturne-payment-page .payment-method-panel .card-body{padding:2px!important}
        .nocturne-payment-page .payment-summary-panel .card-body{min-width:0}
        .nocturne-payment-page .nocturne-pay-list-heading{align-items:flex-start}
        .nocturne-payment-page .nocturne-pay-list-heading>div{min-width:0;flex:1}
        .nocturne-payment-page .nocturne-pay-list-heading .nocturne-pay-step{line-height:1.35;overflow-wrap:anywhere}
        .nocturne-payment-page .nocturne-pay-secure{flex:none;white-space:nowrap;padding:8px 11px}
        .nocturne-payment-page .nocturne-pay-detail-heading{padding:10px 8px 20px}
        .nocturne-payment-page .payment-summary-panel .payment-system-list{padding:0 8px 10px!important}
        .nocturne-payment-page .deposit-info__input-group{display:flex;flex-wrap:nowrap;width:100%;min-width:0}
        .nocturne-payment-page .deposit-info__input-group-text{display:flex;align-items:center;justify-content:center;min-width:56px;padding:0 15px;border-right:1px solid rgba(133,142,175,.15)}
        .nocturne-payment-page .deposit-info__input-group .amount{flex:1;min-width:0;padding:0 18px;background:#0d1225!important;text-align:right;font-variant-numeric:tabular-nums}
        .nocturne-payment-page .nocturne-payment-history{margin-top:28px;padding:24px;border:1px solid rgba(133,142,175,.14);border-radius:22px;background:linear-gradient(145deg,#151a31,#10152a);box-shadow:0 16px 42px rgba(0,0,0,.16)}
        .nocturne-payment-page .nocturne-payment-history__heading{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px}
        .nocturne-payment-page .nocturne-payment-history__heading h2{margin:0;color:#f4f5fd;font-size:21px;font-weight:850;letter-spacing:-.025em}
        .nocturne-payment-page .nocturne-payment-history__heading h2 i{margin-right:6px;color:#858eaf}
        .nocturne-payment-page .nocturne-payment-history__heading>a{display:inline-flex;align-items:center;gap:7px;flex:none;padding:11px 14px;border-radius:12px;background:#222a47;color:#f4f5fd;font-size:13px;font-weight:750;text-decoration:none}
        .nocturne-payment-page .nocturne-payment-history__heading>a i{color:var(--pay-accent)}
        .nocturne-payment-history__table-wrap{overflow:auto;border:1px solid rgba(133,142,175,.1);border-radius:15px}
        .nocturne-payment-history__table{width:100%;border-collapse:collapse;color:#edf0fc}
        .nocturne-payment-history__table th{padding:14px 18px;background:#222a47;color:#e9ebf8;font-size:12px;font-weight:800;text-align:left;white-space:nowrap}
        .nocturne-payment-history__table td{padding:15px 18px;border-top:1px solid rgba(133,142,175,.1);vertical-align:middle;font-size:13px}
        .nocturne-payment-history__table td strong,.nocturne-payment-history__table td small{display:block}
        .nocturne-payment-history__table td small{margin-top:4px;color:#858eaf;font-size:11px;overflow-wrap:anywhere}
        .nocturne-payment-history__table td .badge{white-space:nowrap}
        .nocturne-history-empty{display:grid;justify-items:center;gap:5px;padding:30px 12px;text-align:center}
        .nocturne-history-empty i{color:var(--pay-accent);font-size:26px}
        .nocturne-history-empty strong{color:#f3f4fc;font-size:16px}
        .nocturne-history-empty span{color:var(--pay-muted);font-size:12px}
        .nocturne-payment-history__pagination{padding-top:14px}
        .nocturne-payment-history__pagination nav{margin:0}
        @media(max-width:767.98px){
            .nocturne-payment-page .payment-method-panel,.nocturne-payment-page .payment-summary-panel{padding:clamp(16px,5vw,22px);border-radius:20px}
            .nocturne-payment-page .payment-workspace{--bs-gutter-x:12px}
            .nocturne-payment-page .nocturne-pay-list-heading{gap:8px}
            .nocturne-payment-page .nocturne-pay-list-heading .nocturne-pay-step{font-size:14px}
            .nocturne-payment-page .nocturne-pay-secure{padding:7px 8px;font-size:9px}
            .nocturne-payment-page .nocturne-pay-detail-heading{padding:4px 2px 17px}
            .nocturne-payment-page .payment-summary-panel .payment-system-list{padding:0!important}
            .nocturne-payment-page .deposit-info{gap:10px}
            .nocturne-payment-page .deposit-info__input-group .amount{font-size:21px}
            .nocturne-payment-page .nocturne-payment-history{margin-top:20px;padding:17px 14px;border-radius:19px}
            .nocturne-payment-page .nocturne-payment-history__heading{align-items:flex-start}
            .nocturne-payment-page .nocturne-payment-history__heading h2{font-size:18px}
            .nocturne-payment-page .nocturne-payment-history__heading>a{padding:9px 10px;font-size:11px}
            .nocturne-payment-history__table-wrap{overflow:visible;border:0}
            .nocturne-payment-history__table thead{display:none}
            .nocturne-payment-history__table,.nocturne-payment-history__table tbody{display:block;width:100%}
            .nocturne-payment-history__table tbody tr{display:grid;grid-template-columns:1fr 1fr;gap:0;margin-bottom:10px;padding:8px 12px;border:1px solid rgba(133,142,175,.13);border-radius:14px;background:#11162b}
            .nocturne-payment-history__table tbody tr:last-child{margin-bottom:0}
            .nocturne-payment-history__table td{min-width:0;padding:9px 4px;border:0;font-size:12px;overflow-wrap:anywhere}
            .nocturne-payment-history__table td:before{display:block;margin-bottom:4px;color:#858eaf;font-size:9px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;content:attr(data-label)}
            .nocturne-payment-history__table td[colspan]{grid-column:1/-1}
            .nocturne-payment-history__table td[colspan]:before{display:none}
        }
        @media(max-width:360px){.nocturne-payment-page .nocturne-pay-tab{gap:7px;padding:8px}.nocturne-payment-page .nocturne-pay-tab strong{font-size:11px}.nocturne-payment-page .nocturne-pay-tab small{font-size:8px}}
        @media(min-width:768px){.nocturne-deposit-page .nocturne-pay-tabs{grid-template-columns:repeat(3,minmax(0,1fr))}.nocturne-deposit-page .nocturne-pay-tab{min-width:0;padding:9px}.nocturne-deposit-page .nocturne-pay-tab>i{width:32px;height:32px;font-size:19px}.nocturne-deposit-page .nocturne-pay-tab strong{font-size:11px}.nocturne-deposit-page .nocturne-pay-tab small{font-size:9px}}
        @media(max-width:767.98px){.nocturne-deposit-page .nocturne-pay-tabs{display:flex;overflow-x:auto;overscroll-behavior-x:contain;scroll-snap-type:x mandatory;padding-bottom:4px}.nocturne-deposit-page .nocturne-pay-tab{flex:0 0 min(78%,220px);scroll-snap-align:start}}

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
                if (!gatewayElement.length) {
                    gateway = null;
                    $('.nocturne-pay-selected__thumb').attr('src', '').hide();
                    $('.nocturne-pay-selected__name').text("{{ __('Select a payment option') }}");
                    $('.nocturne-pay-selected__agent').text('').prop('hidden', true);
                    $('.nocturne-pay-selected__badge').text("{{ __('Choose method') }}");
                    $('.deposit-form button[type=submit]').prop('disabled', true);
                    $('.gateway-limit, .processing-fee, .final-amount').text('0.00');
                    return;
                }
                let methodCode = gatewayElement.val();

                gateway = gatewayElement.data('gateway');
                const paymentAgentName = gatewayElement.data('payment-agent');
                $('.nocturne-pay-selected__agent').text(paymentAgentName ? `{{ __('Handled by') }} ${paymentAgentName}` : '').prop('hidden', !paymentAgentName);
                minAmount = gatewayElement.data('min-amount');
                maxAmount = gatewayElement.data('max-amount');

                let processingFeeInfo =
                    `${parseFloat(gateway.percent_charge).toFixed(2)}% with ${parseFloat(gateway.fixed_charge).toFixed(2)} {{ __(gs('cur_text')) }} charge for payment gateway processing fees`
                $(".proccessing-fee-info").attr("data-bs-original-title", processingFeeInfo);
                let selectedImage = gatewayElement.closest('.payment-item').find('.payment-item__thumb-img').attr('src');
                if (selectedImage) $('.nocturne-pay-selected__thumb').attr('src', selectedImage).prop('hidden', false).show();
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
                let filter = String($(this).data('filter'));
                $(".nocturne-pay-tab").removeClass('is-active');
                $(this).addClass('is-active');
                $(".gateway-option").each(function() {
                    $(this).removeClass('d-none');
                    $(this).toggleClass('d-none', String($(this).data('category')) !== filter);
                });
                let firstVisible = $(".gateway-option:not(.d-none)").first();
                if (firstVisible.length) firstVisible.find('.gateway-input').prop('checked', true);
                else $('.gateway-input').prop('checked', false);
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
                let checkedCategory = $('.gateway-input:checked').closest('.gateway-option').data('category');
                let initialFilter = checkedCategory === undefined ? $('.nocturne-pay-tab:not(:disabled)').first().data('filter') : checkedCategory;
                $(".nocturne-pay-tab").filter(`[data-filter="${initialFilter}"]`).trigger('click');
            } else {
                $('.gateway-input').change();
            }
        })(jQuery);
    </script>
@endpush
