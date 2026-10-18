@extends('admin.layouts.app')

@section('panel')
    <div class="row g-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <h4 class="mb-1">Live support team</h4>
                        <p class="text-muted mb-0">Manage agent profiles, login access, and live chat availability.</p>
                    </div>
                    <a href="{{ route('admin.support.agents.create') }}" class="btn btn--primary"><i class="las la-user-plus"></i> Add agent</a>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table--light mb-0">
                            <thead>
                                <tr><th>Agent</th><th>Login</th><th>Open chats</th><th>Availability</th><th>Account</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                @forelse($agents as $agent)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                @if($agent->photo)
                                                    <img src="{{ asset('assets/images/support-agents/' . $agent->photo) }}" alt="" class="rounded-circle" style="width:44px;height:44px;object-fit:cover">
                                                @else
                                                    <span class="rounded-circle d-grid place-items-center bg--primary text-white" style="width:44px;height:44px">{{ strtoupper(substr($agent->name, 0, 1)) }}</span>
                                                @endif
                                                <span><strong class="d-block">{{ $agent->name }}</strong><small class="text-muted">{{ $agent->title ?: 'Support agent' }}</small></span>
                                            </div>
                                        </td>
                                        <td><span class="d-block">{{ $agent->username }}</span><small class="text-muted">{{ $agent->email }}</small></td>
                                        <td><span class="badge badge--primary">{{ $agent->open_chats_count }}</span></td>
                                        <td>
                                            @if($agent->is_active)
                                                <span class="badge badge--success">Enabled</span>
                                                <small class="d-block text-muted mt-1">{{ $agent->last_seen_at ? 'Seen ' . diffForHumans($agent->last_seen_at) : 'Not signed in yet' }}</small>
                                            @else
                                                <span class="badge badge--danger">Disabled</span>
                                            @endif
                                        </td>
                                        <td>{{ $agent->created_at->format('M j, Y') }}</td>
                                        <td class="text-nowrap">
                                            <a href="{{ route('admin.support.agents.edit', $agent->id) }}" class="btn btn-sm btn-outline--primary"><i class="las la-pen"></i> Edit</a>
                                            <form action="{{ route('admin.support.agents.status', $agent->id) }}" method="post" class="d-inline">@csrf<button class="btn btn-sm btn-outline--warning" type="submit">{{ $agent->is_active ? 'Disable' : 'Enable' }}</button></form>
                                            <button type="button" class="btn btn-sm btn-outline--danger confirmationBtn" data-question="Remove {{ $agent->name }} from the support team? Existing tickets will remain." data-action="{{ route('admin.support.agents.delete', $agent->id) }}"><i class="las la-trash"></i></button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center py-5 text-muted">No support agents yet. Add your first agent to open live chat.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($agents->hasPages())<div class="card-footer">{{ paginateLinks($agents) }}</div>@endif
            </div>
        </div>
    </div>
    <x-confirmation-modal />
@endsection

@push('breadcrumb-plugins')
    <a class="btn btn-sm btn-outline--primary" href="{{ route('admin.ticket.index') }}"><i class="las la-ticket-alt"></i> Support tickets</a>
@endpush
