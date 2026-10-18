@php $priority = getContent('about_priority.content', true)?->data_values; @endphp
<section class="nocturne-about-section">
    <div class="nocturne-about-section-title"><i class="ph-fill ph-seal-check"></i><h2>{{ __($priority->heading ?? 'Our Priority') }}</h2></div>
    <div class="nocturne-about-priority">
        <div class="nocturne-about-priority__copy">
            <h3>Our Priority is the Safety of Every Player</h3>
            <div class="nocturne-about-founder">
                <span class="nocturne-about-founder__portrait">
                    @if(!empty($priority->founder_image))<img src="{{ frontendImage('about_priority', $priority->founder_image, '160x160') }}" alt="{{ __($priority->founder_name ?? 'Winxpot representative') }}">@else<i class="ph-fill ph-user"></i><small>Portrait here</small>@endif
                </span>
                <div><strong>{{ __($priority->founder_name ?? '') }}</strong><span>{{ __($priority->founder_role ?? '') }}</span></div>
            </div>
            <blockquote>{{ __($priority->founder_message ?? '') }}</blockquote>
            <div class="nocturne-about-signature">
                @if(!empty($priority->signature_image))<img src="{{ frontendImage('about_priority', $priority->signature_image, '500x180') }}" alt="{{ __($priority->founder_name ?? 'Signature') }} signature">@else<span>Signature image here</span>@endif
            </div>
        </div>
        <div class="nocturne-about-priority__art">
            <span class="nocturne-about-priority__decoration"><i class="ph-fill ph-lock-key"></i></span>
            <div class="nocturne-about-license-card">
                @if(!empty($priority->license_document))
                    <button type="button" class="nocturne-about-license-card__preview" data-license-open aria-label="View license document">
                        <img src="{{ frontendImage('about_priority', $priority->license_document, '900x1200') }}" alt="{{ __($priority->license_label ?? 'License document') }}">
                        <span class="nocturne-about-license-card__hover"><i class="ph-bold ph-eye"></i><b>View document</b></span>
                    </button>
                    <button type="button" class="nocturne-about-license-card__expand" data-license-open aria-label="Open license document larger"><i class="ph-bold ph-arrows-out-simple"></i></button>
                @else
                    <div class="nocturne-about-license-card__empty"><i class="ph-bold ph-file-image"></i><strong>License document image here</strong><span>Upload the document in the About Us page content.</span></div>
                @endif
                <div class="nocturne-about-license-card__meta"><strong>{{ __($priority->license_label ?? 'License document') }}</strong><span>{{ __($priority->license_number ?? 'License details editable in Admin') }}</span></div>
            </div>
            <span class="nocturne-about-priority__pen"><i class="ph-fill ph-pen-nib"></i></span>
        </div>
    </div>
    @if(!empty($priority->license_document))
        <dialog class="nocturne-about-license-dialog" aria-label="License document viewer">
            <button type="button" class="nocturne-about-license-dialog__close" aria-label="Close license document"><i class="ph-bold ph-x"></i></button>
            <img src="{{ frontendImage('about_priority', $priority->license_document, '900x1200') }}" alt="{{ __($priority->license_label ?? 'License document') }}">
            <span>{{ __($priority->license_label ?? 'License document') }} @if(!empty($priority->license_number))<small> · {{ __($priority->license_number) }}</small>@endif</span>
        </dialog>
    @endif
</section>
@if(!empty($priority->license_document))
    @push('script')
    <script>
    (() => {
        const dialog = document.querySelector('.nocturne-about-license-dialog');
        if (!dialog) return;
        document.querySelectorAll('[data-license-open]').forEach((button) => button.addEventListener('click', () => dialog.showModal()));
        dialog.querySelector('.nocturne-about-license-dialog__close').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    })();
    </script>
    @endpush
@endif
