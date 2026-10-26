@extends('Template::layouts.frontend')
@section('content')
<section class="nocturne-page-hero">
    <div class="nocturne-page-hero__text">
        <span class="nocturne-eyebrow"><i class="ph-bold ph-handshake"></i> @lang('WinxPay')</span>
        <p>@lang('Deposit through a verified Winxpot payment agent - fast, human, and backed by a public speed score.')</p>
    </div>
</section>

<section class="nocturne-section">
    @if(!count($directoryItems ?? []))
    @if($agents->isEmpty())
        <div class="nocturne-winxpay-empty"><i class="ph-fill ph-handshake"></i><h3>@lang('No WinxPay agents available yet')</h3><p>@lang('Check back soon, or use Crypto or another deposit method.')</p></div>
    @else
        <div class="nocturne-winxpay-grid">
            @foreach($agents as $agent)
                @php($speed = $speedScores[$agent->id] ?? 0)
                <article class="nocturne-winxpay-card">
                    <div class="nocturne-winxpay-card__head">
                        <span class="nocturne-winxpay-card__avatar">
                            @if($agent->photo)<img src="{{ asset('assets/images/support-agents/' . $agent->photo) }}" alt="">@else<i class="ph-fill ph-user-circle"></i>@endif
                        </span>
                        <div class="nocturne-winxpay-card__identity">
                            <strong>{{ $agent->name }} @if($agent->is_verified)<span class="nocturne-winxpay-verified" title="@lang('Verified')"><i class="ph-fill ph-seal-check"></i></span>@endif</strong>
                            <span>{{ $agent->title ?: __('Payment agent') }}</span>
                        </div>
                        <button type="button" class="nocturne-winxpay-card__info" data-toggle-info aria-label="@lang('Accepted payment types')"><i class="ph-bold ph-info"></i></button>
                    </div>

                    <div class="nocturne-winxpay-card__speed">
                        <div class="nocturne-winxpay-card__speed-label"><span>@lang('Speed')</span><strong>{{ $speed }}/100</strong></div>
                        <div class="nocturne-winxpay-card__speed-bar"><span style="width:{{ $speed }}%"></span></div>
                    </div>

                    <div class="nocturne-winxpay-card__types" hidden>
                        @if(!empty($agent->accepted_payment_types))
                            <strong>@lang('Accepts'):</strong>
                            <div class="nocturne-winxpay-card__type-tags">@foreach($agent->accepted_payment_types as $type)<span>{{ $type }}</span>@endforeach</div>
                        @else
                            <em>@lang('No payment types listed yet.')</em>
                        @endif
                    </div>

                    @auth
                        <a href="{{ route('user.deposit.index', ['agent' => $agent->id]) }}" class="nocturne-winxpay-card__deposit"><i class="ph-bold ph-wallet"></i> @lang('Deposit')</a>
                    @else
                        <a href="{{ route('user.login') }}" class="nocturne-winxpay-card__deposit"><i class="ph-bold ph-sign-in"></i> @lang('Sign in to deposit')</a>
                    @endauth
                </article>
            @endforeach
        </div>
    @endif
    @else
        <div class="nocturne-winxpay-grid">
            @foreach($directoryItems as $offer)
                <article class="nocturne-winxpay-card">
                    <div class="nocturne-winxpay-card__head"><span class="nocturne-winxpay-card__avatar">@if($offer['avatar_url'])<img src="{{ $offer['avatar_url'] }}" alt="">@else{{ strtoupper(substr($offer['name'],0,1)) }}@endif</span><div class="nocturne-winxpay-card__identity"><strong>{{ $offer['name'] }} <span class="nocturne-winxpay-verified"><i class="ph-fill ph-seal-check"></i></span></strong><span>{{ $offer['type']==='vendor'?'Verified Coin Vendor':'Payment agent' }} · {{ $offer['reliability_score'] }}/100</span></div></div>
                    @if($coinMode ?? false)<div class="nocturne-winxpay-card__speed-label"><span>Rate per coin</span><strong>{{ $offer['currency_code'] }} {{ number_format($offer['rate_per_coin'],4) }}</strong></div><div class="nocturne-winxpay-card__speed-label"><span>Per 100 / 1,000</span><strong>{{ $offer['currency_code'] }} {{ number_format($offer['rate_per_100'],2) }} / {{ number_format($offer['rate_per_1000'],2) }}</strong></div>@endif
                    <div class="nocturne-winxpay-card__speed-label"><span>{{ number_format($offer['completed_trades']) }} completed · {{ $offer['average_release_minutes'] ? $offer['average_release_minutes'].' min avg' : 'New offer' }}</span><strong>{{ $offer['online']?'Online':'Offline' }}</strong></div>
                    @if($offer['payment_methods'])<div class="nocturne-winxpay-card__type-tags">@foreach($offer['payment_methods'] as $method)<span>{{ $method }}</span>@endforeach</div>@endif
                    @auth
                        @if(($coinMode ?? false) && $offer['type']==='vendor')
                            <form method="post" action="{{ route('user.coin-vendor.buy') }}" class="nocturne-vendor-buy">@csrf<input type="hidden" name="vendor_id" value="{{ $offer['id'] }}"><label>Coins<input type="number" name="amount" min="10" max="{{ min($offer['per_trade_limit'],$offer['available_coins']) }}" step="1" required placeholder="Amount"></label><button type="submit">Buy coins</button></form>
                        @elseif(($coinMode ?? false) && $offer['type']==='agent')
                            <form method="post" action="{{ route('user.coin.buy') }}" class="nocturne-vendor-buy">@csrf<input type="hidden" name="support_agent_id" value="{{ $offer['id'] }}"><label>Coins<input type="number" name="amount" min="10" max="{{ min($offer['per_trade_limit'],$offer['available_coins']) }}" step="1" required placeholder="Amount"></label><button type="submit">Buy coins</button></form>
                        @else
                            <a href="{{ route('user.deposit.index') }}" class="nocturne-winxpay-card__deposit"><i class="ph-bold ph-wallet"></i> @lang('Open WinxPay')</a>
                        @endif
                    @else<a href="{{ route('user.login') }}" class="nocturne-winxpay-card__deposit">@lang('Sign in to trade')</a>@endauth
                </article>
            @endforeach
        </div>
    @endif
</section>
@endsection

@push('style')
<style>
.nocturne-winxpay-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:18px}
.nocturne-winxpay-card{display:grid;gap:14px;padding:22px;border:1px solid var(--nocturne-line);border-radius:var(--nocturne-radius-lg);background:var(--nocturne-panel)}
.nocturne-winxpay-card__head{display:flex;align-items:center;gap:12px}
.nocturne-winxpay-card__avatar{display:grid;place-items:center;width:52px;height:52px;flex:0 0 52px;border-radius:50%;background:rgba(0,239,135,.1);color:var(--nocturne-green);font-size:26px;overflow:hidden}
.nocturne-winxpay-card__avatar img{width:100%;height:100%;object-fit:cover}
.nocturne-winxpay-card__identity{flex:1;min-width:0}
.nocturne-winxpay-card__identity strong{display:flex;align-items:center;gap:5px;font-size:15px;color:var(--nocturne-text)}
.nocturne-winxpay-card__identity span{display:block;color:var(--nocturne-muted);font-size:12px}
.nocturne-winxpay-verified{color:var(--nocturne-green);font-size:16px}
.nocturne-winxpay-card__info{flex:0 0 auto;display:grid;place-items:center;width:30px;height:30px;border:1px solid var(--nocturne-line);border-radius:50%;background:transparent;color:var(--nocturne-muted);cursor:pointer}
.nocturne-winxpay-card__info:hover{color:var(--nocturne-green);border-color:rgba(0,239,135,.35)}
.nocturne-winxpay-card__speed-label{display:flex;justify-content:space-between;font-size:11px;color:var(--nocturne-muted);margin-bottom:5px}
.nocturne-winxpay-card__speed-label strong{color:var(--nocturne-text)}
.nocturne-winxpay-card__speed-bar{height:7px;border-radius:999px;background:rgba(255,255,255,.08);overflow:hidden}
.nocturne-winxpay-card__speed-bar span{display:block;height:100%;background:linear-gradient(90deg,#00e78a,#00ef87);border-radius:999px}
.nocturne-winxpay-card__types{padding:12px;border:1px dashed var(--nocturne-line);border-radius:12px;font-size:12.5px;color:var(--nocturne-muted)}
.nocturne-winxpay-card__type-tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
.nocturne-winxpay-card__type-tags span{padding:4px 9px;border-radius:999px;background:rgba(0,239,135,.1);color:var(--nocturne-green);font-size:11px;font-weight:800}
.nocturne-winxpay-card__deposit{display:flex;align-items:center;justify-content:center;gap:8px;min-height:46px;border-radius:12px;background:var(--nocturne-green);color:#062a23;text-decoration:none;font-weight:850;font-size:13.5px}
.nocturne-winxpay-empty{display:grid;justify-items:center;gap:8px;padding:48px;border:1px dashed var(--nocturne-line);border-radius:var(--nocturne-radius-lg);color:var(--nocturne-muted);text-align:center}
.nocturne-winxpay-empty i{color:var(--nocturne-green);font-size:34px}
</style>
@endpush

@push('script')
<script>
document.querySelectorAll('[data-toggle-info]').forEach(btn => {
    btn.addEventListener('click', () => {
        btn.closest('.nocturne-winxpay-card')?.querySelector('.nocturne-winxpay-card__types')?.toggleAttribute('hidden');
    });
});
</script>
@endpush
