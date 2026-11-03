@extends('Template::layouts.master')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card custom--card">
                <div class="card-body  ">
                    <form action="{{ route('user.deposit.manual.update') }}" method="POST" class="disableSubmission"
                        enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-md-12">
                                <div class="alert alert-primary">
                                    <p class="text-center mt-2"><i class="las la-info-circle"></i> @lang('You are requesting')
                                        <b>{{ showAmount($data['amount']) }}</b> @lang('to deposit.') @lang('Please pay')
                                        <b>{{ showAmount($data['final_amount'], currencyFormat: false) . ' ' . $data['method_currency'] }}
                                        </b> @lang('for successful payment.')
                                    </p>
                                </div>

                                <div class="my-4 text-center">@php echo  $data->gateway->description @endphp</div>

                                @if($data->payment_agent_snapshot)
                                    <section class="manual-payment-agent my-4 p-4 rounded-4 text-start" aria-label="@lang('Payment agent details')">
                                        <div class="d-flex align-items-center gap-3 mb-3">
                                            @if($data->payment_agent_snapshot->photo)<img src="{{ asset('assets/images/support-agents/' . basename($data->payment_agent_snapshot->photo)) }}" alt="" class="rounded-circle" style="width:52px;height:52px;object-fit:cover">@else<span class="rounded-circle d-grid place-items-center" style="width:52px;height:52px;background:rgba(0,231,138,.12);color:#00e78a"><i class="las la-user-tie fs-3"></i></span>@endif
                                            <div><small class="d-block text-uppercase">@lang('Payment agent')</small><strong>{{ $data->payment_agent_snapshot->name }}</strong><small class="d-block">{{ $data->payment_agent_snapshot->email }}</small></div>
                                        </div>
                                        @php($pd = $data->payment_agent_snapshot->payment_details ?? null)
                                        @if(is_object($pd) && (!empty($pd->methods) || !empty($pd->instructions)))
                                            <div class="manual-payment-agent__methods">
                                                @foreach($pd->methods ?? [] as $method)
                                                    <div class="manual-payment-agent__method">
                                                        <strong>{{ $method->label ?: ucfirst(str_replace('_', ' ', $method->type ?? 'method')) }}</strong>
                                                        <dl>
                                                            @if(!empty($method->bank_name))<div><dt>@lang('Bank')</dt><dd>{{ $method->bank_name }}</dd></div>@endif
                                                            @if(!empty($method->account_number))<div><dt>@lang('Account number')</dt><dd>{{ $method->account_number }}<button type="button" class="manual-payment-agent__copy" data-copy="{{ $method->account_number }}" aria-label="@lang('Copy')"><i class="las la-copy"></i></button></dd></div>@endif
                                                            @if(!empty($method->account_name))<div><dt>@lang('Account name')</dt><dd>{{ $method->account_name }}</dd></div>@endif
                                                            @if(!empty($method->provider))<div><dt>@lang('Provider')</dt><dd>{{ $method->provider }}</dd></div>@endif
                                                            @if(!empty($method->phone_number))<div><dt>@lang('Phone')</dt><dd>{{ $method->phone_number }}</dd></div>@endif
                                                            @if(!empty($method->network))<div><dt>@lang('Network')</dt><dd>{{ $method->network }}</dd></div>@endif
                                                            @if(!empty($method->wallet_address))<div><dt>@lang('Wallet address')</dt><dd>{{ $method->wallet_address }}<button type="button" class="manual-payment-agent__copy" data-copy="{{ $method->wallet_address }}" aria-label="@lang('Copy')"><i class="las la-copy"></i></button></dd></div>@endif
                                                            @if(!empty($method->memo))<div><dt>@lang('Memo')</dt><dd>{{ $method->memo }}</dd></div>@endif
                                                            @if(!empty($method->details))<div><dd>{{ $method->details }}</dd></div>@endif
                                                        </dl>
                                                    </div>
                                                @endforeach
                                            </div>
                                            @if(!empty($pd->instructions))<p class="manual-payment-agent__instructions">{{ $pd->instructions }}</p>@endif
                                        @elseif(is_string($pd) && $pd !== '')
                                            <div class="manual-payment-agent__details">{!! nl2br(e($pd)) !!}</div>
                                        @endif
                                        <p class="mb-0 mt-3 small">@lang('Complete payment using these instructions, then attach the requested payment proof below. Your deposit is credited after review.')</p>
                                    </section>
                                @endif

                            </div>

                            <x-viser-form identifier="id" identifierValue="{{ $gateway->form_id }}" />
                        </div>
                        <button type="submit" class="btn btn--base w-100">@lang('Pay Now')</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('style')
    <style>
        .manual-payment-agent{border:1px solid rgba(0,231,138,.2);background:linear-gradient(135deg,rgba(0,231,138,.09),rgba(20,26,48,.96));box-shadow:0 14px 35px rgba(0,0,0,.18);color:#f4f5fd}
        .manual-payment-agent small{color:#9ca6c7}
        .manual-payment-agent__details{padding:16px;border:1px solid rgba(255,255,255,.08);border-radius:14px;background:rgba(7,11,27,.42);color:#eef1fc;line-height:1.75;overflow-wrap:anywhere}
        .manual-payment-agent__methods{display:grid;gap:12px}
        .manual-payment-agent__method{padding:15px 16px;border:1px solid rgba(255,255,255,.08);border-radius:14px;background:rgba(7,11,27,.42)}
        .manual-payment-agent__method>strong{display:block;margin-bottom:10px;color:#6cf0b2;font-size:12px;font-weight:800;letter-spacing:.03em;text-transform:uppercase}
        .manual-payment-agent__method dl{margin:0;display:grid;gap:8px}
        .manual-payment-agent__method dl>div{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
        .manual-payment-agent__method dt{flex:0 0 auto;color:#9ca6c7;font-size:11.5px;font-weight:700}
        .manual-payment-agent__method dd{display:flex;align-items:center;gap:8px;margin:0;color:#eef1fc;font-size:13.5px;font-weight:700;overflow-wrap:anywhere;text-align:right}
        .manual-payment-agent__copy{flex:0 0 auto;display:grid;place-items:center;width:26px;height:26px;border:1px solid rgba(255,255,255,.12);border-radius:8px;background:rgba(255,255,255,.04);color:#9ca6c7;cursor:pointer;transition:color .15s ease,border-color .15s ease}
        .manual-payment-agent__copy:hover,.manual-payment-agent__copy.is-copied{color:#00e78a;border-color:rgba(0,231,138,.4)}
        .manual-payment-agent__instructions{margin:12px 0 0;padding:13px 15px;border:1px dashed rgba(255,255,255,.12);border-radius:12px;color:#c7cedd;font-size:13px;line-height:1.6}
        @media(max-width:575.98px){.manual-payment-agent{padding:18px!important;border-radius:18px}.manual-payment-agent__details{padding:13px;font-size:13px}.manual-payment-agent__method dl>div{flex-direction:column;align-items:flex-start}.manual-payment-agent__method dd{text-align:left}}
    </style>
@endpush

@push('script')
    <script>
        document.querySelectorAll('.manual-payment-agent__copy').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var value = btn.getAttribute('data-copy') || '';
                (navigator.clipboard ? navigator.clipboard.writeText(value) : Promise.reject()).catch(function () {
                    var tmp = document.createElement('textarea');
                    tmp.value = value; document.body.appendChild(tmp); tmp.select();
                    try { document.execCommand('copy'); } catch (e) {}
                    document.body.removeChild(tmp);
                }).finally(function () {
                    btn.classList.add('is-copied');
                    var icon = btn.querySelector('i');
                    if (icon) { icon.className = 'las la-check'; setTimeout(function () { icon.className = 'las la-copy'; btn.classList.remove('is-copied'); }, 1500); }
                });
            });
        });
    </script>
@endpush
