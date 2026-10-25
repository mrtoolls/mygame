<div class="nocturne-otp" id="nocturne-otp">
    <label class="sr-only" for="nocturne-otp-0">@lang('Verification Code')</label>
    <div class="nocturne-otp__boxes">
        @for ($i = 0; $i < 6; $i++)
            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}" class="nocturne-otp__box" id="nocturne-otp-{{ $i }}" data-index="{{ $i }}">
        @endfor
    </div>
    <input type="hidden" name="code" id="verification-code">
</div>

@push('style')
<style>
    .sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
    .nocturne-otp__boxes{display:grid;grid-template-columns:repeat(6,1fr);gap:8px;max-width:100%}
    .nocturne-otp__box{width:100%;aspect-ratio:1/1;min-width:0;border-radius:12px;border:1.5px solid rgba(255,255,255,.14);background:rgba(255,255,255,.04);color:var(--nocturne-text,#fff);font-size:clamp(16px,5vw,22px);font-weight:800;text-align:center;font-family:inherit;transition:border-color .15s ease,background .15s ease}
    .nocturne-otp__box:focus{outline:0;border-color:#{{ @gs('base_color') ?: '00EF87' }};background:rgba(255,255,255,.07)}
    .nocturne-otp__box.is-filled{border-color:rgba(255,255,255,.3)}
</style>
@endpush

@push('script')
<script>
(function() {
    var wrap = document.getElementById('nocturne-otp');
    if (!wrap) return;
    var boxes = Array.prototype.slice.call(wrap.querySelectorAll('.nocturne-otp__box'));
    var hidden = document.getElementById('verification-code');
    var form = wrap.closest('form');

    function sync() {
        var value = boxes.map(function(box) { return box.value; }).join('');
        hidden.value = value;
        if (value.length === 6 && form) form.requestSubmit ? form.requestSubmit() : form.submit();
    }

    boxes.forEach(function(box, index) {
        box.addEventListener('input', function() {
            box.value = box.value.replace(/[^0-9]/g, '').slice(0, 1);
            box.classList.toggle('is-filled', box.value !== '');
            if (box.value && boxes[index + 1]) boxes[index + 1].focus();
            sync();
        });
        box.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !box.value && boxes[index - 1]) {
                boxes[index - 1].focus();
            }
        });
        box.addEventListener('paste', function(e) {
            var pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '').slice(0, 6);
            if (!pasted) return;
            e.preventDefault();
            pasted.split('').forEach(function(digit, i) {
                if (boxes[i]) { boxes[i].value = digit; boxes[i].classList.add('is-filled'); }
            });
            (boxes[pasted.length - 1] || boxes[boxes.length - 1]).focus();
            sync();
        });
    });

    boxes[0].focus();
})();
</script>
@endpush
