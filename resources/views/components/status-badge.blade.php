@props(['value', 'label' => null])
@php
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
<span class="badge rounded-pill text-bg-{{ $variant }}">{{ $label ?? ucfirst((string) $value) }}</span>
