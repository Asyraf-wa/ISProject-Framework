{{--
    The chosen accent, as custom property overrides.

    Inline rather than a stylesheet route: it costs no extra request, and it
    can never be served stale from a browser cache — the accent has to change
    the moment somebody saves the setting.

    Emits nothing at all for the default, because the compiled stylesheet is
    already indigo.
--}}
@php $isAccentCss = app(\IsProject\Framework\Support\Theme::class)->css(); @endphp

@if ($isAccentCss)
    <style>{!! $isAccentCss !!}</style>
@endif
