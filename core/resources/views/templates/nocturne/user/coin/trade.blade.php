@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content">
            <h1 class="nocturne-page-title">@lang('Trade') #{{ $trade->id }}</h1>

            <section class="transection-section">
                <div class="card mb-3">
                    <div class="card-body">
                        <p class="mb-1">@lang('Amount'): <strong>{{ $trade->amount }}</strong> @lang('coins') &middot; @lang('Fee'): {{ $trade->fee_amount }} ({{ $trade->fee_payer }} pays)</p>
                        <p class="mb-1">@lang('Status'): <strong>{{ $trade->status }}</strong></p>
                        @if($trade->payment_deadline_at && $trade->status === 'pending')
                            <p class="mb-1 text-warning">@lang('Payment window ends'): {{ $trade->payment_deadline_at }}</p>
                        @endif
                        @if($trade->confirmation_deadline_at && $trade->status === 'paid_claimed')
                            <p class="mb-1 text-warning">@lang('Confirmation window ends'): {{ $trade->confirmation_deadline_at }}</p>
                        @endif
                        <div class="alert alert--warning mt-2 mb-0 small">@lang('Never share your password, payment card number, or verification code in chat.')</div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body d-flex flex-wrap gap-2">
                        @if($trade->status === 'pending' && $trade->coin_buyer_type === 'user' && (int)$trade->coin_buyer_id === auth()->id())
                            <form method="post" action="{{ route('user.coin.trade.claim-payment', $trade->id) }}" enctype="multipart/form-data" class="d-flex gap-2 align-items-end">
                                @csrf
                                <input type="file" name="proof" class="form-control">
                                <button type="submit" class="btn btn--gradient">@lang("I have paid")</button>
                            </form>
                        @endif
                        @if($trade->status === 'pending' && $trade->coin_seller_type === 'user' && (int)$trade->coin_seller_id === auth()->id())
                            <form method="post" action="{{ route('user.coin.trade.reopen-listing', $trade->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline--secondary">@lang('Put back on sale')</button>
                            </form>
                        @endif
                        @if($trade->status === 'paid_claimed' && $trade->coin_seller_type === 'user' && (int)$trade->coin_seller_id === auth()->id())
                            <form method="post" action="{{ route('user.coin.trade.confirm-received', $trade->id) }}">
                                @csrf
                                <button type="submit" class="btn btn--gradient">@lang('I received payment - release coins')</button>
                            </form>
                            <form method="post" action="{{ route('user.coin.trade.report-not-received', $trade->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline--danger">@lang('I have not received payment')</button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5>@lang('Chat')</h5>
                        <div id="coinTradeMessages" style="max-height:320px;overflow-y:auto" class="mb-3"></div>
                        <form id="coinTradeChatForm" class="d-flex gap-2">
                            <input type="text" name="body" class="form-control" placeholder="@lang('Write a message...')">
                            <button type="submit" class="btn btn--primary">@lang('Send')</button>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script>
    (function(){
        var tradeId = {{ $trade->id }};
        var baseUrl = '{{ url('/coin-trade/' . $trade->id . '/chat') }}';
        var box = document.getElementById('coinTradeMessages');
        var form = document.getElementById('coinTradeChatForm');

        function render(messages){
            box.innerHTML = messages.map(function(m){
                var att = m.attachment_url ? '<br><a href="'+m.attachment_url+'" target="_blank">attachment</a>' : '';
                return '<div class="mb-2"><strong>'+m.sender_type+' #'+m.sender_id+'</strong>: '+(m.body||'')+att+'</div>';
            }).join('');
            box.scrollTop = box.scrollHeight;
        }

        function poll(){
            fetch(baseUrl, {headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(function(data){
                render(data.messages || []);
            }).catch(function(){}).finally(function(){
                setTimeout(poll, document.hidden ? 7000 : 2500);
            });
        }
        poll();

        form.addEventListener('submit', function(e){
            e.preventDefault();
            var body = form.body.value.trim();
            if (!body) return;
            fetch(baseUrl, {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                body: 'body=' + encodeURIComponent(body)
            }).then(function(){ form.body.value = ''; });
        });
    })();
    </script>
@endsection
