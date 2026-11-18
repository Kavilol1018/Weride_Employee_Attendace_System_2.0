const CACHE_NAME = 'weride-pwa-v2';
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
