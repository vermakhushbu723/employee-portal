/*
 * Service worker — makes the portal installable and app-like.
 *  - Pages always come from the network (data must be live); if offline,
 *    the offline screen is shown.
 *  - CSS / JS / icons / fonts / CDN files are cached (stale-while-revalidate)
 *    so the app shell opens instantly.
 * Bump VERSION when changing this file.
 */
const VERSION = 'v2';
const CACHE = `portal-${VERSION}`;
const PRECACHE = ['/offline.html', '/images/logo.png', '/icons/icon-192.png', '/manifest.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

const isStatic = (url) =>
    (url.origin === self.location.origin && /^\/(css|js|icons|images)\//.test(url.pathname)) ||
    ['cdn.jsdelivr.net', 'fonts.googleapis.com', 'fonts.gstatic.com'].includes(url.hostname);

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
        return;
    }

    const url = new URL(request.url);
    if (!isStatic(url)) return; // JSON / everything else: normal network

    event.respondWith(
        caches.open(CACHE).then(async (cache) => {
            const cached = await cache.match(request);
            const network = fetch(request)
                .then((response) => {
                    if (response.ok || response.type === 'opaque') cache.put(request, response.clone());
                    return response;
                })
                .catch(() => cached);
            return cached || network;
        })
    );
});
