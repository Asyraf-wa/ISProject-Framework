{{--
    Page size selector.

        <x-isproject::per-page :total="$products->total()" />

    A plain form that submits on change, carrying the rest of the query with it
    as hidden fields — so changing the size keeps your search and your sort.
--}}
@props(['total' => null])

@php
    $options = array_values(array_filter(
        array_map('intval', (array) config('isproject.per_page_options', [15, 25, 50, 100])),
        fn (int $size) => $size > 0,
    ));

    $max = (int) config('isproject.max_per_page', 200);
    $current = request()->query('per_page', (string) config('isproject.per_page', 15));
@endphp

<form method="GET" action="{{ url()->current() }}" class="is-per-page">
    @foreach (request()->except(['per_page', 'page']) as $key => $value)
        @if (! is_array($value))
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach

    <label for="per-page">Show</label>

    <select id="per-page" name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">
        @foreach ($options as $size)
            <option value="{{ $size }}" @selected((string) $current === (string) $size)>{{ $size }}</option>
        @endforeach

        {{-- Capped rather than unlimited: pagination still appears beyond the
             ceiling, so "All" never becomes a browser that stops responding. --}}
        <option value="all" @selected($current === 'all')>All (max {{ $max }})</option>
    </select>

    @if ($total !== null)
        <span class="is-per-page-total">of {{ number_format($total) }}</span>
    @endif

    <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">Apply</button></noscript>
</form>
