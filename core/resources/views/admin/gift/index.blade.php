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
                                    <th>@lang('Sort')</th>
                                    <th>@lang('Image')</th>
                                    <th>@lang('Name')</th>
                                    <th>@lang('Buy Price')</th>
                                    <th>@lang('Cash-out Value')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($gifts as $gift)
                                    <tr>
                                        <td>{{ $gift->sort_order }}</td>
                                        <td><img src="{{ $gift->imageUrl() }}" alt="{{ $gift->name }}" width="40" height="40" style="object-fit:contain"></td>
                                        <td class="fw-bold">{{ __($gift->name) }}</td>
                                        <td>${{ showAmount($gift->buy_price, currencyFormat: false) }}</td>
                                        <td>${{ showAmount($gift->cashout_value, currencyFormat: false) }}</td>
                                        <td>@php echo $gift->statusBadge @endphp</td>
                                        <td>
                                            <div class="button--group">
                                                <a href="{{ route('admin.gift.edit', $gift->id) }}" class="btn btn-sm btn-outline--primary ms-1"><i class="las la-pen"></i>@lang('Edit')</a>
                                                @if ($gift->status == Status::ENABLE)
                                                    <button class="btn btn-sm btn-outline--danger ms-1 confirmationBtn" data-question="@lang('Are you sure to disable this gift?')" data-action="{{ route('admin.gift.status', $gift->id) }}">
                                                        <i class="la la-eye-slash"></i>@lang('Disable')
                                                    </button>
                                                @else
                                                    <button class="btn btn-sm btn-outline--success ms-1 confirmationBtn" data-question="@lang('Are you sure to enable this gift?')" data-action="{{ route('admin.gift.status', $gift->id) }}">
                                                        <i class="la la-eye"></i>@lang('Enable')
                                                    </button>
                                                @endif
                                                <button class="btn btn-sm btn-outline--danger ms-1 confirmationBtn" data-question="@lang('Are you sure to delete this gift? Any inventory users hold will stop being viewable.')" data-action="{{ route('admin.gift.destroy', $gift->id) }}">
                                                    <i class="la la-trash"></i>@lang('Delete')
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __('No gifts added yet') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <x-confirmation-modal />
@endsection

@push('breadcrumb-plugins')
    <a class="btn btn-outline--primary" href="{{ route('admin.gift.preview-effects') }}"><i class="las la-magic"></i>@lang('Preview Effects')</a>
    <a class="btn btn-outline--primary" href="{{ route('admin.gift.create') }}"><i class="las la-plus"></i>@lang('Add New')</a>
@endpush
