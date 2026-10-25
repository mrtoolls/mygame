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
                    <h2>@lang('Almost there')</h2>
                    <p>@lang('Enter the code we sent you to continue resetting your password.')</p>
                    <div class="nocturne-auth-visual__trust"><x-icon name="shield" /> @lang('Secure, encrypted connection')</div>
                </div>
                <small class="nocturne-auth-visual__copyright">&copy; {{ date('Y') }} {{ gs('site_name') }} | @lang('All Rights Reserved').</small>
            </div>
            <div class="nocturne-auth-form">
                <h1>@lang('Enter verification code')</h1>
                <p class="nocturne-auth-form__intro">@lang('A 6 digit verification code sent to your email address'): <strong>{{ showEmailAddress($email) }}</strong></p>

                <form action="{{ route('user.password.verify.code') }}" method="POST" class="submit-form">
                    @csrf
                    <input type="hidden" name="email" value="{{ $email }}">

                    @include('Template::partials.verification_code')

                    <div class="form-submit">
                        <button type="submit" class="btn btn--gradient w-100">@lang('Submit')</button>
                    </div>
                </form>

                <div class="nocturne-auth-switch">
                    <span>@lang("Please check including your Junk/Spam Folder. If not found, you can")</span>
                    <a href="{{ route('user.password.request') }}"><x-icon name="arrow-clockwise" />@lang('Try again')</a>
                </div>
            </div>
        </div>
    </section>
@endsection
