@php
    // Feedback after an action: success, error, warning, or plain information.
    $types = [
        'success' => ['success', 'check'],
        'error' => ['danger', 'alert'],
        'warning' => ['warning', 'alert'],
        'info' => ['info', 'flag'],
    ];
    $toasts = collect($types)->filter(fn ($type, $key) => session()->has($key));
@endphp
@if ($toasts->isNotEmpty())
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100">
        @foreach ($toasts as $key => [$variant, $icon])
            <div class="toast toast-sies is-{{ $variant }}" role="{{ $variant === 'danger' ? 'alert' : 'status' }}"
                 aria-live="{{ $variant === 'danger' ? 'assertive' : 'polite' }}" aria-atomic="true" data-bs-delay="5000">
                <div class="toast-body">
                    <span class="toast-icon" aria-hidden="true"><x-icon :name="$icon" :size="18" /></span>
                    <div class="flex-grow-1">{{ session($key) }}</div>
                    <button type="button" class="btn-close btn-close-sm ms-2" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        @endforeach
    </div>
@endif
