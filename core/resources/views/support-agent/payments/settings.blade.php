<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Accepted payment types · Winxpot Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/global/css/support-agent.css') }}?v={{ @filemtime(public_path('assets/global/css/support-agent.css')) }}">
</head>
<body class="support-agent-app">
    <header class="support-topbar">
        <a class="support-agent-brand" href="{{ route('support-agent.dashboard') }}"><span class="support-agent-brand__mark">W</span><span>Winxpot <b>Care</b></span></a>
        <div class="support-topbar__right">
            <a href="{{ route('support-agent.payments.queue') }}" style="color:var(--agent-muted);text-decoration:none;font-size:12px;font-weight:800;padding:7px 11px;border:1px solid var(--agent-line);border-radius:9px">← Back to queue</a>
        </div>
    </header>
    <main style="max-width:620px;margin:0 auto;padding:28px 20px">
        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px">
            <div style="flex:1;min-width:150px;padding:18px;border:1px solid var(--agent-line);border-radius:16px;background:var(--agent-panel)">
                <small style="color:var(--agent-muted);text-transform:uppercase;font-size:11px;font-weight:800">Speed score</small>
                <div style="font-size:26px;font-weight:900;color:var(--agent-green)">{{ $speedScore }}<span style="font-size:13px;color:var(--agent-muted)">/100</span></div>
            </div>
            <div style="flex:1;min-width:150px;padding:18px;border:1px solid var(--agent-line);border-radius:16px;background:var(--agent-panel)">
                <small style="color:var(--agent-muted);text-transform:uppercase;font-size:11px;font-weight:800">Fast confirms this month</small>
                <div style="font-size:26px;font-weight:900">{{ $fastStreakCount }}<span style="font-size:13px;color:var(--agent-muted)">/50</span></div>
            </div>
        </div>

        @if(session('status'))
            <div style="margin-bottom:18px;padding:12px 16px;border:1px solid rgba(0,239,135,.3);border-radius:12px;background:rgba(0,239,135,.08);color:#b3f9da">{{ session('status') }}</div>
        @endif

        <h1 style="font-size:19px;margin-bottom:6px">Accepted payment types</h1>
        <p style="color:var(--agent-muted);font-size:13px;margin-bottom:18px">Shown to users on your WinxPay card so they know how to pay you (e.g. Zelle, Cashapp, Bank Transfer, Crypto).</p>

        <form method="post" action="{{ route('support-agent.payments.settings.update') }}" id="types-form">
            @csrf
            <div id="tag-list" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px">
                @foreach($agent->accepted_payment_types ?? [] as $type)
                    <span class="payment-type-tag" style="display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:rgba(0,239,135,.1);color:var(--agent-green);font-size:12px;font-weight:800">
                        <input type="hidden" name="accepted_payment_types[]" value="{{ $type }}">
                        {{ $type }}
                        <button type="button" class="remove-tag" style="border:0;background:transparent;color:inherit;cursor:pointer;font-weight:900">×</button>
                    </span>
                @endforeach
            </div>
            <div style="display:flex;gap:8px;margin-bottom:18px">
                <input type="text" id="new-type-input" placeholder="e.g. Zelle" style="flex:1;padding:10px 12px;border:1px solid var(--agent-line);border-radius:10px;background:#0d1122;color:var(--agent-text);font:inherit">
                <button type="button" id="add-type-btn" style="padding:10px 16px;border:0;border-radius:10px;background:var(--agent-raised);color:var(--agent-text);font-size:13px;font-weight:800;cursor:pointer">Add</button>
            </div>
            <button type="submit" style="padding:11px 18px;border:0;border-radius:11px;background:var(--agent-green);color:#063b29;font-size:13px;font-weight:800;cursor:pointer">Save</button>
        </form>
    </main>
    <script>
    (() => {
        const list = document.getElementById('tag-list');
        const input = document.getElementById('new-type-input');

        function makeTag(value) {
            const span = document.createElement('span');
            span.className = 'payment-type-tag';
            span.style.cssText = 'display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:rgba(0,239,135,.1);color:var(--agent-green);font-size:12px;font-weight:800';
            span.innerHTML = '<input type="hidden" name="accepted_payment_types[]" value="' + value.replace(/"/g, '&quot;') + '">' + value + ' <button type="button" class="remove-tag" style="border:0;background:transparent;color:inherit;cursor:pointer;font-weight:900">×</button>';
            return span;
        }

        document.getElementById('add-type-btn')?.addEventListener('click', () => {
            const value = input.value.trim();
            if (!value) return;
            list.appendChild(makeTag(value));
            input.value = '';
            input.focus();
        });
        input?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); document.getElementById('add-type-btn').click(); }
        });
        list?.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-tag')) {
                e.target.closest('.payment-type-tag')?.remove();
            }
        });
    })();
    </script>
</body>
</html>
