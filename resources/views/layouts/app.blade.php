{{--
    Application shell: sidebar + topbar + content.

    Assets are self-hosted — publish them with
        php artisan vendor:publish --tag=isproject-assets
    There is no CDN, no Google Fonts and no paid theme involved, so this runs
    unchanged on an offline lab machine.

    To use your own layout instead, set ISPROJECT_LAYOUT in .env and provide a
    "content" section and a "title" section.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-is-sidebar="expanded" data-bs-theme="light"
      data-is-select-threshold="{{ (int) config('isproject.select.threshold', 8) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $isName = isproject_setting('app_name', config('app.name'));
        $isFavicon = isproject_setting('favicon');
        $isAnnouncementId = app(\IsProject\Framework\Support\SiteNotices::class)->announcement()['id'] ?? null;
    @endphp

    <title>@yield('title', $isName) &middot; {{ $isName }}</title>

    {{-- indexable: false, always. Every screen using this layout sits behind
         authentication; a crawler cannot reach them, and listing their titles
         and URLs in a search index would only advertise the shape of the
         system. The site setting governs the signed-out pages instead. --}}
    @include('isproject::partials.seo', ['title' => trim($__env->yieldContent('title', $isName)), 'indexable' => false])

    @if ($isFavicon)
        <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::disk(config('isproject.settings.disk', 'public'))->url($isFavicon) }}">
    @endif

    {{-- Restore the saved shell state before first paint: no flash of the
         wrong theme or an expanded sidebar on reload.

         The default below comes from the settings screen and is only a
         fallback: once a visitor has chosen a theme, their choice is what is
         stored, and it wins on every later visit. --}}
    <script>
        (function () {
            try {
                var root = document.documentElement;
                var sidebar = localStorage.getItem('isproject.sidebar');
                var theme = localStorage.getItem('isproject.theme') || @json(isproject_setting('default_theme', 'system'));

                if (sidebar) {
                    root.setAttribute('data-is-sidebar', sidebar);
                }

                root.setAttribute('data-bs-theme',
                    theme === 'light' || theme === 'dark'
                        ? theme
                        : (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));

                // Suppress an announcement this visitor closed recently. Done
                // here, before paint, so a dismissed bar never flashes up.
                // Matching on the id means editing the message brings it back
                // immediately, even for someone who had closed the old one.
                var announcement = @json($isAnnouncementId);
                var dismissed = JSON.parse(localStorage.getItem('isproject.announcement') || 'null');

                if (announcement && dismissed && dismissed.id === announcement && Date.now() < dismissed.until) {
                    root.setAttribute('data-is-announcement', 'hidden');
                }
            } catch (e) {}
        })();
    </script>

    <link rel="stylesheet" href="{{ asset('vendor/isproject/isproject.css') }}">

    @include('isproject::partials.pwa')

    @stack('styles')
</head>
<body>
@include('isproject::partials.ribbon')

<div class="is-layout">
    @include('isproject::partials.sidebar')

    <div class="is-main">
        {{-- Above the sticky topbar, so it scrolls away rather than
             permanently eating a strip of every screen. --}}
        @include('isproject::partials.announcement')

        @include('isproject::partials.topbar')

        <main class="is-content">
            @yield('content')
        </main>

        @include('isproject::partials.footer')
    </div>
</div>

<script src="{{ asset('vendor/isproject/bootstrap.bundle.min.js') }}"></script>

{{-- Searchable dropdowns. Before ours, which looks for TomSelect and simply
     leaves the selects alone if it is not there. --}}
@if (config('isproject.select.enabled', true))
    <script src="{{ asset('vendor/isproject/tom-select.min.js') }}"></script>
@endif

<script src="{{ asset('vendor/isproject/isproject.js') }}"></script>
@stack('scripts')
</body>
</html>
