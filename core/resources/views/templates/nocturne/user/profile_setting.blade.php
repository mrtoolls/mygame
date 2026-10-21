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
        })(jQuery);
    </script>
@endpush
