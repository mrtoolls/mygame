<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Support Agent Sign In · Winxpot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/global/css/support-agent.css') }}?v={{ @filemtime(public_path('assets/global/css/support-agent.css')) }}">
</head>
<body class="support-agent-auth">
    <main class="support-login-card">
        <a class="support-agent-brand" href="{{ route('home') }}"><span class="support-agent-brand__mark">W</span><span>Winxpot <b>Care</b></span></a>
        <span class="support-agent-eyebrow">AGENT WORKSPACE</span>
        <h1>Good to see you.</h1>
        <p>Sign in to pick up customer conversations and help players get back in the game.</p>
        @if($errors->any())<div class="support-login-error">{{ $errors->first() }}</div>@endif
        <form method="post" action="{{ route('support-agent.login.submit') }}">
            @csrf
            <label for="agent-login">Email or username</label><input id="agent-login" name="login" value="{{ old('login') }}" autocomplete="username" required autofocus>
            <label for="agent-password">Password</label><input id="agent-password" type="password" name="password" autocomplete="current-password" required>
            <button type="submit">Sign in to inbox <span>→</span></button>
        </form>
        <small class="support-login-foot">Your account is managed by the Winxpot administrator.</small>
    </main>
</body>
</html>
