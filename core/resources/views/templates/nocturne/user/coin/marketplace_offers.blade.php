<section class="cm-market" id="coin-marketplace" aria-labelledby="cm-title" data-max-price="{{ $maxOfferPrice }}" data-price-prefix="{{ $user->currency?->symbol ?: '' }}" data-minimum="{{ $minimumTradeAmount }}" data-maximum="{{ $maximumTradeAmount }}">
    <header class="cm-header">
        <h2 id="cm-title">@lang('Coin marketplace')</h2>
        <button class="cm-filter-open" id="cm-filter-open" type="button" aria-haspopup="dialog" aria-controls="cm-filter" aria-expanded="false">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h9m4 0h3M4 17h3m4 0h9M13 4v6M9 14v6"/></svg><span>@lang('Filter')</span><b class="cm-filter-count" id="cm-filter-count" hidden>0</b>
        </button>
    </header>
    <div class="cm-active-filters" id="cm-active-filters" aria-live="polite"></div>
    <div class="cm-grid" id="cm-grid" role="list">
        @foreach($marketplaceOffers as $offer)
            <article class="cm-card" role="listitem" data-key="{{ $offer['key'] }}" data-kind="{{ $offer['kind'] }}" data-role="{{ $offer['role'] }}" data-verified="{{ $offer['verified'] ? '1' : '0' }}" data-online="{{ $offer['online'] ? '1' : '0' }}" data-amount="{{ $offer['amount'] }}" data-price="{{ $offer['price'] }}" data-trades="{{ $offer['trades'] }}" data-reliability="{{ $offer['reliability'] ?? '' }}" data-release="{{ $offer['release_minutes'] ?? '' }}" data-created="{{ $offer['created'] }}">
                <span class="cm-best" aria-label="@lang('Best price')"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.2 5.2 5.6.5-4.2 3.7 1.2 5.5-4.8-2.9-4.8 2.9 1.2-5.5-4.2-3.7 5.6-.5L12 3Z"/></svg>@lang('Best price')</span>
                <div class="cm-card__seller">
                    <span class="cm-avatar" aria-hidden="true">
                        @if($offer['avatar'])<img src="{{ $offer['avatar'] }}" alt="" loading="lazy" decoding="async">@endif
                        <span class="cm-avatar__fallback">{{ strtoupper(substr($offer['name'], 0, 1)) }}</span>
                        @if($offer['verified'])<i class="cm-avatar__verified" aria-label="@lang('Verified')"><svg viewBox="0 0 16 16" aria-hidden="true"><path d="m4 8 2.5 2.5L12 5"/></svg></i>@endif
                    </span>
                    <span class="cm-card__identity"><strong title="{{ $offer['name'] }}">{{ $offer['name'] }}</strong><span class="cm-card__role"><i></i>{{ __(ucfirst($offer['role'])) }}{{ $offer['online'] ? ' · ' . __('Online') : '' }}</span></span>
                </div>
                <div class="cm-card__amount"><svg viewBox="0 0 24 24" aria-hidden="true"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v7c0 1.7 3.6 3 8 3s8-1.3 8-3v-7"/></svg><strong>{{ number_format($offer['amount'], 0) }}</strong><span>@lang('coins')</span></div>
                <div class="cm-card__price"><strong>{{ $offer['display_currency'] }}{{ number_format($offer['display_price'], 0) }}</strong><span>@lang('per 1,000')</span></div>
                <div class="cm-card__stats"><span><strong>{{ number_format($offer['trades']) }}</strong> @lang('trades')</span><i></i><span class="cm-release">{{ $offer['release_minutes'] ? '~' . number_format($offer['release_minutes']) . ' min' : __('No release data') }}</span><span class="cm-reliable">{{ $offer['reliability'] !== null ? number_format($offer['reliability'], 0) . '% ' . __('reliable') : '' }}</span></div>
                <form class="cm-card__buy" method="post" action="{{ $offer['buy_url'] }}">
                    @csrf
                    @if($offer['buy_mode'] === 'vendor')<input type="hidden" name="vendor_id" value="{{ $offer['id'] }}"><label class="cm-amount-input" hidden><span>@lang('Coins to buy')</span><input type="number" name="amount" min="{{ $minimumTradeAmount }}" max="{{ min($offer['amount'], $offer['trade_limit'], $maximumTradeAmount) }}" step="1" placeholder="{{ number_format($minimumTradeAmount) }}" required></label>
                    @elseif($offer['buy_mode'] === 'agent')<input type="hidden" name="support_agent_id" value="{{ $offer['id'] }}"><label class="cm-amount-input" hidden><span>@lang('Coins to buy')</span><input type="number" name="amount" min="{{ $minimumTradeAmount }}" max="{{ min($offer['amount'], $offer['trade_limit'], $maximumTradeAmount) }}" step="1" placeholder="{{ number_format($minimumTradeAmount) }}" required></label>
                    @endif
                    @if($offer['buy_mode'] === 'listing')<button class="cm-buy" type="submit">@lang('Buy coins')</button>@else<button class="cm-buy" type="button" data-amount-toggle data-confirm-label="@lang('Start trade')">@lang('Buy coins')</button>@endif
                </form>
            </article>
        @endforeach
    </div>
    <div class="cm-empty" id="cm-empty" hidden><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5M8 11h6"/></svg><strong>@lang('No offers match these filters')</strong><button id="cm-empty-reset" type="button">@lang('Reset filters')</button></div>
    <button class="cm-more" id="cm-more" type="button"><span>@lang('View more')</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg><small id="cm-more-count"></small></button>
    <div class="cm-backdrop" id="cm-backdrop" hidden></div>
    <aside class="cm-filter" id="cm-filter" role="dialog" aria-modal="true" aria-labelledby="cm-filter-title" aria-hidden="true" tabindex="-1">
        <div class="cm-filter__head"><span class="cm-sheet-handle" aria-hidden="true"></span><h3 id="cm-filter-title">@lang('Filter offers')</h3><button class="cm-reset" id="cm-reset" type="button">@lang('Reset')</button><button class="cm-close" id="cm-close" type="button" aria-label="@lang('Close filters')"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div>
        <div class="cm-filter__body" id="cm-filter-body">
            <fieldset class="cm-filter-group"><legend>@lang('Sort by')</legend><div class="cm-sort-grid">
                <button type="button" class="cm-sort-option" data-sort-option="price"><i><svg viewBox="0 0 24 24"><path d="M20 13 11 22l-9-9V2h11l9 9-2 2ZM7 7h.01"/></svg></i><strong>@lang('Best price')</strong><small>@lang('Lowest first')</small></button>
                <button type="button" class="cm-sort-option" data-sort-option="fastest"><i><svg viewBox="0 0 24 24"><path d="m13 2-3 9h7L8 22l3-9H4l9-11Z"/></svg></i><strong>@lang('Fastest')</strong><small>@lang('Quick release')</small></button>
                <button type="button" class="cm-sort-option" data-sort-option="reliable"><i><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/></svg></i><strong>@lang('Most reliable')</strong><small>@lang('Top rated')</small></button>
                <button type="button" class="cm-sort-option" data-sort-option="newest"><i><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></i><strong>@lang('Newest')</strong><small>@lang('Just listed')</small></button>
            </div></fieldset>
            <fieldset class="cm-filter-group"><legend>@lang('Offered by')</legend><div class="cm-pills"><button type="button" data-role-filter="all">@lang('All')</button><button type="button" data-role-filter="vendor"><i class="is-vendor"></i>@lang('Vendors')</button><button type="button" data-role-filter="agent"><i class="is-agent"></i>@lang('Agents')</button><button type="button" data-role-filter="member"><i class="is-member"></i>@lang('Members')</button></div></fieldset>
            <fieldset class="cm-filter-group"><legend>@lang('Trust')</legend><label class="cm-switch-row"><span><strong>@lang('Verified only')</strong><small>@lang('Vendors and agents with a verified badge')</small></span><input type="checkbox" id="cm-verified" role="switch"><i></i></label><label class="cm-switch-row"><span><strong>@lang('Online now')</strong><small>@lang('Ready to trade right away')</small></span><input type="checkbox" id="cm-online" role="switch"><i></i></label></fieldset>
            <fieldset class="cm-filter-group"><legend>@lang('Coins available')</legend><div class="cm-pills cm-coin-pills"><button type="button" data-coins="any">@lang('Any')</button><button type="button" data-coins="under50">@lang('Under 50K')</button><button type="button" data-coins="50to200">@lang('50K to 200K')</button><button type="button" data-coins="over200">@lang('200K+')</button></div></fieldset>
            <fieldset class="cm-filter-group"><legend>@lang('Max price per 1,000 coins')</legend><div class="cm-range-value"><span id="cm-price-value"></span><button id="cm-price-clear" type="button">@lang('Any price')</button></div><input id="cm-price-range" type="range" min="0" max="{{ max(1, ceil($maxOfferPrice)) }}" step="1" value="{{ max(1, ceil($maxOfferPrice)) }}"></fieldset>
        </div>
        <footer class="cm-filter__foot"><button class="cm-show" id="cm-show" type="button">@lang('Show') <span id="cm-result-count">0</span> @lang('offers')</button></footer>
    </aside>
</section>
<script src="{{ asset('assets/global/js/coin-marketplace-filters.js') }}?v={{ @filemtime('assets/global/js/coin-marketplace-filters.js') }}" defer></script>
<script src="{{ asset('assets/global/js/coin-marketplace.js') }}?v={{ @filemtime('assets/global/js/coin-marketplace.js') }}" defer></script>
