{{-- Greeting, quick actions and term in one slim bar. Needs: $greeting, $term, $isTeacher. --}}
@php $role = auth()->user()->role; @endphp
@php
    $actions = $isTeacher
        ? [['Open attendance', route('attendance.index'), 'check', true], ['My classes', route('teacher.classes'), 'classes', false], ['Reports', route('reports.index'), 'chart', false]]
        : [['Add student', route('students.create'), 'plus', true], ['New enrollment request', route('enrollments.create'), 'clipboard', false], ['Attendance', route('attendance.index'), 'check', false], ['Reports', route('reports.index'), 'chart', false]];
@endphp
<div class="welcome-banner mb-3">
    <div>
        <div class="eyebrow">{{ now()->format('l, F j, Y') }}</div>
        <h1 class="welcome-title">{{ $greeting }}</h1>
    </div>
    <nav class="quick-actions" aria-label="Quick actions">
        @foreach ($actions as [$label, $url, $icon, $primary])
            <a href="{{ $url }}" class="quick-action {{ $primary ? 'is-primary' : '' }}"><x-icon :name="$icon" :size="16" /> {{ $label }}</a>
        @endforeach
    </nav>
    <div class="welcome-term">
        @if ($term)
            <div class="fw-semibold">{{ $term->name }} · {{ $term->academicYear->name }}</div>
            <div class="small opacity-75">Enrollment is {{ $term->enrollment_open ? 'open' : 'closed' }}</div>
        @else
            <div class="fw-semibold">No current term</div>
            @if ($role === 'admin')
                <a href="{{ route('academic-years.index') }}" class="small text-white">Choose the current term</a>
            @endif
        @endif
    </div>
</div>
