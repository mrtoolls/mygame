<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Direct Messages · Winxpot Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css">
    <link rel="stylesheet" href="{{ asset('assets/global/css/support-agent.css') }}?v={{ @filemtime(public_path('assets/global/css/support-agent.css')) }}">
</head>
<body class="support-agent-app">
    @include('support-agent.partials.topbar', ['agent' => $agent, 'active' => 'messages'])
    <main style="max-width:720px;margin:0 auto;padding:28px 20px">
        @if(session('status'))
            <div style="margin-bottom:18px;padding:12px 16px;border:1px solid rgba(0,239,135,.3);border-radius:12px;background:rgba(0,239,135,.08);color:#b3f9da">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div style="margin-bottom:18px;padding:12px 16px;border:1px solid rgba(255,95,125,.3);border-radius:12px;background:rgba(255,95,125,.08);color:#ff9bad">{{ $errors->first() }}</div>
        @endif

        <h1 style="font-size:19px;margin-bottom:6px">Message a user</h1>
        <p style="color:var(--agent-muted);font-size:13px;margin-bottom:16px">Reach out to any user directly by username - distinct from the support ticket inbox.</p>
        <form method="post" action="{{ route('support-agent.messages.start') }}" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:28px">
            @csrf
            <input type="text" name="username" value="{{ old('username') }}" placeholder="Exact username" style="flex:1 1 180px;height:44px;padding:0 13px;border:1px solid var(--agent-line);border-radius:11px;background:#0b0f20;color:var(--agent-text);font-size:13.5px" required>
            <input type="text" name="body" value="{{ old('body') }}" placeholder="First message" style="flex:2 1 260px;height:44px;padding:0 13px;border:1px solid var(--agent-line);border-radius:11px;background:#0b0f20;color:var(--agent-text);font-size:13.5px" required maxlength="2000">
            <button type="submit" style="display:flex;align-items:center;gap:8px;height:44px;padding:0 18px;border:0;border-radius:11px;background:var(--agent-green);color:#063b29;font-size:12.5px;font-weight:900">Send</button>
        </form>

        <h2 style="font-size:16px;margin-bottom:12px">Conversations</h2>
        @forelse($conversations as $conversation)
            <a href="{{ route('support-agent.messages.show', $conversation['id']) }}" style="display:flex;align-items:center;gap:12px;padding:12px;margin-bottom:6px;border-radius:13px;background:rgba(255,255,255,.02);text-decoration:none;color:var(--agent-text)">
                <span style="display:grid;place-items:center;width:38px;height:38px;flex:0 0 38px;border-radius:50%;background:rgba(0,239,135,.1);color:var(--agent-green)"><i class="ph-fill ph-user-circle" style="font-size:19px"></i></span>
                <div style="flex:1;min-width:0">
                    <strong style="display:block;font-size:13.5px">{{ $conversation['name'] }}</strong>
                    <span style="display:block;color:var(--agent-muted);font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $conversation['preview'] }}</span>
                </div>
                <div style="flex:0 0 auto;text-align:right">
                    <span style="display:block;color:var(--agent-muted);font-size:10.5px">{{ $conversation['time'] }}</span>
                    @if($conversation['unread'])<span style="display:inline-block;margin-top:3px;min-width:18px;padding:0 5px;border-radius:9px;background:#ff4d67;color:#fff;font-size:10px;font-weight:800;line-height:18px">{{ $conversation['unread'] }}</span>@endif
                </div>
            </a>
        @empty
            <p style="color:var(--agent-muted);font-size:13px">No direct message conversations yet.</p>
        @endforelse
    </main>
</body>
</html>
