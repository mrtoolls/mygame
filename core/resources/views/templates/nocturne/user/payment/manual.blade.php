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
                                        @if($data->payment_agent_snapshot->payment_details)<div class="manual-payment-agent__details">{!! nl2br(e($data->payment_agent_snapshot->payment_details)) !!}</div>@endif
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
        .manual-payment-agent small{color:#9ca6c7}.manual-payment-agent__details{padding:16px;border:1px solid rgba(255,255,255,.08);border-radius:14px;background:rgba(7,11,27,.42);color:#eef1fc;line-height:1.75;overflow-wrap:anywhere}
        @media(max-width:575.98px){.manual-payment-agent{padding:18px!important;border-radius:18px}.manual-payment-agent__details{padding:13px;font-size:13px}}
    </style>
@endpush
