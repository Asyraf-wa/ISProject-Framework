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

    @include('isproject::partials.accent')

    @include('isproject::partials.pwa')

    @stack('styles')
</head>
<body>
{{-- Announcements matter most on the sign-in screen: "the system is down for
     maintenance at 6pm" is worth reading before you try to work. --}}
@include('isproject::partials.announcement')
@include('isproject::partials.ribbon')

@php
    $brand = config('isproject.brand');
    $isLogo = isproject_setting('logo');
    $isTagline = isproject_setting('app_tagline');
    $isLanding = (array) config('isproject.landing', []);
    $isShowcase = (bool) ($isLanding['enabled'] ?? true);
    $isHeadline = $isLanding['headline'] ?: ($isTagline ?: ($isLanding['fallback_headline'] ?? null));

    $isMark = fn () => isproject_setting('brand_icon', $brand['icon'] ?? 'grid');
@endphp

<div class="is-guest{{ $isShowcase ? ' is-guest-split' : '' }}">
    @if ($isShowcase)
        {{--
            The half of the page that is not a form. Everything on it comes from
            Settings or config, so a project renames and re-points it without
            touching this file.

            aria-hidden is deliberately absent: this is real content, not
            decoration, and somebody using a screen reader should be able to
            read what the system they are signing in to actually is.
        --}}
        <section class="is-showcase">
            <a href="{{ url('/') }}" class="is-showcase-brand">
                @if ($isLogo)
                    <img class="is-brand-logo"
                         src="{{ \Illuminate\Support\Facades\Storage::disk(config('isproject.settings.disk', 'public'))->url($isLogo) }}"
                         alt="{{ $isName }}">
                @else
                    <span class="is-brand-mark"><x-isproject::icon :name="$isMark()" size="18" /></span>
                    <span class="is-brand-text fs-5">{{ $isName }}</span>
                @endif
            </a>

            @if ($isHeadline)
                <h1 class="is-showcase-headline">{{ $isHeadline }}</h1>
            @endif

            @if (! empty($isLanding['points']))
                <ul class="is-showcase-points">
                    @foreach ($isLanding['points'] as $isPoint)
                        <li>
                            <span class="is-showcase-icon">
                                <x-isproject::icon :name="$isPoint['icon'] ?? 'check-circle'" size="16" />
                            </span>
                            <span>
                                <strong>{{ $isPoint['title'] ?? '' }}</strong>
                                <span class="d-block">{{ $isPoint['text'] ?? '' }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <p class="is-showcase-foot">
                {{ isproject_setting('footer_text', '© '.date('Y').' '.$isName) }}
            </p>
        </section>
    @endif

    <div class="is-guest-panel">
        <div class="is-guest-card">
            {{-- On a narrow screen the showcase is hidden, so the card carries
                 the identity instead — otherwise the form floats unlabelled. --}}
            <div class="is-guest-brand{{ $isShowcase ? ' d-lg-none' : '' }}">
                @if ($isLogo)
                    <img class="is-brand-logo"
                         src="{{ \Illuminate\Support\Facades\Storage::disk(config('isproject.settings.disk', 'public'))->url($isLogo) }}"
                         alt="{{ $isName }}">
                @else
                    <span class="is-brand-mark"><x-isproject::icon :name="$isMark()" size="18" /></span>
                    <span class="is-brand-text fs-5">{{ $isName }}</span>
                @endif
            </div>

            @if ($isTagline && ! $isShowcase)
                <p class="text-center text-body-secondary small mb-4">{{ $isTagline }}</p>
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
</div>

<script src="{{ asset('vendor/isproject/bootstrap.bundle.min.js') }}"></script>

@if (config('isproject.select.enabled', true))
    <script src="{{ asset('vendor/isproject/tom-select.min.js') }}"></script>
@endif

<script src="{{ asset('vendor/isproject/isproject.js') }}"></script>
@stack('scripts')
</body>
</html>
