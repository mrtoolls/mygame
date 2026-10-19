@php($feedbackFormContent = getContent('feedback_form.content', true)?->data_values)
<section class="nocturne-feedback-submit"><div class="nocturne-feedback-section-head"><span class="nocturne-feedback-kicker"><i class="ph-fill ph-chat-circle-dots"></i> @lang('Your experience')</span><h2>{{ __($feedbackFormContent->heading ?? 'Help us improve') }}</h2><p>{{ __($feedbackFormContent->description ?? 'Share an honest review. Your testimonial will be visible after our team approves it.') }}</p></div>
@auth
    @if($errors->any())<div class="nocturne-feedback-errors">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('feedback.store') }}" enctype="multipart/form-data" class="nocturne-feedback-form">@csrf
        <fieldset class="nocturne-feedback-rating"><legend>@lang('Your rating')</legend><div class="nocturne-feedback-stars">@for($star=5;$star>=1;$star--)<input id="feedback-star-{{ $star }}" type="radio" name="rating" value="{{ $star }}" {{ (string)old('rating','5')===(string)$star?'checked':'' }} required><label for="feedback-star-{{ $star }}" aria-label="{{ $star }} @lang('stars')">★</label>@endfor</div></fieldset>
        <label class="nocturne-feedback-label" for="feedback-message">@lang('Your review')</label><textarea id="feedback-message" name="message" minlength="15" maxlength="1500" required placeholder="@lang('Tell us about your experience…')">{{ old('message') }}</textarea>
        <div class="nocturne-feedback-form__bottom"><label class="nocturne-feedback-upload"><i class="ph ph-paperclip"></i> @lang('Add a photo (optional)')<input type="file" name="image" accept="image/jpeg,image/png"></label><label class="nocturne-feedback-consent"><input type="checkbox" name="public_consent" value="1" required {{ old('public_consent')?'checked':'' }}> @lang('I agree that my review may be shown publicly after approval.')</label><button class="nocturne-feedback-submit-btn" type="submit"><i class="ph-fill ph-paper-plane-tilt"></i> @lang('Submit for review')</button></div>
    </form>
@else
    <a class="nocturne-feedback-submit-btn" href="{{ route('user.login') }}">@lang('Sign in to leave a review') <i class="ph-bold ph-arrow-right"></i></a>
@endauth
</section>
