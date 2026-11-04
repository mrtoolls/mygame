@extends('admin.layouts.app')

@section('panel')
<div class="card">
    <div class="card-body table-responsive">
        <table class="table">
            <thead><tr><th>@lang('When')</th><th>@lang('Admin')</th><th>@lang('Action')</th><th>@lang('Subject')</th><th>@lang('Details')</th></tr></thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td>{{ $entry->created_at }}</td>
                        <td>{{ $entry->admin_id ?? '-' }}</td>
                        <td>{{ str_replace('_', ' ', $entry->action) }}</td>
                        <td>{{ $entry->subject_type ? class_basename($entry->subject_type) . ' #' . $entry->subject_id : '-' }}</td>
                        <td><code>{{ $entry->payload ? json_encode($entry->payload) : '-' }}</code></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center">@lang('No audit entries yet.')</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $entries->links() }}
    </div>
</div>
@endsection

@push('breadcrumb-plugins')
<x-back route="{{ route('admin.fantasy.index') }}" />
@endpush
