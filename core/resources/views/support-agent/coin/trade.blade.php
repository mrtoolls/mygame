<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Trade #{{ $trade->id }} · Agent</title>
<style>body{font-family:system-ui,sans-serif;max-width:900px;margin:20px auto;padding:0 16px}.card{border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:16px}button{padding:8px 14px;border-radius:6px;border:1px solid #333;background:#111;color:#fff;cursor:pointer;margin-right:8px}#msgs{max-height:300px;overflow-y:auto;border:1px solid #eee;padding:8px;margin-bottom:8px}</style>
</head>
<body>
<h1>Trade #{{ $trade->id }}</h1>
@if(session('notify'))@foreach(session('notify') as $n)<p style="color:{{ $n[0]==='error'?'red':'green' }}">{{ $n[1] }}</p>@endforeach@endif

<div class="card">
    <p>Amount: <strong>{{ $trade->amount }}</strong> coins &middot; Fee: {{ $trade->fee_amount }} ({{ $trade->fee_payer }} pays)</p>
    <p>Status: <strong>{{ $trade->status }}</strong></p>
    <p style="background:#fff3cd;padding:8px;border-radius:6px">Never share passwords, card numbers, or verification codes in chat.</p>
</div>

<div class="card">
    @php($agentId = auth('support_agent')->id())
    @if($trade->status === 'pending' && $trade->coin_buyer_type === 'agent' && (int)$trade->coin_buyer_id === $agentId)
        <form method="post" action="{{ route('support-agent.coin.trade.claim-payment', $trade->id) }}" enctype="multipart/form-data">
            @csrf
            <input type="file" name="proof">
            <button type="submit">I have paid</button>
        </form>
    @endif
    @if($trade->status === 'paid_claimed' && $trade->coin_seller_type === 'agent' && (int)$trade->coin_seller_id === $agentId)
        <form method="post" action="{{ route('support-agent.coin.trade.confirm-received', $trade->id) }}" style="display:inline">
            @csrf
            <button type="submit">I received payment - release coins</button>
        </form>
        <form method="post" action="{{ route('support-agent.coin.trade.report-not-received', $trade->id) }}" style="display:inline">
            @csrf
            <button type="submit">I have not received payment</button>
        </form>
    @endif
</div>

<div class="card">
    <h3>Chat</h3>
    <div id="msgs"></div>
    <form id="chatForm"><input type="text" name="body" placeholder="Write a message..." style="width:70%"><button type="submit">Send</button></form>
</div>

<script>
(function(){
    var baseUrl = '{{ url('/coin-trade/' . $trade->id . '/chat') }}';
    var box = document.getElementById('msgs');
    var form = document.getElementById('chatForm');
    function render(messages){
        box.innerHTML = messages.map(function(m){
            var att = m.attachment_url ? '<br><a href="'+m.attachment_url+'" target="_blank">attachment</a>' : '';
            return '<div>'+'<strong>'+m.sender_type+' #'+m.sender_id+'</strong>: '+(m.body||'')+att+'</div>';
        }).join('');
        box.scrollTop = box.scrollHeight;
    }
    function poll(){
        fetch(baseUrl).then(r=>r.json()).then(d=>render(d.messages||[])).catch(()=>{}).finally(()=>setTimeout(poll, document.hidden?7000:2500));
    }
    poll();
    form.addEventListener('submit', function(e){
        e.preventDefault();
        var body = form.body.value.trim();
        if (!body) return;
        fetch(baseUrl, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-TOKEN':'{{ csrf_token() }}'}, body:'body='+encodeURIComponent(body)}).then(()=>{form.body.value='';});
    });
})();
</script>
</body>
</html>
