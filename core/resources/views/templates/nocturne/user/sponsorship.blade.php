@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account nocturne-business-promo-page">
    @include('Template::partials.account_nav')
    <main class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Sponsor Winxpot')</span></div>
        <section class="nocturne-business-promo-hero">
            <div><span class="nocturne-vip-kicker"><i class="ph-fill ph-handshake"></i> @lang('WINXPOT EVENT SPONSORSHIP')</span><h1>@lang('Sponsor a Winxpot event')</h1><p>@lang('Tell us how you would like to support our events. Our team reviews every proposal and reaches out using the contact details you provide.')</p></div>
            <span class="nocturne-business-promo-hero__art"><i class="ph-fill ph-handshake"></i><i class="ph-fill ph-sparkle"></i></span>
        </section>

        <div class="nocturne-business-promo-layout">
            <section class="nocturne-business-promo-form-card">
                <div class="nocturne-vip-section-head"><div><span class="nocturne-vip-kicker">@lang('YOUR PROPOSAL')</span><h2>@lang('Sponsorship details')</h2><p>@lang('Submissions are reviewed by our team before any follow-up.')</p></div></div>
                @if ($errors->any())
                    <div class="nocturne-business-promo-errors" role="alert"><strong>@lang('Please review the following')</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                <form method="post" action="{{ route('user.sponsorships.store') }}" enctype="multipart/form-data" class="nocturne-business-promo-form">
                    @csrf
                    <div class="nocturne-business-promo-fields">
                        <label><span>@lang('Full name')</span><input type="text" name="full_name" maxlength="150" value="{{ old('full_name') }}" placeholder="@lang('Your full name')" required></label>
                        <label><span>@lang('Brand name') <small>@lang('Optional')</small></span><input type="text" name="brand_name" maxlength="150" value="{{ old('brand_name') }}" placeholder="@lang('Your brand or company')"></label>
                        <label><span>@lang('Phone number')</span><input type="tel" name="phone" maxlength="30" value="{{ old('phone') }}" placeholder="@lang('Best number to reach you')" required></label>
                        <label><span>@lang('Email address')</span><input type="email" name="email" maxlength="150" value="{{ old('email') }}" placeholder="you@example.com" required></label>
                        <label class="is-wide"><span>@lang('Brand logo') <small>@lang('Optional · JPG, PNG or WebP')</small></span><input type="file" name="logo" accept="image/jpeg,image/png,image/webp"></label>
                        <label class="is-wide"><span>@lang('How would you like to sponsor us?')</span><textarea name="message" rows="6" maxlength="3000" placeholder="@lang('Tell us in full detail: the event(s) you want to support, what you would provide, and anything else we should know.')" required>{{ old('message') }}</textarea></label>
                    </div>
                    <label class="nocturne-sponsor-anon"><input type="checkbox" name="is_anonymous" value="1" @checked(old('is_anonymous'))><span>@lang('Keep my name and brand private - do not mention us publicly even if this sponsorship is accepted.')</span></label>
                    <div class="nocturne-business-promo-consent"><i class="ph-fill ph-shield-check"></i><span>@lang('Your details are only visible to our team and are never shown publicly unless we agree with you directly.')</span></div>
                    <button type="submit" class="nocturne-business-promo-submit"><i class="ph-bold ph-paper-plane-tilt"></i>@lang('Submit proposal')</button>
                </form>
            </section>

            <aside class="nocturne-business-promo-side">
                <article><i class="ph-fill ph-chats-circle"></i><strong>@lang('We follow up directly')</strong><p>@lang('Our team reviews every proposal and reaches out using the phone or email you provide.')</p></article>
                <article><i class="ph-fill ph-medal"></i><strong>@lang('Get featured')</strong><p>@lang('If you would like, your brand and logo can be featured on our public Sponsorships page.')</p></article>
            </aside>
        </div>

        <section class="nocturne-business-promo-history">
            <div class="nocturne-vip-section-head"><div><span class="nocturne-vip-kicker">@lang('YOUR SUBMISSIONS')</span><h2>@lang('Submission history')</h2><p>@lang('Track the status of proposals you have sent us.')</p></div></div>
            <div class="nocturne-business-promo-list">
                @forelse ($submissions as $submission)
                    <article class="nocturne-business-promo-row">
                        @if($submission->logo)
                            <img src="{{ getImage('assets/images/sponsorship-logos/' . $submission->logo) }}" alt="" loading="lazy">
                        @else
                            <span class="nocturne-sponsor-row-icon"><i class="ph-fill ph-handshake"></i></span>
                        @endif
                        <div class="nocturne-business-promo-row__copy"><strong>{{ $submission->brand_name ?: $submission->full_name }}</strong><span>{{ \Illuminate\Support\Str::limit($submission->message, 90) }}</span>@if($submission->admin_note)<small>{{ $submission->admin_note }}</small>@endif</div>
                        <span class="nocturne-business-promo-status is-{{ $submission->status === 'approved' ? 'active' : $submission->status }}">{{ __(ucfirst($submission->status)) }}</span>
                    </article>
                @empty
                    <div class="nocturne-business-promo-empty"><i class="ph-fill ph-handshake"></i><strong>@lang('Your proposals will appear here')</strong><span>@lang('Submit your first sponsorship proposal to get started.')</span></div>
                @endforelse
            </div>
            @if ($submissions->hasPages())<div class="mt-3">{{ $submissions->links() }}</div>@endif
        </section>
    </main>
</div>
@endsection
