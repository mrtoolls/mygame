<header class="nocturne-topbar">
    <div class="nocturne-brand-group">
        <a class="nocturne-brand" href="{{ route('home') }}" aria-label="{{ gs('site_name') }} home">
            <img src="{{ siteLogo() }}" alt="{{ gs('site_name') }}">
        </a>
        <button class="nocturne-collapse d-none d-xl-grid" type="button" aria-label="@lang('Collapse menu')">
            <i class="ph-fill ph-caret-left"></i>
        </button>
    </div>
    <button class="nocturne-menu-toggle" type="button" aria-label="@lang('Toggle menu')" aria-expanded="false">
        <i class="ph-fill ph-list"></i>
    </button>
    <nav class="nocturne-topnav" aria-label="@lang('Primary navigation')">
        <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'is-active' : '' }}"><i class="ph-fill ph-house"></i><span>@lang('Home')</span></a>
        @auth
            <a href="{{ route('user.home') }}" class="{{ request()->routeIs('user.home') ? 'is-active' : '' }}"><i class="ph-fill ph-user"></i><span>@lang('Profile')</span></a>
            <a href="{{ route('user.referrals') }}" class="{{ request()->routeIs('user.referrals') ? 'is-active' : '' }}"><i class="ph-fill ph-gift"></i><span>@lang('Bonuses')</span></a>
        @else
            <a href="{{ route('games') }}" class="{{ request()->routeIs('games') ? 'is-active' : '' }}"><i class="ph-fill ph-dice-five"></i><span>@lang('Games')</span></a>
        @endauth
        <a href="{{ auth()->check() ? route('user.vip.index') : route('pages', 'vip-club') }}"><i class="ph-fill ph-crown"></i><span>@lang('VIP-Club')</span></a>
    </nav>
    <div class="nocturne-actions">
        @auth
            <div class="nocturne-balance"><span>@lang('Your Balance')</span><strong>{!! showUserBalance() !!}</strong></div>
            <div class="nocturne-bell-menu" data-bell>
                <button class="nocturne-notifications" type="button" aria-haspopup="true" aria-expanded="false" aria-label="@lang('Notifications')" data-bell-toggle>
                    <i class="ph-fill ph-bell"></i>
                    <span class="nocturne-notifications__count" data-bell-count hidden>0</span>
                </button>
                <div class="nocturne-notifications-panel" role="menu" data-bell-panel>
                    <div class="nocturne-notifications-panel__head">
                        <strong>@lang('Notifications')</strong>
                        <button type="button" class="nocturne-notifications-panel__mark-all" data-bell-mark-all>@lang('Mark all read')</button>
                    </div>
                    <div class="nocturne-notifications-panel__list" data-bell-list>
                        <p class="nocturne-notifications-panel__empty" data-bell-empty>@lang('No notifications yet')</p>
                    </div>
                </div>
            </div>
            <div class="nocturne-user-menu">
                <button class="nocturne-avatar" type="button" aria-haspopup="true" aria-expanded="false" aria-label="@lang('Account menu')">
                    @if (auth()->user()->avatarUrl())
                        <img src="{{ auth()->user()->avatarUrl() }}" alt="{{ auth()->user()->username }}">
                    @else
                        <i class="ph-fill ph-user-circle"></i>
                    @endif
                </button>
                <div class="nocturne-user-dropdown" role="menu">
                    <div class="nocturne-user-dropdown__head">
                        <span class="nocturne-account-avatar">
                            @if (auth()->user()->avatarUrl())
                                <img src="{{ auth()->user()->avatarUrl() }}" alt="{{ auth()->user()->username }}">
                            @else
                                <i class="ph-fill ph-user-circle"></i>
                            @endif
                        </span>
                        <div>
                            <strong>{{ auth()->user()->username }}</strong>
                            <span>{!! showUserBalance() !!}</span>
                        </div>
                    </div>
                    <a class="nocturne-user-dropdown__deposit" href="{{ route('user.deposit.index') }}"><i class="ph-fill ph-plus-circle"></i>@lang('Deposit')</a>
                    <div class="nocturne-user-dropdown__links">
                        <a href="{{ route('user.home') }}"><i class="ph-fill ph-squares-four"></i>@lang('Dashboard')</a>
                        <a href="{{ route('games') }}"><i class="ph-fill ph-dice-five"></i>@lang('Games')</a>
                        <a href="{{ route('user.referrals') }}"><i class="ph-fill ph-gift"></i>@lang('Bonuses')</a>
                        <a href="{{ route('user.vip.index') }}"><i class="ph-fill ph-crown"></i>@lang('VIP Club')</a>
                        <a href="{{ route('user.transactions') }}"><i class="ph-fill ph-list-bullets"></i>@lang('Transactions')</a>
                        <a href="{{ route('user.profile.setting') }}"><i class="ph-fill ph-gear"></i>@lang('Settings')</a>
                    </div>
                    <a class="nocturne-user-dropdown__logout" href="{{ route('user.logout') }}"><i class="ph-fill ph-sign-out"></i>@lang('Log out')</a>
                </div>
            </div>
        @else
            <a class="nocturne-login" href="{{ route('user.login') }}"><i class="ph-fill ph-sign-in"></i><span>@lang('Login')</span></a>
            @if (gs('registration'))
                <a class="nocturne-register" href="{{ route('user.register') }}"><i class="ph-fill ph-user-plus"></i><span>@lang('Register')</span></a>
            @endif
        @endauth
    </div>
</header>
@auth
    @push('script')
        <script>
            (() => {
                const menu = document.querySelector('.nocturne-user-menu');
                const toggle = menu?.querySelector('.nocturne-avatar');
                if (!menu || !toggle) return;
                const close = () => { menu.classList.remove('is-open'); toggle.setAttribute('aria-expanded', 'false'); };
                toggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const open = menu.classList.toggle('is-open');
                    toggle.setAttribute('aria-expanded', String(open));
                });
                document.addEventListener('click', (e) => { if (!menu.contains(e.target)) close(); });
                document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
            })();
        </script>
        <script>
            (() => {
                'use strict';
                const root = document.querySelector('[data-bell]');
                if (!root) return;
                const toggle = root.querySelector('[data-bell-toggle]');
                const panel = root.querySelector('[data-bell-panel]');
                const countEl = root.querySelector('[data-bell-count]');
                const listEl = root.querySelector('[data-bell-list]');
                const emptyEl = root.querySelector('[data-bell-empty]');
                const markAllBtn = root.querySelector('[data-bell-mark-all]');
                const badgeUrl = @json(route('user.notifications.badge'));
                const listUrl = @json(route('user.notifications.list'));
                const readAllUrl = @json(route('user.notifications.read.all'));
                const readOneUrl = (id) => @json(route('user.notifications.read.single', ':id')).replace(':id', id);
                const csrf = @json(csrf_token());
                const seenKey = 'winxpot-bell-last-seen-id';
                let listLoaded = false;
                let lastKnownId = Number(localStorage.getItem(seenKey) || 0) || 0;

                const iconFor = (icon) => /^[a-z0-9-]+$/.test(icon || '') ? icon : 'bell-ringing';

                function close() {
                    root.classList.remove('is-open');
                    toggle.setAttribute('aria-expanded', 'false');
                }
                toggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const open = root.classList.toggle('is-open');
                    toggle.setAttribute('aria-expanded', String(open));
                    if (open && !listLoaded) loadList();
                });
                document.addEventListener('click', (e) => { if (!root.contains(e.target)) close(); });
                document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });

                function setCount(count) {
                    count = Math.max(0, Number(count || 0));
                    countEl.textContent = count > 99 ? '99+' : String(count);
                    countEl.hidden = count === 0;
                }

                function renderList(items) {
                    listEl.querySelectorAll('.nocturne-notifications-panel__item').forEach(el => el.remove());
                    emptyEl.hidden = items.length > 0;
                    items.forEach(item => {
                        const row = document.createElement(item.url ? 'a' : 'div');
                        row.className = 'nocturne-notifications-panel__item' + (item.is_read ? '' : ' is-unread');
                        if (item.url) row.href = item.url;
                        row.innerHTML = `
                            <span class="nocturne-notifications-panel__icon"><i class="ph-fill ph-${iconFor(item.icon)}"></i></span>
                            <span class="nocturne-notifications-panel__body">
                                ${item.title ? `<strong>${escapeHtml(item.title)}</strong>` : ''}
                                <span>${escapeHtml(item.message)}</span>
                                <time>${escapeHtml(item.time)}</time>
                            </span>`;
                        row.addEventListener('click', () => markRead(item.id));
                        listEl.appendChild(row);
                    });
                }

                function escapeHtml(str) {
                    const div = document.createElement('div');
                    div.textContent = str || '';
                    return div.innerHTML;
                }

                async function loadList() {
                    try {
                        const response = await fetch(listUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                        if (!response.ok) return;
                        const data = await response.json();
                        listLoaded = true;
                        renderList(data.notifications || []);
                    } catch (_) {}
                }

                async function markRead(id) {
                    try {
                        // keepalive: a click on an item with a url navigates away immediately -
                        // without this, the browser can abort the request before it lands.
                        await fetch(readOneUrl(id), {
                            method: 'POST', credentials: 'same-origin', keepalive: true,
                            headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                        });
                        refreshBadge();
                    } catch (_) {}
                }

                markAllBtn.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    try {
                        await fetch(readAllUrl, {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                        });
                        listEl.querySelectorAll('.nocturne-notifications-panel__item.is-unread').forEach(el => el.classList.remove('is-unread'));
                        setCount(0);
                    } catch (_) {}
                });

                async function refreshBadge() {
                    try {
                        const response = await fetch(badgeUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                        if (!response.ok) return;
                        const data = await response.json();
                        setCount(data.count);
                    } catch (_) {}
                }

                // Polls the feed (not just the badge count) so a brand-new item can pop a toast
                // the moment it arrives, the same way a native app would - on every device, with
                // or without Firebase push configured (that's a separate, OS-level channel; this
                // is the always-available fallback/companion to it).
                async function pollForNew() {
                    try {
                        const response = await fetch(listUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                        if (!response.ok) return;
                        const data = await response.json();
                        const items = data.notifications || [];
                        if (listLoaded) renderList(items);
                        const freshest = items.filter(i => i.id > lastKnownId);
                        if (lastKnownId && freshest.length && typeof window.notify === 'function') {
                            freshest.slice(0, 3).reverse().forEach(item => window.notify('info', item.title ? `${item.title}: ${item.message}` : item.message));
                        }
                        if (items.length) {
                            lastKnownId = Math.max(lastKnownId, ...items.map(i => i.id));
                            localStorage.setItem(seenKey, String(lastKnownId));
                        }
                    } catch (_) {}
                }

                function poll() { refreshBadge(); pollForNew(); }
                poll();
                setInterval(poll, 20000);
                document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
            })();
        </script>
    @endpush
@endauth
