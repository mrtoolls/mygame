@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <form action="{{ route('admin.news.update', $post->id) }}" class="disableSubmission" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label>@lang('Title')</label>
                                    <input type="text" class="form-control" name="title" value="{{ old('title', $post->title) }}" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>@lang('Badge Label (optional)')</label>
                                    <input type="text" class="form-control" name="badge_label" value="{{ old('badge_label', $post->badge_label) }}" placeholder="@lang('e.g. New Partnership')">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>@lang('Excerpt')</label>
                                    <textarea class="form-control" name="excerpt" rows="2" maxlength="500" placeholder="@lang('Short summary shown on the news list')">{{ old('excerpt', $post->excerpt) }}</textarea>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>@lang('Cover Image')</label>
                                    @if($post->image)
                                        <div class="mb-2"><img src="{{ $post->imageUrl() }}" alt="" style="width:160px;height:90px;border-radius:10px;object-fit:cover"></div>
                                    @endif
                                    <input type="file" class="form-control" name="image" accept=".png,.jpg,.jpeg,.webp">
                                    <small class="text-muted">@lang('Leave empty to keep the current image. Recommended 1200x600')</small>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>@lang('Body')</label>
                                    <textarea class="nicEdit" name="body">{{ old('body', $post->body) }}</textarea>
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
    <x-back route="{{ route('admin.news.index') }}" />
@endpush
