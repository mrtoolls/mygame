@extends('Template::layouts.master')
@section('content')
    <link rel="stylesheet" href="{{ asset('assets/global/css/coin-marketplace.css') }}?v={{ @filemtime('assets/global/css/coin-marketplace.css') }}">
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content">
            <h1 class="nocturne-page-title">@lang('Trade') #{{ $trade->id }}</h1>

            <div class="coin-page">
                <div class="coin-trade-head">
                    <div>
                        <div class="coin-trade-head__amount">{{ number_format($trade->amount, 0) }} <small style="font-size:14px;color:var(--nocturne-muted);font-weight:700">@lang('coins')</small></div>
                        <div class="coin-trade-head__meta">
                            <span>@lang('Fee'): {{ number_format($trade->fee_amount, 2) }} ({{ $trade->fee_payer }} @lang('pays'))</span>
                            <span>{{ ucfirst(str_replace('_', ' ', $trade->direction)) }}</span>
                        </div>
                    </div>
                    <span class="coin-badge coin-badge--{{ $trade->status }}" style="font-size:12.5px;padding:8px 15px">{{ str_replace('_', ' ', $trade->status) }}</span>
                </div>

                @if($trade->payment_deadline_at && $trade->status === 'pending')
                    <div class="coin-warning-strip"><i class="ph-fill ph-clock-countdown"></i> @lang('Payment window ends'): {{ $trade->payment_deadline_at }}</div>
                @endif
                @if($trade->confirmation_deadline_at && $trade->status === 'paid_claimed')
                    <div class="coin-warning-strip"><i class="ph-fill ph-clock-countdown"></i> @lang('Confirmation window ends'): {{ $trade->confirmation_deadline_at }}</div>
                @endif
                <div class="coin-warning-strip"><i class="ph-fill ph-shield-warning"></i> @lang('Never share your password, payment card number, or verification code in chat.')</div>

                <div class="coin-section">
                    <div class="coin-trade-actions coin-trade-sticky-actions">
                        @if($trade->status === 'pending' && $trade->coin_buyer_type === 'user' && (int)$trade->coin_buyer_id === auth()->id())
                            <form method="post" action="{{ route('user.coin.trade.claim-payment', $trade->id) }}" enctype="multipart/form-data" class="coin-upload">
                                @csrf
                                <input type="file" name="proof">
                                <button type="submit" class="coin-btn coin-btn--primary"><i class="ph-bold ph-check-circle"></i> @lang("I have paid")</button>
                            </form>
                        @endif
                        @if($trade->status === 'pending' && $trade->coin_seller_type === 'user' && (int)$trade->coin_seller_id === auth()->id())
                            <form method="post" action="{{ route('user.coin.trade.reopen-listing', $trade->id) }}">
                                @csrf
                                <button type="submit" class="coin-btn coin-btn--outline"><i class="ph-bold ph-arrow-counter-clockwise"></i> @lang('Put back on sale')</button>
                            </form>
                        @endif
                        @if($trade->status === 'paid_claimed' && $trade->coin_seller_type === 'user' && (int)$trade->coin_seller_id === auth()->id())
                            <form method="post" action="{{ route('user.coin.trade.confirm-received', $trade->id) }}">
                                @csrf
                                <button type="submit" class="coin-btn coin-btn--primary"><i class="ph-bold ph-check-circle"></i> @lang('I received payment — release coins')</button>
                            </form>
                            <form method="post" action="{{ route('user.coin.trade.report-not-received', $trade->id) }}">
                                @csrf
                                <button type="submit" class="coin-btn coin-btn--danger"><i class="ph-bold ph-warning"></i> @lang('I have not received payment')</button>
                            </form>
                        @endif
                        @if(in_array($trade->status, ['completed', 'disputed', 'cancelled', 'expired'], true))
                            <a href="{{ route('user.coin.marketplace') }}" class="coin-btn coin-btn--outline"><i class="ph-bold ph-arrow-left"></i> @lang('Back to marketplace')</a>
                        @endif
                    </div>
                </div>

                <div class="coin-chat">
                    <div class="coin-chat__head"><i class="ph-fill ph-chat-circle-dots"></i> <strong>@lang('Trade chat')</strong></div>
                    <div id="coinTradeMessages" class="coin-chat__messages"><div class="coin-chat__empty">@lang('Loading messages…')</div></div>
                    <form id="coinTradeChatForm" class="coin-chat__composer">
                        <input type="text" name="body" placeholder="@lang('Write a message…')" maxlength="1000">
                        <button type="submit" aria-label="@lang('Send')"><i class="ph-bold ph-paper-plane-right"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function(){
        var authUserId = {{ (int) auth()->id() }};
        var baseUrl = '{{ url('/coin-trade/' . $trade->id . '/chat') }}';
        var box = document.getElementById('coinTradeMessages');
        var form = document.getElementById('coinTradeChatForm');
        var polling = true;

        function escapeHtml(value) {
            return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function render(messages) {
            if (!messages.length) { box.innerHTML = '<div class="coin-chat__empty">@lang('No messages yet — say hello.')</div>'; return; }
            box.innerHTML = messages.map(function (m) {
                var mine = m.sender_type === 'user' && Number(m.sender_id) === authUserId;
                var att = m.attachment_url ? '<a class="coin-msg__attachment" href="' + m.attachment_url + '" target="_blank" rel="noopener"><i class="ph-bold ph-paperclip"></i> @lang('Attachment')</a>' : '';
                return '<div class="coin-msg' + (mine ? ' is-mine' : '') + '">' +
                    '<span class="coin-msg__meta">' + escapeHtml(m.sender_type) + ' #' + escapeHtml(m.sender_id) + '</span>' +
                    '<div class="coin-msg__bubble">' + escapeHtml(m.body || '') + att + '</div>' +
                    '</div>';
            }).join('');
            box.scrollTop = box.scrollHeight;
        }

        function poll() {
            if (!polling) return;
            fetch(baseUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) { render(data.messages || []); })
                .catch(function () {})
                .finally(function () { setTimeout(poll, document.hidden ? 7000 : 2500); });
        }
        poll();
        document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });

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
@endsection
