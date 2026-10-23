@extends('Template::layouts.master')

@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <main class="nocturne-account-content nocturne-crypto-payment">
            <h1 class="nocturne-page-title">@lang('Deposit')</h1>
            <section class="crypto-payment-layout">
                <aside class="crypto-payment-overview">
                    <span class="crypto-payment-kicker">@lang('DEPOSIT OVERVIEW')</span>
                    <h2>@lang('Payment in progress')</h2>
                    <p>@lang('Send the exact amount shown to the address on this page. Your balance is credited after the network confirms the payment.')</p>
                    <div class="crypto-overview-row"><span>@lang('Payment method')</span><strong>{{ $deposit->methodName() ?: $data->currency }}</strong></div>
                    <div class="crypto-overview-row"><span>@lang('Deposit amount')</span><strong>{{ showAmount($deposit->amount) }}</strong></div>
                    <div class="crypto-overview-row"><span>@lang('Amount to send')</span><strong class="crypto-highlight">{{ $data->amount }} {{ $data->currency }}</strong></div>
                    <div class="crypto-overview-status"><i class="las la-shield-alt"></i><span><strong>@lang('Address reserved for this payment')</strong><small>@lang('Only send the selected asset using the matching network.')</small></span></div>
                    <a class="crypto-history-link" href="{{ route('user.deposit.history') }}"><i class="las la-receipt"></i>@lang('View deposit history')<i class="las la-arrow-right"></i></a>
                </aside>

                <section class="crypto-payment-card">
                    <header class="crypto-payment-card__header">
                        <div><span class="crypto-payment-kicker">@lang('PAYMENT DETAILS')</span><h2>{{ $data->currency }} <small>@lang('Deposit')</small></h2></div>
                        <span class="crypto-selected-badge"><i class="las la-check-circle"></i> @lang('Selected')</span>
                    </header>
                    <div class="crypto-payment-body">
                        <div class="crypto-qr-frame"><img src="{{ $data->img }}" alt="@lang('Deposit QR code')"></div>
                        <h3>@lang('Make payment to your :currency address', ['currency' => $data->currency])</h3>
                        <div class="crypto-address-row"><code id="deposit-address">{{ $data->sendto }}</code><button type="button" class="crypto-copy-button" data-copy-target="deposit-address" aria-label="@lang('Copy deposit address')"><i class="las la-copy"></i></button></div>
                        <div class="crypto-payment-warning"><i class="las la-info-circle"></i><p>@lang('Send only :currency using the correct network. Sending another asset or using a different network may result in permanent loss. Your deposit is credited automatically after the required confirmations.', ['currency' => $data->currency])</p></div>
                        <div class="crypto-payment-meta">
                            <div><span>@lang('Minimum deposit')</span><strong>{{ showAmount(optional($deposit->gatewayCurrency())->min_amount ?? 0) }}</strong></div>
                            <div><span>@lang('Network / currency')</span><strong>{{ $data->currency }}</strong></div>
                        </div>
                    </div>
                </section>
            </section>
        </main>
    </div>
@endsection

@push('style')
    <style>
        .nocturne-crypto-payment{max-width:1480px;margin-inline:auto;padding-bottom:32px}
        .crypto-payment-layout{display:grid;grid-template-columns:minmax(280px,.78fr) minmax(0,1.22fr);gap:18px;align-items:stretch}
        .crypto-payment-overview,.crypto-payment-card{min-width:0;border:1px solid rgba(133,142,175,.15);border-radius:22px;background:#151a31;box-shadow:0 15px 40px rgba(0,0,0,.16)}
        .crypto-payment-overview{display:flex;flex-direction:column;padding:clamp(20px,2.6vw,32px);background:radial-gradient(ellipse at 15% 0,rgba(0,231,138,.12),transparent 40%),#151a31}
        .crypto-payment-kicker{color:#00e78a;font-size:10px;font-weight:850;letter-spacing:.14em}
        .crypto-payment-overview h2{margin:10px 0 8px;color:#f5f6ff;font-size:clamp(18px,2.2vw,22px);font-weight:850;letter-spacing:-.04em}
        .crypto-payment-overview>p{margin:0 0 24px;color:#858eaf;font-size:14px;line-height:1.65}
        .crypto-overview-row{display:flex;justify-content:space-between;gap:12px;padding:15px 0;border-bottom:1px dashed rgba(133,142,175,.2);color:#858eaf;font-size:13px}
        .crypto-overview-row strong{color:#f5f6ff;text-align:right}
        .crypto-overview-row .crypto-highlight{color:#00e78a}
        .crypto-overview-status{display:flex;gap:12px;margin-top:22px;padding:15px;border:1px solid rgba(0,231,138,.16);border-radius:15px;background:rgba(0,231,138,.07);color:#00e78a}
        .crypto-overview-status>i{font-size:22px}
        .crypto-overview-status span{display:grid;gap:4px}
        .crypto-overview-status strong{color:#f5f6ff;font-size:12px}
        .crypto-overview-status small{color:#858eaf;font-size:11px;line-height:1.45}
        .crypto-history-link{display:flex;align-items:center;gap:9px;margin-top:auto;padding-top:24px;color:#dfe2f4;font-size:13px;font-weight:750;text-decoration:none}
        .crypto-history-link i:last-child{margin-left:auto;color:#00e78a}
        .crypto-payment-card{overflow:hidden}
        .crypto-payment-card__header{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:22px 26px;border-bottom:1px solid rgba(133,142,175,.14)}
        .crypto-payment-card__header h2{margin:5px 0 0;color:#f5f6ff;font-size:18px;font-weight:850}
        .crypto-payment-card__header h2 small{color:#858eaf;font-size:14px;font-weight:700}
        .crypto-selected-badge{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border-radius:999px;background:rgba(0,231,138,.12);color:#00e78a;font-size:11px;font-weight:850}
        .crypto-payment-body{padding:clamp(18px,2.5vw,30px)}
        .crypto-qr-frame{display:grid;place-items:center;width:min(220px,62vw);aspect-ratio:1;margin:0 auto 23px;padding:13px;border:1px solid rgba(133,142,175,.2);border-radius:22px;background:#fff;box-shadow:0 12px 35px rgba(0,0,0,.25)}
        .crypto-qr-frame img{display:block;width:100%;height:100%;object-fit:contain}
        .crypto-payment-body h3{margin:0 0 13px;color:#f5f6ff;font-size:clamp(16px,1.8vw,20px);font-weight:800}
        .crypto-address-row{display:flex;align-items:stretch;min-width:0;overflow:hidden;border:1px solid rgba(133,142,175,.2);border-radius:14px;background:#0e1327}
        .crypto-address-row code{flex:1;min-width:0;padding:15px;color:#f2f3fc;font-family:inherit;font-size:13px;font-weight:750;overflow-wrap:anywhere}
        .crypto-copy-button{flex:none;width:58px;border:0;border-left:1px solid rgba(133,142,175,.15);background:#202744;color:#9aa4c4;font-size:20px}
        .crypto-copy-button:hover{background:#00e78a;color:#071b18}
        .crypto-payment-warning{display:flex;gap:11px;margin:16px 0;padding:15px;border-radius:14px;background:rgba(133,142,175,.08);color:#00e78a}
        .crypto-payment-warning i{flex:none;font-size:19px}
        .crypto-payment-warning p{margin:0;color:#9aa4c4;font-size:12px;line-height:1.6}
        .crypto-payment-meta{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .crypto-payment-meta>div{display:flex;justify-content:space-between;gap:10px;padding:13px 15px;border-bottom:1px dashed rgba(133,142,175,.2);color:#858eaf;font-size:12px}
        .crypto-payment-meta strong{color:#f5f6ff;text-align:right}
        @media(max-width:991.98px){.crypto-payment-layout{grid-template-columns:1fr}.crypto-payment-overview{order:2}.crypto-payment-card{order:1}}
        @media(max-width:575.98px){.nocturne-crypto-payment{padding-bottom:calc(24px + env(safe-area-inset-bottom))}.crypto-payment-layout{gap:13px}.crypto-payment-card__header{padding:18px}.crypto-payment-body{padding:18px}.crypto-payment-meta{grid-template-columns:1fr}.crypto-payment-meta>div{padding-inline:2px}.crypto-address-row code{font-size:11px}.crypto-copy-button{width:48px}.crypto-payment-overview,.crypto-payment-card{border-radius:18px}}
    </style>
@endpush

@push('script')
    <script>
        document.querySelectorAll('.crypto-copy-button').forEach((button) => {
            button.addEventListener('click', async () => {
                const value = document.getElementById(button.dataset.copyTarget)?.textContent?.trim();
                if (!value) return;
                try {
                    await navigator.clipboard.writeText(value);
                    button.innerHTML = '<i class="las la-check"></i>';
                    setTimeout(() => button.innerHTML = '<i class="las la-copy"></i>', 1400);
                } catch (error) {
                    const range = document.createRange();
                    range.selectNodeContents(document.getElementById(button.dataset.copyTarget));
                    const selection = window.getSelection();
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
            });
        });
    </script>
@endpush
