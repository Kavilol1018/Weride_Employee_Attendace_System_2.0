const CACHE_NAME = 'weride-pwa-v4';
const STATIC_ASSETS = [
  './pic/logo_only.png',
  './pic/WeRide.png',
  './assets/icons/icon-192x192.png',
  './assets/icons/icon-512x512.png',
  './assets/icons/apple-touch-icon.png',
  './assets/css/global_fixes.css',
  './assets/css/dark_mode.css',
  './assets/js/theme_toggle.js'
];

// Install event: cache essential static assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS).catch((err) => {
        console.warn('PWA: Non-fatal caching warning on install', err);
      });
    })
  );
  self.skipWaiting();
});

// Activate event: clean up outdated caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch event: Network-first approach to guarantee 100% real-time attendance data
self.addEventListener('fetch', (event) => {
  const req = event.request;
  
  // Non-GET requests (POST actions for login, clock-in, leave application) pass directly to network
  if (req.method !== 'GET') {
    return;
  }

  // Network-first: always fetch the latest PHP responses from the server
  event.respondWith(
    fetch(req)
      .then((networkResponse) => {
        // Cache static assets dynamically
        if (
          networkResponse &&
          networkResponse.status === 200 &&
          (req.url.includes('/assets/') || req.url.includes('/pic/'))
        ) {
          const resClone = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(req, resClone));
        }
        return networkResponse;
      })
      .catch(() => {
        // If completely offline, fall back to cache if available
        return caches.match(req).then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }
          // If the page itself is offline and not in cache
          if (req.headers.get('accept') && req.headers.get('accept').includes('text/html')) {
            return new Response(
              '<div style="font-family:sans-serif; text-align:center; padding:50px;"><h2>You are offline</h2><p>Please check your internet connection and refresh.</p></div>',
              { headers: { 'Content-Type': 'text/html' } }
            );
          }
        });
      })
  );
});

// ============================================================================
// Web Push Notifications Handler (With Instagram-style WeRide Logo)
// ============================================================================
self.addEventListener('push', (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch (e) {
    data = {
      title: 'WeRide Update',
      body: event.data ? event.data.text() : 'You have a new attendance update.'
    };
  }

  const origin = self.location ? self.location.origin : '';
  const title = data.title || 'WeRide Attendance';
  const logoUrl = origin ? (origin + '/pic/logo_only.png') : './pic/logo_only.png';

  const options = {
    body: data.body || 'You have an important update from WeRide.',
    icon: data.icon || logoUrl,
    badge: data.badge || logoUrl,
    image: data.image || null,
    vibrate: [200, 100, 200],
    tag: data.tag || ('weride-alert-' + Date.now()),
    renotify: true,
    data: {
      url: data.url || './',
      timestamp: Date.now()
    }
  };

  event.waitUntil(
    self.registration.showNotification(title, options)
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = (event.notification.data && event.notification.data.url) ? event.notification.data.url : './';
  
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
      for (let client of windowClients) {
        if ('focus' in client) {
          if (client.url && client.url.includes('weride')) {
            client.navigate(targetUrl);
            return client.focus();
          }
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
    })
  );
});
