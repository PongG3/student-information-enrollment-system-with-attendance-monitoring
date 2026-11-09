@props(['value', 'label' => null])
@php
    // One colour per meaning, used everywhere: green = done/good, amber = waiting, red = problem,
    // blue = in progress/approved, navy = current, grey = closed/inactive.
    $map = [
        'active' => 'success', 'enrolled' => 'success', 'verified' => 'success', 'present' => 'success', 'open' => 'success',
        'current' => 'primary',
        'pending' => 'warning', 'submitted' => 'warning', 'late' => 'warning',
        'approved' => 'info', 'excused' => 'info',
        'inactive' => 'secondary', 'closed' => 'secondary', 'cancelled' => 'secondary',
        'rejected' => 'danger', 'absent' => 'danger',
    ];
    $variant = $map[strtolower((string) $value)] ?? 'secondary';
@endphp
<span {{ $attributes->class(['badge', 'badge-soft', 'badge-soft-' . $variant]) }}>{{ $label ?? ucfirst((string) $value) }}</span>
