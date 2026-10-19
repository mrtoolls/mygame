@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content nocturne-withdraw-preview">
    <h1 class="nocturne-page-title">@lang('Review withdrawal')</h1>
    <section class="withdraw-review-hero"><span><i class="las la-shield-alt"></i> @lang('Secure payout review')</span><h2>@lang('One last look before your funds move')</h2><p>@lang('Confirm the payout details and provide the requested account information to continue.')</p></section>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card custom--card withdraw-review-card">
                <div class="card-header text-center">
                    <h5 class="card-title">@lang('Withdraw Via') {{ $withdraw->method->name }}</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <p class="mb-0"><i class="las la-info-circle"></i> @lang('You are requesting')
                            <b>{{ showAmount($withdraw->amount) }}</b> @lang('for withdraw.') @lang('The admin will send you')
                            <b class="text--success">{{ showAmount($withdraw->final_amount, currencyFormat: false) . ' ' . $withdraw->currency }}
                            </b> @lang('to your account.')
                        </p>
                    </div>
                    <form action="{{ route('user.withdraw.submit') }}" class="disableSubmission" method="post"
                        enctype="multipart/form-data">
                        @csrf
                        <div class="my-3">
                            @php
                                echo $withdraw->method->description;
                            @endphp
                        </div>

                        @if ($withdraw->withdraw_information)
                            <div class="withdraw-payout-details">
                                <h6>@lang('Payout details provided')</h6>
                                @foreach ($withdraw->withdraw_information as $item)
                                    <div class="withdraw-payout-details__row"><span>{{ __($item->name ?? __('Detail')) }}</span><strong>{{ is_array($item->value ?? null) ? implode(', ', $item->value) : ($item->value ?? '—') }}</strong></div>
                                @endforeach
                            </div>
                        @else
                            <x-viser-form identifier="id" identifierValue="{{ $withdraw->method->form_id }}" />
                        @endif

                        @if (auth()->user()->ts)
                            <div class="form-group">
                                <label>@lang('Google Authenticator Code')</label>
                                <input type="text" name="authenticator_code" class="form-control form--control" required>
                            </div>
                        @endif

                        <button type="submit" class="btn btn--base w-100">@lang('Submit')</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
        </div>
    </div>
@endsection

@push('style')
    <style>
        .nocturne-withdraw-preview{max-width:1200px;margin-inline:auto}
        .withdraw-review-hero{margin:0 0 22px;padding:24px 28px;border:1px solid rgba(133,142,175,.14);border-radius:22px;background:radial-gradient(ellipse at 85% 0,rgba(0,231,138,.18),transparent 38%),linear-gradient(120deg,#171d37,#11162b 70%)}
        .withdraw-review-hero span{color:#00e78a;font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}
        .withdraw-review-hero h2{margin:9px 0 5px;color:#f5f6ff;font-size:clamp(22px,2.4vw,30px);font-weight:800;letter-spacing:-.04em}
        .withdraw-review-hero p{margin:0;color:#858eaf;font-size:14px}
        .withdraw-review-card{padding:clamp(16px,3vw,28px);border:1px solid rgba(133,142,175,.14);border-radius:22px;background:#151a31}
        .withdraw-review-card .card-header{padding:0 0 18px;border-color:rgba(133,142,175,.15);background:transparent}
        .withdraw-review-card .card-title{color:#f5f6ff;font-weight:800}
        .withdraw-review-card .alert-info{padding:17px;border:1px solid rgba(0,231,138,.18);border-radius:15px;background:rgba(0,231,138,.07);color:#dce1f5;line-height:1.65}
        .withdraw-review-card .alert-info b{color:#00e78a}
        .withdraw-review-card .form-group{margin-bottom:16px}
        .withdraw-review-card .form-control{min-height:48px;border-radius:12px}
        .withdraw-payout-details{margin:18px 0;padding:16px;border:1px solid rgba(133,142,175,.18);border-radius:15px;background:#10152a}
        .withdraw-payout-details h6{margin:0 0 10px;color:#f5f6ff;font-weight:800}
        .withdraw-payout-details__row{display:flex;justify-content:space-between;gap:14px;padding:10px 0;border-bottom:1px solid rgba(133,142,175,.12);font-size:13px}
        .withdraw-payout-details__row:last-child{border-bottom:0}
        .withdraw-payout-details__row span{color:#858eaf}
        .withdraw-payout-details__row strong{max-width:65%;color:#f5f6ff;text-align:right;overflow-wrap:anywhere}
        .withdraw-review-card button[type=submit]{min-height:52px;border-radius:14px;font-weight:800}
        @media(max-width:767.98px){.withdraw-review-hero{padding:20px;border-radius:19px}.withdraw-review-card{border-radius:19px}.nocturne-withdraw-preview{padding-bottom:calc(24px + env(safe-area-inset-bottom))}}
    </style>
@endpush
