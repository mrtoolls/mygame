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
                <a href="{{ route('home') }}" class="nocturne-auth-visual__brand">
                    <img src="{{ siteLogo() }}" alt="{{ gs('site_name') }}">
                    <span>{{ gs('site_name') }}</span>
                </a>
                <div class="nocturne-auth-visual__copy">
                    <h2>@lang('Welcome to') <span>{{ gs('site_name') }}.pro</span>!</h2>
                    <p>{{ __(@$register->data_values->heading) ?: __('Begin Your Adventure Today!') }}</p>
                    <div class="nocturne-auth-visual__trust"><i class="las la-shield-alt"></i> @lang('Secure, encrypted connection')</div>
                </div>
                <small class="nocturne-auth-visual__copyright">&copy; {{ date('Y') }} {{ gs('site_name') }} | @lang('All Rights Reserved').</small>
            </div>
            <div class="nocturne-auth-form">
                @if (!gs('registration'))
                    <span class="lock-icon"><i class="las la-lock"></i></span>
                @endif
                <h1>@lang('Register')</h1>

                <form class="verify-gcaptcha" action="{{ route('user.register') }}" method="POST">
                    @csrf
                    @if (session()->get('reference') != null)
                        <div class="nocturne-field">
                            <label class="form--label">@lang('Referred By')</label>
                            <div class="nocturne-field__control">
                                <i class="las la-user"></i>
                                <input name="referBy" type="text" value="{{ session()->get('reference') }}" readonly>
                            </div>
                            <small class="text--danger usernameExist"></small>
                        </div>
                    @endif

                    <div class="nocturne-field-grid">
                        <div class="nocturne-field">
                            <label class="form--label">@lang('First Name')</label>
                            <div class="nocturne-field__control">
                                <i class="las la-user"></i>
                                <input type="text" name="firstname" value="{{ old('firstname') }}" placeholder="@lang('Enter First Name')" required>
                            </div>
                        </div>
                        <div class="nocturne-field">
                            <label class="form--label">@lang('Last Name')</label>
                            <div class="nocturne-field__control">
                                <i class="las la-user"></i>
                                <input type="text" name="lastname" value="{{ old('lastname') }}" placeholder="@lang('Enter Last Name')" required>
                            </div>
                        </div>
                    </div>

                    <div class="nocturne-field">
                        <label class="form--label">@lang('Email')</label>
                        <div class="nocturne-field__control">
                            <i class="las la-envelope"></i>
                            <input class="checkUser" type="email" name="email" value="{{ old('email') }}" placeholder="@lang('Enter Email')" required>
                        </div>
                    </div>

                    <div class="nocturne-field-grid">
                        <div class="nocturne-field">
                            <label class="form--label">@lang('Password')</label>
                            <div class="nocturne-field__control">
                                <i class="las la-lock"></i>
                                <input id="password" class="nocturne-password-input" type="password" name="password" placeholder="@lang('Enter Password')" required>
                                <button type="button" class="nocturne-field__toggle" aria-label="@lang('Show password')"><i class="las la-eye"></i></button>
                            </div>
                        </div>
                        <div class="nocturne-field">
                            <label class="form--label">@lang('Repeat Password')</label>
                            <div class="nocturne-field__control">
                                <i class="las la-lock"></i>
                                <input id="confirm-password" class="nocturne-password-input" name="password_confirmation" type="password" placeholder="@lang('Repeat Password')" required>
                                <button type="button" class="nocturne-field__toggle" aria-label="@lang('Show password')"><i class="las la-eye"></i></button>
                            </div>
                        </div>
                    </div>

                    <x-captcha />

                    @if (gs('agree'))
                        @php
                            $policyPages = getContent('policy_pages.element', false, null, true);
                        @endphp
                        <div class="form--check nocturne-agree">
                            <input class="form-check-input" name="agree" type="checkbox" id="remember"
                                @checked(old('agree'))>
                            <div class="form-check-label">
                                <label for="remember">@lang('I agree to the')</label>
                                @foreach ($policyPages as $policy)
                                    <a href="{{ route('policy.pages', $policy->slug) }}" class="text">{{ __($policy->data_values->title) }}</a>
                                    @if (!$loop->last)
                                        ,
                                    @endif
                                @endforeach
                                @lang('and confirm I am at least 18 years old')
                            </div>
                        </div>
                    @endif

                    <div class="form-submit">
                        <button type="submit" class="btn btn--gradient w-100">@lang('Register')</button>
                    </div>
                </form>

                @include('Template::partials.social_login')

                <div class="nocturne-auth-switch">
                    <span>@lang('Already Have an Account?')</span>
                    <a href="{{ route('user.login') }}"><i class="las la-sign-in-alt"></i>@lang('Log In')</a>
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
                var input = $(this).siblings('input');
                var showing = input.attr('type') === 'text';
                input.attr('type', showing ? 'password' : 'text');
                $(this).find('i').toggleClass('la-eye la-eye-slash');
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
