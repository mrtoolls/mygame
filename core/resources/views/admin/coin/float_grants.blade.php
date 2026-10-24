@extends('admin.layouts.app')

@section('panel')
    <div class="row g-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-3">Request a float grant</h4>
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
                            <button type="submit" class="btn btn--primary w-100">Request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table--light mb-0">
                            <thead><tr><th>Agent</th><th>Amount</th><th>Reason</th><th>Requested by</th><th>Status</th><th>Approved by</th><th>Actions</th></tr></thead>
                            <tbody>
                                @forelse($grants as $grant)
                                    <tr>
                                        <td>{{ $grant->agent->name ?? '-' }}</td>
                                        <td>{{ $grant->amount }}</td>
                                        <td>{{ $grant->reason }}</td>
                                        <td>{{ $grant->requestedBy->username ?? '-' }}</td>
                                        <td><span class="badge badge--{{ $grant->status === 'approved' ? 'success' : ($grant->status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($grant->status) }}</span></td>
                                        <td>{{ $grant->approvedBy->username ?? '-' }}</td>
                                        <td>
                                            @if($grant->status === 'pending')
                                                <form method="post" action="{{ route('admin.coin.float.approve', $grant->id) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn--success">Approve</button>
                                                </form>
                                                <form method="post" action="{{ route('admin.coin.float.reject', $grant->id) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline--danger">Reject</button>
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
            {{ $grants->links() }}
        </div>
    </div>
@endsection
