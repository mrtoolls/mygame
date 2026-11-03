@extends('Template::layouts.master')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="nocturne-crypto-wait" id="crypto-wait" data-deadline="{{ $deposit->confirmation_deadline_at?->timestamp * 1000 }}" data-status-url="{{ route('user.deposit.confirmation.status') }}" data-dispute-url="{{ route('user.deposit.confirmation.dispute') }}" data-history-url="{{ route('user.deposit.history') }}">
                <span class="nocturne-crypto-wait__icon"><i class="las la-hourglass-half"></i></span>
                <h3>@lang('Confirming your deposit')</h3>
                <p>@lang('Our team is reviewing your payment details. This updates automatically once confirmed.')</p>

                <div class="nocturne-crypto-wait__countdown">
                    <span data-unit="h">00</span><em>:</em><span data-unit="m">00</span><em>:</em><span data-unit="s">00</span>
                </div>
                <small>@lang('Target confirmation window')</small>

                <div class="nocturne-crypto-wait__state" data-state="pending">
                    <span class="badge badge--warning">@lang('Pending review')</span>
                </div>

                <button type="button" class="nocturne-crypto-wait__dispute-btn" id="crypto-dispute-btn"><i class="las la-exclamation-triangle"></i> @lang('Dispute this deposit')</button>

                <div id="crypto-dispute-form" class="nocturne-crypto-wait__dispute-form d-none">
                    <textarea id="crypto-dispute-reason" rows="3" placeholder="@lang('Tell us what went wrong (optional)')" maxlength="500"></textarea>
                    <button type="button" class="nocturne-crypto-wait__dispute-submit" id="crypto-dispute-submit">@lang('Submit dispute')</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('style')
<style>
.nocturne-crypto-wait{max-width:380px;margin:0 auto;padding:26px 24px;border:1px solid rgba(255,255,255,.08);border-radius:20px;background:linear-gradient(150deg,rgba(255,190,65,.08),rgba(20,26,48,.94));box-shadow:0 18px 45px rgba(0,0,0,.2);text-align:center;color:#f4f5fd}
.nocturne-crypto-wait__icon{display:inline-grid;place-items:center;width:42px;height:42px;border-radius:50%;background:rgba(255,190,65,.12);color:#ffca69;font-size:19px}
.nocturne-crypto-wait h3{margin:14px 0 5px;font-size:16px;font-weight:700;letter-spacing:-.01em}
.nocturne-crypto-wait p{margin:0 0 16px;color:#9ca6c7;font-size:12.5px;line-height:1.5}
.nocturne-crypto-wait__countdown{display:flex;align-items:center;justify-content:center;gap:3px;margin:0 0 4px;font-variant-numeric:tabular-nums}
.nocturne-crypto-wait__countdown span{display:inline-flex;min-width:34px;justify-content:center;padding:5px 7px;border-radius:9px;background:rgba(7,11,27,.55);color:#f4f5fd;font-size:16px;font-weight:700;letter-spacing:.02em}
.nocturne-crypto-wait__countdown em{color:#5b6585;font-style:normal;font-size:14px}
.nocturne-crypto-wait small{display:block;margin-bottom:14px;color:#6c7694;font-size:10.5px;text-transform:uppercase;letter-spacing:.06em}
.nocturne-crypto-wait__state .badge{font-size:10.5px;font-weight:700;padding:5px 12px}
.nocturne-crypto-wait__state.is-success .badge{background:#00e78a!important;color:#062718}
.nocturne-crypto-wait__state.is-disputed .badge{background:#ff5069!important}
.nocturne-crypto-wait__dispute-btn{display:inline-flex;align-items:center;gap:6px;margin-top:18px;padding:8px 14px;border:1px solid rgba(255,80,105,.25);border-radius:10px;background:rgba(255,80,105,.06);color:#ff9bad;font-size:11.5px;font-weight:700;cursor:pointer}
.nocturne-crypto-wait__dispute-btn:hover{background:rgba(255,80,105,.12)}
.nocturne-crypto-wait__dispute-form{margin-top:12px;text-align:left}
.nocturne-crypto-wait__dispute-form textarea{width:100%;padding:10px 12px;margin-bottom:8px;border:1px solid rgba(255,255,255,.1);border-radius:11px;background:#0d1122;color:#f4f5fd;font-size:12.5px;resize:vertical}
.nocturne-crypto-wait__dispute-submit{width:100%;height:42px;border:0;border-radius:11px;background:#ff5069;color:#fff;font-size:12.5px;font-weight:700;cursor:pointer}
@media(max-width:575.98px){.nocturne-crypto-wait{padding:22px 18px;border-radius:18px}}
</style>
@endpush

@push('script')
<script>
(() => {
    const root = document.getElementById('crypto-wait');
    if (!root) return;
    const deadline = Number(root.dataset.deadline) || 0;
    const statusUrl = root.dataset.statusUrl;
    const disputeUrl = root.dataset.disputeUrl;
    const historyUrl = root.dataset.historyUrl;
    const unitH = root.querySelector('[data-unit="h"]');
    const unitM = root.querySelector('[data-unit="m"]');
    const unitS = root.querySelector('[data-unit="s"]');
    const stateEl = root.querySelector('.nocturne-crypto-wait__state');
    const pad = n => String(Math.max(0, n)).padStart(2, '0');

    function tick() {
        const remaining = deadline - Date.now();
        const clamped = Math.max(0, remaining);
        unitH.textContent = pad(Math.floor(clamped / 3600000));
        unitM.textContent = pad(Math.floor((clamped % 3600000) / 60000));
        unitS.textContent = pad(Math.floor((clamped % 60000) / 1000));
        if (remaining <= 0) {
            unitH.closest('.nocturne-crypto-wait__countdown').nextElementSibling.textContent = '{{ __('Taking longer than expected - we will notify you the moment it is confirmed.') }}';
        }
    }
    if (deadline) { tick(); setInterval(tick, 1000); }

    function poll() {
        fetch(statusUrl, { headers: { Accept: 'application/json' } })
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (!data) return;
                if (data.status === 'success') {
                    stateEl.classList.add('is-success');
                    stateEl.innerHTML = '<span class="badge">{{ __('Confirmed! Redirecting...') }}</span>';
                    clearInterval(timer);
                    setTimeout(() => { window.location.href = data.history_url || historyUrl; }, 1200);
                } else if (data.status === 'rejected') {
                    stateEl.innerHTML = '<span class="badge badge--danger">{{ __('Rejected') }}</span>';
                    clearInterval(timer);
                } else if (data.disputed) {
                    stateEl.classList.add('is-disputed');
                    stateEl.innerHTML = '<span class="badge">{{ __('Under review (disputed)') }}</span>';
                }
            })
            .catch(() => {});
    }
    poll();
    const timer = setInterval(poll, 5000);

    document.getElementById('crypto-dispute-btn')?.addEventListener('click', () => {
        document.getElementById('crypto-dispute-form')?.classList.toggle('d-none');
    });
    document.getElementById('crypto-dispute-submit')?.addEventListener('click', function () {
        const btn = this;
        btn.disabled = true;
        const reason = document.getElementById('crypto-dispute-reason')?.value || '';
        fetch(disputeUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', Accept: 'application/json' },
            body: JSON.stringify({ reason }),
        }).then(() => { poll(); btn.disabled = false; document.getElementById('crypto-dispute-form').classList.add('d-none'); })
          .catch(() => { btn.disabled = false; });
    });
})();
</script>
@endpush
