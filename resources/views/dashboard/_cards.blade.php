{{-- The four summary cards. Needs: $today, $term, $isTeacher and the role's card data. --}}
@php
    $percent = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format($value, 1), '0'), '.') . '%';
    // Say plainly when today's figure only covers part of the classes.
    $todayNote = match (true) {
        $today['expected'] === 0 => 'No sheets expected today',
        $today['recorded'] === 0 => 'Nothing recorded yet',
        $today['recorded'] < $today['expected'] => 'Partial: ' . $today['recorded'] . ' of ' . $today['expected'] . ' sheets recorded',
        default => 'All ' . $today['expected'] . ' ' . \Illuminate\Support\Str::plural('sheet', $today['expected']) . ' recorded',
    };

    // A percentage only means something once every sheet is in; until then show the plain count.
    $todayTotal = array_sum($today['counts']);
    $todayComplete = $today['expected'] > 0 && $today['recorded'] >= $today['expected'];
    $todayValue = match (true) {
        $todayComplete => $percent($today['percentage']),
        $todayTotal > 0 => $today['counts']['present'] . ' of ' . $todayTotal . ' present',
        default => '—',
    };
    $todaySmall = ! $todayComplete && $todayTotal > 0;

    // [label, value, note, link, icon, change this week, smaller value text]
    if ($isTeacher) {
        $cards = [
            ['My classes', $teacherCards['classes'], $term ? $term->name : 'No current term', route('teacher.classes'), 'classes', null, false],
            ['Enrolled students', $teacherCards['students'], 'Across my classes', null, 'students', null, false],
            ['Attendance sheets today', $today['recorded'] . ' of ' . $today['expected'], 'Recorded of expected', route('attendance.index'), 'check', null, false],
            ['Attendance this term', $percent($termPercentage), 'My classes, to date', route('attendance.index', ['range' => 'month']), 'chart', null, false],
        ];
    } else {
        $cards = [
            ['Registered students', $overview['students'], 'Not archived', route('students.index'), 'students', $week['students'], false],
            ['Enrolled students', $overview['enrolled'], $term ? $term->name : 'No current term', route('enrollments.index', array_filter(['status' => 'enrolled', 'term' => $term?->id])), 'clipboard', $week['enrolled'], false],
            ['Classes this term', $overview['classes'], $overview['subjects'] . ' ' . \Illuminate\Support\Str::plural('subject', $overview['subjects']) . ' offered', route('classes.index'), 'classes', null, false],
            ['Attendance today', $todayValue, $todayNote, route('attendance.index'), 'check', null, $todaySmall],
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
