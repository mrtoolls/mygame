@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content">
            <h1 class="nocturne-page-title">@lang('Choose Your Avatar')</h1>
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <div class="card custom--card">
                        <div class="card-body">
                            <div class="avatar-picker__current">
                                <span class="avatar-picker__current-preview">
                                    @if ($user->avatarUrl())
                                        <img src="{{ $user->avatarUrl() }}" alt="{{ $user->username }}">
                                    @else
                                        <i class="las la-user"></i>
                                    @endif
                                </span>
                                <div>
                                    <strong>@lang('Your avatar')</strong>
                                    <p>@lang('Pick any avatar below to use it instead - it saves right away, no extra confirmation needed.')</p>
                                </div>
                            </div>

                            @if (count($avatars))
                                <form method="post" action="{{ route('user.profile.avatar.select') }}" class="avatar-picker__grid">
                                    @csrf
                                    @php($currentPath = 'assets/avatars/' . $gender . '/')
                                    @foreach ($avatars as $avatar)
                                        <button type="submit" name="avatar" value="{{ $avatar }}"
                                            class="avatar-picker__item {{ $user->image === $currentPath . $avatar ? 'is-selected' : '' }}"
                                            aria-label="@lang('Use this avatar')">
                                            <img src="{{ asset($currentPath . $avatar) }}" alt="" loading="lazy" decoding="async">
                                        </button>
                                    @endforeach
                                </form>
                            @else
                                <div class="avatar-picker__empty"><i class="las la-images"></i><p>@lang('No avatars are available right now.')</p></div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('style')
    <style>
        .avatar-picker__current { display: flex; align-items: center; gap: 16px; margin-bottom: 22px; padding-bottom: 22px; border-bottom: 1px solid var(--nocturne-line, rgba(145,156,205,.16)); }
        .avatar-picker__current-preview { display: grid; place-items: center; width: 76px; height: 76px; flex: 0 0 auto; border-radius: 50%; overflow: hidden; background: var(--nocturne-raised, #232a47); color: var(--nocturne-muted, #929abc); font-size: 26px; box-shadow: 0 0 0 3px rgba(0,239,135,.18); }
        .avatar-picker__current-preview img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-picker__current strong { display: block; margin-bottom: 4px; color: var(--nocturne-text, #f5f6ff); font-size: 15px; font-weight: 900; }
        .avatar-picker__current p { margin: 0; color: var(--nocturne-muted, #929abc); font-size: 12.5px; line-height: 1.5; }
        .avatar-picker__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(66px, 1fr)); gap: 10px; max-height: 70vh; overflow-y: auto; padding-right: 4px; }
        .avatar-picker__item { display: block; padding: 0; width: 100%; aspect-ratio: 1; border: 2px solid transparent; border-radius: 50%; overflow: hidden; background: var(--nocturne-raised, #232a47); cursor: pointer; transition: border-color .15s ease, transform .15s ease; }
        .avatar-picker__item img { display: block; width: 100%; height: 100%; object-fit: cover; }
        .avatar-picker__item:hover { transform: translateY(-2px); border-color: rgba(0,239,135,.4); }
        .avatar-picker__item.is-selected { border-color: var(--nocturne-green, #00ef87); box-shadow: 0 0 0 3px rgba(0,239,135,.18); }
        .avatar-picker__empty { display: grid; justify-items: center; gap: 8px; padding: 40px 20px; color: var(--nocturne-muted, #929abc); text-align: center; }
        .avatar-picker__empty i { font-size: 28px; color: var(--nocturne-green, #00ef87); }
    </style>
@endpush
