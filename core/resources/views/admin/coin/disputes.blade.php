@extends('admin.layouts.app')

@section('panel')
    <div class="row g-4">
        <div class="col-12">
            <a href="{{ route('admin.coin.settings') }}" class="btn btn-sm btn-outline--primary"><i class="las la-arrow-left"></i> Coin Settings</a>
        </div>

        <div class="col-12">
            @forelse($disputed as $trade)
                <div class="card mb-3">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <h5 class="mb-0"><i class="las la-gavel me-2"></i>Trade #{{ $trade->id }} &middot; {{ number_format($trade->amount, 0) }} coins</h5>
                        <span class="badge badge--danger">Disputed</span>
                    </div>
                    <div class="card-body">
                        @php($sellerParty = $trade->coin_seller_type === 'user' && in_array((int)$trade->coin_seller_id,$vendorUserIds,true) ? 'Vendor' : ucfirst($trade->coin_seller_type))
                        @php($buyerParty = $trade->coin_buyer_type === 'user' && in_array((int)$trade->coin_buyer_id,$vendorUserIds,true) ? 'Vendor' : ucfirst($trade->coin_buyer_type))
                        <p class="text-muted small mb-3">
                            <i class="las la-arrow-circle-up text-warning"></i> Seller: <strong>{{ $sellerParty }}#{{ $trade->coin_seller_id }}</strong>
                            &nbsp; <i class="las la-arrow-circle-down text-success"></i> Buyer: <strong>{{ $buyerParty }}#{{ $trade->coin_buyer_id }}</strong>
                            &nbsp; · Opened by {{ $trade->dispute_opened_by_type }}#{{ $trade->dispute_opened_by_id }} at {{ $trade->dispute_opened_at }}
                        </p>

                        <form method="post" action="{{ route('admin.coin.disputes.resolve', $trade->id) }}" class="row g-2 mb-3 align-items-end">
                            @csrf
                            <div class="col-md-4">
                                <label class="form-label">Decision</label>
                                <select name="decision" class="form-control" required>
                                    <option value="release_to_buyer">Release coins to buyer (payment was made)</option>
                                    <option value="return_to_seller">Return coins to seller (no payment made)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Reason</label>
                                <textarea name="reason" class="form-control" rows="1" required placeholder="Required"></textarea>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn--primary w-100"><i class="las la-gavel"></i> Resolve</button>
                            </div>
                        </form>

                        <hr class="my-3">

                        <form method="post" action="{{ route('admin.coin.disputes.penalize', $trade->id) }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-md-2">
                                <label class="form-label">Offender</label>
                                <select name="offender_type" class="form-control" required><option value="user">User</option><option value="agent">Agent</option></select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Offender ID</label>
                                <input type="number" name="offender_id" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tier</label>
                                <select name="tier" class="form-control" required>
                                    <option value="1">Tier 1 — 10%</option>
                                    <option value="2">Tier 2 — 25%</option>
                                    <option value="3">Tier 3 — 50%</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">2nd Approver (admin ID)</label>
                                <input type="number" name="second_approver_id" class="form-control" placeholder="If above threshold">
                                <input type="hidden" name="second_approver_type" value="admin">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Reason</label>
                                <input type="text" name="reason" class="form-control" required placeholder="Required">
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-outline--danger w-100"><i class="las la-exclamation-triangle"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            @empty
                <div class="card">
                    <div class="card-body text-center text-muted py-5">
                        <i class="las la-grin-stars fs-1 d-block mb-2 text-success"></i>
                        No open disputes.
                    </div>
                </div>
            @endforelse
            <div class="mt-3">{{ $disputed->links() }}</div>
        </div>
    </div>
@endsection
