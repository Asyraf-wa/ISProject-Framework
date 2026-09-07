{{--
    One chart.

        <x-isproject::chart :option="$option" height="280" />

    $option is an ECharts option array. It is handed to the browser as JSON on a
    data attribute and initialised by isproject.js, which also supplies the
    colours: a chart that hard-codes its palette cannot follow the light and
    dark themes, and this application has both.

    The library is pushed to the scripts stack rather than loaded by the layout.
    It is 664 KB, and a page with no chart on it should not pay for that.
--}}
@props([
    'option' => [],
    'height' => 300,
    'id' => null,
])

@once
    @push('scripts')
        <script src="{{ isproject_asset('echarts.min.js') }}"></script>
    @endpush
@endonce

<div {{ $attributes->class(['is-chart']) }}
     @if ($id) id="{{ $id }}" @endif
     style="height: {{ is_numeric($height) ? $height.'px' : $height }}"
     data-is-chart='@json($option)'
     role="img"
     aria-label="{{ $attributes->get('aria-label', data_get($option, 'title.text', 'Chart')) }}"></div>
