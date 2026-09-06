<?php

use Illuminate\Support\Facades\Route;
use IsProject\Framework\Support\Pwa;

/*
 * Progressive web app: the manifest, the service worker and the offline page.
 *
 * All three are public. A manifest behind auth is never fetched — the browser
 * asks for it before anyone signs in — and a service worker that 302s to the
 * login screen registers a redirect as its own script and breaks the site.
 *
 * The worker is served from the site root on purpose. A service worker may only
 * control pages at or below its own path, so one at /isproject/sw.js could not
 * control /books; keeping it at the root is what makes the whole application
 * work offline-aware.
 */
Route::middleware(['web'])->group(function () {
    Route::get('manifest.webmanifest', function (Pwa $pwa) {
        // A manifest served while the feature is off would let a browser go on
        // offering to install an app the site has stopped claiming to be.
        abort_unless($pwa->enabled(), 404);

        return response()->json($pwa->manifest(), 200, [
            'Content-Type' => 'application/manifest+json',

            // Revalidated every time rather than held for an hour. This file is
            // governed by a switch on the settings screen, and an hour-long
            // cache means turning that switch off does nothing for an hour —
            // the browser goes on reading a manifest the site has withdrawn.
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    })->name('isproject.pwa.manifest');

    Route::get('isproject-sw.js', function (Pwa $pwa) {
        $view = $pwa->enabled()
            ? 'isproject::pwa.service-worker'
            : 'isproject::pwa.kill-switch';

        return response()->view($view, ['pwa' => $pwa], 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',

            // The worker script itself must never be held in an HTTP cache:
            // a stale copy is how a site gets stuck on an old version, and it
            // is also how the kill switch below would fail to arrive.
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    })->name('isproject.pwa.serviceworker');

    Route::view('offline', 'isproject::pwa.offline')->name('isproject.pwa.offline');
});
