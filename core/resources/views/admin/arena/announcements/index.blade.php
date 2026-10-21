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
                                    <th>@lang('Banner')</th>
                                    <th>@lang('Title')</th>
                                    <th>@lang('Ends At')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($items as $item)
                                    <tr>
                                        <td>
                                            @if($item->image)
                                                <img src="{{ $item->imageUrl() }}" alt="" style="width:64px;height:40px;border-radius:8px;object-fit:cover">
                                            @endif
                                        </td>
                                        <td class="fw-bold">
                                            {{ __($item->title) }}
                                            @if($item->badge_label)<br><span class="badge badge--dark">{{ __($item->badge_label) }}</span>@endif
                                        </td>
                                        <td>{{ $item->ends_at ? showDateTime($item->ends_at) : '-' }}</td>
                                        <td>@php echo $item->statusBadge @endphp</td>
                                        <td>
                                            <div class="button--group">
                                                <a href="{{ route('admin.arena.announcements.edit', $item->id) }}" class="btn btn-sm btn-outline--primary ms-1"><i class="las la-pen"></i>@lang('Edit')</a>
                                                @if ($item->status == Status::ENABLE)
                                                    <button class="btn btn-sm btn-outline--danger ms-1 confirmationBtn" data-question="@lang('Are you sure to disable this announcement?')" data-action="{{ route('admin.arena.announcements.status', $item->id) }}">
                                                        <i class="la la-eye-slash"></i>@lang('Disable')
                                                    </button>
                                                @else
                                                    <button class="btn btn-sm btn-outline--success ms-1 confirmationBtn" data-question="@lang('Are you sure to enable this announcement?')" data-action="{{ route('admin.arena.announcements.status', $item->id) }}">
                                                        <i class="la la-eye"></i>@lang('Enable')
                                                    </button>
                                                @endif
                                                <button class="btn btn-sm btn-outline--danger ms-1 confirmationBtn" data-question="@lang('Are you sure to delete this announcement?')" data-action="{{ route('admin.arena.announcements.destroy', $item->id) }}">
                                                    <i class="la la-trash"></i>@lang('Delete')
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __('No announcements added yet') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            {{ paginateLinks($items) }}
        </div>
    </div>
    <x-confirmation-modal />
@endsection

@push('breadcrumb-plugins')
    <a class="btn btn-outline--primary" href="{{ route('admin.arena.announcements.create') }}"><i class="las la-plus"></i>@lang('Add New')</a>
@endpush
