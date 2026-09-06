{{-- Flash messages set by the generated controllers. --}}
@php
    $variants = [
        'success' => ['success', 'check-circle'],
        'error' => ['danger', 'alert-circle'],
        'warning' => ['warning', 'alert-circle'],
        'status' => ['info', 'alert-circle'],
    ];
@endphp

@foreach ($variants as $key => [$variant, $icon])
    @if (session()->has($key))
        <div class="alert alert-{{ $variant }} alert-dismissible" role="alert">
            <x-isproject::icon :name="$icon" size="17" />
            <div class="flex-grow-1">{{ session($key) }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
@endforeach
