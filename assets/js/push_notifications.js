/**
 * WeRide PWA Push Notifications Manager
 * Connects the browser PushManager to WeRide's self-hosted VAPID backend
 */
(function() {
    const VAPID_PUBLIC_KEY = 'BKWkXjQLYw-ZyqjMlhMAEg0yI9CB56DTHjW7UciMG-VSnio36NYHF7z4wvSo4F4YENfEspH3_zfqk8zj_V8mjko';

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    async function registerPushSubscription(subscription) {
        try {
            const rawKey = subscription.getKey ? subscription.getKey('p256dh') : null;
            const rawAuth = subscription.getKey ? subscription.getKey('auth') : null;

            if (!rawKey || !rawAuth) return false;

            const p256dh = btoa(String.fromCharCode.apply(null, new Uint8Array(rawKey)))
                .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
            const auth = btoa(String.fromCharCode.apply(null, new Uint8Array(rawAuth)))
                .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

            const payload = {
                endpoint: subscription.endpoint,
                keys: {
                    p256dh: p256dh,
                    auth: auth
                }
            };

            const response = await fetch('api/save_push_subscription.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            return response.ok;
        } catch (err) {
            console.warn('WeRide Push: Registration sync error', err);
            return false;
        }
    }

    window.subscribeWeRidePush = async function(promptUser = true) {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            if (promptUser) {
                alert("Push notifications are not supported by this browser. If on iPhone, please ensure you have added WeRide to your Home Screen first.");
            }
            return false;
        }

        try {
            let permission = Notification.permission;
            if (permission !== 'granted' && promptUser) {
                permission = await Notification.requestPermission();
            }

            if (permission !== 'granted') {
                return false;
            }

            const reg = await navigator.serviceWorker.ready;
            let sub = await reg.pushManager.getSubscription();

            if (!sub) {
                const convertedKey = urlBase64ToUint8Array(VAPID_PUBLIC_KEY);
                sub = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: convertedKey
                });
            }

            const success = await registerPushSubscription(sub);
            if (success && promptUser) {
                // Show confirmation
                if (window.showPushSuccessBanner) {
                    window.showPushSuccessBanner();
                }
            }
            return success;
        } catch (err) {
            console.error('WeRide Push: Subscribe error', err);
            return false;
        }
    };

    // Auto-sync existing subscription on page load if permission was previously granted
    window.addEventListener('load', () => {
        if ('Notification' in window && Notification.permission === 'granted') {
            window.subscribeWeRidePush(false);
        }
    });
})();
