@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content">
            <a href="{{ route('user.messages.index') }}" class="msg-back"><i class="ph-bold ph-arrow-left"></i> @lang('Messages')</a>
            <h1 class="nocturne-page-title">{{ $otherName }} <small>{{ $otherRole }}</small></h1>

            <div class="msg-thread">
                <div id="msgThreadMessages" class="msg-thread__messages"><div class="msg-thread__empty">@lang('Loading messages…')</div></div>
                <form id="msgThreadForm" class="msg-thread__composer">
                    <input type="text" name="body" placeholder="@lang('Write a message…')" maxlength="2000">
                    <button type="submit" aria-label="@lang('Send')"><i class="ph-bold ph-paper-plane-right"></i></button>
                </form>
            </div>
        </div>
    </div>

    <style>
        .msg-back{display:inline-flex;align-items:center;gap:6px;margin-bottom:10px;color:var(--nocturne-muted,#838bb4);text-decoration:none;font-size:12.5px;font-weight:700}
        .nocturne-page-title small{margin-left:8px;color:var(--nocturne-muted,#838bb4);font-size:13px;font-weight:700}
        .msg-thread{display:flex;flex-direction:column;height:min(68vh,620px);border:1px solid var(--nocturne-line,rgba(150,165,210,.16));border-radius:18px;background:var(--nocturne-panel,#151a31);overflow:hidden}
        .msg-thread__messages{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:9px}
        .msg-thread__empty{margin:auto;color:var(--nocturne-muted,#838bb4);font-size:13px}
        .msg-bubble-row{display:flex}
        .msg-bubble-row.is-mine{justify-content:flex-end}
        .msg-bubble{max-width:72%;padding:9px 13px;border-radius:14px;background:var(--nocturne-raised,#232a47);color:var(--nocturne-text,#f5f6ff);font-size:13.5px;line-height:1.45;overflow-wrap:anywhere}
        .msg-bubble-row.is-mine .msg-bubble{background:var(--nocturne-green,#00ef87);color:#063b29}
        .msg-bubble__time{display:block;margin-top:4px;font-size:10px;opacity:.65}
        .msg-thread__composer{display:flex;gap:8px;padding:12px;border-top:1px solid var(--nocturne-line,rgba(150,165,210,.16))}
        .msg-thread__composer input{flex:1;height:44px;padding:0 14px;border:1px solid var(--nocturne-line,rgba(150,165,210,.16));border-radius:12px;background:#0c0f22;color:var(--nocturne-text,#f5f6ff);font-size:13.5px}
        .msg-thread__composer button{display:grid;place-items:center;width:44px;height:44px;border:0;border-radius:12px;background:var(--nocturne-green,#00ef87);color:#063b29;cursor:pointer}
    </style>

    <script>
    (function(){
        var box = document.getElementById('msgThreadMessages');
        var form = document.getElementById('msgThreadForm');
        var pollUrl = @json(route('user.messages.poll', $conversation->id));
        var replyUrl = @json(route('user.messages.reply', $conversation->id));

        function escapeHtml(value) {
            return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function render(messages) {
            if (!messages.length) { box.innerHTML = '<div class="msg-thread__empty">@lang('No messages yet — say hello.')</div>'; return; }
            box.innerHTML = messages.map(function (m) {
                return '<div class="msg-bubble-row' + (m.mine ? ' is-mine' : '') + '">' +
                    '<div class="msg-bubble">' + escapeHtml(m.body) + '<span class="msg-bubble__time">' + escapeHtml(m.time) + '</span></div>' +
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
@endsection
