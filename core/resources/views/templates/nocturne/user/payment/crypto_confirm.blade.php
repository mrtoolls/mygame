@extends('Template::layouts.master')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card custom--card">
                <div class="card-body">
                    <div class="nocturne-crypto-pay">
                        <div class="nocturne-crypto-pay__head">
                            <span class="nocturne-crypto-pay__coin"><i class="lab la-bitcoin"></i></span>
                            <div>
                                <strong>{{ __($gateway->name) }}</strong>
                                <span>{{ __($method->currency) }} @if($method->network_label)· {{ __($method->network_label) }}@endif</span>
                            </div>
                        </div>

                        <p class="text-center mt-2 mb-3">@lang('You are requesting') <b>{{ showAmount($data->amount) }}</b> @lang('to deposit.') @lang('Please pay') <b>{{ showAmount($data->final_amount, currencyFormat: false) . ' ' . $data->method_currency }}</b> @lang('to the address below.')</p>

                        <div class="nocturne-crypto-pay__panel">
                            <div class="nocturne-crypto-pay__qr"><canvas id="crypto-qr"></canvas></div>
                            <div class="nocturne-crypto-pay__address">
                                <label>@lang('Wallet address')</label>
                                <div class="nocturne-crypto-pay__copy">
                                    <code id="crypto-address">{{ $method->wallet_address }}</code>
                                    <button type="button" id="crypto-copy-btn" class="btn btn-sm btn--primary"><i class="las la-copy"></i> @lang('Copy')</button>
                                </div>
                            </div>
                        </div>

                        @if($gateway->description)
                            <div class="nocturne-crypto-pay__instructions">{!! $gateway->description !!}</div>
                        @endif

                        <div class="nocturne-crypto-pay__footrow">
                            <div><span>@lang('Minimum Deposit')</span><strong>{{ showAmount($method->min_amount) }}</strong></div>
                            <div><span>@lang('Network')</span><strong>{{ $method->network_label ?: __($method->currency) }}</strong></div>
                        </div>

                        <form action="{{ route('user.deposit.confirmation.submit') }}" method="POST" enctype="multipart/form-data" class="nocturne-crypto-pay__form disableSubmission">
                            @csrf
                            <div class="form-group">
                                <label>@lang('Payment receipt') <small class="text-muted">(@lang('JPG or PNG, up to 5MB'))</small></label>
                                <input type="file" name="payment_proof" accept="image/jpeg,image/png" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>@lang('Wallet address you paid from')</label>
                                <input type="text" name="payer_wallet_address" class="form-control" placeholder="@lang('Paste the sending address')" required>
                            </div>
                            @if ($errors->any())
                                <div class="alert alert-danger">{{ $errors->first() }}</div>
                            @endif
                            <button type="submit" class="btn btn--base w-100"><i class="las la-check-circle"></i> @lang('Confirm Deposit')</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('style')
<style>
.nocturne-crypto-pay{display:grid;gap:16px}
.nocturne-crypto-pay__head{display:flex;align-items:center;gap:12px}
.nocturne-crypto-pay__coin{display:grid;place-items:center;width:52px;height:52px;border-radius:50%;background:rgba(0,231,138,.12);color:#00e78a;font-size:26px}
.nocturne-crypto-pay__head strong{display:block;font-size:17px}
.nocturne-crypto-pay__head span{color:#9ca6c7;font-size:13px}
.nocturne-crypto-pay__panel{display:flex;flex-wrap:wrap;align-items:center;gap:20px;padding:20px;border:1px solid rgba(255,255,255,.08);border-radius:16px;background:rgba(7,11,27,.42)}
.nocturne-crypto-pay__qr{flex:0 0 auto;display:grid;place-items:center;padding:10px;background:#fff;border-radius:12px}
.nocturne-crypto-pay__qr canvas{display:block}
.nocturne-crypto-pay__address{flex:1;min-width:220px}
.nocturne-crypto-pay__address label{display:block;margin-bottom:6px;color:#9ca6c7;font-size:12px;text-transform:uppercase;letter-spacing:.05em}
.nocturne-crypto-pay__copy{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.nocturne-crypto-pay__copy code{word-break:break-all;color:#f4f5fd;background:rgba(0,0,0,.25);padding:8px 10px;border-radius:8px;flex:1;min-width:160px}
.nocturne-crypto-pay__instructions{padding:16px;border:1px solid rgba(255,255,255,.08);border-radius:14px;background:rgba(7,11,27,.42);color:#eef1fc;line-height:1.75}
.nocturne-crypto-pay__footrow{display:flex;justify-content:space-between;gap:12px;padding:14px 18px;border:1px dashed rgba(255,255,255,.12);border-radius:12px;flex-wrap:wrap}
.nocturne-crypto-pay__footrow div{display:grid;gap:2px}
.nocturne-crypto-pay__footrow span{color:#9ca6c7;font-size:11px;text-transform:uppercase}
.nocturne-crypto-pay__form{display:grid;gap:12px;margin-top:8px}
@media(max-width:575.98px){.nocturne-crypto-pay__panel{flex-direction:column;align-items:stretch}.nocturne-crypto-pay__qr{justify-self:center}}
</style>
@endpush

@push('script')
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
<script>
(() => {
    const address = document.getElementById('crypto-address')?.textContent.trim();
    const canvas = document.getElementById('crypto-qr');
    if (address && canvas && window.QRCode) {
        QRCode.toCanvas(canvas, address, { width: 160, margin: 1 }, () => {});
    }
    document.getElementById('crypto-copy-btn')?.addEventListener('click', () => {
        if (!address) return;
        navigator.clipboard?.writeText(address).then(() => {
            const btn = document.getElementById('crypto-copy-btn');
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="las la-check"></i> ' + '{{ __('Copied') }}';
            setTimeout(() => { btn.innerHTML = original; }, 1500);
        });
    });
})();
</script>
@endpush
