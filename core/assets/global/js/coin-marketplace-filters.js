(function (root, factory) {
    const filters = factory();
    if (typeof module === 'object' && module.exports) module.exports = filters;
    else root.CoinMarketplaceFilters = filters;
})(typeof globalThis === 'undefined' ? this : globalThis, function () {
    'use strict';

    const value = (offer, key) => Number(offer[key]) || 0;
    const scored = (offer, key, fallback) => offer[key] === '' || offer[key] == null ? fallback : Number(offer[key]);

    function filterAndSort(offers, state, maxPrice) {
        const filtered = offers.filter(offer => {
            if (state.role !== 'all' && offer.role !== state.role) return false;
            if (state.verified && !offer.verified) return false;
            if (state.online && !offer.online) return false;
            const amount = value(offer, 'amount');
            if (state.coins === 'under50' && amount >= 50000) return false;
            if (state.coins === '50to200' && (amount < 50000 || amount > 200000)) return false;
            if (state.coins === 'over200' && amount < 200000) return false;
            if (state.price < maxPrice && value(offer, 'price') > state.price) return false;
            return true;
        });

        filtered.sort((a, b) => {
            if (state.sort === 'fastest') return scored(a, 'release', Infinity) - scored(b, 'release', Infinity) || value(a, 'price') - value(b, 'price');
            if (state.sort === 'reliable') return scored(b, 'reliability', -1) - scored(a, 'reliability', -1) || value(a, 'price') - value(b, 'price');
            if (state.sort === 'newest') return value(b, 'created') - value(a, 'created');
            return value(a, 'price') - value(b, 'price');
        });
        return filtered;
    }

    function columnsForWidth(width) {
        return width >= 1100 ? 4 : width >= 768 ? 3 : 2;
    }

    function page(offers, rows, columns) {
        const visibleCount = Math.min(offers.length, rows * columns);
        return { visible: offers.slice(0, visibleCount), remaining: Math.max(0, offers.length - visibleCount) };
    }

    return { filterAndSort, columnsForWidth, page };
});
