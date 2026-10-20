@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <h4 class="mb-1">{{ $submission->brand_name ?: $submission->full_name }}</h4>
                        <small class="text-muted">@lang('Submitted') {{ $submission->created_at->format('Y-m-d H:i') }}</small>
                    </div>
                    <span class="badge badge--{{ $submission->status==='approved'?'success':($submission->status==='pending'?'warning':'danger') }}">{{ ucfirst($submission->status) }}</span>
                </div>

                @if($submission->logo)
                    <img src="{{ getImage('assets/images/sponsorship-logos/'.$submission->logo) }}" alt="" style="max-width:160px;max-height:120px;border-radius:12px" class="mb-3">
                @endif

                <h6 class="mb-2">@lang('Proposal')</h6>
                <p style="white-space:pre-line">{{ $submission->message }}</p>

                @if($submission->admin_note)
                    <div class="alert alert--warning mt-3"><strong>@lang('Admin note'):</strong> {{ $submission->admin_note }}</div>
                @endif

                <div class="d-flex flex-wrap gap-2 mt-4">
                    @if($submission->status!=='approved')
                        <form method="post" action="{{ route('admin.sponsorship-submissions.approve',$submission->id) }}">@csrf<button class="btn btn--success btn-sm"><i class="las la-check"></i> @lang('Mark Approved')</button></form>
                    @endif
                    @if($submission->status!=='rejected')
                        <form method="post" action="{{ route('admin.sponsorship-submissions.reject',$submission->id) }}" class="d-flex gap-2">@csrf<input name="admin_note" class="form-control form-control-sm" placeholder="@lang('Optional note')"><button class="btn btn--warning btn-sm"><i class="las la-times"></i> @lang('Reject')</button></form>
                    @endif
                    <button class="btn btn-outline--danger btn-sm confirmationBtn" data-question="@lang('Permanently delete this submission?')" data-action="{{ route('admin.sponsorship-submissions.destroy',$submission->id) }}"><i class="las la-trash"></i> @lang('Delete')</button>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h6 class="mb-3">@lang('Contact details')</h6>
                <p class="mb-2"><i class="las la-user"></i> {{ $submission->full_name }} @if($submission->is_anonymous)<span class="badge badge--dark">@lang('Wants to stay anonymous')</span>@endif</p>
                <p class="mb-2"><i class="las la-phone"></i> <a href="tel:{{ $submission->phone }}">{{ $submission->phone }}</a></p>
                <p class="mb-2"><i class="las la-envelope"></i> <a href="mailto:{{ $submission->email }}">{{ $submission->email }}</a></p>
                @if($submission->brand_name)<p class="mb-2"><i class="las la-building"></i> {{ $submission->brand_name }}</p>@endif
                @if($submission->user)
                    <hr>
                    <small class="text-muted">@lang('Winxpot account'): {{ $submission->user->username }} ({{ $submission->user->email }})</small>
                @endif
                @if($submission->moderator)
                    <hr>
                    <small class="text-muted">@lang('Last moderated by'): {{ $submission->moderator->username ?? $submission->moderator->name }}</small>
                @endif
            </div>
        </div>
    </div>
</div>
<x-confirmation-modal />
@endsection

@push('breadcrumb-plugins')
    <x-back route="{{ route('admin.sponsorship-submissions.index') }}" />
@endpush
