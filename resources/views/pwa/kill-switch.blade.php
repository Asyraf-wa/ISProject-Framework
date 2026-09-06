{{-- Served in place of the real worker when the setting is switched off. --}}
/**
 * isproject/framework — service worker, disabled.
 *
 * This file is what "off" looks like from the browser's side.
 *
 * A registered service worker does not go away because the page stopped asking
 * for it. It stays installed, keeps its caches, and keeps answering requests,
 * for as long as that browser profile exists. Serving nothing here would leave
 * every visitor who ever loaded the site while it was on running the old worker
 * indefinitely.
 *
 * So the disabled state ships a worker whose entire job is to remove itself.
 * Browsers re-fetch this script on navigation and at least daily, which means
 * an old installation finds this and takes itself apart without anybody having
 * to clear their site data by hand.
 */
self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        for (const key of await caches.keys()) {
            if (key.startsWith('isproject-')) {
                await caches.delete(key);
            }
        }

        await self.registration.unregister();

        // Reload whatever is still open, so those tabs stop being controlled by
        // a worker that no longer exists.
        for (const client of await self.clients.matchAll({ type: 'window' })) {
            client.navigate(client.url);
        }
    })());
});

// Until the activate step finishes, stay out of the way entirely: no caching,
// no interception, straight to the network.
self.addEventListener('fetch', () => {});
