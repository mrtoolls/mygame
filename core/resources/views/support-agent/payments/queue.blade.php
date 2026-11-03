<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Payments · Winxpot Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css">
    <link rel="stylesheet" href="{{ asset('assets/global/css/support-agent.css') }}?v={{ @filemtime(public_path('assets/global/css/support-agent.css')) }}">
</head>
<body class="support-agent-app">
    @include('support-agent.partials.topbar', ['agent' => $agent, 'active' => 'payments'])
    <div style="max-width:960px;margin:12px auto 0;padding:0 20px"><a href="{{ route('support-agent.payments.settings') }}" class="support-nav-link"><i class="ph-fill ph-sliders"></i> <span>Accepted payment types</span></a></div>
    <main style="max-width:960px;margin:0 auto;padding:28px 20px">
        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px">
            <div style="flex:1;min-width:180px;padding:18px;border:1px solid var(--agent-line);border-radius:16px;background:var(--agent-panel)">
                <small style="color:var(--agent-muted);text-transform:uppercase;font-size:11px;font-weight:800">Speed score</small>
                <div style="font-size:30px;font-weight:900;color:var(--agent-green)">{{ $speedScore }}<span style="font-size:14px;color:var(--agent-muted)">/100</span></div>
            </div>
            <div style="flex:1;min-width:180px;padding:18px;border:1px solid var(--agent-line);border-radius:16px;background:var(--agent-panel)">
                <small style="color:var(--agent-muted);text-transform:uppercase;font-size:11px;font-weight:800">Fast confirms this month</small>
                <div style="font-size:30px;font-weight:900">{{ $fastStreakCount }}<span style="font-size:14px;color:var(--agent-muted)">/50</span></div>
            </div>
        </div>

        @if(session('status'))
            <div style="margin-bottom:18px;padding:12px 16px;border:1px solid rgba(0,239,135,.3);border-radius:12px;background:rgba(0,239,135,.08);color:#b3f9da">{{ session('status') }}</div>
        @endif

        @if($notifications->isNotEmpty())
            <div style="margin-bottom:24px;padding:16px;border:1px solid rgba(255,190,65,.3);border-radius:14px;background:rgba(255,190,65,.06)">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                    <strong style="font-size:13px">🔔 {{ $notifications->count() }} new notification{{ $notifications->count() === 1 ? '' : 's' }}</strong>
                    <form method="post" action="{{ route('support-agent.payments.notifications.read') }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button type="submit" style="border:0;background:transparent;color:var(--agent-muted);font-size:11px;font-weight:800;cursor:pointer;text-decoration:underline">Mark all read</button></form>
                </div>
                <div style="display:grid;gap:6px">
                    @foreach($notifications as $n)
                        <a href="{{ $n->click_url ?: '#' }}" style="display:block;font-size:12.5px;color:var(--agent-text);text-decoration:none"><strong>{{ $n->title }}</strong> @if($n->body)<span style="color:var(--agent-muted)"> — {{ $n->body }}</span>@endif</a>
                    @endforeach
                </div>
            </div>
        @endif

        <h1 style="font-size:20px;margin-bottom:16px">Pending deposits awaiting your confirmation</h1>

        <div style="display:grid;gap:12px">
            @forelse($deposits as $deposit)
                <div style="display:flex;align-items:center;gap:14px;padding:16px;border:1px solid var(--agent-line);border-radius:14px;background:var(--agent-panel);flex-wrap:wrap">
                    <div style="flex:1;min-width:200px">
                        <strong>{{ $deposit->user?->username }}</strong>
                        <div style="color:var(--agent-muted);font-size:12px">{{ $deposit->coin_mode_purchase ? coinAmountText($deposit->coin_amount) : showAmount($deposit->amount) }} · {{ $deposit->trx }} · {{ showDateTime($deposit->created_at) }}</div>
                    </div>
                    <a href="{{ route('support-agent.payments.show', $deposit->id) }}" style="padding:9px 14px;border:1px solid var(--agent-line);border-radius:10px;color:var(--agent-text);text-decoration:none;font-size:12px;font-weight:800">Review</a>
                    <form method="post" action="{{ route('support-agent.payments.confirm', $deposit->id) }}">
                        @csrf
                        <button type="submit" style="padding:9px 14px;border:0;border-radius:10px;background:var(--agent-green);color:#063b29;font-size:12px;font-weight:800;cursor:pointer">Confirm &amp; credit</button>
                    </form>
                </div>
            @empty
                <div style="padding:40px;text-align:center;border:1px dashed var(--agent-line);border-radius:16px;color:var(--agent-muted)">No pending deposits right now.</div>
            @endforelse
        </div>
    </main>
</body>
</html>
