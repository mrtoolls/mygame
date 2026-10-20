const CACHE_NAME = 'winxpot-pwa-v2';
// Only images get opportunistically cached (see the fetch handler) - deliberately not CSS/JS,
// which should always come from the network so a deploy is never masked by a stale asset cache.
const CACHEABLE_IMAGE = /\.(?:png|jpe?g|webp|gif|svg|ico)(?:\?.*)?$/i;

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.add('/').catch(() => null))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(keys.filter(key => key.startsWith('winxpot-pwa-') && key !== CACHE_NAME).map(key => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET' || new URL(event.request.url).origin !== self.location.origin) return;

    if (event.request.mode === 'navigate') {
        event.respondWith(fetch(event.request).catch(async () => (await caches.match(event.request)) || (await caches.match('/'))));
        return;
    }

    // Previously: network-only, falling back to a cache that nothing but "/" was ever written
    // into - so a single failed image fetch (a slow connection timing out on an oversized file,
    // for example) showed a broken image forever, even after the network recovered, since there
    // was never anything cached to fall back to. Now a successful image fetch is cached as it
    // happens, so a later failed fetch for that same image has a real, recent copy to fall back
    // to instead of nothing.
    const isImage = CACHEABLE_IMAGE.test(new URL(event.request.url).pathname);
    if (!isImage) {
        event.respondWith(fetch(event.request).catch(() => caches.match(event.request)));
        return;
    }
    event.respondWith(
        fetch(event.request)
            .then(response => {
                if (response && response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, copy)).catch(() => null);
                }
                return response;
            })
            .catch(() => caches.match(event.request))
    );
});
