@php
    $toasts = collect(['success' => 'success', 'error' => 'danger', 'warning' => 'warning'])
        ->filter(fn ($variant, $key) => session()->has($key));
@endphp
@if ($toasts->isNotEmpty())
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100">
        @foreach ($toasts as $key => $variant)
            <div class="toast align-items-center text-bg-{{ $variant }} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="6000">
                <div class="d-flex">
                    <div class="toast-body">{{ session($key) }}</div>
                    <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        @endforeach
    </div>
@endif
