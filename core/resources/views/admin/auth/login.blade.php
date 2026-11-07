@extends('admin.layouts.master')
@section('content')
<div class="gate">
    <div class="gate__glow gate__glow--1"></div>
    <div class="gate__glow gate__glow--2"></div>
    <div class="gate__grid"></div>

    <div class="gate__card">
        <div class="gate__brand">
            <img src="{{ siteLogo() }}" alt="{{ gs('site_name') }}">
        </div>

        <div class="gate__head">
            <span class="gate__kicker"><i class="las la-lock"></i>@lang('Restricted Access')</span>
            <h1>@lang('Admin Sign In')</h1>
            <p>@lang('This area is for authorized administrators only. All access attempts are logged.')</p>
        </div>

        <form action="{{ route('admin.login') }}" method="POST" class="gate__form verify-gcaptcha" autocomplete="off">
            @csrf
            <label class="gate__field">
                <span>@lang('Username')</span>
                <div class="gate__control">
                    <i class="las la-user"></i>
                    <input type="text" name="username" value="{{ old('username') }}" placeholder="@lang('your_username')" autocomplete="username" required autofocus>
                </div>
            </label>
            <label class="gate__field">
                <div class="gate__field-head">
                    <span>@lang('Password')</span>
                    <a href="{{ route('admin.password.reset') }}">@lang('Forgot password?')</a>
                </div>
                <div class="gate__control">
                    <i class="las la-key"></i>
                    <input type="password" name="password" id="gate-password" placeholder="••••••••" autocomplete="current-password" required>
                    <button type="button" class="gate__toggle" id="gate-password-toggle" aria-label="@lang('Show password')"><i class="las la-eye"></i></button>
                </div>
            </label>

            <div class="gate__captcha">
                <x-captcha />
                <div id="g-recaptcha-error"></div>
            </div>

            <button type="submit" class="gate__submit"><i class="las la-sign-in-alt"></i>@lang('Sign In')</button>
        </form>

        <p class="gate__foot"><i class="las la-fingerprint"></i>@lang('Secured session - every request is verified server-side.')</p>
    </div>
</div>
@endsection

@push('style')
<style>
.gate {
    --gate-green: #00e78a; --gate-line: rgba(133,142,175,.18); --gate-panel: #10152a; --gate-muted: #929abc; --gate-text: #f3f5ff;
    position: relative; min-height: 100vh; display: flex; align-items: center; justify-content: center;
    padding: 24px; overflow: hidden; background: radial-gradient(ellipse at 50% -10%, #111c2e, #060814 60%); box-sizing: border-box;
}
.gate * { box-sizing: border-box; }
.gate__glow { position: absolute; border-radius: 50%; filter: blur(10px); pointer-events: none; }
.gate__glow--1 { top: -180px; left: -120px; width: 420px; height: 420px; background: radial-gradient(circle, rgba(0,231,138,.16), transparent 70%); }
.gate__glow--2 { bottom: -220px; right: -140px; width: 480px; height: 480px; background: radial-gradient(circle, rgba(56,189,248,.1), transparent 70%); }
.gate__grid {
    position: absolute; inset: 0; opacity: .5; pointer-events: none;
    background-image: linear-gradient(rgba(133,142,175,.06) 1px, transparent 1px), linear-gradient(90deg, rgba(133,142,175,.06) 1px, transparent 1px);
    background-size: 42px 42px; mask-image: radial-gradient(ellipse at 50% 30%, #000 10%, transparent 70%);
}
.gate__card {
    position: relative; z-index: 1; width: min(420px, 100%); padding: clamp(26px, 4vw, 38px);
    border: 1px solid var(--gate-line); border-radius: 24px;
    background: linear-gradient(160deg, rgba(23,29,55,.92), rgba(12,16,33,.95)); backdrop-filter: blur(16px);
    box-shadow: 0 30px 80px -24px rgba(0,0,0,.65);
}
.gate__brand { display: flex; align-items: center; justify-content: center; gap: 9px; margin-bottom: 22px; }
.gate__brand img { max-height: 32px; max-width: 160px; object-fit: contain; }
.gate__brand-name { color: var(--gate-text); font-weight: 800; font-size: 14px; letter-spacing: .02em; }
.gate__head { text-align: center; margin-bottom: 26px; }
.gate__kicker { display: inline-flex; align-items: center; gap: 6px; color: var(--gate-green); font-size: 10.5px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
.gate__head h1 { margin: 8px 0 6px; color: var(--gate-text); font-size: 23px; font-weight: 750; letter-spacing: -.02em; }
.gate__head p { margin: 0; color: var(--gate-muted); font-size: 12.5px; line-height: 1.55; }
.gate__form { display: grid; gap: 16px; }
.gate__field { display: grid; gap: 7px; color: var(--gate-muted); font-size: 11.5px; font-weight: 700; }
.gate__field-head { display: flex; align-items: center; justify-content: space-between; }
.gate__field-head a { color: var(--gate-green); font-size: 11px; font-weight: 700; text-decoration: none; }
.gate__field-head a:hover { text-decoration: underline; }
.gate__control {
    position: relative; display: flex; align-items: center; gap: 10px;
    border: 1px solid var(--gate-line); border-radius: 13px; background: #0b0f20; padding: 0 14px;
    min-height: 50px; transition: border-color .15s ease, box-shadow .15s ease;
}
.gate__control:focus-within { border-color: rgba(0,231,138,.55); box-shadow: 0 0 0 3px rgba(0,231,138,.12); }
.gate__control > i:first-child { color: var(--gate-muted); font-size: 16px; flex: 0 0 auto; }
.gate__control input { flex: 1; min-width: 0; background: transparent; border: 0; outline: 0; color: var(--gate-text); font-size: 14px; height: 48px; }
.gate__control input::placeholder { color: #5c6690; }
.gate__toggle { flex: 0 0 auto; background: transparent; border: 0; color: var(--gate-muted); font-size: 16px; cursor: pointer; padding: 6px; }
.gate__toggle:hover { color: var(--gate-text); }
.gate__captcha { margin-top: -2px; }
.gate__captcha .form-group { margin: 0; }
.gate__captcha .form-label { display: block; margin: 10px 0 6px; color: var(--gate-muted); font-size: 11.5px; font-weight: 700; }
.gate__captcha .form-control { min-height: 46px; border-radius: 12px; background: #0b0f20; border: 1px solid var(--gate-line); color: var(--gate-text); }
.gate__captcha .form-control:focus { border-color: rgba(0,231,138,.55); box-shadow: 0 0 0 3px rgba(0,231,138,.12); }
#g-recaptcha-error { min-height: 14px; margin-top: 6px; font-size: 11.5px; }
.gate__submit {
    display: inline-flex; align-items: center; justify-content: center; gap: 9px; width: 100%; min-height: 50px;
    border: 0; border-radius: 13px; background: linear-gradient(110deg, #00ef87, #00c878); color: #06281c;
    font-size: 14px; font-weight: 800; cursor: pointer; transition: filter .15s ease, transform .1s ease;
}
.gate__submit:hover { filter: brightness(1.06); }
.gate__submit:active { transform: scale(.98); }
.gate__foot { display: flex; align-items: center; justify-content: center; gap: 7px; margin: 20px 0 0; color: #5c6690; font-size: 10.5px; }
.gate__foot i { color: var(--gate-green); }
@media (max-width: 480px) {
    .gate { padding: 16px; }
    .gate__card { padding: 24px 18px; border-radius: 20px; }
    .gate__head h1 { font-size: 20px; }
}
</style>
@endpush

@push('script')
<script>
"use strict";
(function() {
    var toggle = document.getElementById('gate-password-toggle');
    var input = document.getElementById('gate-password');
    if (!toggle || !input) return;
    toggle.addEventListener('click', function() {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        toggle.querySelector('i').className = show ? 'las la-eye-slash' : 'las la-eye';
        toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
})();
</script>
@endpush
