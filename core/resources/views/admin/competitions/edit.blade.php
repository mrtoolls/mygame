@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <form action="{{ route('admin.competitions.update', $competition->id) }}" class="disableSubmission" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="card-body">
                        @include('admin.competitions._form')
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
    <x-back route="{{ route('admin.competitions.index') }}" />
@endpush
