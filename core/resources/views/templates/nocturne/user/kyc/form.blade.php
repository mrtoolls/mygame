@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content">
            <section class="nocturne-kyc-hero">
                <span class="nocturne-kyc-hero__icon"><i class="ph-fill ph-shield-check"></i></span>
                <div>
                    <h1>@lang('Verify your identity')</h1>
                    <p>@lang('A quick, one-time check that keeps your account and winnings secure. It usually takes less than two minutes.')</p>
                </div>
            </section>

            <div class="nocturne-steps">
                <div class="nocturne-step is-active"><i class="ph-bold ph-user-check"></i><span>@lang('Start Verification')</span></div>
                <div class="nocturne-step"><i class="ph-bold ph-identification-card"></i><span>@lang('Data Input')</span></div>
                <div class="nocturne-step"><i class="ph-bold ph-magnifying-glass"></i><span>@lang('Data Checking')</span></div>
                <div class="nocturne-step"><i class="ph-bold ph-check-circle"></i><span>@lang('Verification Complete')</span></div>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card custom--card">
                        <div class="card-body">
                            <form action="{{ route('user.kyc.submit') }}" method="post" enctype="multipart/form-data">
                                @csrf

                                <div class="row gy-2">
                                    <x-viser-form identifier="act" identifierValue="kyc" />
                                </div>

                                <div class="nocturne-kyc-note">
                                    <i class="ph-fill ph-lock-key"></i>
                                    <span>@lang('Your documents are transmitted securely and only used to verify your identity - they are never shared with third parties.')</span>
                                </div>

                                <div class="form-group mt-3">
                                    <button class="btn btn--base w-100" type="submit">@lang('Submit for Review')</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
