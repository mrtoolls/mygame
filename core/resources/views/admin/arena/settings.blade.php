@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <form method="post" action="{{ route('admin.arena.settings.update') }}">
                @csrf
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="form-group">
                                <label>@lang('Require moderator approval before posts go live')</label>
                                <input type="checkbox" data-width="100%" data-height="50" data-onstyle="-success" data-offstyle="-danger" data-bs-toggle="toggle" data-on="@lang('Required')" data-off="@lang('Not required')" @if(@gs('arena_post_approval_required')) checked @endif name="status">
                                <small class="text-muted d-block mt-2">@lang('When on, new Arena posts stay hidden from the public feed until an admin or a moderator-flagged support agent approves them.')</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn--primary w-100 h-45">@lang('Submit')</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
