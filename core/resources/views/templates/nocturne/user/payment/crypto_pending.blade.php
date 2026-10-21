@extends('Template::layouts.master')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card custom--card">
                <div class="card-body text-center">
                    <div class="nocturne-crypto-wait" id="crypto-wait" data-deadline="{{ $deposit->confirmation_deadline_at?->timestamp * 1000 }}" data-status-url="{{ route('user.deposit.confirmation.status') }}" data-dispute-url="{{ route('user.deposit.confirmation.dispute') }}" data-history-url="{{ route('user.deposit.history') }}">
                        <span class="nocturne-crypto-wait__icon"><i class="las la-hourglass-half"></i></span>
                        <h3 class="mt-3">@lang('Confirming your deposit')</h3>
                        <p class="text-muted">@lang('Our team is reviewing your receipt and paid-from address. This page will update automatically once confirmed.')</p>

                        <div class="nocturne-crypto-wait__countdown">
                            <span data-unit="h">00</span>:<span data-unit="m">00</span>:<span data-unit="s">00</span>
                        </div>
                        <small class="d-block text-muted mb-3">@lang('Target confirmation window')</small>

                        <div class="nocturne-crypto-wait__state" data-state="pending">
                            <span class="badge badge--warning">@lang('Pending review')</span>
                        </div>

                        <button type="button" class="btn btn-outline--danger btn-sm mt-4" id="crypto-dispute-btn"><i class="las la-exclamation-triangle"></i> @lang('Dispute this deposit')</button>

                        <div id="crypto-dispute-form" class="mt-3 d-none text-start">
                            <textarea class="form-control mb-2" id="crypto-dispute-reason" rows="3" placeholder="@lang('Tell us what went wrong (optional)')" maxlength="500"></textarea>
                            <button type="button" class="btn btn--danger w-100" id="crypto-dispute-submit">@lang('Submit dispute')</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('style')
<style>
.nocturne-crypto-wait__icon{display:inline-grid;place-items:center;width:64px;height:64px;border-radius:50%;background:rgba(255,190,65,.12);color:#ffca69;font-size:30px}
.nocturne-crypto-wait__countdown{display:flex;justify-content:center;gap:6px;margin:18px 0 4px;font-size:26px;font-weight:800;color:#f4f5fd}
.nocturne-crypto-wait__countdown span{display:inline-flex;min-width:46px;justify-content:center;padding:6px 8px;border-radius:10px;background:rgba(7,11,27,.5)}
.nocturne-crypto-wait__state.is-success .badge{background:#00e78a!important;color:#062718}
.nocturne-crypto-wait__state.is-disputed .badge{background:#ff5069!important}
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
