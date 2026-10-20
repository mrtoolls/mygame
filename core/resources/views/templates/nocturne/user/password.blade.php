@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content">
    <h1 class="nocturne-page-title">@lang('Change Password')</h1>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card custom--card">
                <div class="card-body">
                    <form method="post">
                        @csrf
                        <div class="form-group">
                            <label class="form-label">@lang('Current Password')</label>
                            <div class="nocturne-field__control">
                                <x-icon name="lock" />
                                <input type="password" class="nocturne-password-input" name="current_password" required
                                    autocomplete="current-password">
                                <button type="button" class="nocturne-field__toggle" aria-label="@lang('Show password')">
                                    <x-icon name="eye" class="icon-eye" />
                                    <x-icon name="eye-off" class="icon-eye-off d-none" />
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">@lang('Password')</label>
                            <div class="nocturne-field__control">
                                <x-icon name="lock" />
                                <input type="password"
                                    class="nocturne-password-input @if (gs('secure_password')) secure-password @endif"
                                    name="password" required autocomplete="current-password">
                                <button type="button" class="nocturne-field__toggle" aria-label="@lang('Show password')">
                                    <x-icon name="eye" class="icon-eye" />
                                    <x-icon name="eye-off" class="icon-eye-off d-none" />
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">@lang('Confirm Password')</label>
                            <div class="nocturne-field__control">
                                <x-icon name="lock" />
                                <input type="password" class="nocturne-password-input" name="password_confirmation" required
                                    autocomplete="current-password">
                                <button type="button" class="nocturne-field__toggle" aria-label="@lang('Show password')">
                                    <x-icon name="eye" class="icon-eye" />
                                    <x-icon name="eye-off" class="icon-eye-off d-none" />
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn--base w-100">@lang('Submit')</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
        </div>
    </div>
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
    </style>
@endpush

@push('script')
    <script>
        "use strict";
        (function($) {
            $('.nocturne-field__toggle').on('click', function() {
                var input = $(this).siblings('input');
                var showing = input.attr('type') === 'text';
                input.attr('type', showing ? 'password' : 'text');
                $(this).find('.icon-eye, .icon-eye-off').toggleClass('d-none');
            });
        })(jQuery);
    </script>
@endpush
