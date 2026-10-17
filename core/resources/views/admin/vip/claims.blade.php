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
                                    <th>@lang('Tier')</th>
                                    <th>@lang('Amount')</th>
                                    <th>@lang('Lifetime Wagered at Claim')</th>
                                    <th>@lang('Trx')</th>
                                    <th>@lang('Date')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($claims as $claim)
                                    <tr>
                                        <td>{{ $claim->user?->username ?? '-' }}</td>
                                        <td>{{ $claim->tier?->name ?? '-' }}</td>
                                        <td class="fw-bold text--success">{{ showAmount($claim->amount) }}</td>
                                        <td>{{ showAmount($claim->total_wagered_snapshot) }}</td>
                                        <td>{{ $claim->trx }}</td>
                                        <td>{{ showDateTime($claim->created_at) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __('No rakeback claims yet') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        {{ paginateLinks($claims) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('breadcrumb-plugins')
    <x-back route="{{ route('admin.vip.index') }}" />
@endpush
