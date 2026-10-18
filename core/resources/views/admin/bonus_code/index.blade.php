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
                                    <th>@lang('Code')</th>
                                    <th>@lang('Amount')</th>
                                    <th>@lang('Uses')</th>
                                    <th>@lang('Expires')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($codes as $code)
                                    <tr>
                                        <td class="fw-bold">{{ $code->code }}</td>
                                        <td>{{ showAmount($code->amount) }}</td>
                                        <td>{{ $code->used_count }}{{ $code->max_uses ? ' / ' . $code->max_uses : '' }}</td>
                                        <td>{{ $code->expires_at ? showDateTime($code->expires_at) : __('Never') }}</td>
                                        <td>@php echo $code->statusBadge @endphp</td>
                                        <td>
                                            <div class="button--group">
                                                <a href="{{ route('admin.bonus.code.edit', $code->id) }}" class="btn btn-sm btn-outline--primary ms-1"><i class="las la-pen"></i>@lang('Edit')</a>
                                                @if ($code->status == Status::ENABLE)
                                                    <button class="btn btn-sm btn-outline--danger ms-1 confirmationBtn" data-question="@lang('Are you sure to disable this code?')" data-action="{{ route('admin.bonus.code.status', $code->id) }}">
                                                        <i class="la la-eye-slash"></i>@lang('Disable')
                                                    </button>
                                                @else
                                                    <button class="btn btn-sm btn-outline--success ms-1 confirmationBtn" data-question="@lang('Are you sure to enable this code?')" data-action="{{ route('admin.bonus.code.status', $code->id) }}">
                                                        <i class="la la-eye"></i>@lang('Enable')
                                                    </button>
                                                @endif
                                                <button class="btn btn-sm btn-outline--danger ms-1 confirmationBtn" data-question="@lang('Are you sure to delete this code?')" data-action="{{ route('admin.bonus.code.destroy', $code->id) }}">
                                                    <i class="la la-trash"></i>@lang('Delete')
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __('No bonus codes added yet') }}</td>
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
    <a class="btn btn-outline--primary" href="{{ route('admin.bonus.code.redemptions') }}"><i class="las la-list"></i>@lang('Redemptions')</a>
    <a class="btn btn-outline--primary" href="{{ route('admin.bonus.code.create') }}"><i class="las la-plus"></i>@lang('Add New')</a>
@endpush
