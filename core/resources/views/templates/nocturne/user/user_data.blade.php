@extends('Template::layouts.auth')
@section('content')
    @php
        $register = getContent('register.content', true);
        $authImage = @$register->data_values->image
            ? getImage('assets/images/frontend/register/' . $register->data_values->image, '1920x1080')
            : asset('assets/images/frontend/nockturne_assets/landing_girl.jpg');
        // Sample thumbnails for the gender-picker cards - just previews, the real assignment on
        // submit is a random pick from the same pool (see AvatarLibrary::randomPath() in
        // UserController), these two are only here so the cards aren't blank/stale.
        $maleAvatarSample = \App\Support\AvatarLibrary::filenames('male')[0] ?? null;
        $femaleAvatarSample = \App\Support\AvatarLibrary::filenames('female')[0] ?? null;
    @endphp
    <section class="nocturne-auth">
        <div class="nocturne-auth-card nocturne-auth-card--wide">
            <div class="nocturne-auth-visual" @if ($authImage) style="background-image:url('{{ $authImage }}')" @endif>
                <a href="{{ route('landing') }}" class="nocturne-auth-visual__brand">
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
                                <span class="nocturne-gender-card__preview"><img src="{{ $maleAvatarSample ? asset('assets/avatars/male/' . $maleAvatarSample) : asset('assets/images/profilepics/male1.png') }}" alt="@lang('Male avatar')" loading="lazy"></span>
                                <span class="nocturne-gender-card__label">@lang('Male')</span>
                            </label>
                            <label class="nocturne-gender-card">
                                <input type="radio" name="gender" value="female" required @checked(old('gender') === 'female')>
                                <span class="nocturne-gender-card__preview"><img src="{{ $femaleAvatarSample ? asset('assets/avatars/female/' . $femaleAvatarSample) : asset('assets/images/profilepics/female1.png') }}" alt="@lang('Female avatar')" loading="lazy"></span>
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
                        <div class="nocturne-phone-row">
                            <div class="nocturne-field__control nocturne-field__control--code">
                                <span class="mobile-code-prefix">+</span>
                                <input name="mobile_code" id="onboard-mobile-code" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="4" placeholder="1" value="{{ old('mobile_code') }}" required aria-label="@lang('Country calling code')">
                            </div>
                            <div class="nocturne-field__control nocturne-field__control--number">
                                <input name="mobile" class="checkUser" type="number" value="{{ old('mobile') }}" required>
                            </div>
                        </div>
                        <input name="country_code" id="onboard-country-code" type="hidden" value="{{ old('country_code') }}">
                        <small class="text-muted">@lang('Auto-filled from your country - edit it if it looks wrong.')</small>
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
        /* Two separate .nocturne-field__control boxes side by side (a small one for the dial
           code, a flexible one for the number) instead of two <input>s crammed into one box -
           every existing .nocturne-field__control/input rule (including the page-wide "mobile
           hardening" width:100% below) already assumes exactly one input per control box, so
           fighting that assumption with ever-more-specific selectors on the <input>s themselves
           kept losing to some other rule in the cascade. Sizing the two BOXES instead (a layer
           those rules don't touch) sidesteps the conflict entirely - each box's own input still
           gets a clean, unmolested width:100% of ITS OWN box, same as every other field on this
           page. */
        .nocturne-phone-row { display: flex; align-items: center; gap: 8px; }
        .nocturne-phone-row .nocturne-field__control--code { width: 76px; flex: 0 0 76px; gap: 4px; padding: 0 10px; }
        .nocturne-phone-row .nocturne-field__control--number { flex: 1 1 0%; width: auto; min-width: 0; }
        .mobile-code-prefix { color: var(--nocturne-muted, #929abc); font-size: 14px; font-weight: 800; }
        #onboard-country.is-invalid { border-color: #ff5c7a !important; box-shadow: 0 0 0 3px rgba(255, 92, 122, .14); }
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
            /* The base rule's margin:-10px pulls this up to sit snug under the desktop h1's
               much bigger 26px bottom margin - against this breakpoint's own shrunk-down 4px
               heading margin, that same -10px instead dragged the subtitle up INTO the
               heading's text, overlapping it. Redeclare the full margin (no negative top) here
               instead of just font-size/margin-bottom. */
            .nocturne-onboard-sub { margin: 4px 0 20px; font-size: 12.5px; }
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
            // Pre-select the visitor's geo-detected country by comparing values in JS, not by
            // string-interpolating the server value into a raw CSS attribute selector - a stray
            // character in that value (e.g. from an unreliable IP-geolocation lookup) could throw
            // a hard jQuery selector-syntax error here and silently abort this whole script
            // before it ever reached the change handler/initial sync below, which is exactly the
            // failure mode that left mobile_code/country_code empty at submit even though a
            // country was visibly selected on screen.
            @if ($mobileCode)
                var detectedCountryCode = @json($mobileCode);
                $('#onboard-country option').each(function() {
                    if ($(this).data('code') === detectedCountryCode) {
                        $(this).prop('selected', true);
                        return false;
                    }
                });
            @endif

            // The dial code field is a real, directly-editable input now (not a read-only
            // display) - this auto-fill is a convenience, never a requirement. If it silently
            // fails to run in some browser, the user can still just type their own code, and the
            // field's own 'required' attribute (plus the server-side country_code fallback
            // below) means the form is never blocked on this auto-detection working.
            var mobileCodeTouched = $('#onboard-mobile-code').val() !== '';
            $('#onboard-mobile-code').on('input', function() { mobileCodeTouched = true; });

            function syncMobileCode() {
                var selected = $('#onboard-country option:selected');
                var dial = selected.data('mobile_code');
                var code = selected.data('code');
                if (dial && !mobileCodeTouched) $('#onboard-mobile-code').val(dial);
                $('#onboard-country-code').val(code || '');
            }
            $('#onboard-country').on('change input', function() {
                syncMobileCode();
                checkUser($('[name=mobile]').val(), 'mobile');
            });
            syncMobileCode();

            // A browser restoring this form from bfcache (back/forward navigation) or a plain
            // reload can repopulate the <select>'s value without firing 'change'/'input' at all -
            // 'pageshow' fires on every such restore (including the normal first load), so
            // re-running the sync there keeps country_code in step with whatever the select
            // actually shows, and still respects anything the user already typed into the dial
            // code field themselves.
            window.addEventListener('pageshow', syncMobileCode);

            // A last-chance convenience sync right before submit (covers autofill/bfcache
            // restores that don't always fire a native 'change' event) - this no longer blocks
            // submission on its own: country_code has a server-side fallback (derived from the
            // selected country's name) if this sync never ran, and the mobile_code field's own
            // 'required' attribute handles an empty dial code with the browser's native prompt.
            $('#onboardForm').on('submit', function() {
                syncMobileCode();
            });

            $('.checkUser').on('focusout', function(e) {
                checkUser($(this).val(), $(this).attr('name'));
            });

            function checkUser(value, name) {
                var url = '{{ route('user.checkUser') }}';
                var token = '{{ csrf_token() }}';
                var data;
                if (name == 'mobile') {
                    data = { mobile: value, mobile_code: $('#onboard-mobile-code').val(), _token: token };
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
