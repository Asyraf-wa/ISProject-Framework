{{-- Served as JavaScript from the site root. See routes/pwa.php. --}}
@php
    $version = $pwa->version();
    $precache = $pwa->precache();
    $offline = $pwa->pathOf(route('isproject.pwa.offline'));
    $assetPrefix = parse_url(asset('vendor/isproject/isproject.css'), PHP_URL_PATH);
    $storagePrefix = parse_url(\Illuminate\Support\Facades\Storage::disk(config('isproject.settings.disk', 'public'))->url('x'), PHP_URL_PATH);
@endphp
/**
 * isproject/framework service worker.
 *
 * Generated from the settings screen — do not edit a copy of this file, edit
 * the settings and let it regenerate.
 *
 * What it deliberately does NOT do: cache pages. Every screen in this system is
 * behind sign-in and filtered by role, and these run on shared machines. A
 * cached page would show the next person at that computer whatever the last one
 * was looking at. Assets are safe to keep; answers are not.
 */
const VERSION = @json($version);
const CACHE = 'isproject-' + VERSION;
const OFFLINE_URL = @json($offline);
const PRECACHE = @json($precache);
const ASSET_PREFIX = @json(rtrim(dirname((string) $assetPrefix), '/') . '/');
const STORAGE_PREFIX = @json(rtrim(dirname((string) $storagePrefix), '/') . '/');

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE);

        // One at a time, not cache.addAll: that rejects the whole batch if a
        // single URL 404s, and a missing published asset would then stop the
        // worker installing at all.
        await Promise.all(PRECACHE.map(async (url) => {
            try {
                await cache.add(new Request(url, { cache: 'reload' }));
            } catch (e) {
                // A missing asset is not worth failing the install over.
            }
        }));

        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        for (const key of await caches.keys()) {
            if (key.startsWith('isproject-') && key !== CACHE) {
                await caches.delete(key);
            }
        }

        await self.clients.claim();
    })());
});

/** Assets we may keep: our own published files and uploaded images. */
function isCacheableAsset(url) {
    return url.pathname.startsWith(ASSET_PREFIX) || url.pathname.startsWith(STORAGE_PREFIX);
}

self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Anything that changes something on the server is none of our business.
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Never let the worker answer for its own script or the manifest: that is
    // how a site pins itself to a version it can no longer replace.
    if (url.pathname.endsWith('/isproject-sw.js') || url.pathname.endsWith('/manifest.webmanifest')) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith((async () => {
            try {
                return await fetch(request);
            } catch (e) {
                // Offline. Show the fallback rather than the browser's dinosaur.
                const cached = await caches.match(OFFLINE_URL);

                return cached || Response.error();
            }
        })());

        return;
    }

    if (!isCacheableAsset(url)) {
        return;
    }

    event.respondWith((async () => {
        const cached = await caches.match(request);

        if (cached) {
            return cached;
        }

        try {
            const response = await fetch(request);

            // Only a complete, successful, same-origin response is worth
            // keeping. An opaque or partial one caches a failure.
            if (response.ok && response.status === 200 && response.type === 'basic') {
                const cache = await caches.open(CACHE);
                cache.put(request, response.clone());
            }

            return response;
        } catch (e) {
            return Response.error();
        }
    })());
});

// The page asks for this after an update so the new worker takes over without
// the visitor having to close every tab.
self.addEventListener('message', (event) => {
    if (event.data === 'isproject:skip-waiting') {
        self.skipWaiting();
    }
});
