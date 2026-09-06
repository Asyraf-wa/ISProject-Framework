{{-- Validation summary. Individual fields also show their own invalid-feedback. --}}
@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <x-isproject::icon name="alert-circle" size="17" />
        <div class="flex-grow-1">
            <h6 class="alert-heading mb-1">Please fix the following:</h6>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
