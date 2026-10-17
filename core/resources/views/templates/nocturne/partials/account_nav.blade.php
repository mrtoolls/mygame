@auth
<aside class="nocturne-account-nav">
    <div class="nocturne-account-card">
        <div class="nocturne-account-id">
            <span class="nocturne-account-avatar"><i class="las la-user-circle"></i></span>
            <div>
                <strong>{{ auth()->user()->username }}</strong>
                <small>{{ auth()->user()->email }}</small>
            </div>
        </div>
        <nav class="nocturne-account-links">
            <a href="{{ route('user.home') }}" class="{{ request()->routeIs('user.home') ? 'is-active' : '' }}"><i class="las la-user"></i>@lang('Profile')</a>
            <a href="{{ route('user.deposit.index') }}" class="{{ request()->routeIs('user.deposit.*') && !request()->routeIs('user.deposit.history') ? 'is-active' : '' }}"><i class="las la-wallet"></i>@lang('Deposit')</a>
            <a href="{{ route('user.withdraw') }}" class="{{ request()->routeIs('user.withdraw') || request()->routeIs('user.withdraw.preview') ? 'is-active' : '' }}"><i class="las la-money-bill-wave"></i>@lang('Withdraw')</a>
            <a href="{{ route('user.referrals') }}" class="{{ request()->routeIs('user.referrals') ? 'is-active' : '' }}"><i class="las la-gift"></i>@lang('Bonuses')</a>
            <a href="{{ route('user.kyc.form') }}" class="{{ request()->routeIs('user.kyc.*') ? 'is-active' : '' }}"><i class="las la-shield-alt"></i>@lang('Verification')</a>
            <a href="{{ route('user.profile.setting') }}" class="{{ request()->routeIs('user.profile.setting') || request()->routeIs('user.change.password') || request()->routeIs('user.twofactor') ? 'is-active' : '' }}"><i class="las la-cog"></i>@lang('Settings')</a>
            <a href="{{ route('user.transactions') }}" class="{{ request()->routeIs('user.transactions') || request()->routeIs('user.deposit.history') || request()->routeIs('user.withdraw.history') ? 'is-active' : '' }}"><i class="las la-receipt"></i>@lang('Transactions')</a>
        </nav>
    </div>
    <div class="nocturne-account-support">
        <strong>@lang('Have a Question?')</strong>
        <p>@lang("Write us and our support team will help you")</p>
        <a href="{{ route('ticket.index') }}" class="nocturne-account-support-btn"><i class="las la-headset"></i>@lang('Support')</a>
    </div>
</aside>
@endauth
