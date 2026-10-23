@extends('Template::layouts.auth')
@section('content')
    @php
        $register = getContent('register.content', true);
        $authImage = @$register->data_values->image
            ? getImage('assets/images/frontend/register/' . $register->data_values->image, '1920x1080')
            : asset('assets/images/frontend/nockturne_assets/landing_girl.jpg');
    @endphp
    <section class="nocturne-auth">
        <div class="nocturne-auth-card nocturne-auth-card--wide">
            <div class="nocturne-auth-visual" @if ($authImage) style="background-image:url('{{ $authImage }}')" @endif>
                <a href="{{ route('home') }}" class="nocturne-auth-visual__brand">
                    <img src="{{ siteLogo() }}" alt="{{ gs('site_name') }}">
                </a>
                <div class="nocturne-auth-visual__copy">
                    <h2>@lang('One last step,') <span>{{ auth()->user()->username ? ucfirst(auth()->user()->username) : __('Player') }}</span>!</h2>
                    <p>@lang('Tell us a little about you so we can get your table ready.')</p>
                    <div class="nocturne-auth-visual__trust"><x-icon name="shield" /> @lang('Secure, encrypted connection')</div>
                </div>
                <small class="nocturne-auth-visual__copyright">&copy; {{ date('Y') }} {{ gs('site_name') }} | @lang('All Rights Reserved').</small>
            </div>
            <div class="nocturne-auth-form">
                <h1>@lang('Complete Your Profile')</h1>
                <p class="nocturne-onboard-sub">@lang('Just a few details and you\'re ready to play.')</p>

                <form action="{{ route('user.data.submit') }}" method="POST" id="onboardForm">
                    @csrf

                    <div class="nocturne-field">
                        <label class="form--label">@lang('Choose your avatar')</label>
                        <div class="nocturne-gender-picker">
                            <label class="nocturne-gender-card">
                                <input type="radio" name="gender" value="male" required @checked(old('gender') === 'male')>
                                <span class="nocturne-gender-card__preview"><img src="{{ asset('assets/images/profilepics/male1.png') }}" alt="@lang('Male avatar')" loading="lazy"></span>
                                <span class="nocturne-gender-card__label">@lang('Male')</span>
                            </label>
                            <label class="nocturne-gender-card">
                                <input type="radio" name="gender" value="female" required @checked(old('gender') === 'female')>
                                <span class="nocturne-gender-card__preview"><img src="{{ asset('assets/images/profilepics/female1.png') }}" alt="@lang('Female avatar')" loading="lazy"></span>
                                <span class="nocturne-gender-card__label">@lang('Female')</span>
                            </label>
                        </div>
                        <small class="text-muted">@lang('We\'ll assign you a profile picture to start - you can upload your own anytime in Settings.')</small>
                    </div>

                    <div class="nocturne-field">
                        <label class="form--label">@lang('Username')</label>
                        <div class="nocturne-field__control">
                            <x-icon name="user-plus" />
                            <input name="username" class="checkUser" type="text" value="{{ old('username') }}" required autocomplete="off" autocapitalize="none" spellcheck="false">
                        </div>
                        <small class="text--danger usernameExist"></small>
                    </div>

                    <div class="nocturne-field-grid">
                        <div class="nocturne-field">
                            <label class="form--label">@lang('Country')</label>
                            <div class="nocturne-field__control nocturne-field__control--select">
                                <i class="ph-bold ph-globe nocturne-icon" aria-hidden="true"></i>
                                <select name="country" id="onboard-country" required aria-label="@lang('Choose your country')">
                                    <option value="">@lang('Choose your country')</option>
                                    @foreach ($countries as $key => $country)
                                        <option data-mobile_code="{{ $country->dial_code }}" data-code="{{ $key }}" value="{{ $country->country }}" @selected(old('country') === $country->country)>
                                            {{ __($country->country) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="nocturne-field">
                            <label class="form--label">@lang('State')</label>
                            <div class="nocturne-field__control">
                                <i class="ph-bold ph-map-pin nocturne-icon" aria-hidden="true"></i>
                                <input name="state" type="text" value="{{ old('state') }}">
                            </div>
                        </div>
                    </div>

                    <div class="nocturne-field">
                        <label class="form--label">@lang('Mobile')</label>
                        <div class="nocturne-field__control">
                            <span class="mobile-code" id="onboard-mobile-code"></span>
                            <input name="mobile_code" type="hidden">
                            <input name="country_code" type="hidden">
                            <input name="mobile" class="checkUser" type="number" value="{{ old('mobile') }}" required>
                        </div>
                        <small class="text--danger mobileExist"></small>
                    </div>

                    <div class="form-submit">
                        <button type="submit" class="btn btn--gradient w-100">@lang('Continue')</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection

@push('style')
    <style>
        .nocturne-onboard-sub { margin: -10px 0 18px; color: var(--nocturne-muted, #929abc); font-size: 13px; }
        .nocturne-gender-picker { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 8px; }
        .nocturne-gender-card {
            position: relative; display: grid; justify-items: center; gap: 8px; padding: 18px 10px;
            border: 1px solid #2c3453; border-radius: 15px; background: #0d1023; cursor: pointer; transition: border-color .15s ease, box-shadow .15s ease;
        }
        .nocturne-gender-card input { position: absolute; opacity: 0; inset: 0; margin: 0; cursor: pointer; }
        .nocturne-gender-card__preview {
            display: grid; place-items: center; width: 64px; height: 64px; border-radius: 50%; overflow: hidden;
            background: var(--nocturne-raised, #232a47); border: 2px solid transparent; transition: border-color .15s ease;
        }
        .nocturne-gender-card__preview img { width: 100%; height: 100%; object-fit: cover; }
        .nocturne-gender-card__label { color: var(--nocturne-text, #f5f6ff); font-size: 13px; font-weight: 800; }
        .nocturne-gender-card:has(input:checked) { border-color: var(--nocturne-green, #00ef87); box-shadow: 0 0 0 3px rgba(0, 239, 135, .12); }
        .nocturne-gender-card:has(input:checked) .nocturne-gender-card__preview { border-color: var(--nocturne-green, #00ef87); }
        .nocturne-field__control .mobile-code { color: var(--nocturne-muted, #929abc); font-size: 13px; font-weight: 800; white-space: nowrap; }
        .nocturne-field__control--select select {
            appearance: none; -webkit-appearance: none; -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23929abc' stroke-width='1.6' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 2px center; padding-right: 22px;
        }

        /* Mobile hardening: the page was overflowing horizontally on phones
           (text/fields running past the viewport edge) - force every control
           to respect its container and give it a premium native-app feel. */
        html, body { overflow-x: hidden; }
        .nocturne-auth-form, .nocturne-field, .nocturne-field-grid, .nocturne-field__control { max-width: 100%; box-sizing: border-box; }
        .nocturne-field__control { width: 100%; }
        .nocturne-field__control select, .nocturne-field__control input { min-width: 0; width: 100%; box-sizing: border-box; }
        @media (max-width: 575.98px) {
            .nocturne-auth { min-height: 100dvh; }
            .nocturne-auth-card { box-shadow: none; }
            .nocturne-auth-visual { min-height: 220px; padding: 22px 20px 26px; }
            .nocturne-auth-visual__copy h2 { font-size: 17px; }
            .nocturne-auth-visual__copy p { font-size: 13px; margin-bottom: 12px; }
            .nocturne-auth-form { padding: 22px 16px calc(22px + env(safe-area-inset-bottom)); }
            .nocturne-auth-form h1 { font-size: 22px; margin-bottom: 4px; }
            .nocturne-onboard-sub { font-size: 12.5px; margin-bottom: 20px; }
            .nocturne-gender-picker { gap: 8px; }
            .nocturne-gender-card { padding: 14px 8px; border-radius: 16px; }
            .nocturne-gender-card__preview { width: 54px; height: 54px; }
            .nocturne-field { margin-bottom: 14px; }
            .nocturne-field__control { min-height: 50px; border-radius: 14px; }
            .form-submit .btn--gradient { min-height: 52px; border-radius: 15px; font-size: 15px; }
        }
    </style>
@endpush

@push('script')
    <script>
        "use strict";
        (function($) {
            @if ($mobileCode)
                $(`option[data-code={{ $mobileCode }}]`).attr('selected', '');
            @endif

            function syncMobileCode() {
                var selected = $('#onboard-country :selected');
                $('input[name=mobile_code]').val(selected.data('mobile_code'));
                $('input[name=country_code]').val(selected.data('code'));
                $('#onboard-mobile-code').text(selected.data('mobile_code') ? '+' + selected.data('mobile_code') : '');
            }
            $('#onboard-country').on('change', function() {
                syncMobileCode();
                checkUser($('[name=mobile]').val(), 'mobile');
            });
            syncMobileCode();

            $('.checkUser').on('focusout', function(e) {
                checkUser($(this).val(), $(this).attr('name'));
            });

            function checkUser(value, name) {
                var url = '{{ route('user.checkUser') }}';
                var token = '{{ csrf_token() }}';
                var data;
                if (name == 'mobile') {
                    data = { mobile: value, mobile_code: $('#onboard-mobile-code').text().substr(1), _token: token };
                }
                if (name == 'username') {
                    data = { username: value, _token: token };
                }
                if (!data) return;
                $.post(url, data, function(response) {
                    if (response.data != false) {
                        $(`.${response.type}Exist`).text(`${response.field} already exist`);
                    } else {
                        $(`.${response.type}Exist`).text('');
                    }
                });
            }
        })(jQuery);
    </script>
@endpush
