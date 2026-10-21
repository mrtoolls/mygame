const CACHE_NAME = 'winxpot-pwa-v2';
// Only images get opportunistically cached (see the fetch handler) - deliberately not CSS/JS,
// which should always come from the network so a deploy is never masked by a stale asset cache.
const CACHEABLE_IMAGE = /\.(?:png|jpe?g|webp|gif|svg|ico)(?:\?.*)?$/i;

// Share the root PWA worker with Firebase so push subscriptions use the same
// origin-wide registration (two workers cannot own the same scope reliably).
try {
    importScripts('/assets/global/js/firebase/firebase-app.js', '/assets/global/js/firebase/firebase-messaging.js', '/firebase-messaging-sw-config.js');
    if (self.firebaseConfig && self.firebaseConfig.projectId) {
        firebase.initializeApp(self.firebaseConfig);
        const messaging = firebase.messaging();

        function updateStoredBadge(increment) {
            return new Promise((resolve) => {
                const request = indexedDB.open('winxpot-push-state', 1);
                request.onupgradeneeded = () => request.result.createObjectStore('state');
                request.onerror = () => resolve(1);
                request.onsuccess = () => {
                    const db = request.result;
                    const tx = db.transaction('state', 'readwrite');
                    const store = tx.objectStore('state');
                    const get = store.get('badge');
                    get.onsuccess = () => {
                        const count = Math.max(0, Number(get.result || 0) + (increment ? 1 : 0));
                        if (count) store.put(count, 'badge'); else store.delete('badge');
                        resolve(count);
                    };
                    get.onerror = () => resolve(1);
                };
            });
        }

        messaging.setBackgroundMessageHandler((payload) => {
            return updateStoredBadge(true).then((count) => {
                if (self.navigator && self.navigator.setAppBadge) self.navigator.setAppBadge(count).catch(() => {});
                self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
                    clients.forEach((client) => client.postMessage({ type: 'WINXPOT_PUSH_RECEIVED' }));
                });
                const data = payload.data || {};
                const notice = payload.notification || {};
                return self.registration.showNotification(notice.title || data.title || 'Winxpot', {
                    body: notice.body || data.body || 'You have a new notification.',
                    icon: data.icon || '/assets/images/logoIcon/favicon.png',
                    badge: data.badge || '/assets/images/logoIcon/favicon.png',
                    image: notice.image || undefined,
                    data: { url: data.click_action || data.url || '/' },
                    tag: data.tag || undefined,
                    renotify: false,
                });
            });
        });

        self.addEventListener('notificationclick', (event) => {
            event.notification.close();
            const target = new URL((event.notification.data || {}).url || '/', self.location.origin);
            if (target.origin !== self.location.origin) return;
            event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
                for (const client of clients) {
                    if (new URL(client.url).origin === target.origin) {
                        client.navigate(target.href);
                        return client.focus();
                    }
                }
                return self.clients.openWindow(target.href);
            }));
        });

        self.addEventListener('message', (event) => {
            if (!event.data || event.data.type !== 'WINXPOT_BADGE_SET') return;
            const count = Math.max(0, Number(event.data.count || 0));
            event.waitUntil(updateStoredBadge(false).then(() => {
                const request = indexedDB.open('winxpot-push-state', 1);
                request.onsuccess = () => {
                    const db = request.result;
                    const tx = db.transaction('state', 'readwrite');
                    if (count) tx.objectStore('state').put(count, 'badge'); else tx.objectStore('state').delete('badge');
                };
                if (self.navigator && self.navigator.setAppBadge) {
                    return (count ? self.navigator.setAppBadge(count) : self.navigator.clearAppBadge()).catch(() => {});
                }
            }));
        });
    }
} catch (error) {
    // Keep offline caching operational when push is unavailable or not configured.
    console.warn('Winxpot push setup unavailable');
}

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
