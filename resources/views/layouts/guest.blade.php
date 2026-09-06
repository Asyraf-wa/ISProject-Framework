{{--
    Centred, chrome-free shell for login, registration and password reset —
    no sidebar, no topbar.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light"
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

    <title>@yield('title', 'Sign in') &middot; {{ $isName }}</title>

    {{-- The signed-out pages are the only public face this system has, so this
         is where the "allow indexing" setting actually applies. --}}
    @include('isproject::partials.seo', ['title' => trim($__env->yieldContent('title', $isName)), 'indexable' => true])

    @if ($isFavicon)
        <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::disk(config('isproject.settings.disk', 'public'))->url($isFavicon) }}">
    @endif

    <script>
        (function () {
            try {
                var theme = localStorage.getItem('isproject.theme') || @json(isproject_setting('default_theme', 'system'));

                document.documentElement.setAttribute('data-bs-theme',
                    theme === 'light' || theme === 'dark'
                        ? theme
                        : (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));

                var announcement = @json($isAnnouncementId);
                var dismissed = JSON.parse(localStorage.getItem('isproject.announcement') || 'null');

                if (announcement && dismissed && dismissed.id === announcement && Date.now() < dismissed.until) {
                    document.documentElement.setAttribute('data-is-announcement', 'hidden');
                }
            } catch (e) {}
        })();
    </script>

    <link rel="stylesheet" href="{{ asset('vendor/isproject/isproject.css') }}">

    @include('isproject::partials.pwa')

    @stack('styles')
</head>
<body>
{{-- Announcements matter most on the sign-in screen: "the system is down for
     maintenance at 6pm" is worth reading before you try to work. --}}
@include('isproject::partials.announcement')
@include('isproject::partials.ribbon')

<div class="is-guest">
    <div class="is-guest-card">
        @php
            $brand = config('isproject.brand');
            $isLogo = isproject_setting('logo');
            $isTagline = isproject_setting('app_tagline');
        @endphp

        <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
            @if ($isLogo)
                <img class="is-brand-logo"
                     src="{{ \Illuminate\Support\Facades\Storage::disk(config('isproject.settings.disk', 'public'))->url($isLogo) }}"
                     alt="{{ $isName }}">
            @else
                <span class="is-brand-mark"><x-isproject::icon :name="isproject_setting('brand_icon', $brand['icon'] ?? 'grid')" size="18" /></span>
            @endif

            <span class="is-brand-text fs-5">{{ $isName }}</span>
        </div>

        @if ($isTagline)
            <p class="text-center text-body-secondary small mb-4">{{ $isTagline }}</p>
        @else
            <div class="mb-4"></div>
        @endif

        <div class="card">
            <div class="card-body p-4">
                @yield('content')
            </div>
        </div>

        @hasSection('footer')
            <p class="text-center text-body-secondary small mt-3 mb-0">@yield('footer')</p>
        @endif
    </div>
</div>

<script src="{{ asset('vendor/isproject/bootstrap.bundle.min.js') }}"></script>

@if (config('isproject.select.enabled', true))
    <script src="{{ asset('vendor/isproject/tom-select.min.js') }}"></script>
@endif

<script src="{{ asset('vendor/isproject/isproject.js') }}"></script>
@stack('scripts')
</body>
</html>
