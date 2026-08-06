/*
 * Service worker de Pointage.
 *
 * Parti pris : les pages HTML ne sont jamais mises en cache. Elles portent des
 * soldes et des opérations, et un cache survivrait à la déconnexion. Seuls les
 * assets construits — au nom déjà empreinté d'un hash, donc immuables — et la
 * coquille hors ligne sont conservés. Sans réseau, l'application affiche un écran
 * hors ligne explicite plutôt que des montants périmés.
 */

const CACHE_VERSION = 'pointage-v1';
const OFFLINE_PAGE = '/offline.html';

const SHELL_ASSETS = [
    OFFLINE_PAGE,
    '/manifest.webmanifest',
    '/icons/icon.svg',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE_VERSION)
            .then((cache) => cache.addAll(SHELL_ASSETS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((names) =>
                Promise.all(names.filter((name) => name !== CACHE_VERSION).map((name) => caches.delete(name))),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) {
        return;
    }

    // Les visites Inertia attendent du JSON : leur répondre du HTML en cache
    // casserait la navigation. Elles restent donc strictement en ligne.
    if (request.headers.get('X-Inertia')) {
        return;
    }

    if (new URL(request.url).pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(request));

        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkThenOfflinePage(request));
    }
});

async function cacheFirst(request) {
    const cache = await caches.open(CACHE_VERSION);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        cache.put(request, response.clone());
    }

    return response;
}

async function networkThenOfflinePage(request) {
    try {
        return await fetch(request);
    } catch {
        const cache = await caches.open(CACHE_VERSION);

        return (
            (await cache.match(OFFLINE_PAGE)) ??
            new Response('Hors ligne', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } })
        );
    }
}

/*
 * Rappels de pointage. Le serveur envoie un JSON { title, body, data.url } à la
 * fin de la période de pointage d'un compte ; cliquer ouvre le pointage guidé.
 */
self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    const payload = event.data.json();

    event.waitUntil(
        self.registration.showNotification(payload.title ?? 'Pointage', {
            body: payload.body ?? '',
            icon: '/icons/icon-192.png',
            badge: '/icons/icon-192.png',
            lang: 'fr',
            data: payload.data ?? {},
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data?.url ?? '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            // Une fenêtre de l'application est déjà ouverte : on la réutilise.
            const existing = windows.find((window) => new URL(window.url).origin === self.location.origin);

            if (existing) {
                return existing.focus().then((focused) => focused.navigate(url));
            }

            return clients.openWindow(url);
        }),
    );
});
