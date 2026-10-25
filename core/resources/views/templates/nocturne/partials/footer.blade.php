@php
    $policyPages = getContent('policy_pages.element', false, null, true);
    $socialIcon = getContent('social_icon.element', false, null, true);
    $partnerLogos = getContent('footer_partners.element', false, null, true);
    $footer = getContent('footer.content', true);
@endphp
<footer class="footer-area nocturne-site-footer {{ request()->routeIs('policy.pages') || request()->routeIs('blog') || request()->routeIs('blog.details') ? 'nocturne-footer--show-mobile' : '' }}">
    @if ($footer && @$footer->data_values->image)
        <div class="footer-area__thumb">
            <img src="{{ getImage('assets/images/frontend/footer/' . $footer->data_values->image, '1110x420') }}" alt="@lang('image')">
        </div>
    @endif

    <div class="container">
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
                    <li><a href="{{ route('feedback') }}">@lang('Feedback about us')</a></li>
                    <li><a href="{{ route('contact') }}">@lang('Contact')</a></li>
                </ul>
            </div>
            <div class="nocturne-footer-col">
                <h6>@lang('Info')</h6>
                <ul>
                    @foreach ($policyPages as $policy)
                        <li><a href="{{ route('policy.pages', $policy->slug) }}">{{ __($policy->data_values->title) }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div class="nocturne-footer-col">
                <h6>@lang('Profile')</h6>
                <ul>
                    @auth
                        <li><a href="{{ route('user.deposit.index') }}">{{ gs('coin_mode') ? __('Buy Coins') : __('Deposit') }}</a></li>
                        @if(gs('coin_mode'))
                            @if(auth()->user()->country?->coin_marketplace_enabled)
                                <li><a href="{{ route('user.coin.marketplace') }}">@lang('Coin Marketplace')</a></li>
                            @endif
                        @else
                            <li><a href="{{ route('user.withdraw') }}">@lang('Withdraw')</a></li>
                        @endif
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
                    <div class="nocturne-footer-support__head"><i class="ph-bold ph-headset"></i> @lang('Support') <span>24/7</span></div>
                    <p>@lang('Contact us if you have questions')</p>
                    <a class="btn-write" href="mailto:support@winxpot.com"><i class="ph-bold ph-envelope"></i>support@winxpot.com</a>
                </div>
            </div>
        </div>

        <div class="nocturne-footer-brandline">
            <a href="{{ route('home') }}" class="nocturne-footer-brand" aria-label="Winxpot home">
                <img src="{{ siteLogo() }}" alt="Winxpot">
            </a>
            <div class="nocturne-footer-newsletter">
                <div class="nocturne-footer-newsletter__copy"><strong>@lang('A little more Winxpot')</strong><span>@lang('Get updates and new offers in your inbox.')</span></div>
                <form class="newsletter__wrapper nocturne-footer-newsletter__form">
                    <label class="nocturne-footer-newsletter__input"><i class="ph ph-envelope-simple"></i><input class="form--control" name="email" type="email" placeholder="@lang('Your email address')" autocomplete="email" aria-label="@lang('Your email address')" required></label>
                    <button type="submit" class="newsletter__btn subscribe-btn">@lang('Subscribe') <i class="ph-bold ph-arrow-right"></i></button>
                </form>
            </div>
        </div>

        <div class="nocturne-footer-aboutline">
            <div class="nocturne-footer-copyright">
                <p>&copy; {{ date('Y') }} <a href="{{ route('home') }}">Winxpot</a>. @lang('All rights reserved').</p>
                <span>@lang('Winxpot is an online gaming platform. Availability, features and payment options may vary by location. Please play responsibly and review the terms that apply to you.')</span>
            </div>
            @if ($socialIcon->count())
                <ul class="nocturne-footer-socials">
                    @foreach ($socialIcon as $social)
                        <li><a href="{{ @$social->data_values->url }}" aria-label="{{ strip_tags(@$social->data_values->social_icon) }}" target="_blank" rel="noopener noreferrer">{!! $social->data_values->social_icon !!}</a></li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="nocturne-footer-trust">
            <div class="nocturne-footer-contacts">
                <a href="mailto:support@winxpot.com"><span>@lang('Support')</span> support@winxpot.com</a>
                <a href="mailto:partners@winxpot.com"><span>@lang('Partners')</span> partners@winxpot.com</a>
                <a href="mailto:press@winxpot.com"><span>@lang('Press')</span> press@winxpot.com</a>
            </div>
            <div class="nocturne-footer-trustmarks" aria-label="@lang('Player safety and partner logos')">
                <div class="nocturne-footer-responsible"><i class="ph-bold ph-shield-check"></i><span>@lang('Gamble')<br>@lang('Responsibly')</span></div>
                <div class="nocturne-footer-age" aria-label="18 plus"><span>18</span><b>+</b></div>
                @foreach ($partnerLogos as $partner)
                    <div class="nocturne-footer-partner" title="{{ __($partner->data_values->partner_name ?: 'Partner logo') }}">
                        @if (!empty($partner->data_values->image))
                            <img src="{{ frontendImage('footer_partners', $partner->data_values->image, '180x80') }}" alt="{{ __($partner->data_values->partner_name ?: 'Partner logo') }}" loading="lazy">
                        @else
                            <span>@lang('Partner logo')</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="nocturne-footer-legalnav">
            <span>@lang('Play for entertainment. Set limits that work for you.')</span>
            <ul>
                @foreach ($policyPages as $policy)
                    <li><a href="{{ route('policy.pages', $policy->slug) }}">{{ __($policy->data_values->title) }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>
</footer>
@push('script')
    <script>
        (function($) {
            "use strict";
            $('.nocturne-footer-newsletter__form').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                if (form[0] && !form[0].reportValidity()) return;
                var email = form.find('[name=email]').val();
                $.ajax({
                    headers: { "X-CSRF-TOKEN": "{{ csrf_token() }}" },
                    url: "{{ route('subscribe.post') }}",
                    method: "POST",
                    data: { email: email },
                    success: function(response) {
                        if (response.success) {
                            form.find('[name=email]').val('');
                            notify('success', response.success);
                        } else {
                            notify('error', response.error);
                        }
                    }
                });
            });
        })(jQuery);
    </script>
@endpush
