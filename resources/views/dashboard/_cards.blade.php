{{-- The four summary cards. Needs: $term, $isTeacher and the role's card data.
     Today's sheets are not a card: "Needs attention" and the Today panel already say it. --}}
@php
    $percent = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format($value, 1), '0'), '.') . '%';
    // The attendance rule behind the percentage, said on the card itself.
    $lateRule = 'late counts as ' . (\App\Services\AttendanceStats::rules()['late'] ? 'attended' : 'absent');
    $role = auth()->user()->role;

    // [label, value, note, link, icon, change this week, smaller value text]
    if ($isTeacher) {
        $next = $nextClass ?? null;
        $nextValue = $next ? $next['class']->subject->code . ' · ' . \App\Services\ScheduleChecker::time($next['start']->format('H:i')) : '—';
        $nextNote = $next
            ? ($next['start']->isToday() ? 'Today' : ($next['start']->isTomorrow() ? 'Tomorrow' : $next['start']->format('l, M j'))) . ($next['room'] ? ' · ' . $next['room'] : '')
            : 'No meetings in the next two weeks';
        $cards = [
            ['My classes', $teacherCards['classes'], $term ? $term->name : 'No current term', route('teacher.classes'), 'classes', null, false],
            ['Enrolled students', $teacherCards['students'], 'Across my classes', route('teacher.classes'), 'students', null, false],
            ['Next class', $nextValue, $nextNote, $next ? route('classes.show', $next['class']) : null, 'calendar', null, true],
            ['Attendance this term', $percent($termPercentage), 'My classes · ' . $lateRule, route('attendance.index', ['range' => 'month']), 'chart', null, false],
        ];
    } elseif ($role === 'admin') {
        $cards = [
            ['Registered students', $overview['students'], 'Not archived', route('students.index'), 'students', $week['students'], false],
            ['Enrolled students', $overview['enrolled'], $term ? $term->name : 'No current term', route('enrollments.index', array_filter(['status' => 'enrolled', 'term' => $term?->id])), 'clipboard', $week['enrolled'], false],
            ['Active accounts', $accounts['active'], $accounts['staff'] . ' staff · ' . $accounts['students'] . ' ' . \Illuminate\Support\Str::plural('student', $accounts['students']), route('users.index', ['status' => 'active']), 'user', null, false],
            ['Inactive accounts', $accounts['inactive'], 'Cannot sign in', route('users.index', ['status' => 'inactive']), 'key', null, false],
        ];
    } else {
        $cards = [
            ['Registered students', $overview['students'], 'Not archived', route('students.index'), 'students', $week['students'], false],
            ['Enrolled students', $overview['enrolled'], $term ? $term->name : 'No current term', route('enrollments.index', array_filter(['status' => 'enrolled', 'term' => $term?->id])), 'clipboard', $week['enrolled'], false],
            ['Classes this term', $overview['classes'], $overview['subjects'] . ' ' . \Illuminate\Support\Str::plural('subject', $overview['subjects']) . ' offered', route('classes.index'), 'classes', null, false],
            ['Attendance this term', $percent($termPercentage), 'All classes · ' . $lateRule, route('reports.index'), 'chart', null, false],
        ];
    }
@endphp

<div class="row g-3 mb-3">
    @foreach ($cards as [$label, $value, $note, $url, $icon, $change, $small])
        <div class="col-6 col-xl-3">
            @if ($url)
                <a href="{{ $url }}" class="stat-card has-link text-decoration-none d-block">
            @else
                <div class="stat-card">
            @endif
                    @unless ($compact ?? false)<span class="stat-icon"><x-icon :name="$icon" :size="16" /></span>@endunless
                    <div class="stat-label">{{ $label }}</div>
                    <div class="stat-value {{ $small ? 'is-small' : '' }}">{{ $value }}@if ($change) <span class="stat-change" title="In the last 7 days">+{{ $change }} this week</span>@endif</div>
                    <div class="stat-note">{{ $note }}</div>
                    @if ($url)<span class="stat-go" aria-hidden="true">View →</span>@endif
            @if ($url)
                </a>
            @else
                </div>
            @endif
        </div>
    @endforeach
</div>
