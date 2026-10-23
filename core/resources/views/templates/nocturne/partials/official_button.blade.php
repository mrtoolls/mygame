@php
    $officialLinks = \Illuminate\Support\Facades\Schema::hasTable('social_links')
        ? \App\Models\SocialLink::active()->get()
        : collect();
@endphp
@if($officialLinks->isNotEmpty())
    <div class="wx-official">
        <button type="button" class="wx-official__btn" data-official-toggle aria-haspopup="true" aria-expanded="false">
            <i class="ph-fill ph-seal-check"></i><span>@lang('Official')</span>
        </button>
        <div class="wx-official__sheet" data-official-sheet hidden>
            <button type="button" class="wx-official__backdrop" data-official-close aria-label="@lang('Close')"></button>
            <div class="wx-official__panel" role="dialog" aria-modal="true" aria-label="@lang('Official pages')">
                <button type="button" class="wx-official__close" data-official-close aria-label="@lang('Close')">&times;</button>
                <h3><i class="ph-fill ph-seal-check"></i>@lang('Official Winxpot Pages')</h3>
                <div class="wx-official__grid">
                    @foreach($officialLinks as $link)
                        <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer" class="wx-official__item wx-official__item--{{ $link->platform }}">
                            <span class="wx-official__icon">
                                @if($link->platform === 'x')
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                                @else
                                    <i class="{{ $link->iconClass() }}"></i>
                                @endif
                            </span>
                            <span>{{ $link->label }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @once
        @push('style')
            <style>
            .wx-official__btn{display:inline-flex;align-items:center;gap:7px;height:46px;padding:0 18px;border:0;border-radius:999px;background:#fff;color:#0b0d1e;font:800 13px/1 "Montserrat",system-ui,sans-serif;letter-spacing:.01em;cursor:pointer;transition:transform .15s ease,box-shadow .2s ease}
            .wx-official__btn i{font-size:16px;color:#00a86b}
            .wx-official__btn:hover{transform:translateY(-2px);box-shadow:0 10px 26px rgba(255,255,255,.18)}
            .wx-official__sheet{position:fixed;inset:0;z-index:1300;display:flex;align-items:flex-end;justify-content:center}
            @media(min-width:641px){.wx-official__sheet{align-items:center}}
            .wx-official__backdrop{position:absolute;inset:0;border:0;background:rgba(5,8,18,.75);backdrop-filter:blur(6px);cursor:pointer}
            .wx-official__panel{position:relative;z-index:1;width:min(440px,100%);max-height:80vh;overflow:auto;padding:26px 22px;border-radius:26px 26px 0 0;background:linear-gradient(165deg,#171d37,#0e1226);border:1px solid rgba(255,255,255,.1);box-shadow:0 -20px 60px rgba(0,0,0,.5)}
            @media(min-width:641px){.wx-official__panel{border-radius:26px;box-shadow:0 30px 70px rgba(0,0,0,.6)}}
            .wx-official__close{position:absolute;top:14px;right:14px;display:grid;place-items:center;width:34px;height:34px;border:0;border-radius:50%;background:rgba(255,255,255,.08);color:#f2f5ff;font-size:18px;cursor:pointer}
            .wx-official__panel h3{display:flex;align-items:center;gap:9px;margin:0 0 18px;color:#f2f5ff;font-size:17px;font-weight:900}
            .wx-official__panel h3 i{color:#00ee88}
            .wx-official__grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
            .wx-official__item{display:flex;align-items:center;gap:10px;padding:13px 14px;border:1px solid rgba(255,255,255,.1);border-radius:16px;background:rgba(255,255,255,.04);color:#f2f5ff;text-decoration:none;font-size:13px;font-weight:700;transition:border-color .15s ease,background .15s ease,transform .15s ease}
            .wx-official__item:hover{transform:translateY(-2px);border-color:rgba(0,238,136,.4);background:rgba(0,238,136,.08)}
            .wx-official__icon{display:grid;place-items:center;width:34px;height:34px;flex:0 0 34px;border-radius:50%;background:rgba(255,255,255,.08);font-size:17px}
            .wx-official__icon svg{width:16px;height:16px;fill:currentColor}
            .wx-official__item--x .wx-official__icon{color:#fff}
            .wx-official__item--facebook .wx-official__icon{color:#1877f2}
            .wx-official__item--instagram .wx-official__icon{color:#e1306c}
            .wx-official__item--discord .wx-official__icon{color:#5865f2}
            .wx-official__item--tiktok .wx-official__icon{color:#fff}
            </style>
        @endpush
        @push('script')
            <script>
            (() => {
                document.addEventListener('click', function (e) {
                    var toggle = e.target.closest('[data-official-toggle]');
                    if (toggle) {
                        var wrap = toggle.closest('.wx-official');
                        var sheet = wrap ? wrap.querySelector('[data-official-sheet]') : null;
                        if (sheet) {
                            sheet.hidden = false;
                            toggle.setAttribute('aria-expanded', 'true');
                        }
                        return;
                    }
                    var close = e.target.closest('[data-official-close]');
                    if (close) {
                        var sheetEl = close.closest('[data-official-sheet]');
                        if (sheetEl) {
                            sheetEl.hidden = true;
                            var wrap2 = sheetEl.closest('.wx-official');
                            var t = wrap2 ? wrap2.querySelector('[data-official-toggle]') : null;
                            if (t) t.setAttribute('aria-expanded', 'false');
                        }
                    }
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key !== 'Escape') return;
                    document.querySelectorAll('[data-official-sheet]:not([hidden])').forEach(function (sheet) {
                        sheet.hidden = true;
                        var wrap = sheet.closest('.wx-official');
                        var t = wrap ? wrap.querySelector('[data-official-toggle]') : null;
                        if (t) t.setAttribute('aria-expanded', 'false');
                    });
                });
            })();
            </script>
        @endpush
    @endonce
@endif
