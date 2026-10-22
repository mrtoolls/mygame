/* Gift presentation effects: shop idle/hover/purchase animation, and the queued live-game
   send overlay. Presentation only - never calls a purchase/send endpoint itself. See
   docs/GIFT_EFFECTS.md for how to add a new gift or change its tier. */
(function () {
    'use strict';

    // Tier config keyed by gift slug (shop markup) / lowercased gift name (live overlay -
    // that's what the existing gift chat message payload already carries). A gift not listed
    // here falls back to a price-based tier below, so future gifts work without code changes.
    const TIERS = {
        fox: { glow: 'var(--gift-glow-fox)', particles: 6, extra: null, overlayClass: '' },
        rose: { glow: 'var(--gift-glow-rose)', particles: 8, extra: 'petals', overlayClass: '' },
        heart: { glow: 'var(--gift-glow-heart)', particles: 8, extra: 'hearts', overlayClass: 'is-heart' },
        crown: { glow: 'var(--gift-glow-crown)', particles: 10, extra: 'gold', overlayClass: 'is-crown' },
        lion: { glow: 'var(--gift-glow-lion)', particles: 12, extra: 'lion', overlayClass: 'is-lion' },
        diamond: { glow: 'var(--gift-glow-diamond)', particles: 16, extra: 'diamond', overlayClass: 'is-diamond' },
    };
    const PRICE_FALLBACK = [
        { max: 1.5, like: 'fox' },
        { max: 3.5, like: 'rose' },
        { max: 7.5, like: 'heart' },
        { max: 15, like: 'crown' },
        { max: 30, like: 'lion' },
        { max: Infinity, like: 'diamond' },
    ];

    function tierFor(key, price) {
        const norm = (key == null ? '' : String(key)).toLowerCase().trim();
        if (TIERS[norm]) return TIERS[norm];
        const p = Number(price) || 0;
        const row = PRICE_FALLBACK.find(function (r) { return p <= r.max; });
        return TIERS[row ? row.like : 'fox'];
    }

    function reduceMotion() {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }
    function isMobile() {
        return !!(window.matchMedia && window.matchMedia('(max-width: 575px)').matches);
    }
    function escapeHtml(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // ---------- Shop grid: idle float, hover sparkle, purchase pop ----------
    function initShop(root) {
        if (!root) return null;
        const cards = Array.prototype.slice.call(root.querySelectorAll('[data-gift-fx]'));
        if (!cards.length) return null;

        const visible = new WeakMap();
        function updateVisible(card) {
            card.classList.toggle('is-visible', (visible.get(card) !== false) && !document.hidden);
        }

        cards.forEach(function (card, index) {
            const tier = tierFor(card.dataset.giftFx, card.dataset.giftPrice);
            card.style.setProperty('--gift-fx-glow', tier.glow);
            card.style.setProperty('--gift-fx-delay', (index % 6) * 0.35 + 's');

            const media = card.querySelector('.gift-fx__media') || card;
            let particles = media.querySelector('.gift-fx__particles');
            if (!particles) {
                particles = document.createElement('span');
                particles.className = 'gift-fx__particles';
                media.appendChild(particles);
            }

            function burst(count) {
                if (reduceMotion()) return;
                const n = isMobile() ? Math.ceil(count / 2) : count;
                for (let i = 0; i < n; i++) {
                    const spark = document.createElement('span');
                    spark.className = 'gift-fx__spark';
                    const angle = Math.random() * Math.PI * 2;
                    const dist = 24 + Math.random() * 30;
                    spark.style.setProperty('--gift-fx-dx', Math.cos(angle) * dist + 'px');
                    spark.style.setProperty('--gift-fx-dy', Math.sin(angle) * dist + 'px');
                    spark.style.animationDelay = (Math.random() * 0.15) + 's';
                    particles.appendChild(spark);
                    setTimeout(function () { spark.remove(); }, 900);
                }
            }

            let hoverFired = false;
            function onEnter() {
                card.classList.add('is-active');
                if (hoverFired) return;
                hoverFired = true;
                burst(Math.max(3, Math.round(tier.particles / 2)));
                setTimeout(function () { hoverFired = false; }, 600);
            }
            card.addEventListener('pointerenter', onEnter);
            card.addEventListener('touchstart', onEnter, { passive: true });
            card.addEventListener('pointerleave', function () { card.classList.remove('is-active'); });

            card._giftFxBurst = burst;
            card._giftFxTier = tier;
            visible.set(card, true);
        });

        if ('IntersectionObserver' in window) {
            const io = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    visible.set(entry.target, entry.isIntersecting);
                    updateVisible(entry.target);
                });
            }, { threshold: 0.1 });
            cards.forEach(function (card) { io.observe(card); });
        } else {
            cards.forEach(function (card) { card.classList.add('is-visible'); });
        }
        document.addEventListener('visibilitychange', function () { cards.forEach(updateVisible); });

        return {
            pop: function (giftId) {
                const card = cards.find(function (c) { return String(c.dataset.giftId) === String(giftId); });
                if (!card) return;
                card.classList.remove('is-popping');
                void card.offsetWidth; // restart the animation if it's already mid-play
                card.classList.add('is-popping');
                card._giftFxBurst && card._giftFxBurst(card._giftFxTier.particles);
                setTimeout(function () { card.classList.remove('is-popping'); }, 650);
            },
        };
    }

    // ---------- Live-game send overlay: tiered, queued (capped), auto-clearing ----------
    function initLiveOverlay(overlayEl) {
        if (!overlayEl) return { queue: function () {} };
        const imgEl = overlayEl.querySelector('[data-gift-fx-img]');
        const textEl = overlayEl.querySelector('[data-gift-fx-text]');
        const qtyEl = overlayEl.querySelector('[data-gift-fx-qty]');
        const cardEl = overlayEl.querySelector('.gift-fx-overlay__card');
        const extraEl = overlayEl.querySelector('.gift-fx-overlay__extra');
        const MAX_QUEUE = 3;
        let queue = [];
        let playing = false;

        function spawnExtra(tier) {
            if (!extraEl) return;
            extraEl.replaceChildren();
            if (reduceMotion()) return;
            const count = isMobile() ? 5 : 10;
            if (tier.extra === 'petals') {
                for (let i = 0; i < count; i++) {
                    const p = document.createElement('span');
                    p.className = 'gift-fx-overlay__petal';
                    p.style.left = (10 + Math.random() * 80) + '%';
                    p.style.setProperty('--gift-fx-dx', (Math.random() * 60 - 30) + 'px');
                    p.style.animationDelay = (Math.random() * 0.5) + 's';
                    extraEl.appendChild(p);
                }
            } else if (tier.extra === 'hearts') {
                for (let i = 0; i < Math.ceil(count / 2); i++) {
                    const h = document.createElement('span');
                    h.className = 'gift-fx-overlay__mini-heart';
                    h.textContent = '❤';
                    h.style.left = (20 + Math.random() * 60) + '%';
                    h.style.animationDelay = (Math.random() * 0.6) + 's';
                    extraEl.appendChild(h);
                }
            } else if (tier.extra === 'gold') {
                for (let i = 0; i < count; i++) {
                    const g = document.createElement('span');
                    g.className = 'gift-fx-overlay__gold';
                    g.style.left = (10 + Math.random() * 80) + '%';
                    g.style.animationDelay = (Math.random() * 0.6) + 's';
                    extraEl.appendChild(g);
                }
            } else if (tier.extra === 'lion') {
                const ring = document.createElement('span');
                ring.className = 'gift-fx-overlay__ring';
                extraEl.appendChild(ring);
            } else if (tier.extra === 'diamond') {
                const rayCount = isMobile() ? 6 : 10;
                for (let i = 0; i < rayCount; i++) {
                    const ray = document.createElement('span');
                    ray.className = 'gift-fx-overlay__ray';
                    ray.style.setProperty('--gift-fx-rot', (i * (360 / rayCount)) + 'deg');
                    extraEl.appendChild(ray);
                }
            }
        }

        function playNext() {
            if (!queue.length) { playing = false; return; }
            playing = true;
            const item = queue.shift();
            const tier = tierFor(item.key, item.price);

            overlayEl.style.setProperty('--gift-fx-glow', tier.glow);
            cardEl.className = 'gift-fx-overlay__card' + (tier.overlayClass ? ' ' + tier.overlayClass : '');
            overlayEl.classList.toggle('is-diamond', tier.extra === 'diamond');
            if (imgEl) { imgEl.src = item.image || ''; imgEl.alt = ''; }

            const qty = Math.max(1, Math.floor(Number(item.quantity) || 1));
            if (qtyEl) {
                if (qty > 1) { qtyEl.hidden = false; qtyEl.textContent = 'x' + qty; } else { qtyEl.hidden = true; }
            }

            if (textEl) {
                textEl.className = 'gift-fx-overlay__text' + (tier.extra === 'diamond' ? ' is-diamond-name' : '');
                textEl.replaceChildren();
                const strong = document.createElement('b');
                strong.textContent = item.senderName || 'Someone'; // textContent - never HTML-interpreted
                textEl.appendChild(strong);
                let rest = ' sent ' + (qty > 1 ? qty + 'x ' : '') + (item.giftName || 'a gift');
                if (item.recipientName) rest += ' to ' + item.recipientName;
                textEl.appendChild(document.createTextNode(rest));
            }

            spawnExtra(tier);
            if (!reduceMotion()) window.TableSound && window.TableSound.play && window.TableSound.play('win');

            overlayEl.hidden = false;
            cardEl.classList.remove('is-leaving');

            const duration = reduceMotion() ? 1400 : 2600;
            clearTimeout(overlayEl._giftFxTimer);
            overlayEl._giftFxTimer = setTimeout(function () {
                cardEl.classList.add('is-leaving');
                setTimeout(function () {
                    overlayEl.hidden = true;
                    playNext();
                }, 280);
            }, duration);
        }

        return {
            queue: function (item) {
                if (queue.length >= MAX_QUEUE) queue.shift(); // cap the backlog, never pile up unbounded
                queue.push(item);
                if (!playing) playNext();
            },
        };
    }

    window.GiftEffects = {
        TIERS: TIERS,
        tierFor: tierFor,
        escapeHtml: escapeHtml,
        initShop: initShop,
        initLiveOverlay: initLiveOverlay,
    };
})();
