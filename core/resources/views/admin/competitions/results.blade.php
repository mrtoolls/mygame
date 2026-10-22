@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="mb-1">{{ __($competition->title) }}</h5>
                    <span class="badge badge--dark">{{ __(ucfirst($competition->status)) }}</span>
                    <span class="text-muted ms-2">{{ showDateTime($competition->start_at) }} <i class="las la-arrow-right"></i> {{ showDateTime($competition->end_at) }}</span>
                </div>
            </div>
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive--sm table-responsive">
                        <table class="table table--light style--two custom-data-table">
                            <thead>
                                <tr>
                                    <th>@lang('Rank')</th>
                                    <th>@lang('Player')</th>
                                    <th>@lang('Metric')</th>
                                    <th>@lang('Prize (USD)')</th>
                                    <th>@lang('Payout')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($results as $result)
                                    <tr @class(['table-danger' => $result->disqualified_at])>
                                        <td class="fw-bold">#{{ $result->rank }}</td>
                                        <td>
                                            {{ $result->user->username ?? 'Deleted user' }}
                                            @if ($result->disqualified_at)
                                                <br><span class="badge badge--danger">@lang('Disqualified')</span>
                                                <div class="text-muted small">{{ $result->disqualified_reason }}</div>
                                            @endif
                                        </td>
                                        <td>{{ rtrim(rtrim(number_format((float) $result->metric_value, 4), '0'), '.') }} {{ $result->metric_label }}</td>
                                        <td>{{ $result->prize_amount_usd ? number_format($result->prize_amount_usd, 2) : '-' }}</td>
                                        <td>
                                            @if ($result->isPaid())
                                                <span class="badge badge--success">@lang('Paid')</span>
                                                <div class="text-muted small">{{ showDateTime($result->paid_at) }} @if($result->source_ref) &middot; {{ $result->source_ref }} @endif</div>
                                            @elseif ($result->prize_amount_usd && !$result->disqualified_at)
                                                <span class="badge badge--warning">@lang('Unpaid')</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="button--group">
                                                @if ($result->prize_amount_usd && !$result->isPaid() && !$result->disqualified_at)
                                                    <button type="button" class="btn btn-sm btn-outline--success ms-1" data-bs-toggle="modal" data-bs-target="#payModal{{ $result->id }}"><i class="las la-money-bill"></i>@lang('Mark Paid')</button>
                                                @endif
                                                @if (!$result->disqualified_at && !$competition->hasAnyPaidResult())
                                                    <button type="button" class="btn btn-sm btn-outline--danger ms-1" data-bs-toggle="modal" data-bs-target="#dqModal{{ $result->id }}"><i class="las la-ban"></i>@lang('Disqualify')</button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="payModal{{ $result->id }}">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="{{ route('admin.competitions.results.pay', $result->id) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-header"><h5 class="modal-title">@lang('Mark Prize Paid')</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                                    <div class="modal-body">
                                                        <p class="text-muted">@lang('This only records the conversion and marks the prize paid. Send the actual money to the player manually.')</p>
                                                        <div class="form-group">
                                                            <label>@lang('Payout Currency')</label>
                                                            <select name="payout_currency_id" class="form-control" required>
                                                                @foreach ($currencies as $currency)
                                                                    <option value="{{ $currency->id }}">{{ $currency->code }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="form-group">
                                                            <label>@lang('Rate to USD (1 unit = ? USD)')</label>
                                                            <input type="number" step="0.000000000001" min="0" class="form-control" name="payout_rate_to_usd" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label>@lang('Reference (optional)')</label>
                                                            <input type="text" class="form-control" name="source_ref" maxlength="191">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer"><button type="submit" class="btn btn--primary">@lang('Confirm')</button></div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="modal fade" id="dqModal{{ $result->id }}">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="{{ route('admin.competitions.results.disqualify', $result->id) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-header"><h5 class="modal-title">@lang('Disqualify Participant')</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                                    <div class="modal-body">
                                                        <div class="form-group">
                                                            <label>@lang('Reason')</label>
                                                            <textarea name="reason" class="form-control" rows="2" required></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer"><button type="submit" class="btn btn--danger">@lang('Disqualify')</button></div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __('No results yet - this competition has not been finalized.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            {{ paginateLinks($results) }}
        </div>
    </div>
@endsection

@push('breadcrumb-plugins')
    <x-back route="{{ route('admin.competitions.index') }}" />
@endpush
