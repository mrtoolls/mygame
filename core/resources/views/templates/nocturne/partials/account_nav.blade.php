@auth
<aside class="nocturne-account-nav">
    <div class="nocturne-account-card">
        <div class="nocturne-account-id">
            <span class="nocturne-account-avatar"><i class="ph-bold ph-user-circle"></i></span>
            <div>
                <strong>{{ auth()->user()->username }}</strong>
                <small>{{ auth()->user()->email }}</small>
            </div>
        </div>
        <nav class="nocturne-account-links">
            <a href="{{ route('user.home') }}" class="{{ request()->routeIs('user.home') ? 'is-active' : '' }}"><i class="ph-bold ph-user"></i>@lang('Profile')</a>
            <a href="{{ route('user.deposit.index') }}" class="{{ request()->routeIs('user.deposit.*') && !request()->routeIs('user.deposit.history') ? 'is-active' : '' }}"><i class="ph-bold ph-wallet"></i>@lang('Deposit')</a>
            <a href="{{ route('user.withdraw') }}" class="{{ request()->routeIs('user.withdraw') || request()->routeIs('user.withdraw.preview') ? 'is-active' : '' }}"><i class="ph-bold ph-money-wavy"></i>@lang('Withdraw')</a>
            <a href="{{ route('user.referrals') }}" class="{{ request()->routeIs('user.referrals') ? 'is-active' : '' }}"><i class="ph-bold ph-gift"></i>@lang('Bonuses')</a>
            <a href="{{ route('user.gifts.index') }}" class="{{ request()->routeIs('user.gifts.*') ? 'is-active' : '' }}"><i class="ph-bold ph-package"></i>@lang('My Gifts')</a>
            <a href="{{ route('user.vip.index') }}" class="{{ request()->routeIs('user.vip.*') ? 'is-active' : '' }}"><i class="ph-bold ph-crown"></i>@lang('VIP Club')</a>
            <a href="{{ route('user.business-promotions.index') }}" class="{{ request()->routeIs('user.business-promotions.*') ? 'is-active' : '' }}"><i class="ph-bold ph-megaphone-simple"></i>@lang('Promote a Business')</a>
            <a href="{{ route('user.sponsorships.index') }}" class="{{ request()->routeIs('user.sponsorships.*') ? 'is-active' : '' }}"><i class="ph-bold ph-handshake"></i>@lang('Sponsor Winxpot')</a>
            <a href="{{ route('user.kyc.form') }}" class="{{ request()->routeIs('user.kyc.*') ? 'is-active' : '' }}"><i class="ph-bold ph-shield-check"></i>@lang('Verification')</a>
            <a href="{{ route('user.profile.setting') }}" class="{{ request()->routeIs('user.profile.setting') || request()->routeIs('user.change.password') || request()->routeIs('user.twofactor') ? 'is-active' : '' }}"><i class="ph-bold ph-gear"></i>@lang('Settings')</a>
            <a href="{{ route('user.transactions') }}" class="{{ request()->routeIs('user.transactions') || request()->routeIs('user.deposit.history') || request()->routeIs('user.withdraw.history') ? 'is-active' : '' }}"><i class="ph-bold ph-receipt"></i>@lang('Transactions')</a>
        </nav>
    </div>
    <div class="nocturne-account-support">
        <strong>@lang('Have a Question?')</strong>
        <p>@lang("Write us and our support team will help you")</p>
        <a href="{{ route('ticket.index') }}" class="nocturne-account-support-btn"><i class="ph-bold ph-headset"></i>@lang('Support')</a>
    </div>
</aside>
@endauth
