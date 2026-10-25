@extends('Template::layouts.auth')
@section('content')
    @php
        $loginContent = getContent('login.content', true);
        $authImage = @$loginContent->data_values->image
            ? getImage('assets/images/frontend/login/' . $loginContent->data_values->image, '1920x1080')
            : asset('assets/images/frontend/nockturne_assets/landing_girl.jpg');
    @endphp
    <section class="nocturne-auth">
        <div class="nocturne-auth-card">
            <div class="nocturne-auth-visual" @if ($authImage) style="background-image:url('{{ $authImage }}')" @endif>
                <a href="{{ route('landing') }}" class="nocturne-auth-visual__brand">
                    <img src="{{ siteLogo() }}" alt="{{ gs('site_name') }}">
                </a>
                <div class="nocturne-auth-visual__copy">
                    <h2>@lang('Two-factor authentication')</h2>
                    <p>@lang('Enter the code from your authenticator app to continue.')</p>
                    <div class="nocturne-auth-visual__trust"><x-icon name="shield" /> @lang('Secure, encrypted connection')</div>
                </div>
                <small class="nocturne-auth-visual__copyright">&copy; {{ date('Y') }} {{ gs('site_name') }} | @lang('All Rights Reserved').</small>
            </div>
            <div class="nocturne-auth-form">
                <h1>@lang('Enter authenticator code')</h1>

                <form action="{{ route('user.2fa.verify') }}" method="POST" class="submit-form">
                    @csrf

                    @include('Template::partials.verification_code')

                    <div class="form-submit">
                        <button type="submit" class="btn btn--gradient w-100">@lang('Submit')</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection
