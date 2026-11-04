@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account ff-page">
    @include('Template::user.fantasy.partials.nav')
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Rules & Fair Play')</span></div>
        <div class="ff-header"><div><h1>@lang('Rules & Fair Play')</h1></div></div>

        <h3 class="ff-section-title">@lang('Scoring')</h3>
        <div class="ff-rules-grid">
            <div>@lang('Appearance (any minutes)'): <strong>{{ (int) $rules['appearance'] }}</strong></div>
            <div>@lang('Appearance (60+ minutes)'): <strong>{{ (int) $rules['appearance_60_plus'] }}</strong></div>
            <div>@lang('Goal (GK/DEF)'): <strong>{{ (int) $rules['goal_gk'] }}</strong></div>
            <div>@lang('Goal (MID)'): <strong>{{ (int) $rules['goal_mid'] }}</strong></div>
            <div>@lang('Goal (FWD)'): <strong>{{ (int) $rules['goal_fwd'] }}</strong></div>
            <div>@lang('Assist'): <strong>{{ (int) $rules['assist'] }}</strong></div>
            <div>@lang('Clean sheet (GK/DEF)'): <strong>{{ (int) $rules['clean_sheet_gk'] }}</strong></div>
            <div>@lang('Clean sheet (MID)'): <strong>{{ (int) $rules['clean_sheet_mid'] }}</strong></div>
            <div>@lang('Every N saves (GK)'): <strong>1 / {{ (int) $rules['saves_per_point'] }}</strong></div>
            <div>@lang('Penalty save'): <strong>{{ (int) $rules['penalty_save'] }}</strong></div>
            <div>@lang('Penalty miss'): <strong>{{ (int) $rules['penalty_miss'] }}</strong></div>
            <div>@lang('Every N goals conceded (GK/DEF)'): <strong>-1 / {{ (int) $rules['goals_conceded_per_point'] }}</strong></div>
            <div>@lang('Yellow card'): <strong>{{ (int) $rules['yellow_card'] }}</strong></div>
            <div>@lang('Red card'): <strong>{{ (int) $rules['red_card'] }}</strong></div>
            <div>@lang('Own goal'): <strong>{{ (int) $rules['own_goal'] }}</strong></div>
            <div>@lang('Bonus (match performance)'): <strong>1-{{ (int) $rules['bonus_max'] }}</strong></div>
        </div>

        <h3 class="ff-section-title">@lang('Fair Play & Verification')</h3>
        <div class="ff-fairplay">
            <p>@lang('Every match result is sealed before kickoff: a cryptographic commitment (SHA-256 hash) is published the moment the match begins, derived from a seed only the server knows at that point.')</p>
            <p>@lang('After full time, the seed is revealed and shown on the match page. Anyone can use "Verify this match" to confirm the revealed seed matches the commitment published at kickoff - proving the result was decided before the match, not after.')</p>
            <p>@lang('Coin contests settle only after the gameweek is finalized and a short admin review window has passed, during which obvious statistical errors can be corrected. Once a contest pays out, results are locked permanently.')</p>
        </div>
    </div>
</div>
@endsection

@push('style')
<style>
.ff-rules-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 8px; margin-bottom: 20px; }
.ff-rules-grid div { background: #0d1023; border: 1px solid #2c3453; border-radius: 10px; padding: 10px 14px; font-size: 12.5px; color: var(--nocturne-muted, #929abc); }
.ff-rules-grid strong { float: right; color: var(--nocturne-green, #00ef87); }
.ff-fairplay p { color: var(--nocturne-muted, #929abc); font-size: 13px; line-height: 1.6; }
</style>
@endpush
