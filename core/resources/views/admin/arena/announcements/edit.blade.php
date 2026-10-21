@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <form action="{{ route('admin.arena.announcements.update', $item->id) }}" class="disableSubmission" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label>@lang('Title')</label>
                                    <input type="text" class="form-control" name="title" value="{{ old('title', $item->title) }}" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>@lang('Badge Label (optional)')</label>
                                    <input type="text" class="form-control" name="badge_label" value="{{ old('badge_label', $item->badge_label) }}" placeholder="@lang('e.g. Tournament')">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>@lang('Description')</label>
                                    <textarea class="form-control" name="description" rows="2" maxlength="500">{{ old('description', $item->description) }}</textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Reward Label (optional)')</label>
                                    <input type="text" class="form-control" name="reward_label" value="{{ old('reward_label', $item->reward_label) }}" placeholder="@lang('e.g. $500 prize pool')">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Ends At (optional, drives the countdown)')</label>
                                    <input type="datetime-local" class="form-control" name="ends_at" value="{{ old('ends_at', $item->ends_at?->format('Y-m-d\TH:i')) }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Button Label (optional)')</label>
                                    <input type="text" class="form-control" name="button_label" value="{{ old('button_label', $item->button_label) }}" placeholder="@lang('e.g. Join now')">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Button URL (optional)')</label>
                                    <input type="text" class="form-control" name="button_url" value="{{ old('button_url', $item->button_url) }}" placeholder="https://">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>@lang('Banner Image (optional)')</label>
                                    @if($item->image)
                                        <div class="mb-2"><img src="{{ $item->imageUrl() }}" alt="" style="width:220px;height:74px;border-radius:10px;object-fit:cover"></div>
                                    @endif
                                    <input type="file" class="form-control" name="image" accept=".png,.jpg,.jpeg,.webp">
                                    <small class="text-muted">@lang('Leave empty to keep the current image. Recommended 1200x400')</small>
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

@push('breadcrumb-plugins')
    <x-back route="{{ route('admin.arena.announcements.index') }}" />
@endpush
