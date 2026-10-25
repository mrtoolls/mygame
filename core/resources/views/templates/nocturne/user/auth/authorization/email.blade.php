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
                    <h2>@lang('Verify your email')</h2>
                    <p>@lang('One quick step to keep your account secure.')</p>
                    <div class="nocturne-auth-visual__trust"><x-icon name="shield" /> @lang('Secure, encrypted connection')</div>
                </div>
                <small class="nocturne-auth-visual__copyright">&copy; {{ date('Y') }} {{ gs('site_name') }} | @lang('All Rights Reserved').</small>
            </div>
            <div class="nocturne-auth-form">
                <h1>@lang('Enter verification code')</h1>
                <p class="nocturne-auth-form__intro">@lang('A 6 digit verification code sent to your email address'): <strong>{{ showEmailAddress(auth()->user()->email) }}</strong></p>

                <form action="{{ route('user.verify.email') }}" method="POST" class="submit-form">
                    @csrf

                    @include('Template::partials.verification_code')

                    <div class="form-submit">
                        <button type="submit" class="btn btn--gradient w-100">@lang('Submit')</button>
                    </div>
                </form>

                <div class="nocturne-auth-switch">
                    <span>@lang('If you don\'t get any code'), <span class="countdown-wrapper">@lang('try again after') <span id="countdown" class="fw-bold">--</span> @lang('seconds')</span></span>
                    <a href="{{ route('user.send.verify.code', 'email') }}" class="try-again-link d-none"><x-icon name="arrow-clockwise" />@lang('Try again')</a>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('script')
<script>
    var distance = Number("{{ @$user->ver_code_send_at->addMinutes(2)->timestamp - time() }}");
    var x = setInterval(function() {
        distance--;
        document.getElementById("countdown").innerHTML = distance;
        if (distance <= 0) {
            clearInterval(x);
            document.querySelector('.countdown-wrapper').classList.add('d-none');
            document.querySelector('.try-again-link').classList.remove('d-none');
        }
    }, 1000);
</script>
@endpush
