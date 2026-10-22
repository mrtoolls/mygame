@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content">
    <h1 class="nocturne-page-title">@lang('Settings')</h1>
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card custom--card">
                <div class="card-body">
                    <form class="register" method="post" enctype="multipart/form-data">
                        @csrf
                        <div class="profile-avatar-field">
                            <div class="profile-avatar-field__preview" id="avatar-preview">
                                @if ($user->avatarUrl())
                                    <img src="{{ $user->avatarUrl() }}" alt="{{ $user->username }}" id="avatar-preview-img">
                                @else
                                    <span id="avatar-preview-initial">{{ strtoupper(substr($user->username ?: $user->firstname, 0, 1)) }}</span>
                                    <img src="" alt="{{ $user->username }}" id="avatar-preview-img" hidden>
                                @endif
                            </div>
                            <label class="profile-avatar-field__upload" for="avatar-input">
                                <i class="las la-camera"></i> @lang('Change photo')
                                <input type="file" id="avatar-input" name="image" accept="image/png,image/jpeg,image/webp" hidden>
                            </label>
                        </div>
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label class="form-label">@lang('First Name')</label>
                                    <input type="text" class="form--control" name="firstname"
                                        value="{{ $user->firstname }}" required>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label class="form-label">@lang('Last Name')</label>
                                    <input type="text" class="form--control" name="lastname"
                                        value="{{ $user->lastname }}" required>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label class="form-label">@lang('Username')</label>
                                    <input type="text" class="form--control checkUser" name="username"
                                        value="{{ old('username', $user->username) }}" minlength="3" maxlength="60"
                                        pattern="[a-z0-9_]+" autocomplete="off" autocapitalize="none" spellcheck="false" required>
                                    <small class="text--danger usernameExist"></small>
                                    <small class="text-muted d-block">@lang('Letters, numbers and underscore only, at least 3 characters.')</small>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label class="form-label">@lang('E-mail Address')</label>
                                    <input class="form--control" value="{{ $user->email }}" readonly>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label class="form-label">@lang('Mobile Number')</label>
                                    <input class="form--control" value="{{ $user->mobile }}" readonly>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label class="form-label">@lang('Address')</label>
                                    <input type="text" class="form--control" name="address"
                                        value="{{ @$user->address }}">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label class="form-label">@lang('State')</label>
                                    <input type="text" class="form--control" name="state" value="{{ @$user->state }}">
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label class="form-label">@lang('Zip Code')</label>
                                    <input type="text" class="form--control" name="zip" value="{{ @$user->zip }}">
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label class="form-label">@lang('City')</label>
                                    <input type="text" class="form--control" name="city" value="{{ @$user->city }}">
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label class="form-label">@lang('Country')</label>
                                    <input class="form--control" value="{{ @$user->country_name }}" disabled>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn--base w-100">@lang('Submit')</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if ($currencies->isNotEmpty())
        <div class="row justify-content-center mt-3">
            <div class="col-md-10">
                <div class="card custom--card nocturne-currency-card">
                    <div class="card-body">
                        <div class="nocturne-currency-head">
                            <span class="nocturne-currency-head__icon"><i class="las la-coins"></i></span>
                            <div>
                                <h2>@lang('Preferred Currency')</h2>
                                <p>@lang('This is the currency your balance is held in and shown in across the app. Switching converts your balance using the current exchange rate, it does not change its real-world value.')</p>
                            </div>
                        </div>
                        <form method="post" action="{{ route('user.profile.currency') }}" id="currency-form">
                            @csrf
                            <div class="nocturne-currency-grid">
                                @foreach ($currencies as $currency)
                                    <label class="nocturne-currency-option {{ (int) $user->currency_id === (int) $currency->id ? 'is-selected' : '' }}">
                                        <input type="radio" name="currency_id" value="{{ $currency->id }}" {{ (int) $user->currency_id === (int) $currency->id ? 'checked' : '' }}>
                                        <span class="nocturne-currency-option__symbol">{{ $currency->symbol }}</span>
                                        <span class="nocturne-currency-option__code">{{ $currency->code }}</span>
                                        @if ((int) $user->currency_id === (int) $currency->id)<i class="las la-check-circle nocturne-currency-option__check"></i>@endif
                                    </label>
                                @endforeach
                            </div>
                            <button type="submit" class="btn btn--base nocturne-currency-submit" id="currency-submit" disabled>@lang('Update Currency')</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
        </div>
    </div>
@endsection
@push('style')
    <style>
        .profile-avatar-field { display: flex; align-items: center; gap: 16px; margin-bottom: 22px; }
        .profile-avatar-field__preview {
            display: grid; place-items: center; width: 76px; height: 76px; flex: 0 0 auto;
            border-radius: 50%; overflow: hidden; background: var(--nocturne-raised, #232a47);
            color: var(--nocturne-text, #f5f6ff); font-size: 26px; font-weight: 950;
            box-shadow: 0 0 0 3px rgba(0, 239, 135, .18);
        }
        .profile-avatar-field__preview img { width: 100%; height: 100%; object-fit: cover; }
        .profile-avatar-field__upload {
            display: inline-flex; align-items: center; gap: 8px; min-height: 40px; padding: 0 16px;
            border: 1px solid var(--nocturne-line, rgba(145, 156, 205, .16)); border-radius: 11px;
            background: transparent; color: var(--nocturne-text, #f5f6ff); font-size: 12px; font-weight: 800; cursor: pointer;
        }
        .profile-avatar-field__upload:hover { border-color: var(--nocturne-green, #00ef87); color: var(--nocturne-green, #00ef87); }
        .profile-avatar-field__upload i { font-size: 15px; }

        .nocturne-currency-head { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 20px; }
        .nocturne-currency-head__icon { display: grid; flex: 0 0 44px; width: 44px; height: 44px; place-items: center; border-radius: 13px; background: rgba(0,239,135,.14); color: var(--nocturne-green, #00ef87); font-size: 21px; }
        .nocturne-currency-head h2 { margin: 0 0 5px; color: var(--nocturne-text, #f5f6ff); font-size: 17px; font-weight: 900; }
        .nocturne-currency-head p { margin: 0; max-width: 560px; color: var(--nocturne-muted, #929abc); font-size: 12.5px; line-height: 1.6; }
        .nocturne-currency-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; margin-bottom: 18px; }
        .nocturne-currency-option { position: relative; display: grid; justify-items: center; gap: 4px; padding: 14px 8px; border: 1px solid var(--nocturne-line, rgba(145,156,205,.16)); border-radius: 14px; background: var(--nocturne-raised, #232a47); cursor: pointer; transition: border-color .15s ease, background .15s ease; }
        .nocturne-currency-option:hover { border-color: rgba(0,239,135,.4); }
        .nocturne-currency-option.is-selected { border-color: var(--nocturne-green, #00ef87); background: rgba(0,239,135,.1); }
        .nocturne-currency-option input { position: absolute; opacity: 0; pointer-events: none; }
        .nocturne-currency-option__symbol { color: var(--nocturne-text, #f5f6ff); font-size: 18px; font-weight: 950; }
        .nocturne-currency-option__code { color: var(--nocturne-muted, #929abc); font-size: 10.5px; font-weight: 800; letter-spacing: .04em; }
        .nocturne-currency-option__check { position: absolute; top: 6px; right: 6px; color: var(--nocturne-green, #00ef87); font-size: 13px; }
        .nocturne-currency-submit { min-width: 220px; }
        .nocturne-currency-submit:disabled { opacity: .5; cursor: not-allowed; }
    </style>
@endpush
@push('script')
    <script>
        "use strict";
        (function($) {
            $('#avatar-input').on('change', function(event) {
                var file = event.target.files && event.target.files[0];
                if (!file) return;
                var reader = new FileReader();
                reader.onload = function(e) {
                    $('#avatar-preview-img').attr('src', e.target.result).prop('hidden', false);
                    $('#avatar-preview-initial').remove();
                };
                reader.readAsDataURL(file);
            });

            $('.checkUser').on('focusout', function() {
                var value = $(this).val();
                if (!value) return;
                $.post('{{ route('user.checkUser') }}', { username: value, _token: '{{ csrf_token() }}' }, function(response) {
                    if (response.data != false && value !== '{{ $user->username }}') {
                        $('.usernameExist').text('Username already taken');
                    } else {
                        $('.usernameExist').text('');
                    }
                });
            });

            var initialCurrency = $('.nocturne-currency-option input:checked').val();
            $(document).on('change', '.nocturne-currency-option input', function() {
                $('.nocturne-currency-option').removeClass('is-selected').find('.nocturne-currency-option__check').remove();
                var option = $(this).closest('.nocturne-currency-option').addClass('is-selected');
                option.append('<i class="las la-check-circle nocturne-currency-option__check"></i>');
                $('#currency-submit').prop('disabled', $(this).val() === initialCurrency);
            });
            $('#currency-form').on('submit', function(event) {
                if (!confirm('Switch your preferred currency? Your balance will be converted to the new currency at today\'s rate.')) {
                    event.preventDefault();
                }
            });
        })(jQuery);
    </script>
@endpush
