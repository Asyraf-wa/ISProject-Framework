{{--
    The page shown when a navigation fails and the device is offline.

    Deliberately self-contained: no stylesheet, no script file, no image. This
    is the one page that has to render when nothing else can be fetched, so
    depending on another request would be depending on exactly what has just
    failed.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Offline &middot; {{ isproject_setting('app_name', config('app.name')) }}</title>

    <style>
        :root { color-scheme: light dark; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1.5rem;
            background: #f7f7fb;
            color: #1e1e2d;
            font: 16px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }

        @media (prefers-color-scheme: dark) {
            body { background: #16161f; color: #e6e6ef; }
            .card { background: #1e1e2b; border-color: #2c2c3d; }
            .hint { color: #a0a0b8; }
        }

        .card {
            max-width: 28rem;
            width: 100%;
            padding: 2rem;
            border: 1px solid #e3e3ee;
            border-radius: 0.75rem;
            background: #fff;
            text-align: center;
        }

        h1 { margin: 0 0 0.5rem; font-size: 1.25rem; }
        p { margin: 0 0 1rem; }
        .hint { font-size: 0.875rem; color: #6b6b85; }

        button {
            font: inherit;
            padding: 0.5rem 1.25rem;
            border: 0;
            border-radius: 0.5rem;
            background: {{ app(\IsProject\Framework\Support\Pwa::class)->themeColor() }};
            color: #fff;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>You are offline</h1>

        <p>
            {{ isproject_setting('app_name', config('app.name')) }} needs a connection to show this page.
            Nothing you had saved is lost.
        </p>

        <p><button type="button" onclick="location.reload()">Try again</button></p>

        <p class="hint">
            This page came from your device. Pages with real data in them are never stored offline,
            because this system is shared.
        </p>
    </div>

    <script>
        // Reload by itself the moment the connection comes back.
        window.addEventListener('online', function () {
            location.reload();
        });
    </script>
</body>
</html>
