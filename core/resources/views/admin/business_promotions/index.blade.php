@extends('admin.layouts.app')
@section('panel')
<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <div class="btn-group flex-wrap" role="group">
        @foreach (['pending' => 'Pending', 'active' => 'Active', 'rejected' => 'Rejected', 'expired' => 'Expired', 'all' => 'All'] as $key => $label)
            <a class="btn btn-sm {{ $status === $key ? 'btn--primary' : 'btn-outline--primary' }}" href="{{ route('admin.business-promotions.index', ['status' => $key]) }}">@lang($label)</a>
        @endforeach
    </div>
    <a href="{{ route('admin.business-promotions.rates') }}" class="btn btn--primary"><i class="las la-tags"></i> @lang('Set promotion prices')</a>
</div>
<div class="alert alert-info">@lang('Users pay the configured price from their wallet when they submit. Campaign time begins only after approval. Rejecting a pending campaign refunds the payment automatically.') </div>
<div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table table--light style--two">
    <thead><tr><th>@lang('Business / User')</th><th>@lang('Campaign')</th><th>@lang('Duration / Paid')</th><th>@lang('Status')</th><th>@lang('Dates')</th><th>@lang('Review')</th></tr></thead>
    <tbody>
    @forelse($promotions as $promotion)
        @php $displayStatus = $promotion->status === 'active' && $promotion->ends_at?->isPast() ? 'expired' : $promotion->status; @endphp
        <tr>
            <td><strong>{{ $promotion->business_name }}</strong><br><small>{{ $promotion->user->username }} · {{ $promotion->user->email }}</small></td>
            <td><strong>{{ $promotion->title }}</strong><br><small>{{ Str::limit($promotion->description, 100) }}</small><br><a href="{{ $promotion->destination_url }}" target="_blank" rel="noopener">@lang('Open destination')</a><br><a href="{{ getImage('assets/images/business-promotions/' . $promotion->image) }}" target="_blank" rel="noopener">@lang('View creative')</a></td>
            <td>{{ __(ucfirst($promotion->duration)) }}<br>{{ $promotion->currency->formatMinor($promotion->amount_minor, true) }}</td>
            <td><span class="badge badge--{{ $displayStatus === 'active' ? 'success' : ($displayStatus === 'pending' ? 'warning' : 'dark') }}">{{ __(ucfirst($displayStatus)) }}</span>@if($promotion->refunded_at)<br><small>@lang('Refunded')</small>@endif</td>
            <td>{{ $promotion->starts_at ? showDateTime($promotion->starts_at, 'd M Y H:i') : '—' }}<br>{{ $promotion->ends_at ? showDateTime($promotion->ends_at, 'd M Y H:i') : '' }}</td>
            <td>
                @if($promotion->status === 'pending')
                    <form method="post" action="{{ route('admin.business-promotions.approve', $promotion->id) }}" class="mb-1">@csrf<button class="btn btn-sm btn-outline--success w-100" type="submit">@lang('Approve & publish')</button></form>
                    <form method="post" action="{{ route('admin.business-promotions.reject', $promotion->id) }}">@csrf<input class="form-control form-control-sm mb-1" name="admin_note" maxlength="1000" placeholder="@lang('Optional reason')"><button class="btn btn-sm btn-outline--danger w-100" type="submit">@lang('Reject & refund')</button></form>
                @else
                    <small>{{ $promotion->admin_note }}</small>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="text-center text-muted py-4">@lang('No business promotion submissions found.')</td></tr>
    @endforelse
    </tbody>
</table></div></div></div>
<div class="mt-3">{{ $promotions->links() }}</div>
@endsection
