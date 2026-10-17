@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content">
    <h1 class="nocturne-page-title">@lang('Verification')</h1>

    <div class="nocturne-steps">
        <div class="nocturne-step is-active"><i class="las la-user-check"></i>@lang('Start Verification')</div>
        <div class="nocturne-step"><i class="las la-id-card"></i>@lang('Data Input')</div>
        <div class="nocturne-step"><i class="las la-search"></i>@lang('Data Checking')</div>
        <div class="nocturne-step"><i class="las la-check-circle"></i>@lang('Verification Complete')</div>
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

                        <div class="form-group">
                            <button class="btn btn--base w-100" type="submit">@lang('Submit')</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
        </div>
    </div>
@endsection
