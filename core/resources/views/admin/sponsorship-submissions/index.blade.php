@extends('admin.layouts.app')
@section('panel')
<div class="row"><div class="col-12"><div class="card"><div class="card-body">
    <h4 class="mb-3">Sponsorship submissions</h4>
    <div class="btn-group mb-4">@foreach(['all'=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $key=>$label)<a class="btn btn-sm {{ $status===$key?'btn--primary':'btn--dark' }}" href="{{ route('admin.sponsorship-submissions.index',['status'=>$key]) }}">{{ __($label) }}</a>@endforeach</div>
    <div class="table-responsive--sm table-responsive">
        <table class="table table--light style--two custom-data-table">
            <thead>
                <tr>
                    <th>@lang('Submitted by')</th>
                    <th>@lang('Brand')</th>
                    <th>@lang('Contact')</th>
                    <th>@lang('Date')</th>
                    <th>@lang('Status')</th>
                    <th>@lang('Action')</th>
                </tr>
            </thead>
            <tbody>
                @forelse($submissions as $submission)
                    <tr>
                        <td>
                            {{ $submission->full_name }}
                            @if($submission->is_anonymous)<span class="badge badge--dark">@lang('Anonymous')</span>@endif
                            @if($submission->user)<br><small class="text-muted">{{ $submission->user->username }}</small>@endif
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                @if($submission->logo)<img src="{{ getImage('assets/images/sponsorship-logos/'.$submission->logo) }}" alt="" style="width:32px;height:32px;border-radius:8px;object-fit:cover">@endif
                                <span>{{ $submission->brand_name ?: '-' }}</span>
                            </div>
                        </td>
                        <td><small>{{ $submission->phone }}</small><br><small>{{ $submission->email }}</small></td>
                        <td><small>{{ $submission->created_at->format('Y-m-d H:i') }}</small></td>
                        <td><span class="badge badge--{{ $submission->status==='approved'?'success':($submission->status==='pending'?'warning':'danger') }}">{{ ucfirst($submission->status) }}</span></td>
                        <td>
                            <div class="button--group">
                                <a href="{{ route('admin.sponsorship-submissions.show',$submission->id) }}" class="btn btn-sm btn-outline--primary"><i class="las la-eye"></i> View</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="100%" class="text-center text-muted py-4">No {{ $status }} sponsorship submissions.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $submissions->links() }}
</div></div></div></div>
@endsection
