{{--
    Progressive web app tags and registration.

    Included from every layout. When the setting is off this partial still emits
    something — the cleanup — because a browser that installed the worker while
    it was on will otherwise keep running it forever.
--}}
@php $isPwa = app(\IsProject\Framework\Support\Pwa::class); @endphp

@if ($isPwa->enabled())
    <link rel="manifest" href="{{ route('isproject.pwa.manifest') }}">
    <meta name="theme-color" content="{{ $isPwa->themeColor() }}">

    {{-- Safari reads none of the manifest: on iOS the home screen icon, the
         title and the standalone behaviour all come from these instead. --}}
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ $isPwa->shortName() }}">

    @if ($isIcon = $isPwa->icon())
        <link rel="apple-touch-icon" href="{{ $isIcon['url'] }}">
    @endif

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register(@json(url('/isproject-sw.js')), { scope: '/' })
                    .then(function (registration) {
                        // A worker waiting behind the current one only takes
                        // over once every tab has closed, which for a system
                        // people leave open all day is close to never.
                        if (registration.waiting) {
                            registration.waiting.postMessage('isproject:skip-waiting');
                        }

                        registration.addEventListener('updatefound', function () {
                            var installing = registration.installing;

                            if (!installing) {
                                return;
                            }

                            installing.addEventListener('statechange', function () {
                                if (installing.state === 'installed' && navigator.serviceWorker.controller) {
                                    installing.postMessage('isproject:skip-waiting');
                                }
                            });
                        });
                    })
                    .catch(function () {
                        // Registration fails on a plain http:// host, which is
                        // expected and not worth an error in the console.
                    });
            });
        }
    </script>
@else
    <script>
        // Switched off. Remove any worker a previous visit installed, rather
        // than leaving it to serve caches from a version of this site that no
        // longer wants one. Fetching the script above also serves a
        // self-unregistering worker, so this is the belt to that pair of braces.
        if ('serviceWorker' in navigator && navigator.serviceWorker.getRegistrations) {
            navigator.serviceWorker.getRegistrations().then(function (registrations) {
                registrations.forEach(function (registration) {
                    var worker = registration.active || registration.waiting || registration.installing;

                    if (worker && worker.scriptURL.indexOf('isproject-sw.js') !== -1) {
                        registration.unregister();
                    }
                });
            }).catch(function () {});

            if (window.caches && caches.keys) {
                caches.keys().then(function (keys) {
                    keys.forEach(function (key) {
                        if (key.indexOf('isproject-') === 0) {
                            caches.delete(key);
                        }
                    });
                }).catch(function () {});
            }
        }
    </script>
@endif
