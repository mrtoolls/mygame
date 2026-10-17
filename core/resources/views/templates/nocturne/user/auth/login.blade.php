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
                <a href="{{ route('home') }}" class="nocturne-auth-visual__brand">
                    <img src="{{ siteLogo() }}" alt="{{ gs('site_name') }}">
                    <span>{{ gs('site_name') }}</span>
                </a>
                <div class="nocturne-auth-visual__copy">
                    <h2>@lang('Welcome to') <span>{{ gs('site_name') }}.pro</span>!</h2>
                    <p>{{ __(@$loginContent->data_values->heading) ?: __('Begin Your Adventure Today!') }}</p>
                    <div class="nocturne-auth-visual__trust"><i class="las la-shield-alt"></i> @lang('Secure, encrypted connection')</div>
                </div>
                <small class="nocturne-auth-visual__copyright">&copy; {{ date('Y') }} {{ gs('site_name') }} | @lang('All Rights Reserved').</small>
            </div>
            <div class="nocturne-auth-form">
                <h1>@lang('Log In')</h1>

                <form method="POST" action="{{ route('user.login') }}" class="verify-gcaptcha">
                    @csrf
                    <div class="nocturne-field">
                        <label class="form--label">@lang('Username or Email')</label>
                        <div class="nocturne-field__control">
                            <i class="las la-envelope"></i>
                            <input name="username" type="text" value="{{ old('username') }}" placeholder="@lang('Enter Email')" required>
                        </div>
                    </div>

                    <div class="nocturne-field">
                        <label class="form--label">@lang('Password')</label>
                        <div class="nocturne-field__control">
                            <i class="las la-lock"></i>
                            <input class="nocturne-password-input" type="password" name="password" placeholder="@lang('Enter Password')" required>
                            <button type="button" class="nocturne-field__toggle" aria-label="@lang('Show password')"><i class="las la-eye"></i></button>
                        </div>
                    </div>

                    <x-captcha />

                    <div class="nocturne-field-row">
                        <div class="form--check">
                            <input class="form-check-input" id="remember" name="remember" type="checkbox"
                                {{ old('remember') ? 'checked' : '' }}>
                            <label class="form-check-label" for="remember">@lang('Remember Me') </label>
                        </div>
                        <a href="{{ route('user.password.request') }}" class="forgot-password">@lang('Forget Password?')</a>
                    </div>

                    <div class="form-submit">
                        <button type="submit" class="btn btn--gradient w-100">@lang('Log In')</button>
                    </div>
                </form>

                @include('Template::partials.social_login')
                @if (gs('registration'))
                    <div class="nocturne-auth-switch">
                        <span>@lang("Don't Have an Account Yet?")</span>
                        <a href="{{ route('user.register') }}"><i class="las la-gift"></i>@lang('Register')</a>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
@push('script')
    <script>
        (function($) {
            "use strict";
            $('.nocturne-field__toggle').on('click', function() {
                var input = $(this).siblings('input');
                var showing = input.attr('type') === 'text';
                input.attr('type', showing ? 'password' : 'text');
                $(this).find('i').toggleClass('la-eye la-eye-slash');
            });
        })(jQuery);
    </script>
@endpush
