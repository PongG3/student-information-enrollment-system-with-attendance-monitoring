{{-- Greeting, quick actions and term in one slim bar. Needs: $greeting, $term, $isTeacher.
     Quick actions are the role's everyday "do" tasks; pages to look at are in the menu. --}}
@php
    $role = auth()->user()->role;
    // [label, url, icon, primary, opens in a dialog (size) or false]
    $actions = match ($role) {
        'teacher' => [['Open attendance', route('attendance.index'), 'check', true, false]],
        'admin' => [
            ['Add user', route('users.create'), 'user', true, 'md'],
            ['Terms & enrollment', route('academic-years.index'), 'calendar', false, false],
            ['Add student', route('students.create'), 'plus', false, 'xl'],
        ],
        default => [
            ['Add student', route('students.create'), 'plus', true, 'xl'],
            ['New enrollment request', route('enrollments.create'), 'clipboard', false, 'lg'],
        ],
    };
@endphp
<div class="welcome-banner mb-3">
    <div>
        <div class="eyebrow">{{ now()->format('l, F j, Y') }}</div>
        <h1 class="welcome-title">{{ $greeting }}</h1>
    </div>
    <nav class="quick-actions" aria-label="Quick actions">
        @foreach ($actions as [$label, $url, $icon, $primary, $inDialog])
            <a href="{{ $url }}" @if ($inDialog) data-dialog data-dialog-size="{{ $inDialog }}" @endif class="quick-action {{ $primary ? 'is-primary' : '' }}"><x-icon :name="$icon" :size="16" /> {{ $label }}</a>
        @endforeach
    </nav>
    <div class="welcome-term">
        @if ($term)
            <div class="fw-semibold">{{ $term->name }} · {{ $term->academicYear->name }}</div>
            @unless ($isTeacher){{-- teachers do not enroll anyone --}}
                <div class="small opacity-75">Enrollment is {{ $term->enrollment_open ? 'open' : 'closed' }}</div>
            @endunless
        @else
            <div class="fw-semibold">No current term</div>
            @if ($role === 'admin')
                <a href="{{ route('academic-years.index') }}" class="small text-white">Choose the current term</a>
            @endif
        @endif
    </div>
</div>
