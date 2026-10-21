@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <form action="{{ route('admin.vip.update', $tier->id) }}" class="disableSubmission" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Tier Name')</label>
                                    <input type="text" class="form-control" name="name" value="{{ old('name', $tier->name) }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Badge Text (optional)')</label>
                                    <input type="text" class="form-control" name="badge" value="{{ old('badge', $tier->badge) }}" placeholder="@lang('e.g. Bronze')">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Badge Image (optional)')</label>
                                    <input type="file" class="form-control" name="badge_image" accept=".png,.jpg,.jpeg,.webp">
                                    <small class="text-muted">@lang('Square image, e.g. 160x160. Falls back to a generic icon if not set.')</small>
                                </div>
                            </div>
                            @if($tier->badge_image)
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>@lang('Current Badge Image')</label>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="{{ $tier->badgeImageUrl() }}" alt="" style="width:56px;height:56px;border-radius:12px;object-fit:cover">
                                            <label class="mb-0"><input type="checkbox" name="remove_badge_image" value="1"> @lang('Remove current image')</label>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Minimum Lifetime Wagered to Unlock')</label>
                                    <div class="input-group">
                                        <input type="number" step="any" min="0" class="form-control" name="min_wagered" value="{{ old('min_wagered', $tier->min_wagered) }}" required>
                                        <div class="input-group-text">{{ __(gs('cur_text')) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('One-time Bonus on Unlock')</label>
                                    <div class="input-group">
                                        <input type="number" step="any" min="0" class="form-control" name="bonus_amount" value="{{ old('bonus_amount', $tier->bonus_amount) }}" required>
                                        <div class="input-group-text">{{ __(gs('cur_text')) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Daily Rakeback')</label>
                                    <div class="input-group">
                                        <input type="number" step="any" min="0" max="100" class="form-control" name="rakeback_percent" value="{{ old('rakeback_percent', $tier->rakeback_percent) }}" required>
                                        <div class="input-group-text">%</div>
                                    </div>
                                    <small class="text-muted">@lang('Percent of lifetime wagering a user at this tier can claim back')</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Max Bet Limit (optional)')</label>
                                    <div class="input-group">
                                        <input type="number" step="any" min="0" class="form-control" name="max_bet" value="{{ old('max_bet', $tier->max_bet) }}">
                                        <div class="input-group-text">{{ __(gs('cur_text')) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Daily Free Reward')</label>
                                    <div class="input-group">
                                        <input type="number" step="any" min="0" class="form-control" name="daily_reward_amount" value="{{ old('daily_reward_amount', $tier->daily_reward_amount) }}">
                                        <div class="input-group-text">{{ __(gs('cur_text')) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Birthday Bonus')</label>
                                    <div class="input-group">
                                        <input type="number" step="any" min="0" class="form-control" name="birthday_bonus_amount" value="{{ old('birthday_bonus_amount', $tier->birthday_bonus_amount) }}">
                                        <div class="input-group-text">{{ __(gs('cur_text')) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Withdrawal Processing (optional)')</label>
                                    <div class="input-group">
                                        <input type="number" step="1" min="0" class="form-control" name="withdrawal_processing_hours" value="{{ old('withdrawal_processing_hours', $tier->withdrawal_processing_hours) }}" placeholder="24">
                                        <div class="input-group-text">@lang('hours')</div>
                                    </div>
                                    <small class="text-muted">@lang('Shown as "Up to X h" on the VIP page')</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Monthly Withdrawal Limit (optional)')</label>
                                    <div class="input-group">
                                        <input type="number" step="any" min="0" class="form-control" name="monthly_withdrawal_limit" value="{{ old('monthly_withdrawal_limit', $tier->monthly_withdrawal_limit) }}">
                                        <div class="input-group-text">{{ __(gs('cur_text')) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Support Level (optional)')</label>
                                    <input type="text" class="form-control" name="support_level" value="{{ old('support_level', $tier->support_level) }}" placeholder="@lang('e.g. Standard, Priority, Dedicated manager')">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Exclusive Promotions (optional)')</label>
                                    <input type="text" class="form-control" name="exclusive_promotions" value="{{ old('exclusive_promotions', $tier->exclusive_promotions) }}" placeholder="@lang('e.g. Monthly, Weekly, Personalized')">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Referral Bonus Boost')</label>
                                    <div class="input-group">
                                        <input type="number" step="any" min="0" max="100" class="form-control" name="referral_bonus_boost_percent" value="{{ old('referral_bonus_boost_percent', $tier->referral_bonus_boost_percent) }}">
                                        <div class="input-group-text">%</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Sort Order')</label>
                                    <input type="number" class="form-control" name="sort_order" value="{{ old('sort_order', $tier->sort_order) }}">
                                    <small class="text-muted">@lang('Lowest shows first (e.g. Bronze = 0, Gold = 4)')</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn--primary w-100 h-45">@lang('Submit')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('breadcrumb-plugins')
    <x-back route="{{ route('admin.vip.index') }}" />
@endpush
