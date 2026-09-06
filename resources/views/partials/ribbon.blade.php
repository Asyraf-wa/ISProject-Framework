{{--
    Corner ribbon.

    Fixed to the top-right of the viewport and hidden below the md breakpoint,
    where it would sit on top of the account menu.
--}}
@php $isRibbon = app(\IsProject\Framework\Support\SiteNotices::class)->ribbon(); @endphp

@if ($isRibbon)
    <div class="is-ribbon is-ribbon-{{ $isRibbon['tone'] }}">
        @if ($isRibbon['url'])
            <a href="{{ $isRibbon['url'] }}"
               @if ($isRibbon['external']) target="_blank" rel="noopener noreferrer" @endif>
                {{ $isRibbon['text'] }}
            </a>
        @else
            <span>{{ $isRibbon['text'] }}</span>
        @endif
    </div>
@endif
