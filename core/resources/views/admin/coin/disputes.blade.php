@extends('admin.layouts.app')

@section('panel')
    <div class="row g-4">
        <div class="col-12">
            @forelse($disputed as $trade)
                <div class="card mb-3">
                    <div class="card-body">
                        <p><strong>Trade #{{ $trade->id }}</strong> &middot; {{ $trade->amount }} coins &middot; seller: {{ $trade->coin_seller_type }}#{{ $trade->coin_seller_id }} &middot; buyer: {{ $trade->coin_buyer_type }}#{{ $trade->coin_buyer_id }}</p>
                        <p class="text-muted">Opened by {{ $trade->dispute_opened_by_type }}#{{ $trade->dispute_opened_by_id }} at {{ $trade->dispute_opened_at }}</p>

                        <form method="post" action="{{ route('admin.coin.disputes.resolve', $trade->id) }}" class="mb-3">
                            @csrf
                            <select name="decision" class="form-control mb-2" required>
                                <option value="release_to_buyer">Release coins to buyer (payment was made)</option>
                                <option value="return_to_seller">Return coins to seller (no payment made)</option>
                            </select>
                            <textarea name="reason" class="form-control mb-2" placeholder="Reason (required)" required></textarea>
                            <button type="submit" class="btn btn--primary">Resolve</button>
                        </form>

                        <form method="post" action="{{ route('admin.coin.disputes.penalize', $trade->id) }}">
                            @csrf
                            <div class="row g-2 mb-2">
                                <div class="col-auto">
                                    <select name="offender_type" class="form-control" required><option value="user">User</option><option value="agent">Agent</option></select>
                                </div>
                                <div class="col-auto"><input type="number" name="offender_id" class="form-control" placeholder="Offender ID" required></div>
                                <div class="col-auto">
                                    <select name="tier" class="form-control" required><option value="1">Tier 1 (10%)</option><option value="2">Tier 2 (25%)</option><option value="3">Tier 3 (50%)</option></select>
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-auto">
                                    <select name="second_approver_type" class="form-control"><option value="">No second approver</option><option value="admin">Admin</option></select>
                                </div>
                                <div class="col-auto"><input type="number" name="second_approver_id" class="form-control" placeholder="2nd approver admin ID (if above threshold)"></div>
                            </div>
                            <textarea name="reason" class="form-control mb-2" placeholder="Penalty reason (required)" required></textarea>
                            <button type="submit" class="btn btn-outline--danger">Issue penalty</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="card"><div class="card-body text-center text-muted">No open disputes.</div></div>
            @endforelse
            {{ $disputed->links() }}
        </div>
    </div>
@endsection
