{{--
    A sortable table heading.

        <x-isproject::sort-header column="name" label="Name" />

    Links to the current URL with ?sort= and ?direction= merged in, so the same
    component works on the index, the archive screen, and anything else built
    from the same headers — it never needs to know which route it is on. Every
    other query parameter is carried through, so sorting does not silently drop
    a search or a page size.
--}}
@props([
    'column',
    'label',
    'align' => null,
])

@php
    $active = request()->query('sort') === $column;
    $direction = strtolower((string) request()->query('direction')) === 'asc' ? 'asc' : 'desc';

    // Clicking the column you are already on reverses it; a new column starts
    // ascending, which is what people expect of a name or a date.
    $next = $active && $direction === 'asc' ? 'desc' : 'asc';

    $href = request()->fullUrlWithQuery(['sort' => $column, 'direction' => $next, 'page' => null]);
@endphp

<th @class(['is-sortable', 'text-end' => $align === 'end'])>
    <a href="{{ $href }}"
       @if ($active) aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif>
        <span>{{ $label }}</span>

        @if ($active)
            <span class="is-sort-arrow" aria-hidden="true">{{ $direction === 'asc' ? '↑' : '↓' }}</span>
        @else
            {{-- Present but faint, so the column does not shift when sorted. --}}
            <span class="is-sort-arrow is-sort-idle" aria-hidden="true">↕</span>
        @endif
    </a>
</th>
