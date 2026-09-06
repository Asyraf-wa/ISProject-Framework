{{-- Footer text and support address come from the settings screen. --}}
@php
    $isFooter = isproject_setting('footer_text');
    $isSupport = isproject_setting('support_email');
@endphp

<footer class="is-footer">
    <span>{{ $isFooter ?: isproject_setting('app_name', config('app.name')).' · '.date('Y') }}</span>

    @if ($isSupport)
        <a href="mailto:{{ $isSupport }}">{{ $isSupport }}</a>
    @endif
</footer>
