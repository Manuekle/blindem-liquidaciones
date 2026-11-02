// Offline fallback only: app pages always come from the network; when a navigation fails, the precached offline page
// answers instead of the browser's error screen. Bump VERSION when offline.html or what it uses changes.
const VERSION = 'blindem-offline-v3';
const ASSETS = ['/offline.html', '/css/app.css', '/js/halftone.js', '/icons/hugeicons.svg'];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(VERSION).then(cache => cache.addAll(ASSETS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
  event.waitUntil(caches.keys()
    .then(keys => Promise.all(keys.filter(key => key !== VERSION).map(key => caches.delete(key))))
    .then(() => self.clients.claim()));
});

self.addEventListener('fetch', event => {
  const { request } = event;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
  } else if (url.origin === location.origin && ASSETS.includes(url.pathname)) {
    // The offline page's own assets: network first, and every fresh copy (versioned or not) refreshes the cache.
    event.respondWith(fetch(request).then(response => {
      if (response.ok) {
        const copy = response.clone();
        caches.open(VERSION).then(cache => cache.put(url.pathname, copy));
      }
      return response;
    }).catch(() => caches.match(request, { ignoreSearch: true })));
  }
});
