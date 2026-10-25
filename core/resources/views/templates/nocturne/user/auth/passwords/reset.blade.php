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
                    <h2>@lang('New password')</h2>
                    <p>@lang("You're verified. Choose a strong password and keep it to yourself.")</p>
                    <div class="nocturne-auth-visual__trust"><x-icon name="shield" /> @lang('Secure, encrypted connection')</div>
                </div>
                <small class="nocturne-auth-visual__copyright">&copy; {{ date('Y') }} {{ gs('site_name') }} | @lang('All Rights Reserved').</small>
            </div>
            <div class="nocturne-auth-form">
                <h1>@lang('Reset Password')</h1>
                <p class="nocturne-auth-form__intro">@lang('Your account is verified successfully. Now you can change your password. Please enter a strong password and don\'t share it with anyone.')</p>

                <form method="POST" action="{{ route('user.password.update') }}">
                    @csrf
                    <input type="hidden" name="email" value="{{ $email }}">
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div class="nocturne-field">
                        <label class="form--label">@lang('Password')</label>
                        <div class="nocturne-field__control">
                            <x-icon name="lock" />
                            <input type="password" class="nocturne-password-input @if (gs('secure_password')) secure-password @endif" name="password" placeholder="@lang('Enter new password')" required>
                            <button type="button" class="nocturne-field__toggle" aria-label="@lang('Show password')">
                                <i class="ph-bold ph-eye nocturne-icon nocturne-field__toggle-icon" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div class="nocturne-field">
                        <label class="form--label">@lang('Confirm Password')</label>
                        <div class="nocturne-field__control">
                            <x-icon name="lock" />
                            <input type="password" class="nocturne-password-input" name="password_confirmation" placeholder="@lang('Re-enter new password')" required>
                            <button type="button" class="nocturne-field__toggle" aria-label="@lang('Show password')">
                                <i class="ph-bold ph-eye nocturne-icon nocturne-field__toggle-icon" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-submit">
                        <button type="submit" class="btn btn--gradient w-100">@lang('Submit')</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection

@if (gs('secure_password'))
    @push('script-lib')
        <script src="{{ asset('assets/global/js/secure_password.js') }}"></script>
    @endpush
@endif

@push('style')
<style>
    .hover-input-popup .input-popup { bottom: 80% !important; }
    .nocturne-field__toggle-icon { font-size: 18px; line-height: 1; display: inline-block; color: inherit; }
</style>
@endpush

@push('script')
<script>
    "use strict";
    (function($) {
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
