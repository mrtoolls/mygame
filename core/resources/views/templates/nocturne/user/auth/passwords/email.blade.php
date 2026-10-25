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
                    <h2>@lang('Forgot your password?')</h2>
                    <p>@lang("No worries. We'll send you a code to get you back in.")</p>
                    <div class="nocturne-auth-visual__trust"><x-icon name="shield" /> @lang('Secure, encrypted connection')</div>
                </div>
                <small class="nocturne-auth-visual__copyright">&copy; {{ date('Y') }} {{ gs('site_name') }} | @lang('All Rights Reserved').</small>
            </div>
            <div class="nocturne-auth-form">
                <h1>@lang('Reset Password')</h1>
                <p class="nocturne-auth-form__intro">@lang('To recover your account please provide your email or username to find your account.')</p>

                <form method="POST" action="{{ route('user.password.email') }}" class="verify-gcaptcha">
                    @csrf
                    <div class="nocturne-field">
                        <label class="form--label">@lang('Email or Username')</label>
                        <div class="nocturne-field__control">
                            <x-icon name="mail" />
                            <input name="value" type="text" value="{{ old('value') }}" placeholder="@lang('Enter email or username')" required>
                        </div>
                    </div>

                    <x-captcha />

                    <div class="form-submit">
                        <button type="submit" class="btn btn--gradient w-100">@lang('Submit')</button>
                    </div>
                </form>

                <div class="nocturne-auth-switch">
                    <span>@lang('Remembered your password?')</span>
                    <a href="{{ route('user.login') }}"><x-icon name="sign-in" />@lang('Log In')</a>
                </div>
            </div>
        </div>
    </section>
@endsection
