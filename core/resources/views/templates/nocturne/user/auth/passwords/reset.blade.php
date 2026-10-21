@extends('Template::layouts.frontend')
@section('content')
    <section class="py-100">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-7 col-xl-5">
                    <div class="card custom--card">
                        <div class="card-body">
                            <div class="mb-4">
                                <p>@lang('Your account is verified successfully. Now you can change your password. Please enter a strong password and don\'t share it with anyone.')</p>
                            </div>
                            <form method="POST" action="{{ route('user.password.update') }}">
                                @csrf
                                <input type="hidden" name="email" value="{{ $email }}">
                                <input type="hidden" name="token" value="{{ $token }}">
                                <div class="form-group">
                                    <label class="form-label">@lang('Password')</label>
                                    <div class="nocturne-field__control">
                                        <x-icon name="lock" />
                                        <input type="password"
                                            class="nocturne-password-input @if (gs('secure_password')) secure-password @endif"
                                            name="password" required>
                                        <button type="button" class="nocturne-field__toggle" aria-label="@lang('Show password')">
                                            <i class="ph-bold ph-eye nocturne-icon nocturne-field__toggle-icon" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">@lang('Confirm Password')</label>
                                    <div class="nocturne-field__control">
                                        <x-icon name="lock" />
                                        <input type="password" class="nocturne-password-input" name="password_confirmation" required>
                                        <button type="button" class="nocturne-field__toggle" aria-label="@lang('Show password')">
                                            <i class="ph-bold ph-eye nocturne-icon nocturne-field__toggle-icon" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn--base w-100"> @lang('Submit')</button>
                            </form>
                        </div>
                    </div>
                </div>
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
        .hover-input-popup .input-popup {
            bottom: 80% !important;
        }
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
