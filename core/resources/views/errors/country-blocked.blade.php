<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@lang('Not available in your country') · {{ gs('site_name') }}</title>
    <style>
        *{box-sizing:border-box}body{min-height:100vh;margin:0;display:grid;place-items:center;padding:24px;background:#0b0e1d;color:#f5f6ff;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif}.country-block{width:min(100%,520px);padding:clamp(26px,6vw,46px);border:1px solid rgba(133,142,175,.18);border-radius:26px;background:radial-gradient(ellipse at 85% 0,rgba(0,231,138,.16),transparent 42%),#151a31;text-align:center;box-shadow:0 24px 70px rgba(0,0,0,.35)}.country-block__icon{width:68px;height:68px;margin:0 auto 20px;display:grid;place-items:center;border:1px solid rgba(0,231,138,.2);border-radius:22px;background:rgba(0,231,138,.09);color:#00e78a;font-size:32px}.country-block h1{margin:0 0 10px;font-size:clamp(24px,6vw,34px);letter-spacing:-.04em}.country-block p{margin:0;color:#929bb9;line-height:1.65}.country-block a{display:inline-flex;justify-content:center;margin-top:24px;padding:13px 20px;border-radius:13px;background:#00e78a;color:#052417;font-weight:800;text-decoration:none}
    </style>
</head>
<body><main class="country-block"><div class="country-block__icon" aria-hidden="true">⌖</div><h1>@lang('Not available in your country')</h1><p>@lang('This service is not currently available in your location. If you believe this is a mistake, please contact our support team.')</p><a href="mailto:{{ gs('email_from') }}">@lang('Contact support')</a></main></body>
</html>
