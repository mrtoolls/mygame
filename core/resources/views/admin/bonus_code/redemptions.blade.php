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
                                    <th>@lang('User')</th>
                                    <th>@lang('Code')</th>
                                    <th>@lang('Amount')</th>
                                    <th>@lang('Trx')</th>
                                    <th>@lang('Date')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($redemptions as $redemption)
                                    <tr>
                                        <td>{{ $redemption->user?->username ?? '-' }}</td>
                                        <td class="fw-bold">{{ $redemption->bonusCode?->code ?? '-' }}</td>
                                        <td class="text--success">{{ gs('coin_mode') ? coinAmountText($redemption->amount) : showAmount($redemption->amount) }}</td>
                                        <td>{{ $redemption->trx }}</td>
                                        <td>{{ showDateTime($redemption->created_at) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __('No redemptions yet') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        {{ paginateLinks($redemptions) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('breadcrumb-plugins')
    <x-back route="{{ route('admin.bonus.code.index') }}" />
@endpush
