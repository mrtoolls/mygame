@php
    $faqContent = getContent('vip_member_faq.content', true)?->data_values;
    $faqs = getContent('vip_member_faq.element', orderById: true);
@endphp
@if($faqs->isNotEmpty())
    <section class="nocturne-section nocturne-vip-faq" aria-labelledby="vip-faq-title">
        <div class="nocturne-vip-section-head"><div><span class="nocturne-vip-kicker">@lang('GOOD TO KNOW')</span><h2 id="vip-faq-title">{{ __($faqContent->heading ?? 'VIP Club FAQ') }}</h2></div></div>
        <div class="nocturne-vip-faq-list">
            @foreach($faqs as $faq)
                <details class="nocturne-vip-faq-item"><summary>{{ __($faq->data_values->question) }}<i class="ph-bold ph-plus"></i></summary><div>{{ __($faq->data_values->answer) }}</div></details>
            @endforeach
        </div>
    </section>
@endif
