@php
    $payment = getContent('payment_method.content', true);
    $payments = getContent('payment_method.element', false, null, true);
    $policyPages = getContent('policy_pages.element', false, null, true);
    $socialIcon = getContent('social_icon.element', false, null, true);
    $footer = getContent('footer.content', true);
@endphp
<footer class="footer-area">
    @if ($footer && @$footer->data_values->image)
        <div class="footer-area__thumb">
            <img src="{{ getImage('assets/images/frontend/footer/' . $footer->data_values->image, '1110x420') }}" alt="@lang('image')">
        </div>
    @endif

    <div class="container">
        <div class="nocturne-footer-cta">
            <div class="nocturne-footer-cta__text">
                <strong>@lang('Do you have a question?')</strong>
                <span>@lang('Write us and our support team will help you')</span>
            </div>
            <a class="nocturne-footer-cta__btn" href="{{ route('ticket.index') }}"><i class="las la-headset"></i>@lang('Live Support')</a>
        </div>

        <div class="nocturne-footer-grid">
            <div class="nocturne-footer-col">
                <h6>@lang('Main')</h6>
                <ul>
                    <li><a href="{{ route('games') }}">@lang('Games')</a></li>
                    <li><a href="{{ route('pages', 'promotions') }}">@lang('Promotions')</a></li>
                    <li><a href="{{ route('pages', 'vip-club') }}">@lang('VIP Club')</a></li>
                    <li><a href="{{ route('blog') }}">@lang('News')</a></li>
                </ul>
            </div>
            <div class="nocturne-footer-col">
                <h6>@lang('Team')</h6>
                <ul>
                    <li><a href="{{ route('pages', 'about-us') }}">@lang('About Us')</a></li>
                    <li><a href="{{ route('pages', 'sponsorships') }}">@lang('Sponsorships')</a></li>
                    <li><a href="{{ route('pages', 'licenses-security') }}">@lang('Licenses & Security')</a></li>
                    <li><a href="{{ route('contact') }}">@lang('Contact')</a></li>
                </ul>
            </div>
            <div class="nocturne-footer-col">
                <h6>@lang('Info')</h6>
                <ul>
                    @foreach ($policyPages as $policy)
                        <li><a href="{{ route('policy.pages', @$policy->slug) }}">{{ __(@$policy->data_values->title) }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div class="nocturne-footer-col">
                <h6>@lang('Profile')</h6>
                <ul>
                    @auth
                        <li><a href="{{ route('user.deposit.index') }}">@lang('Deposit')</a></li>
                        <li><a href="{{ route('user.withdraw') }}">@lang('Withdraw')</a></li>
                        <li><a href="{{ route('user.referrals') }}">@lang('Bonuses')</a></li>
                        <li><a href="{{ route('user.profile.setting') }}">@lang('Settings')</a></li>
                    @else
                        <li><a href="{{ route('user.login') }}">@lang('Login')</a></li>
                        @if (gs('registration'))
                            <li><a href="{{ route('user.register') }}">@lang('Create account')</a></li>
                        @endif
                    @endauth
                </ul>
            </div>
            <div class="nocturne-footer-col">
                <div class="nocturne-footer-support">
                    <div class="nocturne-footer-support__head"><i class="las la-headset"></i> @lang('Support') <span>24/7</span></div>
                    <p>@lang('Contact us if you have questions')</p>
                    <a class="btn-write" href="{{ route('contact') }}"><i class="las la-envelope"></i>@lang('Write Us')</a>
                </div>
            </div>
        </div>
    </div>

    <div class="payment-method">
        <div class="container">
            <h4 class="payment-method__title">{{ __(@$payment->data_values->heading) }}</h4>
            <div class="payment-slider">
                @foreach ($payments as $item)
                    <div class="payment-single-item">
                        <div class="payment-item__image">
                            <img src="{{ getImage('assets/images/frontend/payment_method/' . $item->data_values->image, '90x65') }}" alt="@lang('image')">
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="container">
            <div class="footer-bottom__wrapper">
                <a href="{{ route('home') }}" class="footer-bottom__logo">
                    <img src="{{ siteLogo() }}" alt="@lang('image')">
                </a>

                <div class="newsletter">
                    <form class="newsletter__wrapper">
                        <div class="newsletter__input">
                            <input class="form--control" name="email" type="email" placeholder="@lang('Enter email address')" autocomplete="off">
                        </div>
                        <button type="submit" class="newsletter__btn subscribe-btn">@lang('Subscribe')</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="socket-area">
        <div class="container">
            <div class="socket-area__wrapper">
                <p class="socket-area__text"> &copy; @lang('Copyright') @php echo date('Y') @endphp
                    <a href="{{ route('home') }}" class="text--base">{{ gs('site_name') }}</a>. @lang('All rights reserved').</p>
                <ul class="social-list">
                    @foreach ($socialIcon as $social)
                        <li class="social-list__item">
                            <a href="{{ @$social->data_values->url }}" class="social-list__link flex-center" target="_blank">
                                @php echo $social->data_values->social_icon @endphp
                            </a>
                        </li>
                    @endforeach
                </ul>
                <ul class="flex-align socket-nav">
                    @foreach ($policyPages as $policy)
                        <li class="socket-nav__item">
                            <a class="socket-nav__link" href="{{ route('policy.pages', @$policy->slug) }}">{{ __(@$policy->data_values->title) }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</footer>
@push('script')
    <script>
        (function($) {
            "use strict";
            $('.subscribe-btn').on('click', function(e) {
                e.preventDefault()
                var email = $('[name=email]').val();
                $.ajax({
                    headers: {
                        "X-CSRF-TOKEN": "{{ csrf_token() }}",
                    },
                    url: "{{ route('subscribe.post') }}",
                    method: "POST",
                    data: {
                        email: email
                    },
                    success: function(response) {
                        if (response.success) {
                            $('[name=email]').val('')
                            notify('success', response.success);
                        } else {
                            notify('error', response.error);
                        }
                    }
                });
            });
        })(jQuery)
    </script>
@endpush
