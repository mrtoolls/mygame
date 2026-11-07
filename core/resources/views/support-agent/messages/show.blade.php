<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $otherName }} · Winxpot Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css">
    <link rel="stylesheet" href="{{ asset('assets/global/css/support-agent.css') }}?v={{ @filemtime(public_path('assets/global/css/support-agent.css')) }}">
    <style>
        .dm-thread{display:flex;flex-direction:column;height:min(68vh,600px);border:1px solid var(--agent-line);border-radius:18px;background:var(--agent-panel);overflow:hidden}
        .dm-thread__messages{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:9px}
        .dm-thread__empty{margin:auto;color:var(--agent-muted);font-size:13px}
        .dm-row{display:flex}
        .dm-row.is-mine{justify-content:flex-end}
        .dm-bubble{max-width:72%;padding:9px 13px;border-radius:14px;background:var(--agent-raised);color:var(--agent-text);font-size:13.5px;line-height:1.45;overflow-wrap:anywhere}
        .dm-row.is-mine .dm-bubble{background:var(--agent-green);color:#063b29}
        .dm-bubble__time{display:block;margin-top:4px;font-size:10px;opacity:.65}
        .dm-composer{display:flex;gap:8px;padding:12px;border-top:1px solid var(--agent-line)}
        .dm-composer input{flex:1;height:44px;padding:0 14px;border:1px solid var(--agent-line);border-radius:12px;background:#0b0f20;color:var(--agent-text);font-size:13.5px}
        .dm-composer button{display:grid;place-items:center;width:44px;height:44px;border:0;border-radius:12px;background:var(--agent-green);color:#063b29;cursor:pointer}
    </style>
</head>
<body class="support-agent-app">
    @include('support-agent.partials.topbar', ['agent' => $agent, 'active' => 'messages'])
    <main style="max-width:720px;margin:0 auto;padding:28px 20px">
        <a href="{{ route('support-agent.messages.index') }}" style="display:inline-flex;align-items:center;gap:6px;margin-bottom:12px;color:var(--agent-muted);text-decoration:none;font-size:12.5px;font-weight:700"><i class="ph-bold ph-arrow-left"></i> Direct Messages</a>
        <h1 style="font-size:19px;margin-bottom:14px">{{ $otherName }}</h1>

        <div class="dm-thread">
            <div id="dmThreadMessages" class="dm-thread__messages"><div class="dm-thread__empty">Loading messages…</div></div>
            <form id="dmThreadForm" class="dm-composer">
                <input type="text" name="body" placeholder="Write a message…" maxlength="2000">
                <button type="submit" aria-label="Send"><i class="ph-bold ph-paper-plane-right"></i></button>
            </form>
        </div>
    </main>
    <script>
    (function(){
        var box = document.getElementById('dmThreadMessages');
        var form = document.getElementById('dmThreadForm');
        var pollUrl = @json(route('support-agent.messages.poll', $conversation->id));
        var replyUrl = @json(route('support-agent.messages.reply', $conversation->id));

        function escapeHtml(value) {
            return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function render(messages) {
            if (!messages.length) { box.innerHTML = '<div class="dm-thread__empty">No messages yet.</div>'; return; }
            box.innerHTML = messages.map(function (m) {
                return '<div class="dm-row' + (m.mine ? ' is-mine' : '') + '">' +
                    '<div class="dm-bubble">' + escapeHtml(m.body) + '<span class="dm-bubble__time">' + escapeHtml(m.time) + '</span></div>' +
                    '</div>';
            }).join('');
            box.scrollTop = box.scrollHeight;
        }

        function poll() {
            fetch(pollUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) { render(data.messages || []); })
                .catch(function () {})
                .finally(function () { setTimeout(poll, document.hidden ? 8000 : 3000); });
        }
        poll();
        document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var body = form.body.value.trim();
            if (!body) return;
            form.body.disabled = true;
            fetch(replyUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': '{{ csrf_token() }}', Accept: 'application/json' },
                body: 'body=' + encodeURIComponent(body)
            }).finally(function () { form.body.disabled = false; form.body.value = ''; form.body.focus(); poll(); });
        });
    })();
    </script>
</body>
</html>
