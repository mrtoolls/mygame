@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content">
    <h1 class="nocturne-page-title">@lang('Profile')</h1>
    <div class="notice"></div>
    @php
        $kyc = getContent('user_kyc.content', true);
        $kycStatusClass = $user->kv == Status::KYC_VERIFIED ? 'is-verified' : ($user->kv == Status::KYC_PENDING ? 'is-pending' : 'is-unverified');
        $kycStatusText = $user->kv == Status::KYC_VERIFIED ? __('Verified') : ($user->kv == Status::KYC_PENDING ? __('Pending') : __('Unverified'));
    @endphp

    <div class="nocturne-profile-summary">
        <div class="nocturne-balance-card">
            <div class="nocturne-balance-card__head">
                <span class="nocturne-account-avatar"><i class="las la-user-circle"></i></span>
                <div>
                    <strong>{{ $user->username }}</strong>
                    <span>{{ $user->email }}</span>
                </div>
            </div>
            <div class="nocturne-balance-card__amount">@lang('Your Balance')
                <strong>{{ showAmount($widget['total_balance']) }}</strong>
            </div>
            <div class="nocturne-balance-card__actions">
                <a href="{{ route('user.deposit.index') }}"><i class="las la-wallet"></i>@lang('Deposit')</a>
                <a href="{{ route('user.withdraw') }}"><i class="las la-money-bill-wave"></i>@lang('Withdraw')</a>
            </div>
        </div>
        <a class="nocturne-kyc-card" href="{{ route('user.kyc.form') }}">
            <div class="nocturne-kyc-card__top">@lang('KYC Verification') <i class="las la-angle-right"></i></div>
            <div class="nocturne-kyc-card__status {{ $kycStatusClass }}">{{ $kycStatusText }}</div>
            <i class="nocturne-kyc-card__icon las la-lock"></i>
        </a>
    </div>

    @if ($user->kv == Status::KYC_UNVERIFIED && $user->kyc_rejection_reason)
        <div class="card custom--card mb-5">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h4 class="alert-heading text--danger">@lang('KYC Documents Rejected')</h4>
                    <button class="btn btn--danger btn--sm" data-bs-toggle="modal"
                        data-bs-target="#kycRejectionReason">@lang('Show Reason')</button>
                </div>
                <hr>
                <p class="mb-0">{{ __(@$kyc->data_values->reject) }} <a
                        href="{{ route('user.kyc.form') }}">@lang('Click Here to Re-submit Documents')</a>.</p>
                <br>
                <a href="{{ route('user.kyc.data') }}">@lang('See KYC Data')</a>
            </div>
        </div>
    @elseif ($user->kv == Status::KYC_UNVERIFIED)
        <div class="card custom--card mb-5">
            <div class="card-body">
                <h4 class="text--danger">@lang('KYC Verification required')</h4>
                <hr>
                <p>{{ __(@$kyc->data_values->verification_content) }} <a class="text--base"
                        href="{{ route('user.kyc.form') }}">@lang('Click Here to Submit Documents')</a></p>
            </div>
        </div>
    @elseif($user->kv == Status::KYC_PENDING)
        <div class="card custom--card mb-5">
            <div class="card-body">
                <h4 class="text--warning">@lang('KYC Verification pending')</h4>
                <hr>
                <p>{{ __(@$kyc->data_values->pending_content) }} <a class="text--base"
                        href="{{ route('user.kyc.data') }}">@lang('See KYC Data')</a></p>
            </div>
        </div>
    @endif

    <h4 class="mb-3">@lang('Account Overview')</h4>
    <div class="info-wrapper">
        <div class="info-card">
            <div class="info-card__icon">
                <i class="icon icon-Money-Bag"></i>
            </div>
            <div class="info-card__content">
                <p class="info-card__desc mb-2">@lang('Total Balance')</p>
                <h4 class="info-card__title mb-0">{{ showAmount($widget['total_balance']) }}</h4>
            </div>
        </div>
        <div class="info-card">
            <div class="info-card__icon">
                <i class="icon icon-Deposit-1"></i>
            </div>
            <div class="info-card__content">
                <p class="info-card__desc mb-2">@lang('Total Deposit')</p>
                <h4 class="info-card__title mb-0">{{ showAmount($widget['total_deposit']) }}</h4>
            </div>
        </div>
        <div class="info-card">
            <div class="info-card__icon">
                <i class="icon icon-withdraw-1"></i>
            </div>
            <div class="info-card__content">
                <p class="info-card__desc mb-2">@lang('Total Withdraw')</p>
                <h4 class="info-card__title mb-0">{{ showAmount($widget['total_withdrawn']) }}</h4>
            </div>
        </div>
        <div class="info-card">
            <div class="info-card__icon">
                <i class="icon icon-Group-86"></i>
            </div>
            <div class="info-card__content">
                <p class="info-card__desc mb-2">@lang('Total Invest')</p>
                <h4 class="info-card__title mb-0">{{ showAmount($widget['total_invest']) }}</h4>
            </div>
        </div>
        <div class="info-card">
            <div class="info-card__icon">
                <i class="icon icon-trophy"></i>
            </div>
            <div class="info-card__content">
                <p class="info-card__desc mb-2">@lang('Total Win')</p>
                <h4 class="info-card__title mb-0">{{ showAmount($widget['total_win']) }}</h4>
            </div>
        </div>
        <div class="info-card">
            <div class="info-card__icon">
                <i class="icon icon-Layer-21"></i>
            </div>
            <div class="info-card__content">
                <p class="info-card__desc mb-2">@lang('Total Loss')</p>
                <h4 class="info-card__title mb-0">{{ showAmount($widget['total_loss']) }}</h4>
            </div>
        </div>
    </div>

    <h4 class="mb-3 mt-5">@lang('Explore Games')</h4>
    <div class="games-section pb-50">
        <div class="games-section-inner">
            <div class="games-section-wrapper">
                @include('Template::partials.game', ['games' => $games->take(12)])
            </div>
        </div>
    </div>

    @if ($user->kv == Status::KYC_UNVERIFIED && $user->kyc_rejection_reason)
        <div class="modal custom--modal fade" id="kycRejectionReason">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">@lang('KYC Document Rejection Reason')</h5>
                        <span class="close" data-bs-dismiss="modal" type="button" aria-label="Close">
                            <i class="las la-times"></i>
                        </span>
                    </div>
                    <div class="modal-body">
                        <p>{{ $user->kyc_rejection_reason }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
        </div>
    </div>
@endsection
