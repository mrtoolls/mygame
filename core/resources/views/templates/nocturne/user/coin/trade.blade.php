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
                            @if($trade->fiat_total && $trade->fiat_currency)<span>Locked rate: {{ $trade->fiat_currency }} {{ number_format($trade->fiat_total,2) }} total · {{ number_format($trade->price_per_coin,4) }} per coin</span>@endif
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

                @if($counterparty)
                    @php($cm = $counterparty['contact_methods'] ?? null)
                    <section class="coin-contact-card" aria-label="@lang('Trade partner contact details')">
                        <div class="coin-contact-card__identity">
                            <span class="coin-contact-card__avatar">
                                @if($counterparty['avatar'])<img src="{{ $counterparty['avatar'] }}" alt="">@else<i class="ph-fill ph-user-circle"></i>@endif
                                @if($counterparty['verified'])<span class="coin-contact-card__badge" title="@lang('Verified')"><i class="ph-fill ph-seal-check"></i></span>@endif
                            </span>
                            <div>
                                <span class="coin-contact-card__name">{{ $counterparty['name'] }}</span>
                                <span class="coin-contact-card__role">{{ __($counterparty['role']) }}</span>
                            </div>
                        </div>
                        <div class="coin-contact-card__channels">
                            @if(is_array($cm))
                                @if(!empty($cm['whatsapp']['enabled']) && !empty($cm['whatsapp']['value']))<a href="https://wa.me/{{ preg_replace('/\D/', '', $cm['whatsapp']['value']) }}" target="_blank" rel="noopener" class="coin-contact-card__channel"><i class="ph-fill ph-whatsapp-logo"></i> @lang('WhatsApp')</a>@endif
                                @if(!empty($cm['telegram']['enabled']) && !empty($cm['telegram']['value']))<a href="https://t.me/{{ ltrim($cm['telegram']['value'], '@') }}" target="_blank" rel="noopener" class="coin-contact-card__channel"><i class="ph-fill ph-telegram-logo"></i> @lang('Telegram')</a>@endif
                                @if(!empty($cm['phone']['enabled']) && !empty($cm['phone']['value']))<a href="tel:{{ $cm['phone']['value'] }}" class="coin-contact-card__channel"><i class="ph-fill ph-phone"></i> @lang('Call')</a>@endif
                                @if(!empty($cm['email']['enabled']) && !empty($cm['email']['value']))<a href="mailto:{{ $cm['email']['value'] }}" class="coin-contact-card__channel"><i class="ph-fill ph-envelope-simple"></i> @lang('Email')</a>@endif
                            @endif
                        </div>
                    </section>
                @endif

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
                    @if(count($vendorPaymentMethods ?? []))
                        <div class="coin-chat__vendor-methods" style="display:flex;flex-wrap:wrap;gap:7px;padding:10px 14px;border-bottom:1px solid var(--nocturne-line)">
                            @foreach($vendorPaymentMethods as $method)
                                <button type="button" class="coin-btn coin-btn--outline" data-payment-details="{{ e(\App\Support\CoinVendorPaymentMethod::chatSummary((array) $method)) }}">{{ __('Insert :method', ['method' => $method['label'] ?? ucfirst(str_replace('_',' ',$method['type'] ?? 'payment'))]) }}</button>
                            @endforeach
                        </div>
                    @endif
                    <div id="coinTradeMessages" class="coin-chat__messages"><div class="coin-chat__empty">@lang('Loading messages…')</div></div>
                    <form id="coinTradeChatForm" class="coin-chat__composer">
                        <input type="text" name="body" placeholder="@lang('Write a message…')" maxlength="1000">
                        <button type="submit" aria-label="@lang('Send')"><i class="ph-bold ph-paper-plane-right"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <style>
        .coin-contact-card{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:18px;padding:14px 16px;border:1px solid var(--nocturne-line,rgba(150,165,210,.16));border-radius:16px;background:rgba(8,12,27,.28)}
        .coin-contact-card__identity{display:flex;align-items:center;gap:12px;min-width:0}
        .coin-contact-card__avatar{position:relative;display:grid;place-items:center;flex:0 0 44px;width:44px;height:44px;border-radius:50%;background:var(--nocturne-raised,#232a47);color:var(--nocturne-muted,#838bb4);font-size:22px;overflow:hidden}
        .coin-contact-card__avatar img{width:100%;height:100%;object-fit:cover}
        .coin-contact-card__badge{position:absolute;right:-2px;bottom:-2px;display:grid;place-items:center;width:17px;height:17px;border-radius:50%;background:var(--nocturne-green,#00ef87);color:#063b29;font-size:11px}
        .coin-contact-card__name{display:block;color:var(--nocturne-text,#f5f6ff);font-size:14.5px;font-weight:800;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .coin-contact-card__role{display:block;margin-top:1px;color:var(--nocturne-muted,#838bb4);font-size:12px;font-weight:700}
        .coin-contact-card__channels{display:flex;flex-wrap:wrap;gap:8px}
        .coin-contact-card__channel{display:inline-flex;align-items:center;gap:7px;height:38px;padding:0 13px;border:1px solid var(--nocturne-line,rgba(150,165,210,.16));border-radius:11px;background:var(--nocturne-raised,#232a47);color:var(--nocturne-text,#f5f6ff);text-decoration:none;font-size:12.5px;font-weight:700}
        .coin-contact-card__channel:hover{border-color:var(--nocturne-green,#00ef87);color:var(--nocturne-green,#00ef87)}
        @media(max-width:560px){.coin-contact-card{flex-direction:column;align-items:flex-start}.coin-contact-card__channels{width:100%}.coin-contact-card__channel{flex:1 1 auto;justify-content:center}}
    </style>

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
                    '<span class="coin-msg__meta">' + escapeHtml(m.sender_label || m.sender_type) + ' #' + escapeHtml(m.sender_id) + '</span>' +
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
        document.querySelectorAll('[data-payment-details]').forEach(function(button){button.addEventListener('click',function(){form.body.value=button.getAttribute('data-payment-details')||'';form.body.focus();});});
    })();
    </script>
@endsection
