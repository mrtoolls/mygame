const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const filterContext = {};
vm.runInNewContext(fs.readFileSync(require.resolve('../assets/global/js/coin-marketplace-filters.js'), 'utf8'), filterContext);
const { filterAndSort, columnsForWidth, page } = filterContext.CoinMarketplaceFilters;

const offers = [
    { id: 'vendor', role: 'vendor', verified: true, online: true, amount: 120000, price: 13650, release: 5, reliability: 92, created: 10 },
    { id: 'agent', role: 'agent', verified: true, online: false, amount: 30000, price: 13690, release: '', reliability: 78, created: 30 },
    { id: 'member-a', role: 'member', verified: false, online: true, amount: 45000, price: 13700, release: '', reliability: '', created: 20 },
    { id: 'member-b', role: 'member', verified: false, online: false, amount: 250000, price: 13720, release: '', reliability: '', created: 40 },
    { id: 'vendor-b', role: 'vendor', verified: true, online: false, amount: 600000, price: 13740, release: 8, reliability: 85, created: 50 },
];
const defaults = { sort: 'price', role: 'all', verified: false, online: false, coins: 'any', price: 13740 };

test('best price sorts by normalized price and marks no listing data as needed', () => {
    assert.deepEqual(filterAndSort(offers, defaults, 13740).map(offer => offer.id), ['vendor', 'agent', 'member-a', 'member-b', 'vendor-b']);
});

test('seller, trust, online, coin bands, and max price filters compose', () => {
    const state = { ...defaults, role: 'vendor', verified: true, online: true, coins: '50to200', price: 13700 };
    assert.deepEqual(filterAndSort(offers, state, 13740).map(offer => offer.id), ['vendor']);
});

test('fastest and most reliable sorting place offers with no matching metrics last', () => {
    assert.deepEqual(filterAndSort(offers, { ...defaults, sort: 'fastest' }, 13740).map(offer => offer.id), ['vendor', 'vendor-b', 'agent', 'member-a', 'member-b']);
    assert.deepEqual(filterAndSort(offers, { ...defaults, sort: 'reliable' }, 13740).map(offer => offer.id), ['vendor', 'vendor-b', 'agent', 'member-a', 'member-b']);
});

test('newest sort uses listing timestamps', () => {
    assert.deepEqual(filterAndSort(offers, { ...defaults, sort: 'newest' }, 13740).map(offer => offer.id), ['vendor-b', 'member-b', 'agent', 'member-a', 'vendor']);
});

test('responsive grid breakpoints select two, three, then four columns', () => {
    assert.deepEqual([359, 390, 767, 768, 1099, 1100, 1440].map(columnsForWidth), [2, 2, 2, 3, 3, 4, 4]);
});

test('view more reveals two rows and reports exact remaining count for odd totals', () => {
    const first = page(offers, 2, 2);
    assert.equal(first.visible.length, 4);
    assert.equal(first.remaining, 1);
    const last = page(offers, 4, 2);
    assert.equal(last.visible.length, 5);
    assert.equal(last.remaining, 0);
    assert.equal(page([], 2, 4).remaining, 0);
});
