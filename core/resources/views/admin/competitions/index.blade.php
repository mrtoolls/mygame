@extends('admin.layouts.app')
@section('panel')
    @php
        $statusColors = [
            'draft' => 'secondary', 'scheduled' => 'info', 'live' => 'success',
            'ended' => 'warning', 'finalized' => 'primary', 'paid' => 'dark',
        ];
        $typeLabels = [
            'wager_race' => 'Wager Race', 'biggest_win' => 'Biggest Win',
            'lucky_draw' => 'Lucky Draw', 'table_league' => 'Table League',
        ];
    @endphp
    @if (!$launchSeedComplete)
        <div class="row">
            <div class="col-lg-12">
                <div class="card mb-3">
                    <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <h6 class="mb-1">@lang('Launch competitions')</h6>
                            <p class="text-muted mb-0">@lang('Create the five November 2026 launch competitions (scheduled, with prizes). Safe to click more than once - it skips any that already exist and never changes existing ones.')</p>
                        </div>
                        <button class="btn btn-outline--success confirmationBtn" data-question="@lang('Create the launch competitions that do not exist yet?')" data-action="{{ route('admin.competitions.seed-launch') }}"><i class="las la-rocket"></i>@lang('Create launch competitions')</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive--sm table-responsive">
                        <table class="table table--light style--two custom-data-table">
                            <thead>
                                <tr>
                                    <th>@lang('Title')</th>
                                    <th>@lang('Type')</th>
                                    <th>@lang('Window')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($competitions as $item)
                                    <tr>
                                        <td class="fw-bold">{{ __($item->title) }}</td>
                                        <td><span class="badge badge--dark">{{ __($typeLabels[$item->type] ?? $item->type) }}</span></td>
                                        <td>{{ showDateTime($item->start_at) }} <i class="las la-arrow-right"></i> {{ showDateTime($item->end_at) }}</td>
                                        <td><span class="badge badge--{{ $statusColors[$item->status] ?? 'secondary' }}">{{ __(ucfirst($item->status)) }}</span></td>
                                        <td>
                                            <div class="button--group">
                                                <a href="{{ route('admin.competitions.results', $item->id) }}" class="btn btn-sm btn-outline--success ms-1"><i class="las la-list-ol"></i>@lang('Results')</a>
                                                <a href="{{ route('admin.competitions.edit', $item->id) }}" class="btn btn-sm btn-outline--primary ms-1"><i class="las la-pen"></i>@lang('Edit')</a>
                                                @if ($item->status === 'ended')
                                                    <button class="btn btn-sm btn-outline--info ms-1 confirmationBtn" data-question="@lang('Finalize this competition now? This runs the ranking immediately if the grace period has passed.')" data-action="{{ route('admin.competitions.finalize-now', $item->id) }}">
                                                        <i class="las la-flag-checkered"></i>@lang('Finalize')
                                                    </button>
                                                @endif
                                                @if (in_array($item->status, ['draft', 'scheduled']))
                                                    <button class="btn btn-sm btn-outline--danger ms-1 confirmationBtn" data-question="@lang('Are you sure to delete this competition?')" data-action="{{ route('admin.competitions.destroy', $item->id) }}">
                                                        <i class="la la-trash"></i>@lang('Delete')
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __('No competitions added yet') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            {{ paginateLinks($competitions) }}
        </div>
    </div>
    <x-confirmation-modal />
@endsection

@push('breadcrumb-plugins')
    <a class="btn btn-outline--primary" href="{{ route('admin.competitions.flags') }}"><i class="las la-flag"></i>@lang('Flags')</a>
    <a class="btn btn-outline--primary" href="{{ route('admin.competitions.create') }}"><i class="las la-plus"></i>@lang('Add New')</a>
@endpush
