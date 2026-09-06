{{-- Coloured badge for an audit event, so a page of them scans at a glance. --}}
@props(['event'])

@php
    $tone = match ($event) {
        'created' => 'success',
        'updated' => 'primary',
        'deleted' => 'danger',
        'restored' => 'warning',
        default => 'secondary',
    };
@endphp

<span class="badge badge-soft-{{ $tone }}">{{ ucfirst($event) }}</span>
