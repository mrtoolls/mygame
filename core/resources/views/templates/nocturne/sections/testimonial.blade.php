@php
    $testimonialContent = getContent('testimonial.content', true);
    $testimonials = getContent('testimonial.element', orderById: true);
@endphp
@if ($testimonials->count())
    <section class="nocturne-section">
        <div class="nocturne-section__head">
            <h2>{{ __(@$testimonialContent->data_values->heading ?: 'Reviews') }}</h2>
            <p>{{ __(@$testimonialContent->data_values->description) }}</p>
        </div>
        <div class="nocturne-review-grid">
            @foreach ($testimonials as $testimonial)
                <div class="nocturne-review-card">
                    <div class="nocturne-review-card__head">
                        <img src="{{ getImage('assets/images/frontend/testimonial/' . @$testimonial->data_values->image, '90x80') }}" alt="@lang('image')">
                        <div>
                            <strong>{{ __(@$testimonial->data_values->name) }}</strong>
                            <span class="nocturne-review-card__stars"><i class="ph-bold ph-star"></i><i class="ph-bold ph-star"></i><i class="ph-bold ph-star"></i><i class="ph-bold ph-star"></i><i class="ph-bold ph-star"></i></span>
                        </div>
                    </div>
                    <p>{{ __(@$testimonial->data_values->quote) }}</p>
                </div>
            @endforeach
        </div>
    </section>
@endif
