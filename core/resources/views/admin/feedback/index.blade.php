@extends('admin.layouts.app')
@section('panel')
<div class="row"><div class="col-12"><div class="card"><div class="card-body">
    <h4 class="mb-3">Feedback moderation</h4>
    <div class="btn-group mb-4">@foreach(['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $key=>$label)<a class="btn btn-sm {{ $status===$key?'btn--primary':'btn--dark' }}" href="{{ route('admin.feedback.index',['status'=>$key]) }}">{{ __($label) }}</a>@endforeach</div>
    @forelse($feedbacks as $feedback)
        <article class="card mb-3"><div class="card-body"><div class="d-flex justify-content-between flex-wrap gap-2"><div><strong>{{ $feedback->display_name }}</strong> · {{ $feedback->player_since ? __('Player since :year',['year'=>$feedback->player_since]) : __('Player') }} · {{ $feedback->created_at->format('Y-m-d H:i') }}<div class="text-warning">{{ str_repeat('★',(int)$feedback->rating) }}{{ str_repeat('☆',5-(int)$feedback->rating) }}</div></div><span class="badge badge--{{ $feedback->status==='approved'?'success':($feedback->status==='pending'?'warning':'danger') }}">{{ ucfirst($feedback->status) }}</span></div><p class="mt-3">{{ $feedback->message }}</p>@if($feedback->image)<a href="{{ getImage('assets/images/user-feedback/'.$feedback->image) }}" target="_blank"><img src="{{ getImage('assets/images/user-feedback/'.$feedback->image) }}" alt="Feedback attachment" style="max-width:180px;max-height:140px;border-radius:12px"></a>@endif
        @if($feedback->status==='pending')<div class="d-flex gap-2 mt-3"><form method="post" action="{{ route('admin.feedback.approve',$feedback->id) }}">@csrf<button class="btn btn--success btn-sm">Approve & publish</button></form><form method="post" action="{{ route('admin.feedback.reject',$feedback->id) }}" class="d-flex gap-2">@csrf<input name="admin_note" class="form-control form-control-sm" placeholder="Optional note"><button class="btn btn--danger btn-sm">Reject</button></form></div>@endif
        </div></article>
    @empty<div class="text-center py-5">No {{ $status }} feedback.</div>@endforelse
    {{ $feedbacks->links() }}
</div></div></div></div>
@endsection
