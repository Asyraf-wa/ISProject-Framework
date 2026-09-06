{{--
    Announcement bar.

    Rendered normally and hidden before paint by the boot script in the layout
    when this exact announcement has been dismissed recently — see the
    data-is-announcement attribute. Hiding it in CSS rather than skipping it in
    JS after load means no flash of a bar the visitor already closed.
--}}
@php $isAnnouncement = app(\IsProject\Framework\Support\SiteNotices::class)->announcement(); @endphp

@if ($isAnnouncement)
    <div class="is-announcement is-announcement-{{ $isAnnouncement['tone'] }}"
         role="status"
         data-is-announcement-id="{{ $isAnnouncement['id'] }}"
         data-is-announcement-hours="{{ $isAnnouncement['hours'] }}">

        @if ($isAnnouncement['url'])
            <a class="is-announcement-body"
               href="{{ $isAnnouncement['url'] }}"
               @if ($isAnnouncement['external']) target="_blank" rel="noopener noreferrer" @endif>
                <span>{{ $isAnnouncement['text'] }}</span>
                <x-isproject::icon name="chevron-right" size="14" />
            </a>
        @else
            <span class="is-announcement-body">{{ $isAnnouncement['text'] }}</span>
        @endif

        <button type="button"
                class="is-announcement-close"
                data-is-dismiss="announcement"
                aria-label="Close this announcement for {{ $isAnnouncement['hours'] }} hour{{ $isAnnouncement['hours'] === 1 ? '' : 's' }}">
            &times;
        </button>
    </div>
@endif
