@extends('admin.layouts.app')

@section('panel')
    <div class="row g-4">
        <div class="col-12">
            <a href="{{ route('admin.coin.settings') }}" class="btn btn-sm btn-outline--primary"><i class="las la-arrow-left"></i> Coin Settings</a>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="las la-hand-holding-usd me-2"></i>Request a Float Grant</h5></div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.coin.float.store') }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-3">
                            <label class="form-label">Agent</label>
                            <select name="support_agent_id" class="form-control" required>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->name }} ({{ $agent->username }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Amount (coins)</label>
                            <input type="number" step="1" min="1" name="amount" class="form-control" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Reason (required)</label>
                            <input type="text" name="reason" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn--primary w-100"><i class="las la-paper-plane"></i> Request</button>
                        </div>
                    </form>
                    <p class="text-muted small mt-2 mb-0"><i class="las la-info-circle"></i> A different admin must approve this (unless single-approver mode is on).</p>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="las la-list me-2"></i>Grant History</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table--light mb-0">
                            <thead><tr><th>Agent</th><th>Amount</th><th>Reason</th><th>Requested By</th><th>Status</th><th>Approved By</th><th>Actions</th></tr></thead>
                            <tbody>
                                @forelse($grants as $grant)
                                    <tr>
                                        <td>{{ $grant->agent->name ?? '-' }}</td>
                                        <td><span class="fw-bold">{{ number_format($grant->amount, 0) }}</span></td>
                                        <td class="text-muted small">{{ $grant->reason }}</td>
                                        <td>{{ $grant->requestedBy->username ?? '-' }}</td>
                                        <td><span class="badge badge--{{ $grant->status === 'approved' ? 'success' : ($grant->status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($grant->status) }}</span></td>
                                        <td>{{ $grant->approvedBy->username ?? '-' }}</td>
                                        <td>
                                            @if($grant->status === 'pending')
                                                <form method="post" action="{{ route('admin.coin.float.approve', $grant->id) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn--success"><i class="las la-check"></i> Approve</button>
                                                </form>
                                                <form method="post" action="{{ route('admin.coin.float.reject', $grant->id) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline--danger"><i class="las la-times"></i> Reject</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center text-muted py-4">No float grants yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="mt-3">{{ $grants->links() }}</div>
        </div>
    </div>
@endsection
