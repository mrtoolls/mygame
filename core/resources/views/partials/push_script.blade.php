<script src="{{ asset('assets/global/js/firebase/firebase-app.js') }}"></script>
<script src="{{ asset('assets/global/js/firebase/firebase-messaging.js') }}"></script>

@auth
<style>
    .winxpot-push-card{position:fixed;z-index:12000;left:18px;bottom:22px;display:flex;align-items:center;gap:13px;width:min(390px,calc(100vw - 36px));padding:15px 16px;border:1px solid rgba(0,231,138,.24);border-radius:18px;background:linear-gradient(135deg,#1b2340,#111729);box-shadow:0 18px 55px rgba(0,0,0,.38);color:#f4f6ff;font-family:inherit;font-size:14px;font-weight:500;line-height:1.4}
    .winxpot-push-card[hidden]{display:none}.winxpot-push-card__icon{display:grid;place-items:center;flex:0 0 40px;height:40px;border-radius:13px;background:rgba(0,231,138,.13);color:#00e78a;font-size:19px}
    .winxpot-push-card__copy{flex:1;min-width:0}.winxpot-push-card__copy strong{display:block;font-weight:800}.winxpot-push-card__copy span{display:block;margin-top:2px;color:#9ba5c8;font-size:12px}
    .winxpot-push-card__enable{flex:none;border:0;border-radius:11px;padding:10px 13px;background:#00e78a;color:#06291d;font-weight:800;cursor:pointer}.winxpot-push-card__close{align-self:flex-start;border:0;padding:0;background:none;color:#9ba5c8;font-size:19px;cursor:pointer}
    @media(max-width:575px){.winxpot-push-card{left:12px;bottom:calc(84px + env(safe-area-inset-bottom));width:calc(100vw - 24px);padding:12px;border-radius:15px}.winxpot-push-card__copy span{font-size:11px}.winxpot-push-card__enable{padding:9px 11px}}
</style>
<aside class="winxpot-push-card" id="winxpot-push-card" hidden aria-label="Push notification settings">
    <span class="winxpot-push-card__icon" aria-hidden="true"><i class="las la-bell"></i></span>
    <span class="winxpot-push-card__copy"><strong>@lang('Stay in the loop')</strong><span>@lang('Get game invites and important account updates on this device.')</span></span>
    <button class="winxpot-push-card__enable" id="winxpot-push-enable" type="button">@lang('Enable')</button>
    <button class="winxpot-push-card__close" id="winxpot-push-dismiss" type="button" aria-label="@lang('Dismiss')">&times;</button>
</aside>
<script>
(() => {
    'use strict';
    const enabled = @json((bool) gs('pn'));
    const config = @json(gs('firebase_config'));
    const badgeUrl = @json(route('user.notifications.badge'));
    const tokenUrl = @json(route('user.add.device.token'));
    const csrf = @json(csrf_token());
    const dismissedKey = 'winxpot-push-card-dismissed';
    let registration = null;
    let messaging = null;

    function applyBadge(count) {
        count = Math.max(0, Number(count || 0));
        if (count && 'setAppBadge' in navigator) navigator.setAppBadge(count).catch(() => {});
        else if (!count && 'clearAppBadge' in navigator) navigator.clearAppBadge().catch(() => {});
        if (registration && registration.active) registration.active.postMessage({ type: 'WINXPOT_BADGE_SET', count });
    }

    async function refreshBadge() {
        try {
            const response = await fetch(badgeUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) return;
            const data = await response.json();
            applyBadge(data.count);
        } catch (_) {}
    }

    async function saveToken() {
        if (!messaging || !registration || Notification.permission !== 'granted') return;
        try {
            messaging.useServiceWorker(registration);
            const token = await messaging.getToken();
            if (token) await fetch(tokenUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                body: JSON.stringify({ token })
            });
        } catch (error) { console.warn('Push subscription could not be refreshed.'); }
    }

    async function enablePush() {
        if (!('Notification' in window)) return;
        try {
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                await saveToken();
                document.getElementById('winxpot-push-card')?.setAttribute('hidden', '');
            } else if (permission === 'denied') {
                const copy = document.querySelector('#winxpot-push-card .winxpot-push-card__copy span');
                if (copy) copy.textContent = @json(__('Notifications are blocked in your browser settings.'));
            }
        } catch (_) {}
    }

    if (!enabled || !config || !config.projectId || !('serviceWorker' in navigator) || !('Notification' in window)) return;
    window.addEventListener('load', async () => {
        try {
            registration = await navigator.serviceWorker.register(@json(asset('serviceworker.js')), { scope: '/' });
            await navigator.serviceWorker.ready;
            firebase.initializeApp(config);
            messaging = firebase.messaging();
            messaging.onMessage(async (payload) => {
                const title = payload.notification?.title || payload.data?.title || 'Winxpot';
                const options = {
                    body: payload.notification?.body || payload.data?.body || '',
                    icon: payload.data?.icon || @json(siteFavicon()),
                    data: { url: payload.data?.click_action || payload.data?.url || '/' }
                };
                registration.showNotification(title, options).catch(() => {});
                await refreshBadge();
            });

            if (Notification.permission === 'granted') await saveToken();
            if (Notification.permission === 'default' && !localStorage.getItem(dismissedKey)) {
                document.getElementById('winxpot-push-card')?.removeAttribute('hidden');
            }
            await refreshBadge();
            setInterval(refreshBadge, 60000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshBadge(); });
            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data?.type === 'WINXPOT_PUSH_RECEIVED') refreshBadge();
            });
        } catch (error) { console.warn('Push notifications could not be initialized.'); }
    }, { once: true });

    document.getElementById('winxpot-push-enable')?.addEventListener('click', enablePush);
    document.getElementById('winxpot-push-dismiss')?.addEventListener('click', () => {
        localStorage.setItem(dismissedKey, '1');
        document.getElementById('winxpot-push-card')?.setAttribute('hidden', '');
    });
})();
</script>
@endauth
