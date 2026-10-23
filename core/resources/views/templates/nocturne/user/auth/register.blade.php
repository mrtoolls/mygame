@extends('Template::layouts.auth')
@section('content')
    @php
        $register = getContent('register.content', true);
        $authImage = @$register->data_values->image
            ? getImage('assets/images/frontend/register/' . $register->data_values->image, '1920x1080')
            : asset('assets/images/frontend/nockturne_assets/landing_girl.jpg');
    @endphp
    <section class="nocturne-auth">
        <div class="nocturne-auth-card nocturne-auth-card--wide @if (!gs('registration')) form-disabled @endif">
            <div class="nocturne-auth-visual" @if ($authImage) style="background-image:url('{{ $authImage }}')" @endif>
                <a href="{{ route('landing') }}" class="nocturne-auth-visual__brand">
                    <img src="{{ siteLogo() }}" alt="{{ gs('site_name') }}">
                </a>
                <div class="nocturne-auth-visual__copy">
                    <h2>@lang('Welcome to') <span>{{ gs('site_name') }}</span>!</h2>
                    <p>{{ __(@$register->data_values->heading) ?: __('Begin Your Adventure Today!') }}</p>
                    <div class="nocturne-auth-visual__trust"><x-icon name="shield" /> @lang('Secure, encrypted connection')</div>
                </div>
                <small class="nocturne-auth-visual__copyright">&copy; {{ date('Y') }} {{ gs('site_name') }} | @lang('All Rights Reserved').</small>
            </div>
            <div class="nocturne-auth-form">
                @if (!gs('registration'))
                    <span class="lock-icon"><x-icon name="lock" /></span>
                @endif
                <h1>@lang('Register')</h1>

                <form class="verify-gcaptcha" action="{{ route('user.register') }}" method="POST" id="registerForm">
                    @csrf
                    <input type="hidden" name="firstname" id="auto-firstname" value="Member">
                    <input type="hidden" name="lastname" id="auto-lastname" value="{{ gs('site_name') }}">

                    @if ($countries->isNotEmpty())
                        <div class="nocturne-field">
                            <label class="form--label">@lang('Country of residence')</label>
                            <div class="nocturne-field__control nocturne-field__control--select">
                                <x-icon name="globe" />
                                <select name="country_id" required aria-label="@lang('Choose your country')">
                                    <option value="">@lang('Choose your country')</option>
                                    @foreach ($countries as $country)
                                        <option value="{{ $country->id }}" @selected(old('country_id') == $country->id)>{{ $country->name }} · {{ $country->currency?->code }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <small class="text-muted">@lang('Your wallet currency is set by this choice and can only be changed by support after account balances and pending payments are settled.')</small>
                        </div>
                    @endif

                    <div class="nocturne-field">
                        <label class="form--label">@lang('Email')</label>
                        <div class="nocturne-field__control">
                            <x-icon name="mail" />
                            <input class="checkUser" type="email" name="email" value="{{ old('email') }}" placeholder="@lang('Enter Email')" required>
                        </div>
                    </div>

                    <div class="nocturne-field-grid">
                        <div class="nocturne-field">
                            <label class="form--label">@lang('Password')</label>
                            <div class="nocturne-field__control">
                                <x-icon name="lock" />
                                <input id="password" class="nocturne-password-input" type="password" name="password" placeholder="@lang('Enter Password')" required>
                                <button type="button" class="nocturne-field__toggle" aria-label="@lang('Show password')">
                                    <i class="ph-bold ph-eye nocturne-icon nocturne-field__toggle-icon" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div class="nocturne-field">
                            <label class="form--label">@lang('Repeat Password')</label>
                            <div class="nocturne-field__control">
                                <x-icon name="lock" />
                                <input id="confirm-password" class="nocturne-password-input" name="password_confirmation" type="password" placeholder="@lang('Repeat Password')" required>
                                <button type="button" class="nocturne-field__toggle" aria-label="@lang('Show password')">
                                    <i class="ph-bold ph-eye nocturne-icon nocturne-field__toggle-icon" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="nocturne-field">
                        <label class="form--label">@lang('Promo Code') <span class="nocturne-optional">(@lang('Optional'))</span></label>
                        <div class="nocturne-field__control">
                            <x-icon name="gift" />
                            <input name="referBy" type="text" value="{{ old('referBy', session()->get('reference')) }}" placeholder="@lang('Enter Promo Code')">
                        </div>
                        <small class="text--danger usernameExist"></small>
                    </div>

                    <x-captcha />

                    @if (gs('agree'))
                        @php
                            $policyPages = getContent('policy_pages.element', false, null, true);
                            $agreementPage = $policyPages->first();
                        @endphp
                        <div class="nocturne-agree">
                            <input class="form-check-input" name="agree" type="checkbox" id="remember"
                                @checked(old('agree'))>
                            <label for="remember">
                                @lang('I agree to the')
                                @if ($agreementPage)
                                    <a href="{{ route('policy.pages', $agreementPage->slug) }}">@lang('User Agreement')</a>
                                @else
                                    <span>@lang('User Agreement')</span>
                                @endif
                                &amp; @lang('confirm I am at least 18 years old')
                            </label>
                        </div>
                    @endif

                    <div class="form-submit">
                        <button type="submit" class="btn btn--gradient w-100"><x-icon name="user-plus" />@lang('Register')</button>
                    </div>
                </form>

                @include('Template::partials.social_login')

                <div class="nocturne-auth-switch">
                    <span>@lang('Already Have an Account?')</span>
                    <a href="{{ route('user.login') }}"><x-icon name="sign-in" />@lang('Log In')</a>
                </div>
            </div>
        </div>
    </section>

    <div class="modal custom--modal fade" id="existModalCenter" role="dialog" aria-labelledby="existModalCenterTitle"
        aria-hidden="true" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content section--bg">
                <div class="modal-header">
                    <h5 class="modal-title" id="existModalLongTitle">@lang('You are with us')</h5>
                    <span class="close" data-bs-dismiss="modal" type="button" aria-label="Close">
                        <i class="las la-times"></i>
                    </span>
                </div>
                <div class="modal-body">
                    <h6 class="text-center">@lang('You already have an account please Login ')</h6>
                </div>
                <div class="modal-footer">
                    <a class="btn btn--base btn--sm" href="{{ route('user.login') }}">@lang('Login')</a>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('style')
<style>
    .nocturne-field__toggle-icon { font-size: 18px; line-height: 1; display: inline-block; color: inherit; }
    .nocturne-field__control--select select {
        appearance: none; -webkit-appearance: none; -moz-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23929abc' stroke-width='1.6' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 2px center; padding-right: 22px;
    }
</style>
@endpush

@if (gs('secure_password'))
    @push('script-lib')
        <script src="{{ asset('assets/global/js/secure_password.js') }}"></script>
    @endpush
@endif

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

            $('#registerForm').on('submit', function() {
                var local = ($('.checkUser').val() || 'Member').split('@')[0];
                var parts = local.split(/[._-]+/).filter(Boolean);
                var firstname = (parts[0] || 'Member').slice(0, 30);
                var lastname = (parts[1] || parts[0] || '{{ gs('site_name') }}').slice(0, 30);
                $('#auto-firstname').val(firstname);
                $('#auto-lastname').val(lastname);
            });

            $('.checkUser').on('focusout', function(e) {
                var url = '{{ route('user.checkUser') }}';
                var value = $(this).val();
                var token = '{{ csrf_token() }}';

                var data = {
                    email: value,
                    _token: token
                }

                $.post(url, data, function(response) {
                    if (response.data != false) {
                        $('#existModalCenter').modal('show');
                    }
                });
            });
            @if (!gs('registration'))
                notify('error', 'Registration is currency disabled')
            @endif
        })(jQuery);
    </script>
@endpush
