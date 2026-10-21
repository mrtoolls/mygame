@extends('Template::layouts.frontend')
@section('content')
<section class="nocturne-page-hero">
    <div class="nocturne-page-hero__text">
        <span class="nocturne-eyebrow"><i class="ph-bold ph-handshake"></i> @lang('WinxPay')</span>
        <p>@lang('Deposit through a verified Winxpot payment agent - fast, human, and backed by a public speed score.')</p>
    </div>
</section>

<section class="nocturne-section">
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
