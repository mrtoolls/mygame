@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content">
            @php($isPending = $user->kv == \App\Constants\Status::KYC_PENDING)

            <section class="nocturne-kyc-status">
                <span class="nocturne-kyc-status__icon"><i class="ph-fill ph-{{ $isPending ? 'clock-countdown' : 'identification-card' }}"></i></span>
                <div>
                    <strong>{{ $isPending ? __('Verification in progress') : __('Your submitted information') }}</strong>
                    <p>{{ $isPending ? __('Your documents are under review. This usually takes 24-48 hours - we will notify you as soon as it is done.') : __('Here is the information you submitted for identity verification.') }}</p>
                </div>
            </section>

            <div class="card custom--card">
                <div class="card-body p-0">
                    @if ($user->kyc_data)
                        <dl class="nocturne-kyc-data">
                            @foreach ($user->kyc_data as $val)
                                @continue(!$val->value)
                                <div class="nocturne-kyc-data__row">
                                    <dt>{{ __($val->name) }}</dt>
                                    <dd>
                                        @if ($val->type == 'checkbox')
                                            {{ implode(', ', $val->value) }}
                                        @elseif($val->type == 'file')
                                            <a href="{{ route('user.download.attachment', encrypt(getFilePath('verify') . '/' . $val->value)) }}"><i class="ph-fill ph-file-text"></i> @lang('View Attachment')</a>
                                        @else
                                            {{ __($val->value) }}
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    @else
                        <div class="nocturne-kyc-empty">
                            <i class="ph-fill ph-identification-badge"></i>
                            <strong>@lang('KYC data not found')</strong>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
