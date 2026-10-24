<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Trade #{{ $trade->id }} · Winxpot Care</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css">
<link rel="stylesheet" href="{{ asset('assets/global/css/support-agent.css') }}?v={{ @filemtime('assets/global/css/support-agent.css') }}">
<link rel="stylesheet" href="{{ asset('assets/global/css/support-agent-coin.css') }}?v={{ @filemtime('assets/global/css/support-agent-coin.css') }}">
</head>
<body class="support-agent-app">
    <header class="support-topbar">
        <a class="support-agent-brand" href="{{ route('support-agent.coin.marketplace') }}"><span class="support-agent-brand__mark">W</span><span>Winxpot <b>Care</b></span></a>
    </header>

    <main class="coin-agent-shell">
        <div class="coin-agent-topbar">
            <nav class="coin-agent-nav"><a href="{{ route('support-agent.coin.marketplace') }}"><i class="ph-bold ph-arrow-left"></i> @lang('Back to marketplace')</a></nav>
        </div>

        <div class="coin-agent-page">
            <div class="coin-agent-trade-head">
                <div>
                    <div class="coin-agent-trade-head__amount">{{ number_format($trade->amount, 0) }} <small style="font-size:13px;color:var(--agent-muted);font-weight:700">@lang('coins')</small></div>
                    <div style="margin-top:4px;color:var(--agent-muted);font-size:11.5px">@lang('Fee'): {{ number_format($trade->fee_amount, 2) }} ({{ $trade->fee_payer }} @lang('pays'))</div>
                </div>
                <span class="coin-agent-badge coin-agent-badge--{{ $trade->status }}" style="font-size:11px;padding:7px 13px">{{ str_replace('_', ' ', $trade->status) }}</span>
            </div>

            <div class="coin-agent-notice"><i class="ph-fill ph-shield-warning"></i> @lang('Never share passwords, card numbers, or verification codes in chat.')</div>

            @php($agentId = auth('support_agent')->id())
            <div class="coin-agent-card">
                <div class="coin-agent-actions">
                    @if($trade->status === 'pending' && $trade->coin_buyer_type === 'agent' && (int)$trade->coin_buyer_id === $agentId)
                        <form method="post" action="{{ route('support-agent.coin.trade.claim-payment', $trade->id) }}" enctype="multipart/form-data" style="display:flex;gap:9px;flex:1;min-width:220px">
                            @csrf
                            <input type="file" name="proof" style="flex:1;min-width:0;height:42px;padding:9px;border:1px dashed var(--agent-line);border-radius:10px;background:#0d1122;color:var(--agent-muted);font-size:11px">
                            <button type="submit" class="coin-agent-btn coin-agent-btn--primary"><i class="ph-bold ph-check-circle"></i> @lang('I have paid')</button>
                        </form>
                    @endif
                    @if($trade->status === 'paid_claimed' && $trade->coin_seller_type === 'agent' && (int)$trade->coin_seller_id === $agentId)
                        <form method="post" action="{{ route('support-agent.coin.trade.confirm-received', $trade->id) }}">
                            @csrf
                            <button type="submit" class="coin-agent-btn coin-agent-btn--primary"><i class="ph-bold ph-check-circle"></i> @lang('I received payment — release coins')</button>
                        </form>
                        <form method="post" action="{{ route('support-agent.coin.trade.report-not-received', $trade->id) }}">
                            @csrf
                            <button type="submit" class="coin-agent-btn coin-agent-btn--danger"><i class="ph-bold ph-warning"></i> @lang('I have not received payment')</button>
                        </form>
                    @endif
                    @if(in_array($trade->status, ['completed', 'disputed', 'cancelled', 'expired'], true))
                        <span style="color:var(--agent-muted);font-size:12px">@lang('This trade is closed.')</span>
                    @endif
                </div>
            </div>

            <div class="coin-agent-chat">
                <div class="coin-agent-chat__head"><i class="ph-fill ph-chat-circle-dots" style="color:var(--agent-green);margin-right:6px"></i> @lang('Trade chat')</div>
                <div id="coinTradeMessages" class="coin-agent-chat__messages"><div class="coin-agent-empty">@lang('Loading messages…')</div></div>
                <form id="coinTradeChatForm" class="coin-agent-chat__composer">
                    <input type="text" name="body" placeholder="@lang('Write a message…')" maxlength="1000">
                    <button type="submit" aria-label="@lang('Send')"><i class="ph-bold ph-paper-plane-right"></i></button>
                </form>
            </div>
        </div>
    </main>

    <script>
    (function(){
        var authAgentId = {{ (int) $agentId }};
        var baseUrl = '{{ url('/coin-trade/' . $trade->id . '/chat') }}';
        var box = document.getElementById('coinTradeMessages');
        var form = document.getElementById('coinTradeChatForm');

        function escapeHtml(value) {
            return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function render(messages) {
            if (!messages.length) { box.innerHTML = '<div class="coin-agent-empty">@lang('No messages yet.')</div>'; return; }
            box.innerHTML = messages.map(function (m) {
                var mine = m.sender_type === 'agent' && Number(m.sender_id) === authAgentId;
                var att = m.attachment_url ? '<a class="coin-agent-msg__attachment" href="' + m.attachment_url + '" target="_blank" rel="noopener"><i class="ph-bold ph-paperclip"></i> @lang('Attachment')</a>' : '';
                return '<div class="coin-agent-msg' + (mine ? ' is-mine' : '') + '">' +
                    '<span class="coin-agent-msg__meta">' + escapeHtml(m.sender_type) + ' #' + escapeHtml(m.sender_id) + '</span>' +
                    '<div class="coin-agent-msg__bubble">' + escapeHtml(m.body || '') + att + '</div>' +
                    '</div>';
            }).join('');
            box.scrollTop = box.scrollHeight;
        }

        function poll() {
            fetch(baseUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) { render(data.messages || []); })
                .catch(function () {})
                .finally(function () { setTimeout(poll, document.hidden ? 7000 : 2500); });
        }
        poll();

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var body = form.body.value.trim();
            if (!body) return;
            form.body.disabled = true;
            fetch(baseUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: 'body=' + encodeURIComponent(body)
            }).finally(function () { form.body.disabled = false; form.body.value = ''; form.body.focus(); });
        });
    })();
    </script>
</body>
</html>
