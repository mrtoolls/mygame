<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Coin Trade Disputes · Supervisor</title>
<style>body{font-family:system-ui,sans-serif;max-width:1000px;margin:20px auto;padding:0 16px}.card{border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:16px}button{padding:8px 14px;border-radius:6px;border:1px solid #333;background:#111;color:#fff;cursor:pointer;margin-right:8px}textarea,select,input{margin:4px 0}</style>
</head>
<body>
<h1>Coin Trade Disputes</h1>
@if(session('notify'))@foreach(session('notify') as $n)<p style="color:{{ $n[0]==='error'?'red':'green' }}">{{ $n[1] }}</p>@endforeach@endif

@forelse($disputed as $trade)
    <div class="card">
        <p><strong>Trade #{{ $trade->id }}</strong> &middot; {{ $trade->amount }} coins &middot; seller: {{ $trade->coin_seller_type }}#{{ $trade->coin_seller_id }} &middot; buyer: {{ $trade->coin_buyer_type }}#{{ $trade->coin_buyer_id }}</p>
        <p>Opened by {{ $trade->dispute_opened_by_type }}#{{ $trade->dispute_opened_by_id }} at {{ $trade->dispute_opened_at }}</p>

        <form method="post" action="{{ route('support-agent.coin.disputes.resolve', $trade->id) }}">
            @csrf
            <select name="decision" required>
                <option value="release_to_buyer">Release coins to buyer (payment was made)</option>
                <option value="return_to_seller">Return coins to seller (no payment made)</option>
            </select><br>
            <textarea name="reason" placeholder="Reason (required)" required style="width:100%"></textarea><br>
            <button type="submit">Resolve</button>
        </form>

        <form method="post" action="{{ route('support-agent.coin.disputes.penalize', $trade->id) }}">
            @csrf
            <select name="offender_type" required><option value="user">User</option><option value="agent">Agent</option></select>
            <input type="number" name="offender_id" placeholder="Offender ID" required>
            <select name="tier" required><option value="1">Tier 1 (10%)</option><option value="2">Tier 2 (25%)</option><option value="3">Tier 3 (50%)</option></select><br>
            <textarea name="reason" placeholder="Penalty reason (required)" required style="width:100%"></textarea><br>
            <button type="submit">Issue penalty</button>
        </form>
    </div>
@empty
    <p>No open disputes.</p>
@endforelse
{{ $disputed->links() }}
</body>
</html>
