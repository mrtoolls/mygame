@extends('admin.layouts.app')
@section('panel')
<div class="row"><div class="col-12"><div class="card"><div class="card-body">
    <h4 class="mb-3">Feedback moderation</h4>
    <div class="btn-group mb-4">@foreach(['all'=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Hidden/Rejected'] as $key=>$label)<a class="btn btn-sm {{ $status===$key?'btn--primary':'btn--dark' }}" href="{{ route('admin.feedback.index',['status'=>$key]) }}">{{ __($label) }}</a>@endforeach</div>
    @forelse($feedbacks as $feedback)
        <article class="card mb-3"><div class="card-body">
            <div class="d-flex justify-content-between flex-wrap gap-2">
                <div>
                    <strong>{{ $feedback->display_name }}</strong>
                    @if($feedback->user)<small class="text-muted">({{ $feedback->user->username }} · {{ $feedback->user->email }})</small>@else<small class="text-muted">(user deleted)</small>@endif
                    · {{ $feedback->player_since ? __('Player since :year',['year'=>$feedback->player_since]) : __('Player') }} · {{ $feedback->created_at->format('Y-m-d H:i') }}
                    <div class="text-warning">{{ str_repeat('★',(int)$feedback->rating) }}{{ str_repeat('☆',5-(int)$feedback->rating) }}</div>
                </div>
                <span class="badge badge--{{ $feedback->status==='approved'?'success':($feedback->status==='pending'?'warning':'danger') }}">{{ ucfirst($feedback->status) }}</span>
            </div>
            <p class="mt-3" id="feedback-view-{{ $feedback->id }}">{{ $feedback->message }}</p>
            @if($feedback->image)<a href="{{ getImage('assets/images/user-feedback/'.$feedback->image) }}" target="_blank"><img src="{{ getImage('assets/images/user-feedback/'.$feedback->image) }}" alt="Feedback attachment" style="max-width:180px;max-height:140px;border-radius:12px"></a>@endif

            <form method="post" action="{{ route('admin.feedback.update',$feedback->id) }}" id="feedback-edit-{{ $feedback->id }}" class="d-none mt-3">
                @csrf
                <div class="row g-2">
                    <div class="col-sm-6"><label class="form-label">Display name</label><input name="display_name" class="form-control form-control-sm" value="{{ $feedback->display_name }}" required></div>
                    <div class="col-sm-3"><label class="form-label">Player since</label><input name="player_since" type="number" class="form-control form-control-sm" value="{{ $feedback->player_since }}"></div>
                    <div class="col-sm-3"><label class="form-label">Rating</label><select name="rating" class="form-control form-control-sm">@for($r=1;$r<=5;$r++)<option value="{{ $r }}" @selected($feedback->rating==$r)>{{ $r }} star{{ $r>1?'s':'' }}</option>@endfor</select></div>
                    <div class="col-12"><label class="form-label">Message</label><textarea name="message" class="form-control form-control-sm" rows="3" required>{{ $feedback->message }}</textarea></div>
                </div>
                <div class="mt-2 d-flex gap-2"><button class="btn btn--primary btn-sm">Save changes</button><button type="button" class="btn btn--dark btn-sm" onclick="document.getElementById('feedback-edit-{{ $feedback->id }}').classList.add('d-none');document.getElementById('feedback-view-{{ $feedback->id }}').classList.remove('d-none')">Cancel</button></div>
            </form>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="button" class="btn btn-outline--primary btn-sm" onclick="document.getElementById('feedback-view-{{ $feedback->id }}').classList.add('d-none');document.getElementById('feedback-edit-{{ $feedback->id }}').classList.remove('d-none')"><i class="las la-pen"></i> Edit</button>
                @if($feedback->status!=='approved')
                    <form method="post" action="{{ route('admin.feedback.approve',$feedback->id) }}">@csrf<button class="btn btn--success btn-sm"><i class="las la-eye"></i> {{ $feedback->status==='pending'?'Approve & publish':'Show' }}</button></form>
                @endif
                @if($feedback->status!=='rejected')
                    <form method="post" action="{{ route('admin.feedback.reject',$feedback->id) }}" class="d-flex gap-2">@csrf<input name="admin_note" class="form-control form-control-sm" placeholder="Optional note"><button class="btn btn--warning btn-sm"><i class="las la-eye-slash"></i> {{ $feedback->status==='pending'?'Reject':'Hide' }}</button></form>
                @endif
                <button class="btn btn-outline--danger btn-sm confirmationBtn" data-question="Permanently delete this feedback?" data-action="{{ route('admin.feedback.destroy',$feedback->id) }}"><i class="las la-trash"></i> Delete</button>
            </div>
        </div></article>
    @empty<div class="text-center py-5">No {{ $status }} feedback.</div>@endforelse
    {{ $feedbacks->links() }}
</div></div></div></div>
<x-confirmation-modal />
@endsection
