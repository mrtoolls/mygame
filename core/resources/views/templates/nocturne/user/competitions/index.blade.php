@php
    $typeLabels = ['wager_race' => 'Wager Race', 'biggest_win' => 'Biggest Win', 'lucky_draw' => 'Lucky Draw', 'table_league' => 'Table League'];
    $typeIcons = ['wager_race' => 'ph-fill ph-flame', 'biggest_win' => 'ph-fill ph-trophy', 'lucky_draw' => 'ph-fill ph-ticket', 'table_league' => 'ph-fill ph-chess-king'];
    $typeColors = ['wager_race' => '#ff9a4d', 'biggest_win' => '#ffd166', 'lucky_draw' => '#c58bff', 'table_league' => '#00ef87'];
    $allPool = $live->sum(fn ($c) => $c->prizes->sum('amount_usd')) + $upcoming->sum(fn ($c) => $c->prizes->sum('amount_usd'));
    $allPoolCoins = (int) bcdiv((string) $allPool, (string) (gs('coin_peg_usd') ?: '0.01'), 0);
@endphp
@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account nocturne-competitions-page">
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Competitions')</span></div>

        <section class="nocturne-comp-hero">
            <div class="nocturne-comp-hero__glow"></div>
            <div class="nocturne-comp-hero__copy">
                <span class="nocturne-comp-hero__kicker"><i class="ph-fill ph-trophy"></i>@lang('COMPETE & WIN')</span>
                <h1>@lang('Competitions')</h1>
                <p>@lang('Compete for real prizes across Wager Races, Biggest Win, Lucky Draws and the Table League.')</p>
            </div>
            <div class="nocturne-comp-hero__stats">
                <div class="nocturne-comp-hero__stat"><strong>{{ $live->count() }}</strong><span>@lang('Live now')</span></div>
                <div class="nocturne-comp-hero__stat"><strong>{{ $upcoming->count() }}</strong><span>@lang('Upcoming')</span></div>
                <div class="nocturne-comp-hero__stat nocturne-comp-hero__stat--accent"><strong>@if (gs('coin_mode'))<span class="nocturne-comp-coin-value"><i class="ph-fill ph-coin" aria-hidden="true"></i><span>{{ number_format($allPoolCoins) }}</span><span>@lang('Coins')</span></span>@else${{ number_format($allPool, 0) }}@endif</strong><span>@lang('Prize pool')</span></div>
            </div>
        </section>

        @foreach ([['live', $live, 'Live Now', 'ph-fill ph-broadcast'], ['upcoming', $upcoming, 'Upcoming', 'ph-fill ph-calendar-plus'], ['past', $past, 'Past Competitions', 'ph-fill ph-clock-counter-clockwise']] as [$key, $list, $heading, $headingIcon])
            <section class="nocturne-comp-section">
                <h2><i class="{{ $headingIcon }}"></i>{{ __($heading) }}</h2>
                @if ($list->isEmpty())
                    <div class="nocturne-comp-empty"><i class="ph-fill ph-confetti"></i><p>@lang('Nothing here right now - check back soon.')</p></div>
                @else
                    <div class="nocturne-comp-grid">
                        @foreach ($list as $competition)
                            @php
                                $prizePool = $competition->prizes->sum('amount_usd');
                                $prizeCoins = (int) bcdiv((string) $prizePool, (string) (gs('coin_peg_usd') ?: '0.01'), 0);
                                $accent = $typeColors[$competition->type] ?? '#00ef87';
                            @endphp
                            <a href="{{ route('user.competitions.show', $competition->slug) }}" class="nocturne-comp-card nocturne-comp-card--{{ $key }}" style="--comp-accent:{{ $accent }}">
                                <div class="nocturne-comp-card__banner" @if ($competition->banner_image) style="background-image:url('{{ getImage('assets/images/competitions/' . $competition->banner_image, '600x300') }}')" @endif>
                                    @unless ($competition->banner_image)<i class="{{ $typeIcons[$competition->type] ?? 'ph-fill ph-trophy' }} nocturne-comp-card__banner-icon"></i>@endunless
                                    @if ($key === 'live')<span class="nocturne-comp-card__live-badge"><i></i>@lang('LIVE')</span>@elseif($key === 'past')<span class="nocturne-comp-card__past-badge"><i class="ph-fill ph-check-circle"></i>@lang('Completed')</span>@endif
                                </div>
                                <div class="nocturne-comp-card__body">
                                    <span class="nocturne-comp-card__type"><i class="{{ $typeIcons[$competition->type] ?? 'ph-fill ph-trophy' }}"></i>{{ __($typeLabels[$competition->type] ?? $competition->type) }}</span>
                                    <h3>{{ __($competition->title) }}</h3>
                                    <div class="nocturne-comp-card__foot">
                                        @if ($prizePool > 0)
                                            <div class="nocturne-comp-card__pool"><small>@lang('Prize pool')</small>@if (gs('coin_mode'))<span class="nocturne-comp-coin-value"><i class="ph-fill ph-coin" aria-hidden="true"></i><span>{{ number_format($prizeCoins) }}</span><span>@lang('Coins')</span></span>@else<span class="nocturne-comp-card__cash">${{ number_format($prizePool, 0) }}</span>@endif</div>
                                        @endif
                                        <div class="nocturne-comp-card__window">
                                            @if ($key === 'upcoming')
                                                <i class="ph-bold ph-calendar"></i><time datetime="{{ $competition->start_at->toIso8601String() }}" title="{{ showDateTime($competition->start_at) }}">{{ showDateTime($competition->start_at) }}</time>
                                            @else
                                                <i class="ph-bold ph-clock-countdown"></i><time datetime="{{ $competition->end_at->toIso8601String() }}" title="{{ showDateTime($competition->end_at) }}">{{ showDateTime($competition->end_at) }}</time>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <span class="nocturne-comp-card__arrow"><i class="ph-bold ph-arrow-right"></i></span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </div>
</div>

<style>
.nocturne-competitions-page{grid-template-columns:minmax(0,1fr);min-width:0;padding-bottom:calc(90px + env(safe-area-inset-bottom))}
.nocturne-comp-hero{position:relative;isolation:isolate;overflow:hidden;display:flex;align-items:center;justify-content:space-between;gap:24px;flex-wrap:wrap;margin:14px 0 8px;padding:clamp(22px,3.3vw,36px);border-radius:22px;border:1px solid rgba(255,255,255,.085);background:radial-gradient(ellipse at 85% 20%,rgba(0,239,135,.12),transparent 45%),linear-gradient(125deg,#171d37,#0e1226);box-shadow:0 16px 42px rgba(0,0,0,.16)}
.nocturne-comp-hero__glow{position:absolute;z-index:-1;top:-60%;right:-10%;width:420px;height:420px;background:radial-gradient(circle,rgba(255,215,130,.12),transparent 70%);pointer-events:none}
.nocturne-comp-hero__copy{position:relative;min-width:0;max-width:620px}
.nocturne-comp-hero__kicker{display:inline-flex;align-items:center;gap:7px;color:#ffd782;font-size:10px;font-weight:750;letter-spacing:.11em;margin-bottom:9px}
.nocturne-comp-hero__copy h1{margin:0 0 7px;font-size:clamp(25px,3.4vw,34px);font-weight:760;line-height:1.15;letter-spacing:-.03em;color:#fff}
.nocturne-comp-hero__copy p{margin:0;color:#aab2c9;font-size:13px;line-height:1.6}
.nocturne-comp-hero__stats{position:relative;display:flex;gap:9px;flex-wrap:wrap}
.nocturne-comp-hero__stat{display:grid;justify-items:center;align-content:center;gap:3px;min-width:86px;padding:11px 13px;border-radius:14px;border:1px solid rgba(255,255,255,.085);background:rgba(9,13,28,.35)}
.nocturne-comp-hero__stat strong{max-width:170px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:19px;font-weight:750;color:#fff}
.nocturne-comp-hero__stat span{font-size:9px;text-transform:uppercase;letter-spacing:.06em;color:#9da7c1}
.nocturne-comp-hero__stat--accent{border-color:rgba(0,239,135,.25);background:rgba(0,239,135,.055)}
.nocturne-comp-hero__stat--accent strong{color:#7CFFB2}

.nocturne-comp-section{margin-top:26px}
.nocturne-comp-section h2{display:flex;align-items:center;gap:8px;font-size:16px;margin:0 0 12px;font-weight:720;letter-spacing:-.01em;color:#f4f6ff}
.nocturne-comp-section h2 i{color:#ffd782;font-size:17px}
.nocturne-comp-empty{display:flex;align-items:center;gap:12px;padding:18px 17px;border-radius:15px;border:1px solid rgba(255,255,255,.075);background:linear-gradient(145deg,rgba(24,30,54,.62),rgba(15,19,37,.6));color:#aab3ca}
.nocturne-comp-empty i{font-size:21px;color:#ffd782}
.nocturne-comp-empty p{margin:0;font-size:12.5px}

.nocturne-comp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,250px),1fr));gap:13px}
.nocturne-comp-card{position:relative;display:flex;min-width:0;flex-direction:column;border-radius:17px;overflow:hidden;background:linear-gradient(160deg,rgba(255,255,255,.045),rgba(255,255,255,.012));border:1px solid rgba(255,255,255,.085);text-decoration:none;transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease}
.nocturne-comp-card:hover{transform:translateY(-3px);border-color:color-mix(in srgb,var(--comp-accent) 45%,transparent);box-shadow:0 13px 28px rgba(0,0,0,.25)}
.nocturne-comp-card__banner{position:relative;height:clamp(94px,12vw,132px);display:grid;place-items:center;background:linear-gradient(145deg,color-mix(in srgb,var(--comp-accent) 22%,#171d37),#11152a);background-size:cover;background-position:center}
.nocturne-comp-card__banner-icon{font-size:40px;color:color-mix(in srgb,var(--comp-accent) 85%,#fff);opacity:.9}
.nocturne-comp-card__live-badge{position:absolute;top:10px;right:10px;display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:999px;background:rgba(6,8,18,.65);backdrop-filter:blur(4px);color:#7CFFB2;font-size:10px;font-weight:950;letter-spacing:.05em}
.nocturne-comp-card__live-badge i{width:6px;height:6px;border-radius:50%;background:#7CFFB2;box-shadow:0 0 7px #7CFFB2;animation:nocturne-comp-pulse 1.3s ease-in-out infinite}
.nocturne-comp-card__past-badge{position:absolute;top:9px;right:9px;display:inline-flex;align-items:center;gap:4px;padding:5px 8px;border-radius:999px;background:rgba(6,8,18,.68);color:#d8deee;font-size:9px;font-weight:700;backdrop-filter:blur(5px)}
@keyframes nocturne-comp-pulse{0%,100%{opacity:1}50%{opacity:.35}}
.nocturne-comp-card__body{flex:1;display:flex;min-width:0;flex-direction:column;padding:14px 15px 15px;gap:9px}
.nocturne-comp-card__type{display:inline-flex;align-items:center;gap:6px;font-size:9.5px;text-transform:uppercase;letter-spacing:.07em;color:var(--comp-accent);font-weight:750}
.nocturne-comp-card__body h3{flex:1;min-width:0;overflow-wrap:anywhere;font-size:15px;margin:0;color:#f4f6ff;font-weight:700;line-height:1.3}
.nocturne-comp-card__foot{display:flex;align-items:flex-end;justify-content:space-between;gap:10px;padding-top:8px;border-top:1px solid rgba(255,255,255,.06)}
.nocturne-comp-card__pool{font-size:17px;font-weight:750;color:#7CFFB2;line-height:1.1}
.nocturne-comp-card__pool small{display:block;font-size:9px;font-weight:550;color:#9aa3ba;text-transform:none}
.nocturne-comp-card__window{font-size:10px;color:#9aa3ba;display:flex;align-items:center;gap:5px;white-space:normal;text-align:right}
.nocturne-comp-card__arrow{position:absolute;top:14px;right:14px;display:grid;place-items:center;width:28px;height:28px;border-radius:50%;background:rgba(6,8,18,.5);color:#fff;opacity:0;transform:translateX(-4px);transition:opacity .18s ease,transform .18s ease}
.nocturne-comp-card:hover .nocturne-comp-card__arrow{opacity:1;transform:translateX(0)}
.nocturne-comp-card--past{opacity:.92}
@media(max-width:575px){.nocturne-comp-hero{flex-direction:column;align-items:flex-start;gap:17px;margin-top:10px;padding:19px 16px;border-radius:18px}.nocturne-comp-hero__copy h1{font-size:26px}.nocturne-comp-hero__stats{width:100%;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px}.nocturne-comp-hero__stat{min-width:0;padding:9px 5px}.nocturne-comp-hero__stat strong{max-width:100%;font-size:16px}.nocturne-comp-hero__stat span{font-size:8px}.nocturne-comp-section{margin-top:22px}.nocturne-comp-grid{grid-template-columns:minmax(0,1fr);gap:10px}.nocturne-comp-card{display:grid;grid-template-columns:94px minmax(0,1fr);min-height:118px;border-radius:15px}.nocturne-comp-card__banner{height:100%;min-height:118px}.nocturne-comp-card__body{padding:12px;gap:7px}.nocturne-comp-card__body h3{font-size:14px}.nocturne-comp-card__foot{align-items:center;padding-top:7px}.nocturne-comp-card__pool{font-size:14px}.nocturne-comp-card__window{font-size:9px}.nocturne-comp-card__arrow{display:none}}
@media(prefers-reduced-motion:reduce){.nocturne-comp-card,.nocturne-comp-card__live-badge i{transition:none;animation:none}}
.nocturne-comp-hero__stats{min-width:0;max-width:100%}
.nocturne-comp-hero__stat{min-width:0}
.nocturne-comp-hero__stat strong{max-width:none;overflow:visible;text-overflow:clip;white-space:normal}
.nocturne-comp-hero__stat--accent{min-width:max-content}
.nocturne-comp-coin-value{display:inline-flex;align-items:center;justify-content:center;gap:.28em;max-width:100%;white-space:nowrap;font-size:clamp(10px,1.3vw,15px);font-weight:750;letter-spacing:-.025em;line-height:1.2;font-variant-numeric:tabular-nums}
.nocturne-comp-coin-value>i{flex:0 0 auto;color:#00ef9a;font-size:1.05em}
.nocturne-comp-coin-value>span{flex:0 0 auto}
.nocturne-comp-card{min-height:320px}
.nocturne-comp-card__banner{height:auto;min-height:116px;aspect-ratio:2.45/1;flex:0 0 auto}
.nocturne-comp-card__body{display:grid;grid-template-rows:auto minmax(2.6em,1fr) auto;gap:10px;padding:15px 16px 16px}
.nocturne-comp-card__body h3{display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden;min-height:2.6em;overflow-wrap:break-word;font-weight:650}
.nocturne-comp-card__foot{min-width:0;align-items:center;gap:8px}
.nocturne-comp-card__pool{display:grid;min-width:0;gap:4px;font-size:inherit;line-height:1.2}
.nocturne-comp-card__pool small{font-size:9px;line-height:1.2}
.nocturne-comp-card__cash{color:#7CFFB2;font-size:clamp(14px,1.3vw,18px);font-weight:750;white-space:nowrap}
.nocturne-comp-card__pool .nocturne-comp-coin-value{justify-content:flex-start;font-size:clamp(10px,1.05vw,14px)}
.nocturne-comp-card__window{min-width:0;max-width:48%;flex:0 1 auto;justify-content:flex-end;gap:5px;font-size:10px;line-height:1.35}
.nocturne-comp-card__window i{flex:0 0 auto}
.nocturne-comp-card__window time{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
@media(max-width:575px){.nocturne-comp-hero__stats{grid-template-columns:repeat(2,minmax(0,1fr))}.nocturne-comp-hero__stat--accent{grid-column:1/-1;min-width:0}.nocturne-comp-hero__stat--accent strong{font-size:clamp(13px,4vw,17px)}.nocturne-comp-coin-value{font-size:clamp(11px,3.4vw,15px)}.nocturne-comp-card{min-height:130px}.nocturne-comp-card__banner{min-height:130px;aspect-ratio:auto}.nocturne-comp-card__foot{align-items:center}.nocturne-comp-card__pool .nocturne-comp-coin-value{font-size:clamp(9px,2.8vw,12px)}.nocturne-comp-card__window{max-width:46%;font-size:9px}}
</style>
@endsection
