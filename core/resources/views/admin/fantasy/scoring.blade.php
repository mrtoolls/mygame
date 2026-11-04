@extends('admin.layouts.app')

@section('panel')
<div class="card">
    <form action="{{ route('admin.fantasy.scoring.update') }}" method="POST" class="disableSubmission">
        @csrf
        <div class="card-body">
            <div class="row">
                @foreach ($rules as $rule)
                    <div class="col-md-4 mb-3">
                        <label>{{ ucfirst(str_replace('_', ' ', $rule->key)) }}</label>
                        <input type="number" step="0.01" class="form-control" name="values[{{ $rule->key }}]" value="{{ $rule->value }}">
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn--primary w-100">@lang('Save Scoring Rules')</button>
        </div>
    </form>
</div>
@endsection

@push('breadcrumb-plugins')
<x-back route="{{ route('admin.fantasy.index') }}" />
@endpush
