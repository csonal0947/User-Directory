/**
 * Service Worker — Disabled (cache cleared)
 * This SW does nothing except clear old caches.
 * Re-enable caching when deploying to production.
 */

// Install immediately
self.addEventListener('install', () => self.skipWaiting());

// On activate, delete ALL caches and take control
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then(names => Promise.all(names.map(name => caches.delete(name))))
            .then(() => self.clients.claim())
    );
});

// No fetch handler — all requests go directly to the server
