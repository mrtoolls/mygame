@extends('admin.layouts.app')

@section('panel')
<div class="card">
    <div class="card-body table-responsive">
        <table class="table">
            <thead><tr><th>@lang('Club')</th><th>@lang('Code')</th><th>@lang('Colors')</th><th>@lang('Pattern')</th><th>@lang('Strength')</th><th>@lang('Players')</th></tr></thead>
            <tbody>
                @forelse ($clubs as $club)
                    <tr>
                        <td>{{ $club->name }}</td>
                        <td>{{ $club->short_code }}</td>
                        <td><span style="display:inline-block;width:16px;height:16px;background:{{ $club->color_primary }};border-radius:3px;"></span> <span style="display:inline-block;width:16px;height:16px;background:{{ $club->color_secondary }};border-radius:3px;"></span></td>
                        <td>{{ ucfirst($club->pattern) }}</td>
                        <td>{{ $club->strength_rating }}</td>
                        <td>{{ $club->players_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center">@lang('No clubs yet - generate a league first.')</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $clubs->links() }}
    </div>
</div>
@endsection

@push('breadcrumb-plugins')
<x-back route="{{ route('admin.fantasy.index') }}" />
@endpush
