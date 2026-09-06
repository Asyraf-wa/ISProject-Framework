{{--
    Search engine and link-preview tags.

        @include('isproject::partials.seo', ['title' => $t, 'indexable' => false])

    $indexable is the layout's answer to "could this page ever be public", not
    the site setting — the admin layout passes false unconditionally, so no
    signed-in screen is ever offered for indexing however the settings read.
--}}
@php
    $seo = app(\IsProject\Framework\Support\Seo::class)->meta($title ?? '', $indexable ?? false);
@endphp

<meta name="robots" content="{{ $seo['robots'] }}">

@if ($seo['description'])
    <meta name="description" content="{{ $seo['description'] }}">
@endif

@if ($seo['canonical'])
    <link rel="canonical" href="{{ $seo['canonical'] }}">
@endif

@if ($seo['verification'])
    <meta name="google-site-verification" content="{{ $seo['verification'] }}">
@endif

{{-- Open Graph, which is what chat apps and social sites read. Emitted even
     when the page is not indexable: a link pasted into a staff group chat
     should still preview properly on a site that search engines cannot see. --}}
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $seo['siteName'] }}">
<meta property="og:title" content="{{ $seo['title'] }}">
<meta property="og:url" content="{{ $seo['canonical'] ?? url()->current() }}">

@if ($seo['description'])
    <meta property="og:description" content="{{ $seo['description'] }}">
@endif

@if ($seo['image'])
    <meta property="og:image" content="{{ $seo['image'] }}">
    <meta name="twitter:card" content="summary_large_image">
@else
    <meta name="twitter:card" content="summary">
@endif

<meta name="twitter:title" content="{{ $seo['title'] }}">

@if ($seo['description'])
    <meta name="twitter:description" content="{{ $seo['description'] }}">
@endif

@if ($seo['indexable'])
    {{-- Only on a page that may be indexed: describing an organisation on a
         screen search engines are told to ignore achieves nothing.

         Built here rather than inline in @json, whose argument parser does not
         cope with an array union. --}}
    @php
        $isOrganisation = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $seo['siteName'],
            'url' => $seo['canonical'] ?? url('/'),
            'logo' => $seo['image'],
        ]);
    @endphp

    <script type="application/ld+json">
        {!! json_encode($isOrganisation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endif
