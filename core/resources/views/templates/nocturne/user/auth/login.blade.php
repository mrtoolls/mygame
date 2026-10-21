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
                </a>
                <div class="nocturne-auth-visual__copy">
                    <h2>@lang('Welcome to') <span>{{ gs('site_name') }}</span>!</h2>
                    <p>{{ __(@$loginContent->data_values->heading) ?: __('Begin Your Adventure Today!') }}</p>
                    <div class="nocturne-auth-visual__trust"><x-icon name="shield" /> @lang('Secure, encrypted connection')</div>
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
                            <x-icon name="mail" />
                            <input name="username" type="text" value="{{ old('username') }}" placeholder="@lang('Enter Email')" required>
                        </div>
                    </div>

                    <div class="nocturne-field">
                        <label class="form--label">@lang('Password')</label>
                        <div class="nocturne-field__control">
                            <x-icon name="lock" />
                            <input class="nocturne-password-input" type="password" name="password" placeholder="@lang('Enter Password')" required>
                            <button type="button" class="nocturne-field__toggle" aria-label="@lang('Show password')">
                                <i class="ph-bold ph-eye nocturne-icon nocturne-field__toggle-icon" aria-hidden="true"></i>
                            </button>
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
                        <button type="submit" class="btn btn--gradient w-100"><x-icon name="sign-in" />@lang('Log In')</button>
                    </div>
                </form>

                @include('Template::partials.social_login')
                @if (gs('registration'))
                    <div class="nocturne-auth-switch">
                        <span>@lang("Don't Have an Account Yet?")</span>
                        <a href="{{ route('user.register') }}"><x-icon name="gift" />@lang('Register')</a>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
@push('style')
<style>
    .nocturne-field__toggle-icon { font-size: 18px; line-height: 1; display: inline-block; color: inherit; }
</style>
@endpush
@push('script')
    <script>
        (function($) {
            "use strict";
            $('.nocturne-field__toggle').on('click', function() {
                var btn = $(this);
                var input = btn.siblings('input');
                var icon = btn.find('.nocturne-field__toggle-icon');
                var isPassword = input.attr('type') === 'password';
                input.attr('type', isPassword ? 'text' : 'password');
                icon.toggleClass('ph-eye', !isPassword).toggleClass('ph-eye-slash', isPassword);
            });
        })(jQuery);
    </script>
@endpush
