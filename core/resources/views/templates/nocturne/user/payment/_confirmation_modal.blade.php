{{-- Shared deposit confirmation popup: pay -> waiting (countdown) -> success/error states --}}
<div class="nocturne-wmodal" id="wallet-modal" hidden aria-hidden="true">
    <div class="nocturne-wmodal__backdrop" id="wallet-modal-backdrop"></div>
    <div class="nocturne-wmodal__sheet" role="dialog" aria-modal="true">
        <div class="nocturne-wmodal__handle"></div>
        <button type="button" class="nocturne-wmodal__close" id="wallet-modal-close" aria-label="@lang('Close')"><i class="ph-bold ph-x"></i></button>

        <div class="nocturne-wmodal__head">
            <span class="nocturne-wmodal__icon"><img id="wmodal-thumb" src="" alt="" hidden><i class="ph-fill ph-wallet" id="wmodal-fallback-icon"></i></span>
            <div>
                <strong id="wmodal-method-name">—</strong>
                <small id="wmodal-amount">—</small>
            </div>
        </div>

        <!-- STATE: pay (upload receipt) -->
        <div class="nocturne-wmodal__state" data-state="pay">
            <div class="nocturne-wmodal__error" id="wmodal-error" hidden></div>
            <p class="nocturne-wmodal__hint">@lang('Send the exact amount to the wallet address shown, then attach your payment receipt below to start the confirmation review.')</p>
            <label class="nocturne-wmodal__field">
                <span>@lang('Payment receipt')</span>
                <input type="file" id="wmodal-proof" accept="image/jpeg,image/png" required>
                <span class="nocturne-wmodal__file-btn"><i class="ph-bold ph-upload-simple"></i> <span id="wmodal-file-label">@lang('Choose a file')</span></span>
            </label>
            <label class="nocturne-wmodal__field">
                <span>@lang('Wallet address you paid from')</span>
                <input type="text" id="wmodal-payer-address" placeholder="@lang('Paste the sending address')" required>
            </label>
            <button type="button" class="nocturne-wmodal__submit" id="wmodal-submit"><i class="ph-bold ph-paper-plane-tilt"></i> @lang('Submit for review')</button>
        </div>

        <!-- STATE: waiting (live countdown + dispute) -->
        <div class="nocturne-wmodal__state" data-state="wait" hidden>
            <div class="nocturne-wmodal__ring-wrap">
                <svg class="nocturne-wmodal__ring" viewBox="0 0 120 120">
                    <circle class="nocturne-wmodal__ring-track" cx="60" cy="60" r="52"></circle>
                    <circle class="nocturne-wmodal__ring-fill" id="wmodal-ring-fill" cx="60" cy="60" r="52"></circle>
                </svg>
                <div class="nocturne-wmodal__ring-time" id="wmodal-time">00:00</div>
            </div>
            <h4>@lang('Confirming your deposit')</h4>
            <p class="nocturne-wmodal__hint">@lang('Our team is reviewing your payment. This closes automatically the moment it is confirmed.')</p>
            <span class="nocturne-wmodal__badge" id="wmodal-status-badge">@lang('Pending review')</span>

            <button type="button" class="nocturne-wmodal__dispute-toggle" id="wmodal-dispute-toggle"><i class="ph-bold ph-warning"></i> @lang('Dispute this deposit')</button>
            <div class="nocturne-wmodal__dispute-form" id="wmodal-dispute-form" hidden>
                <textarea id="wmodal-dispute-reason" rows="3" maxlength="500" placeholder="@lang('Tell us what went wrong (optional)')"></textarea>
                <button type="button" class="nocturne-wmodal__submit nocturne-wmodal__submit--danger" id="wmodal-dispute-submit">@lang('Submit dispute')</button>
            </div>
        </div>

        <!-- STATE: success -->
        <div class="nocturne-wmodal__state nocturne-wmodal__state--center" data-state="success" hidden>
            <span class="nocturne-wmodal__success-icon"><i class="ph-bold ph-check"></i></span>
            <h4>@lang('Deposit confirmed!')</h4>
            <p class="nocturne-wmodal__hint">@lang('Your balance has been updated.')</p>
            <button type="button" class="nocturne-wmodal__submit" id="wmodal-success-close">@lang('Done')</button>
        </div>
    </div>
</div>

@push('style')
<style>
.nocturne-wmodal{position:fixed;inset:0;z-index:1200;display:flex;align-items:flex-end;justify-content:center}
.nocturne-wmodal[hidden]{display:none}
.nocturne-wmodal__backdrop{position:absolute;inset:0;background:rgba(5,7,16,.72);backdrop-filter:blur(4px)}
.nocturne-wmodal__sheet{position:relative;z-index:1;width:100%;max-width:440px;max-height:92vh;overflow-y:auto;padding:14px 24px 26px;border-radius:26px 26px 0 0;background:linear-gradient(165deg,#181f3c,#10152a 80%);box-shadow:0 -20px 60px rgba(0,0,0,.5);animation:wmodal-up .25s ease}
@keyframes wmodal-up{from{transform:translateY(24px);opacity:0}to{transform:translateY(0);opacity:1}}
@media(min-width:576px){.nocturne-wmodal{align-items:center}.nocturne-wmodal__sheet{border-radius:26px;margin:20px}}
.nocturne-wmodal__handle{width:40px;height:4px;margin:4px auto 14px;border-radius:99px;background:rgba(255,255,255,.18)}
@media(min-width:576px){.nocturne-wmodal__handle{display:none}}
.nocturne-wmodal__close{position:absolute;top:14px;right:16px;display:grid;place-items:center;width:34px;height:34px;border:0;border-radius:50%;background:rgba(255,255,255,.06);color:#dfe2f4;cursor:pointer}
.nocturne-wmodal__close:hover{background:rgba(255,255,255,.12)}
.nocturne-wmodal__head{display:flex;align-items:center;gap:12px;margin-bottom:18px}
.nocturne-wmodal__icon{display:grid;place-items:center;flex:0 0 46px;width:46px;height:46px;border-radius:50%;background:rgba(0,231,138,.12);color:#00e78a;overflow:hidden;font-size:22px}
.nocturne-wmodal__icon img{width:100%;height:100%;object-fit:cover}
.nocturne-wmodal__head strong{display:block;color:#f5f6ff;font-size:16px;font-weight:850}
.nocturne-wmodal__head small{color:#858eaf;font-size:12.5px}
.nocturne-wmodal__hint{margin:0 0 16px;color:#929bc0;font-size:13px;line-height:1.6}
.nocturne-wmodal__error{margin-bottom:14px;padding:11px 14px;border:1px solid rgba(255,80,105,.3);border-radius:12px;background:rgba(255,80,105,.08);color:#ff9aaa;font-size:12.5px}
.nocturne-wmodal__field{display:block;margin-bottom:14px}
.nocturne-wmodal__field>span{display:block;margin-bottom:7px;color:#aab2d4;font-size:12.5px;font-weight:700}
.nocturne-wmodal__field input[type=text]{width:100%;min-height:48px;padding:0 14px;border:1px solid rgba(133,142,175,.22);border-radius:13px;background:#0d1225;color:#f5f6ff;font:inherit;box-sizing:border-box;outline:0}
.nocturne-wmodal__field input[type=text]:focus{border-color:#00e78a}
.nocturne-wmodal__field input[type=file]{position:absolute;width:1px;height:1px;opacity:0}
.nocturne-wmodal__file-btn{display:flex;align-items:center;gap:9px;min-height:48px;padding:0 14px;border:1px dashed rgba(133,142,175,.3);border-radius:13px;background:#0d1225;color:#aab2d4;font-size:13px;cursor:pointer}
.nocturne-wmodal__field:has(input:focus) .nocturne-wmodal__file-btn{border-color:#00e78a}
.nocturne-wmodal__submit{display:flex;align-items:center;justify-content:center;gap:9px;width:100%;min-height:50px;margin-top:4px;border:0;border-radius:14px;background:#00e78a;color:#062a23;font-size:14.5px;font-weight:850;cursor:pointer}
.nocturne-wmodal__submit:disabled{opacity:.5;cursor:wait}
.nocturne-wmodal__submit--danger{margin-top:10px;background:#ff5069;color:#fff}
.nocturne-wmodal__ring-wrap{position:relative;display:grid;place-items:center;width:140px;height:140px;margin:6px auto 16px}
.nocturne-wmodal__ring{width:100%;height:100%;transform:rotate(-90deg)}
.nocturne-wmodal__ring-track{fill:none;stroke:rgba(255,255,255,.08);stroke-width:8}
.nocturne-wmodal__ring-fill{fill:none;stroke:#00e78a;stroke-width:8;stroke-linecap:round;stroke-dasharray:327;stroke-dashoffset:0;transition:stroke-dashoffset 1s linear}
.nocturne-wmodal__ring-time{position:absolute;color:#f5f6ff;font-size:22px;font-weight:850;font-variant-numeric:tabular-nums}
.nocturne-wmodal__state h4{margin:0 0 6px;color:#f5f6ff;font-size:17px;font-weight:850;text-align:center}
.nocturne-wmodal__state--center{text-align:center}
.nocturne-wmodal__badge{display:block;width:max-content;margin:6px auto 18px;padding:6px 14px;border-radius:999px;background:rgba(255,190,65,.14);color:#ffca69;font-size:11.5px;font-weight:800}
.nocturne-wmodal__badge.is-disputed{background:rgba(255,80,105,.14);color:#ff9aaa}
.nocturne-wmodal__dispute-toggle{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;min-height:42px;border:1px solid rgba(255,80,105,.3);border-radius:12px;background:transparent;color:#ff9aaa;font-size:12.5px;font-weight:750;cursor:pointer}
.nocturne-wmodal__dispute-form{margin-top:12px}
.nocturne-wmodal__dispute-form textarea{width:100%;padding:11px;border:1px solid rgba(133,142,175,.22);border-radius:12px;background:#0d1225;color:#f5f6ff;font:inherit;box-sizing:border-box;resize:vertical}
.nocturne-wmodal__success-icon{display:grid;place-items:center;width:64px;height:64px;margin:0 auto 14px;border-radius:50%;background:rgba(0,231,138,.14);color:#00e78a;font-size:30px}
</style>
@endpush

@push('script')
<script>
(function() {
    var modal = document.getElementById('wallet-modal');
    if (!modal) return;

    var statusUrl = "{{ route('user.deposit.confirmation.status') }}";
    var submitUrl = "{{ route('user.deposit.confirmation.submit') }}";
    var disputeUrl = "{{ route('user.deposit.confirmation.dispute') }}";
    var insertUrl = "{{ route('user.deposit.insert') }}";
    var csrf = "{{ csrf_token() }}";
    var pollTimer = null;
    var countdownTimer = null;
    var slaSeconds = 0;
    var deadlineAt = 0;

    function setState(name) {
        modal.querySelectorAll('.nocturne-wmodal__state').forEach(function(el) {
            el.hidden = el.dataset.state !== name;
        });
    }
    function open() { modal.hidden = false; modal.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden'; }
    function close() {
        modal.hidden = true; modal.setAttribute('aria-hidden', 'true'); document.body.style.overflow = '';
        clearInterval(pollTimer); clearInterval(countdownTimer);
    }
    document.getElementById('wallet-modal-close').addEventListener('click', close);
    document.getElementById('wallet-modal-backdrop').addEventListener('click', close);

    document.getElementById('wmodal-proof').addEventListener('change', function() {
        document.getElementById('wmodal-file-label').textContent = this.files[0] ? this.files[0].name : "{{ __('Choose a file') }}";
    });

    function showError(message) {
        var el = document.getElementById('wmodal-error');
        el.textContent = message; el.hidden = false;
    }

    function startDeposit($form, gateway) {
        document.getElementById('wmodal-error').hidden = true;
        document.getElementById('wallet-modal-close').style.display = '';
        document.getElementById('wmodal-method-name').textContent = $form.find('.nocturne-wallet-pick__name').text() || gateway.name;
        var amount = parseFloat($form.find('.amount').val()) || 0;
        document.getElementById('wmodal-amount').textContent = amount.toFixed(2) + ' {{ gs("cur_text") }}';
        var thumb = document.querySelector('.nocturne-wallet-pick__thumb').getAttribute('src');
        if (thumb) { document.getElementById('wmodal-thumb').src = thumb; document.getElementById('wmodal-thumb').hidden = false; document.getElementById('wmodal-fallback-icon').style.display = 'none'; }

        fetch(insertUrl, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: new FormData($form[0]),
        }).then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
          .then(function(res) {
              if (!res.ok) { throw new Error(res.data.message || "{{ __('Something went wrong, please try again.') }}"); }
              setState('pay');
              open();
          })
          .catch(function(err) { alert(err.message || "{{ __('Something went wrong, please try again.') }}"); });
    }

    document.getElementById('wmodal-submit').addEventListener('click', function() {
        var btn = this;
        var proof = document.getElementById('wmodal-proof').files[0];
        var payerAddress = document.getElementById('wmodal-payer-address').value.trim();
        document.getElementById('wmodal-error').hidden = true;
        if (!proof) { showError("{{ __('Please attach a payment receipt.') }}"); return; }
        if (!payerAddress) { showError("{{ __('Please enter the wallet address you paid from.') }}"); return; }

        var fd = new FormData();
        fd.append('payment_proof', proof);
        fd.append('payer_wallet_address', payerAddress);
        btn.disabled = true;

        fetch(submitUrl, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: fd,
        }).then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
          .then(function(res) {
              btn.disabled = false;
              if (!res.ok) { showError(res.data.message || "{{ __('Something went wrong, please try again.') }}"); return; }
              deadlineAt = res.data.deadline;
              slaSeconds = (res.data.sla_minutes || 15) * 60;
              setState('wait');
              startCountdown();
              startPolling();
          })
          .catch(function() { btn.disabled = false; showError("{{ __('Something went wrong, please try again.') }}"); });
    });

    function startCountdown() {
        var ring = document.getElementById('wmodal-ring-fill');
        var circumference = 2 * Math.PI * 52;
        function tick() {
            var remaining = Math.max(0, deadlineAt - Date.now());
            var mins = Math.floor(remaining / 60000);
            var secs = Math.floor((remaining % 60000) / 1000);
            document.getElementById('wmodal-time').textContent = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
            var pct = slaSeconds ? Math.max(0, remaining / 1000 / slaSeconds) : 0;
            ring.style.strokeDashoffset = (circumference * (1 - pct)).toFixed(1);
            if (remaining <= 0) clearInterval(countdownTimer);
        }
        tick();
        countdownTimer = setInterval(tick, 1000);
    }

    function startPolling() {
        function poll() {
            fetch(statusUrl, { headers: { Accept: 'application/json' } })
                .then(function(r) { return r.ok ? r.json() : null; })
                .then(function(data) {
                    if (!data) return;
                    if (data.status === 'success') {
                        clearInterval(pollTimer); clearInterval(countdownTimer);
                        setState('success');
                        document.getElementById('wallet-modal-close').style.display = 'none';
                    } else if (data.status === 'rejected') {
                        clearInterval(pollTimer); clearInterval(countdownTimer);
                        document.getElementById('wmodal-status-badge').textContent = "{{ __('Rejected') }}";
                        document.getElementById('wmodal-status-badge').classList.add('is-disputed');
                    } else if (data.disputed) {
                        document.getElementById('wmodal-status-badge').textContent = "{{ __('Under review (disputed)') }}";
                        document.getElementById('wmodal-status-badge').classList.add('is-disputed');
                    }
                }).catch(function() {});
        }
        poll();
        pollTimer = setInterval(poll, 5000);
    }

    document.getElementById('wmodal-dispute-toggle').addEventListener('click', function() {
        document.getElementById('wmodal-dispute-form').hidden = !document.getElementById('wmodal-dispute-form').hidden;
    });
    document.getElementById('wmodal-dispute-submit').addEventListener('click', function() {
        var btn = this;
        btn.disabled = true;
        fetch(disputeUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ reason: document.getElementById('wmodal-dispute-reason').value }),
        }).then(function() {
            btn.disabled = false;
            document.getElementById('wmodal-dispute-form').hidden = true;
        }).catch(function() { btn.disabled = false; });
    });

    document.getElementById('wmodal-success-close').addEventListener('click', function() {
        close();
        window.location.href = "{{ route('user.deposit.index') }}";
    });

    window.WalletModal = { startDeposit: startDeposit };
})();
</script>
@endpush
