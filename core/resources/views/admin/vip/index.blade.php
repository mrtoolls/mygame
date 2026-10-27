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
                                    <th>@lang('Tier')</th>
                                    <th>@lang('Min. Wagered')</th>
                                    <th>@lang('Bonus')</th>
                                    <th>@lang('Rakeback')</th>
                                    <th>@lang('Max Bet')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($tiers as $tier)
                                    <tr>
                                        <td>{{ $tier->sort_order }}</td>
                                        <td class="fw-bold">
                                            <div class="d-flex align-items-center gap-2">
                                                @if ($tier->badge_image)
                                                    <img src="{{ $tier->badgeImageUrl() }}" alt="" style="width:32px;height:32px;border-radius:8px;object-fit:cover">
                                                @endif
                                                <span>{{ __($tier->name) }} @if ($tier->badge) <span class="badge badge--dark">{{ __($tier->badge) }}</span> @endif</span>
                                            </div>
                                        </td>
                                        <td>{{ formatVipThreshold($tier->min_wagered) }}</td>
                                        <td>{{ formatCoinOrUsd($tier->bonus_amount) }}</td>
                                        <td>{{ showAmount($tier->rakeback_percent, currencyFormat: false) }}%</td>
                                        <td>{{ $tier->max_bet ? formatCoinOrUsd($tier->max_bet) : '-' }}</td>
                                        <td>@php echo $tier->statusBadge @endphp</td>
                                        <td>
                                            <div class="button--group">
                                                <a href="{{ route('admin.vip.edit', $tier->id) }}" class="btn btn-sm btn-outline--primary ms-1"><i class="las la-pen"></i>@lang('Edit')</a>
                                                @if ($tier->status == Status::ENABLE)
                                                    <button class="btn btn-sm btn-outline--danger ms-1 confirmationBtn" data-question="@lang('Are you sure to disable this tier?')" data-action="{{ route('admin.vip.status', $tier->id) }}">
                                                        <i class="la la-eye-slash"></i>@lang('Disable')
                                                    </button>
                                                @else
                                                    <button class="btn btn-sm btn-outline--success ms-1 confirmationBtn" data-question="@lang('Are you sure to enable this tier?')" data-action="{{ route('admin.vip.status', $tier->id) }}">
                                                        <i class="la la-eye"></i>@lang('Enable')
                                                    </button>
                                                @endif
                                                <button class="btn btn-sm btn-outline--danger ms-1 confirmationBtn" data-question="@lang('Are you sure to delete this tier?')" data-action="{{ route('admin.vip.destroy', $tier->id) }}">
                                                    <i class="la la-trash"></i>@lang('Delete')
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __('No VIP tiers added yet') }}</td>
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
    <a class="btn btn-outline--primary" href="{{ route('admin.vip.claims') }}"><i class="las la-list"></i>@lang('Rakeback Claims')</a>
    <a class="btn btn-outline--primary" href="{{ route('admin.vip.create') }}"><i class="las la-plus"></i>@lang('Add New')</a>
@endpush
