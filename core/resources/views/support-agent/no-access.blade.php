<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Agent · Winxpot Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css">
    <link rel="stylesheet" href="{{ asset('assets/global/css/support-agent.css') }}?v={{ @filemtime(public_path('assets/global/css/support-agent.css')) }}">
</head>
<body class="support-agent-app">
    @include('support-agent.partials.topbar', ['agent' => $agent])
    <main style="max-width:480px;margin:60px auto;padding:0 20px;text-align:center">
        <div style="display:grid;place-items:center;width:64px;height:64px;margin:0 auto 18px;border-radius:50%;background:rgba(0,239,135,.1);color:var(--agent-green);font-size:28px"><i class="ph-fill ph-hourglass"></i></div>
        <h1 style="font-size:19px;margin-bottom:8px">Nothing assigned yet</h1>
        <p style="color:var(--agent-muted);font-size:13px;line-height:1.6">Your account is active, but an admin hasn't granted you any tools yet (live chat, payments, moderation, news, or marketplace supervision). Ask an admin to enable at least one from your agent profile.</p>
    </main>
</body>
</html>
