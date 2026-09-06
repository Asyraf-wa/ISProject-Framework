{{-- Routes that exist but have never been scanned cannot be assigned to anyone. --}}
@if (! empty($unsynced))
    <div class="alert alert-warning d-flex gap-2 align-items-start">
        <x-isproject::icon name="alert-circle" />
        <div>
            <strong>{{ count($unsynced) }} route{{ count($unsynced) === 1 ? '' : 's' }} not scanned yet.</strong>
            They cannot be granted to a role until you press <em>Rescan routes</em>.
            <div class="small mt-1 text-body-secondary">
                {{ implode(', ', array_slice($unsynced, 0, 8)) }}@if (count($unsynced) > 8) and {{ count($unsynced) - 8 }} more @endif
            </div>
        </div>
    </div>
@endif
