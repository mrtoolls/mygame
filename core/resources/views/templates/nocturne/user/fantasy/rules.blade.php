@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account ff-page">
    @include('Template::user.fantasy.partials.nav')
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Rules & Fair Play')</span></div>
        <section class="ff-page-hero ff-rules-hero"><div><span class="ff-page-kicker"><i class="ph-fill ph-shield-check"></i>@lang('BUILT FOR FAIR PLAY')</span><h1>@lang('Rules & scoring')</h1><p>@lang('Know how every point is earned and how match results are verified.')</p></div><span class="ff-rules-hero-icon"><i class="ph-fill ph-book-open-text"></i></span></section>

        <div class="ff-section-heading"><div><span class="ff-page-kicker">@lang('POINTS GUIDE')</span><h2>@lang('Scoring rules')</h2></div><span class="ff-section-note">@lang('Points per gameweek')</span></div>
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

        <div class="ff-section-heading"><div><span class="ff-page-kicker">@lang('TRANSPARENT RESULTS')</span><h2>@lang('Fair play & verification')</h2></div></div>
        <div class="ff-fairplay">
            <article><span class="ff-fairplay-icon"><i class="ph-fill ph-lock-key"></i></span><div><strong>@lang('Committed before kickoff')</strong><p>@lang('Every match result is sealed before kickoff: a cryptographic commitment (SHA-256 hash) is published the moment the match begins, derived from a seed only the server knows at that point.')</p></div></article>
            <article><span class="ff-fairplay-icon"><i class="ph-fill ph-check-circle"></i></span><div><strong>@lang('Independently verifiable')</strong><p>@lang('After full time, the seed is revealed and shown on the match page. Anyone can use "Verify this match" to confirm the revealed seed matches the commitment published at kickoff - proving the result was decided before the match, not after.')</p></div></article>
            <article><span class="ff-fairplay-icon"><i class="ph-fill ph-coins"></i></span><div><strong>@lang('Reviewed contest payouts')</strong><p>@lang('Coin contests settle only after the gameweek is finalized and a short admin review window has passed, during which obvious statistical errors can be corrected. Once a contest pays out, results are locked permanently.')</p></div></article>
        </div>
    </div>
</div>
@endsection

@push('style')
<style>
.ff-page-hero { position:relative; isolation:isolate; display:flex; align-items:center; justify-content:space-between; gap:18px; overflow:hidden; padding:23px 24px; margin-bottom:15px; border:1px solid #252d4b; border-radius:20px; background:radial-gradient(ellipse at 90% 0,rgba(0,239,135,.13),transparent 38%),linear-gradient(135deg,#171d38,#10152a 70%,#101d2b); }
.ff-page-hero h1 { margin:0 0 5px; color:#f5f6ff; font-size:clamp(22px,2.2vw,28px); letter-spacing:-.035em; line-height:1.15; font-weight:750; }
.ff-page-hero p { margin:0; color:#9aa5c9; font-size:13px; line-height:1.5; }
.ff-page-kicker { display:inline-flex; gap:6px; align-items:center; margin-bottom:7px; color:#00ef87; font-size:9px; font-weight:800; letter-spacing:.12em; }
.ff-rules-hero-icon { display:grid; place-items:center; flex:0 0 58px; width:58px; height:58px; border:1px solid rgba(0,239,135,.23); border-radius:18px; color:#00ef87; background:rgba(0,239,135,.09); font-size:25px; }
.ff-section-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:12px; margin:20px 0 10px; }
.ff-section-heading h2 { margin:0; color:#f5f6ff; font-size:17px; font-weight:700; letter-spacing:-.02em; }
.ff-section-heading .ff-page-kicker { margin:0 0 4px; }
.ff-section-note { color:#8994b9; font-size:10px; }
.ff-rules-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:8px; margin-bottom:22px; }
.ff-rules-grid div { display:flex; align-items:center; justify-content:space-between; gap:10px; min-height:47px; background:linear-gradient(110deg,#151b34,#101529); border:1px solid #252d4b; border-radius:12px; padding:10px 12px; font-size:11px; line-height:1.4; color:#a3adcc; }
.ff-rules-grid strong { flex:0 0 auto; color:#00ef87; font-size:13px; font-weight:750; white-space:nowrap; }
.ff-fairplay { display:grid; gap:8px; }
.ff-fairplay article { display:flex; align-items:flex-start; gap:12px; padding:14px; border:1px solid #252d4b; border-radius:14px; background:linear-gradient(110deg,#151b34,#101529); }
.ff-fairplay-icon { display:grid; flex:0 0 36px; place-items:center; width:36px; height:36px; border-radius:11px; color:#00ef87; background:rgba(0,239,135,.09); font-size:17px; }
.ff-fairplay article strong { display:block; margin:1px 0 4px; color:#f2f4ff; font-size:12px; font-weight:700; }
.ff-fairplay p { margin:0; color:#9ba6c9; font-size:11px; line-height:1.6; }
@media(max-width:650px) { .ff-page-hero { padding:18px 15px; border-radius:17px; }.ff-page-hero p { max-width:260px; font-size:11px; }.ff-rules-grid { grid-template-columns:1fr; gap:6px; }.ff-rules-grid div { min-height:43px; padding:9px 11px; }.ff-section-heading h2 { font-size:15px; }.ff-fairplay article { gap:9px; padding:11px; }.ff-fairplay p { font-size:10px; } }
</style>
@endpush
