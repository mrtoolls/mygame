<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Coin Marketplace · Agent</title>
<style>body{font-family:system-ui,sans-serif;max-width:900px;margin:20px auto;padding:0 16px}table{width:100%;border-collapse:collapse;margin:12px 0}td,th{border:1px solid #ddd;padding:8px;text-align:left}.card{border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:16px}button{padding:8px 14px;border-radius:6px;border:1px solid #333;background:#111;color:#fff;cursor:pointer}</style>
</head>
<body>
<h1>Coin Marketplace</h1>
@foreach(($errors->any() ? [$errors->first()] : []) as $e)<p style="color:red">{{ $e }}</p>@endforeach
@if(session('notify'))@foreach(session('notify') as $n)<p style="color:{{ $n[0]==='error'?'red':'green' }}">{{ $n[1] }}</p>@endforeach@endif

<div class="card">
    <p>Available: <strong>{{ $wallet->available_balance }}</strong> &middot; Held: <strong>{{ $wallet->held_balance }}</strong></p>
    <form method="post" action="{{ route('support-agent.coin.selling.toggle') }}">
        @csrf
        <button type="submit">{{ $agent->is_selling_coins ? 'Stop selling coins' : 'Start selling coins' }}</button>
    </form>
</div>

<div class="card">
    <h3>Open listings (sell to you)</h3>
    <table>
        <thead><tr><th>Seller</th><th>Amount</th><th></th></tr></thead>
        <tbody>
            @forelse($listings as $listing)
                <tr>
                    <td>{{ $listing->seller->username ?? $listing->seller_user_id }}</td>
                    <td>{{ $listing->amount }}</td>
                    <td>
                        <form method="post" action="{{ route('support-agent.coin.listings.claim', $listing->id) }}">
                            @csrf
                            <button type="submit">Buy</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3">No listings right now.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $listings->links() }}
</div>

<div class="card">
    <h3>Your trades</h3>
    <table>
        <thead><tr><th>#</th><th>Amount</th><th>Status</th><th></th></tr></thead>
        <tbody>
            @forelse($myTrades as $trade)
                <tr>
                    <td>{{ $trade->id }}</td>
                    <td>{{ $trade->amount }}</td>
                    <td>{{ $trade->status }}</td>
                    <td><a href="{{ route('support-agent.coin.trade', $trade->id) }}">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="4">No trades yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $myTrades->links() }}
</div>
<p><a href="{{ route('support-agent.coin.disputes.index') }}">Dispute queue (supervisors)</a></p>
</body>
</html>
