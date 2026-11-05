(() => {
    const root = document.getElementById('coin-marketplace');
    if (!root) return;

    const grid = root.querySelector('#cm-grid');
    const originalCards = [...grid.querySelectorAll('.cm-card')];
    const drawer = root.querySelector('#cm-filter');
    const backdrop = root.querySelector('#cm-backdrop');
    const openButton = root.querySelector('#cm-filter-open');
    const closeButton = root.querySelector('#cm-close');
    const maxPrice = Number(root.dataset.maxPrice) || 0;
    const filterTools = window.CoinMarketplaceFilters;
    const state = { sort: 'price', role: 'all', verified: false, online: false, coins: 'any', price: maxPrice, rows: 2, columns: 0 };
    let backdropTimer = 0;
    const params = new URLSearchParams(location.search);
    const allowedSorts = ['price', 'fastest', 'reliable', 'newest'];
    const allowedRoles = ['all', 'vendor', 'agent', 'member'];
    if (allowedSorts.includes(params.get('sort'))) state.sort = params.get('sort');
    if (allowedRoles.includes(params.get('seller'))) state.role = params.get('seller');
    state.verified = params.get('verified') === '1';
    state.online = params.get('online') === '1';
    if (['under50', '50to200', 'over200'].includes(params.get('coins'))) state.coins = params.get('coins');
    if (params.has('max_price')) state.price = Math.max(0, Math.min(maxPrice, Number(params.get('max_price')) || maxPrice));

    const priceOf = card => Number(card.dataset.price) || 0;
    let visibleCards = [];

    function columns() { return filterTools.columnsForWidth(window.innerWidth); }

    function writeUrl() {
        const url = new URL(location.href);
        ['sort', 'seller', 'verified', 'online', 'coins', 'max_price'].forEach(key => url.searchParams.delete(key));
        if (state.sort !== 'price') url.searchParams.set('sort', state.sort);
        if (state.role !== 'all') url.searchParams.set('seller', state.role);
        if (state.verified) url.searchParams.set('verified', '1');
        if (state.online) url.searchParams.set('online', '1');
        if (state.coins !== 'any') url.searchParams.set('coins', state.coins);
        if (state.price < maxPrice) url.searchParams.set('max_price', String(state.price));
        history.replaceState({}, '', url);
    }

    function activeCount() {
        return (state.sort !== 'price' ? 1 : 0) + (state.role !== 'all' ? 1 : 0) + Number(state.verified) + Number(state.online) + Number(state.coins !== 'any') + Number(state.price < maxPrice);
    }

    function renderChips() {
        const wrap = root.querySelector('#cm-active-filters');
        const values = [];
        if (state.sort !== 'price') values.push(['sort', ({ fastest: 'Fastest', reliable: 'Most reliable', newest: 'Newest' })[state.sort]]);
        if (state.role !== 'all') values.push(['seller', ({ vendor: 'Vendors', agent: 'Agents', member: 'Members' })[state.role]]);
        if (state.verified) values.push(['verified', 'Verified only']);
        if (state.online) values.push(['online', 'Online now']);
        if (state.coins !== 'any') values.push(['coins', ({ under50: 'Under 50K', '50to200': '50K to 200K', over200: '200K+' })[state.coins]]);
        if (state.price < maxPrice) values.push(['max_price', `Up to ${root.dataset.pricePrefix || ''}${new Intl.NumberFormat().format(state.price)}`]);
        wrap.replaceChildren();
        values.forEach(([key, title]) => {
            const chip = document.createElement('button');
            chip.type = 'button'; chip.className = 'cm-chip'; chip.dataset.removeFilter = key;
            chip.innerHTML = `<span>${title}</span><b aria-hidden="true">×</b>`;
            wrap.append(chip);
        });
        if (values.length >= 2) {
            const clear = document.createElement('button'); clear.type = 'button'; clear.className = 'cm-clear-all'; clear.dataset.clearAll = '1'; clear.textContent = 'Clear all'; wrap.append(clear);
        }
        const count = root.querySelector('#cm-filter-count');
        count.textContent = String(values.length); count.hidden = values.length === 0;
    }

    function updateControls() {
        root.querySelectorAll('[data-sort-option]').forEach(button => {
            button.classList.toggle('is-selected', button.dataset.sortOption === state.sort);
            button.setAttribute('aria-pressed', button.dataset.sortOption === state.sort ? 'true' : 'false');
        });
        root.querySelectorAll('[data-role-filter]').forEach(button => {
            button.classList.toggle('is-selected', button.dataset.roleFilter === state.role);
            button.setAttribute('aria-pressed', button.dataset.roleFilter === state.role ? 'true' : 'false');
        });
        root.querySelectorAll('[data-coins]').forEach(button => {
            button.classList.toggle('is-selected', button.dataset.coins === state.coins);
            button.setAttribute('aria-pressed', button.dataset.coins === state.coins ? 'true' : 'false');
        });
        root.querySelector('#cm-verified').checked = state.verified;
        root.querySelector('#cm-online').checked = state.online;
        const range = root.querySelector('#cm-price-range'); range.value = String(state.price);
        root.querySelector('#cm-price-value').textContent = state.price < maxPrice ? `${root.dataset.pricePrefix || ''}${new Intl.NumberFormat().format(state.price)}` : 'Any price';
    }

    function render(resetRows = false) {
        if (resetRows) state.rows = 2;
        const nextColumns = columns();
        if (state.columns && state.columns !== nextColumns) state.rows = 2;
        state.columns = nextColumns;
        const offers = originalCards.map(card => ({
            card, role: card.dataset.role, verified: card.dataset.verified === '1', online: card.dataset.online === '1',
            amount: card.dataset.amount, price: card.dataset.price, trades: card.dataset.trades,
            release: card.dataset.release, reliability: card.dataset.reliability, created: card.dataset.created,
        }));
        visibleCards = filterTools.filterAndSort(offers, state, maxPrice).map(offer => offer.card);
        const bestPrice = Math.min(...visibleCards.map(priceOf));
        const page = filterTools.page(visibleCards, state.rows, state.columns);
        visibleCards.forEach((card, index) => {
            card.classList.toggle('is-best', priceOf(card) === bestPrice && visibleCards.length > 0);
            card.hidden = index >= page.visible.length;
            grid.append(card);
        });
        root.querySelector('#cm-empty').hidden = visibleCards.length > 0;
        const remaining = page.remaining;
        root.querySelector('#cm-more').hidden = remaining === 0;
        root.querySelector('#cm-more-count').textContent = `${remaining} more`;
        // #cm-result-count lives INSIDE #cm-show's own markup, so it must be re-created via
        // innerHTML on every render (not queried and written to separately) - once any render
        // hits the zero-results branch below and replaces #cm-show's content with plain "No
        // offers" text, the #cm-result-count span is gone from the DOM for good, and a later
        // querySelector('#cm-result-count') would throw on the very next render, silently
        // aborting before writeUrl()/renderChips()/updateControls() ever ran again.
        const showButton = root.querySelector('#cm-show');
        showButton.disabled = visibleCards.length === 0;
        showButton.innerHTML = visibleCards.length === 0
            ? 'No offers'
            : `Show <span id="cm-result-count">${visibleCards.length}</span> offers`;
        renderChips(); updateControls(); writeUrl();
    }

    function resetFilters() {
        state.sort = 'price'; state.role = 'all'; state.verified = false; state.online = false; state.coins = 'any'; state.price = maxPrice;
        render(true);
    }

    function openDrawer() {
        window.clearTimeout(backdropTimer);
        root.classList.add('cm-open');
        drawer.setAttribute('aria-hidden', 'false'); drawer.classList.add('is-open');
        backdrop.hidden = false; requestAnimationFrame(() => backdrop.classList.add('is-open'));
        openButton.setAttribute('aria-expanded', 'true'); document.body.classList.add('cm-lock-scroll');
        (root.querySelector('.cm-sort-option.is-selected') || drawer).focus();
    }

    function closeDrawer(restore = true) {
        window.clearTimeout(backdropTimer);
        root.classList.remove('cm-open');
        drawer.classList.remove('is-open'); drawer.setAttribute('aria-hidden', 'true'); backdrop.classList.remove('is-open');
        openButton.setAttribute('aria-expanded', 'false'); document.body.classList.remove('cm-lock-scroll');
        backdropTimer = window.setTimeout(() => { backdrop.hidden = true; }, 220);
        if (restore) openButton.focus();
    }

    openButton.addEventListener('click', openDrawer);
    closeButton.addEventListener('click', () => closeDrawer());
    backdrop.addEventListener('click', () => closeDrawer());
    root.querySelector('#cm-show').addEventListener('click', () => closeDrawer());
    root.querySelector('#cm-reset').addEventListener('click', resetFilters);
    root.querySelector('#cm-empty-reset').addEventListener('click', resetFilters);
    root.querySelector('#cm-price-clear').addEventListener('click', () => { state.price = maxPrice; render(true); });
    root.querySelector('#cm-more').addEventListener('click', () => { state.rows += 2; render(); });
    grid.addEventListener('click', event => {
        const button = event.target.closest('[data-amount-toggle]');
        if (!button) return;
        const form = button.closest('form');
        const field = form.querySelector('.cm-amount-input');
        const input = field.querySelector('input');
        if (field.hidden) {
            field.hidden = false;
            button.dataset.amountConfirm = '1';
            button.textContent = button.dataset.confirmLabel;
            input.focus();
            return;
        }
        if (form.reportValidity()) form.requestSubmit();
    });
    grid.addEventListener('keydown', event => {
        if (event.key !== 'Enter' || !event.target.matches('.cm-amount-input input')) return;
        event.preventDefault();
        const form = event.target.closest('form');
        if (form.reportValidity()) form.requestSubmit();
    });
    root.querySelectorAll('[data-sort-option]').forEach(button => button.addEventListener('click', () => { state.sort = button.dataset.sortOption; render(true); }));
    root.querySelectorAll('[data-role-filter]').forEach(button => button.addEventListener('click', () => { state.role = button.dataset.roleFilter; render(true); }));
    root.querySelectorAll('[data-coins]').forEach(button => button.addEventListener('click', () => { state.coins = button.dataset.coins; render(true); }));
    root.querySelector('#cm-verified').addEventListener('change', event => { state.verified = event.target.checked; render(true); });
    root.querySelector('#cm-online').addEventListener('change', event => { state.online = event.target.checked; render(true); });
    let rangeFrame = 0;
    root.querySelector('#cm-price-range').addEventListener('input', event => {
        state.price = Number(event.target.value);
        cancelAnimationFrame(rangeFrame); rangeFrame = requestAnimationFrame(() => render(true));
    });
    root.querySelector('#cm-active-filters').addEventListener('click', event => {
        const chip = event.target.closest('[data-remove-filter]');
        if (event.target.closest('[data-clear-all]')) return resetFilters();
        if (!chip) return;
        const key = chip.dataset.removeFilter;
        if (key === 'sort') state.sort = 'price';
        if (key === 'seller') state.role = 'all';
        if (key === 'verified') state.verified = false;
        if (key === 'online') state.online = false;
        if (key === 'coins') state.coins = 'any';
        if (key === 'max_price') state.price = maxPrice;
        render(true);
    });
    window.addEventListener('resize', () => render());
    window.addEventListener('popstate', () => location.reload());
    window.addEventListener('keydown', event => {
        if (event.key === 'Escape' && drawer.classList.contains('is-open')) { closeDrawer(); return; }
        if (event.key !== 'Tab' || !drawer.classList.contains('is-open')) return;
        const focusable = [...drawer.querySelectorAll('button:not(:disabled),input:not(:disabled),[tabindex="0"]')].filter(el => el.offsetParent !== null);
        if (!focusable.length) return;
        const first = focusable[0], last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    const handle = root.querySelector('.cm-sheet-handle');
    let pointerStart = null;
    handle.addEventListener('pointerdown', event => { pointerStart = event.clientY; handle.setPointerCapture(event.pointerId); });
    handle.addEventListener('pointerup', event => { if (pointerStart !== null && event.clientY - pointerStart > 85) closeDrawer(); pointerStart = null; });
    grid.addEventListener('error', event => {
        if (event.target.matches('.cm-avatar img')) event.target.remove();
    }, true);

    render();
})();
