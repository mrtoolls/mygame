@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive--sm table-responsive">
                        <table class="table table--light style--two custom-data-table">
                            <thead>
                                <tr>
                                    <th>@lang('Competition')</th>
                                    <th>@lang('Player')</th>
                                    <th>@lang('Related')</th>
                                    <th>@lang('Reason')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($flags as $flag)
                                    <tr>
                                        <td>{{ $flag->competition->title ?? '-' }}</td>
                                        <td>{{ $flag->user->username ?? 'Deleted user' }}</td>
                                        <td>{{ $flag->relatedUser->username ?? '-' }}</td>
                                        <td>{{ $flag->reason }}</td>
                                        <td>
                                            @if ($flag->reviewed_at)
                                                <span class="badge badge--success">@lang('Reviewed')</span>
                                                <div class="text-muted small">{{ showDateTime($flag->reviewed_at) }}</div>
                                            @else
                                                <span class="badge badge--warning">@lang('Pending')</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if (!$flag->reviewed_at)
                                                <button class="btn btn-sm btn-outline--success confirmationBtn" data-question="@lang('Mark this flag as reviewed?')" data-action="{{ route('admin.competitions.flags.review', $flag->id) }}">
                                                    <i class="las la-check"></i>@lang('Mark Reviewed')
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __('No flags raised yet') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            {{ paginateLinks($flags) }}
        </div>
    </div>
    <x-confirmation-modal />
@endsection

@push('breadcrumb-plugins')
    <x-back route="{{ route('admin.competitions.index') }}" />
@endpush
