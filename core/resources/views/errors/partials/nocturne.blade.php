{{--
    Shared chrome for the Nocturne-branded error pages (404/500). Deliberately self-contained
    (no bootstrap, no dependency on the compiled theme stylesheet) so it still renders correctly
    even when the app itself is in a broken state - matches the same standalone approach already
    used by errors/country-blocked.blade.php.

    Expects: $code (string, e.g. "404"), $title (string), $message (string).
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ gs()->siteName($title) }}</title>
    <link rel="shortcut icon" type="image/png" href="{{ siteFavicon() }}">
    <style>
        *{box-sizing:border-box}
        body{min-height:100vh;margin:0;display:grid;place-items:center;padding:24px;overflow-x:hidden;background:#0b0d1e;color:#f5f6ff;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif}
        .nx-error{position:relative;width:min(100%,560px);padding:clamp(30px,6vw,50px) clamp(24px,6vw,46px);border:1px solid rgba(133,142,175,.18);border-radius:28px;background:radial-gradient(ellipse at 85% 0,rgba(0,231,138,.14),transparent 45%),linear-gradient(160deg,#171d37,#10132a);text-align:center;box-shadow:0 30px 80px rgba(0,0,0,.4);overflow:hidden}
        .nx-error::before{content:'';position:absolute;top:-80px;left:50%;width:360px;height:360px;transform:translateX(-50%);background:radial-gradient(circle,rgba(0,231,138,.22),transparent 65%);filter:blur(10px);animation:nxGlow 4s ease-in-out infinite;pointer-events:none}
        @keyframes nxGlow{0%,100%{opacity:.55;transform:translateX(-50%) scale(1)}50%{opacity:.9;transform:translateX(-50%) scale(1.12)}}
        .nx-error__art{position:relative;width:min(220px,62%);margin:0 auto 10px;animation:nxFloat 3.6s ease-in-out infinite}
        .nx-error__art img{width:100%;display:block;filter:drop-shadow(0 18px 30px rgba(0,0,0,.45))}
        @keyframes nxFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
        .nx-error__code{position:relative;display:inline-flex;margin:4px 0 2px;padding:5px 14px;border:1px solid rgba(0,231,138,.3);border-radius:999px;background:rgba(0,231,138,.08);color:#00e78a;font-size:11px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
        .nx-error h1{position:relative;margin:14px 0 10px;font-size:clamp(24px,5.6vw,32px);font-weight:850;letter-spacing:-.03em}
        .nx-error p{position:relative;margin:0 auto;max-width:420px;color:#929bb9;font-size:14.5px;line-height:1.7}
        .nx-error__actions{position:relative;display:flex;flex-wrap:wrap;justify-content:center;gap:12px;margin-top:28px}
        .nx-error__actions a,.nx-error__actions button{display:inline-flex;align-items:center;gap:8px;padding:13px 22px;border:0;border-radius:13px;font:inherit;font-size:13.5px;font-weight:800;text-decoration:none;cursor:pointer;transition:transform .15s ease,opacity .15s ease}
        .nx-error__actions a:hover,.nx-error__actions button:hover{transform:translateY(-2px)}
        .nx-btn--primary{background:#00e78a;color:#052417}
        .nx-btn--ghost{border:1px solid rgba(133,142,175,.3)!important;background:rgba(255,255,255,.03);color:#f5f6ff}
        @media(max-width:480px){.nx-error{padding:26px 18px;border-radius:22px}.nx-error__actions{flex-direction:column}.nx-error__actions a,.nx-error__actions button{width:100%;justify-content:center}}
    </style>
</head>
<body>
    <main class="nx-error">
        <div class="nx-error__art"><img src="{{ asset('assets/images/frontend/nockturne_assets/error.png') }}" alt=""></div>
        <span class="nx-error__code">@lang('Error') {{ $code }}</span>
        <h1>{{ __($title) }}</h1>
        <p>{{ __($message) }}</p>
        <div class="nx-error__actions">
            <a href="{{ route('home') }}" class="nx-btn--primary"><i aria-hidden="true">&#8962;</i>@lang('Return Home')</a>
            <button type="button" class="nx-btn--ghost" onclick="location.reload()"><i aria-hidden="true">&#10227;</i>@lang('Reload Page')</button>
        </div>
    </main>
</body>
</html>
