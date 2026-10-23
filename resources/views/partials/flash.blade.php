@if (session('success'))
    <div class="alert alert-success d-flex gap-2 align-items-start alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill mt-1"></i>
        <div>{{ session('success') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-warning d-flex gap-2 align-items-start alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div>{{ session('error') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

{{-- Business-rule errors thrown by services use the "action" key --}}
@if ($errors->has('action'))
    <div class="alert alert-danger d-flex gap-2 align-items-start alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-octagon-fill mt-1"></i>
        <div>
            @foreach ($errors->get('action') as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
