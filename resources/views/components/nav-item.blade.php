{{--
    One sidebar row. Recursive: an item with children renders a collapse
    toggle and calls itself for each child.

    $item comes from IsProject\Framework\Support\Menu and is already
    normalised — url resolved, permissions applied, active state computed.
--}}
@props(['item', 'depth' => 0])

@if ($item['type'] === 'heading')
    <li class="is-nav-heading">{{ $item['label'] }}</li>
@elseif (empty($item['children']))
    <li class="is-nav-item">
        {{--
            rel="noopener" is not optional on a target="_blank" link: without it
            the page that opens can reach back through window.opener. These URLs
            are typed into a management screen, so they are not ours to trust.
        --}}
        <a href="{{ $item['url'] }}"
           class="is-nav-link{{ $item['active'] ? ' active' : '' }}"
           data-label="{{ $item['label'] }}"
           @if ($item['target'] ?? null) target="_blank" rel="noopener noreferrer" @endif>
            <span class="is-nav-icon"><x-isproject::icon :name="$item['icon']" /></span>
            <span class="is-nav-text">{{ $item['label'] }}</span>
            @if ($item['badge'])
                <span class="badge badge-soft-primary">{{ $item['badge'] }}</span>
            @endif
        </a>
    </li>
@else
    @php $id = 'is-nav-'.\Illuminate\Support\Str::slug($item['label']).'-'.$depth; @endphp

    <li class="is-nav-item">
        <button type="button"
                class="is-nav-link"
                data-label="{{ $item['label'] }}"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $id }}"
                aria-expanded="{{ $item['active'] ? 'true' : 'false' }}"
                aria-controls="{{ $id }}">
            <span class="is-nav-icon"><x-isproject::icon :name="$item['icon']" /></span>
            <span class="is-nav-text">{{ $item['label'] }}</span>
            <span class="is-nav-caret"><x-isproject::icon name="chevron-right" size="12" /></span>
        </button>

        <ul class="is-nav-sub collapse{{ $item['active'] ? ' show' : '' }}" id="{{ $id }}">
            @foreach ($item['children'] as $child)
                <x-isproject::nav-item :item="$child" :depth="$depth + 1" />
            @endforeach
        </ul>
    </li>
@endif
