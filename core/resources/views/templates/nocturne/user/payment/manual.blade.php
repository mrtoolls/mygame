@extends('Template::layouts.master')

@section('content')
    <link rel="stylesheet" href="{{ asset('assets/global/css/payment-agent.css') }}?v={{ @filemtime('assets/global/css/payment-agent.css') }}">
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
                                    @php($snapshot = $data->payment_agent_snapshot)
                                    @php($pd = $snapshot->payment_details ?? null)
                                    @php($cm = $snapshot->contact_methods ?? null)
                                    @php($hasChat = !empty($snapshot->is_chat_agent))
                                    @php($hasAnyContact = $hasChat || (is_object($cm) && collect((array) $cm)->contains(fn ($c) => !empty($c->enabled) && !empty($c->value))))

                                    <section class="pa-card my-4" aria-label="@lang('Payment agent details')">
                                        <div class="pa-card__identity">
                                            <span class="pa-avatar">
                                                @if($snapshot->photo)<img src="{{ asset('assets/images/support-agents/' . basename($snapshot->photo)) }}" alt="">@else<i class="las la-user-tie"></i>@endif
                                                @if(!empty($snapshot->is_verified))<span class="pa-avatar__badge" title="@lang('Verified')"><i class="ph-fill ph-seal-check"></i></span>@endif
                                            </span>
                                            <div>
                                                <span class="pa-card__name">{{ $snapshot->name }}</span>
                                                <span class="pa-card__meta">@lang('Payment agent')</span>
                                            </div>
                                        </div>

                                        @if($hasAnyContact)
                                            <details class="pa-contact">
                                                <summary class="pa-contact__toggle"><i class="ph-bold ph-chats-circle"></i> @lang('Contact agent')</summary>
                                                <div class="pa-contact__menu">
                                                    @if($hasChat)
                                                        <button type="button" class="pa-contact__row" data-open-support-agent="{{ $snapshot->id }}"><i class="ph-fill ph-chat-circle-dots"></i> @lang('Chat in app')</button>
                                                    @endif
                                                    @if(is_object($cm))
                                                        @if(!empty($cm->whatsapp->enabled) && !empty($cm->whatsapp->value))<a href="https://wa.me/{{ preg_replace('/\D/', '', $cm->whatsapp->value) }}" target="_blank" rel="noopener" class="pa-contact__row"><i class="ph-fill ph-whatsapp-logo"></i> @lang('WhatsApp')</a>@endif
                                                        @if(!empty($cm->telegram->enabled) && !empty($cm->telegram->value))<a href="https://t.me/{{ ltrim($cm->telegram->value, '@') }}" target="_blank" rel="noopener" class="pa-contact__row"><i class="ph-fill ph-telegram-logo"></i> @lang('Telegram')</a>@endif
                                                        @if(!empty($cm->phone->enabled) && !empty($cm->phone->value))<a href="tel:{{ $cm->phone->value }}" class="pa-contact__row"><i class="ph-fill ph-phone"></i> @lang('Call')</a>@endif
                                                        @if(!empty($cm->email->enabled) && !empty($cm->email->value))<a href="mailto:{{ $cm->email->value }}" class="pa-contact__row"><i class="ph-fill ph-envelope-simple"></i> @lang('Email')</a>@endif
                                                    @endif
                                                </div>
                                            </details>
                                        @else
                                            <span class="pa-card__meta">{{ $snapshot->email }}</span>
                                        @endif
                                    </section>

                                    @if(is_object($pd) && (!empty($pd->methods) || !empty($pd->instructions)))
                                        <div class="pa-methods">
                                            @foreach($pd->methods ?? [] as $method)
                                                <div class="pa-method">
                                                    <span class="pa-method__label">{{ $method->label ?: ucfirst(str_replace('_', ' ', $method->type ?? 'method')) }}</span>
                                                    <dl>
                                                        @if(!empty($method->bank_name))<div><dt>@lang('Bank')</dt><dd>{{ $method->bank_name }}</dd></div>@endif
                                                        @if(!empty($method->account_number))<div><dt>@lang('Account number')</dt><dd>{{ $method->account_number }}<button type="button" class="pa-copy" data-copy="{{ $method->account_number }}" aria-label="@lang('Copy')"><i class="las la-copy"></i></button></dd></div>@endif
                                                        @if(!empty($method->account_name))<div><dt>@lang('Account name')</dt><dd>{{ $method->account_name }}</dd></div>@endif
                                                        @if(!empty($method->provider))<div><dt>@lang('Provider')</dt><dd>{{ $method->provider }}</dd></div>@endif
                                                        @if(!empty($method->phone_number))<div><dt>@lang('Phone')</dt><dd>{{ $method->phone_number }}</dd></div>@endif
                                                        @if(!empty($method->network))<div><dt>@lang('Network')</dt><dd>{{ $method->network }}</dd></div>@endif
                                                        @if(!empty($method->wallet_address))<div><dt>@lang('Wallet address')</dt><dd>{{ $method->wallet_address }}<button type="button" class="pa-copy" data-copy="{{ $method->wallet_address }}" aria-label="@lang('Copy')"><i class="las la-copy"></i></button></dd></div>@endif
                                                        @if(!empty($method->memo))<div><dt>@lang('Memo')</dt><dd>{{ $method->memo }}</dd></div>@endif
                                                        @if(!empty($method->details))<div><dd>{{ $method->details }}</dd></div>@endif
                                                    </dl>
                                                </div>
                                            @endforeach
                                        </div>
                                        @if(!empty($pd->instructions))<p class="pa-instructions">{{ $pd->instructions }}</p>@endif
                                    @elseif(is_string($pd) && $pd !== '')
                                        <div class="pa-instructions">{!! nl2br(e($pd)) !!}</div>
                                    @endif
                                    <p class="mb-0 mt-3 small text-muted">@lang('Complete payment using these instructions, then attach the requested payment proof below. Your deposit is credited after review.')</p>
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

@push('script')
    <script>
    (function () {
        document.querySelectorAll('.pa-copy').forEach(function (btn) {
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

        document.querySelectorAll('[data-open-support-agent]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var agentId = btn.getAttribute('data-open-support-agent');
                document.dispatchEvent(new CustomEvent('winx-support-start', { detail: { agentId: agentId } }));
            });
        });

        // Close an open contact popover when clicking outside it.
        document.addEventListener('click', function (e) {
            document.querySelectorAll('.pa-contact[open]').forEach(function (details) {
                if (!details.contains(e.target)) details.removeAttribute('open');
            });
        });
    })();
    </script>
@endpush
