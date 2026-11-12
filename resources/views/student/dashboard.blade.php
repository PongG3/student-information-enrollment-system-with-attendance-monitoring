@use('App\Services\StudentPortalData', 'Portal')
<x-app-layout tab-title="Dashboard">
    @php
        // Quick actions: the offered classes come first while enrollment is open (requests go through the registrar).
        $enrolling = $term && $term->enrollment_open;
        $actions = array_values(array_filter([
            $enrolling ? ['Available courses', route('student.courses'), 'book', true] : null,
            ['My schedule', route('student.schedule'), 'calendar', ! $enrolling],
            ['My attendance', route('student.attendance'), 'check', false],
        ]));
    @endphp
    <div class="welcome-banner mb-3">
        <div>
            <div class="eyebrow">{{ now()->format('l, F j, Y') }}</div>
            <h1 class="welcome-title">{{ $greeting }}</h1>
            <div class="small opacity-75">{{ $student->student_number }} · {{ $student->program->code }} · Year {{ $student->year_level }}</div>
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
            @endif
        </div>
    </div>
    @php
        $meetingsRecorded = array_sum($attendance['overall']);
        // A percentage from one or two meetings says little: say how many it is based on.
        $attendanceNote = match (true) {
            $meetingsRecorded === 0 => 'Nothing recorded yet',
            $meetingsRecorded < \App\Services\AttendanceStats::MIN_MEETINGS_FOR_ALERT => 'From ' . $meetingsRecorded . ' ' . \Illuminate\Support\Str::plural('meeting', $meetingsRecorded) . ' so far',
            default => 'This term, to date',
        };
    @endphp
    <div class="row g-3 mb-4">
        @foreach ([
            ['My classes', $classes->count(), $term ? $term->name : 'No current term', route('student.schedule')],
            ['Units', Portal::number(Portal::units($classes)), 'This term', route('student.enrollments')],
            ['Attendance', Portal::percent($attendance['percentage']), $attendanceNote, route('student.attendance')],
            ['Requests in progress', $openRequests, 'Pending or approved', route('student.enrollments')],
        ] as [$label, $value, $note, $url])
            <div class="col-6 col-xl-3">
                <a href="{{ $url }}" class="stat-card text-decoration-none d-block">
                    <div class="stat-label">{{ $label }}</div>
                    <div class="stat-value">{{ $value }}</div>
                    <div class="stat-note">{{ $note }}</div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card-sies h-100">
                <div class="card-header-sies">
                    <h2 class="h6 fw-bold mb-0">Today's classes</h2>
                    <span class="small text-secondary">{{ today()->format('D, M j') }}</span>
                </div>
                @if ($todaysClasses->isEmpty())
                    <x-empty-state icon="calendar" message="No classes today." />
                @else
                    <div class="table-responsive">
                        <table class="table table-sies align-middle mb-0">
                            <thead><tr><th>Time</th><th>Subject</th><th>Room</th><th>Teacher</th></tr></thead>
                            <tbody>
                                @foreach ($todaysClasses as $item)
                                    <tr>
                                        <td class="text-nowrap">{{ \App\Services\ScheduleChecker::time($item['meeting']->start_time) }} to {{ \App\Services\ScheduleChecker::time($item['meeting']->end_time) }}</td>
                                        <td class="fw-semibold">{{ $item['class']->subject->code }}<div class="text-secondary small fw-normal">{{ $item['class']->subject->title }}</div></td>
                                        <td>{{ $item['meeting']->room ?: '—' }}</td>
                                        <td>{{ $item['class']->teacher->name }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card-sies h-100">
                <div class="card-header-sies"><h2 class="h6 fw-bold mb-0">Attendance alerts</h2></div>
                @php
                    $alerts = $attendance['rows']->where('flagged', true);
                    // Absences in classes with too few meetings to judge yet: shown, not hidden behind "Keep it up".
                    $early = $attendance['rows']->where('flagged', false)->filter(fn ($row) => $row['counts']['absent'] > 0);
                    $recorded = array_sum($attendance['overall']);
                    $minimum = \App\Services\AttendanceStats::MIN_MEETINGS_FOR_ALERT;
                @endphp
                @if ($alerts->isEmpty() && $early->isEmpty())
                    <x-empty-state icon="check" :message="$recorded === 0 ? 'No attendance has been recorded for your classes yet.' : 'No attendance concerns. Keep it up.'" />
                @elseif ($alerts->isEmpty())
                    <ul class="list-group list-group-flush">
                        @foreach ($early as $row)
                            <li class="list-group-item">
                                <div class="fw-semibold">{{ $row['class']->subject->code }} · {{ $row['class']->subject->title }}</div>
                                <div class="small text-secondary">
                                    Absent {{ $row['counts']['absent'] }} of {{ $row['meetings'] }} {{ \Illuminate\Support\Str::plural('meeting', $row['meetings']) }} so far.
                                    <a href="{{ route('student.attendance.show', $row['class']) }}">See dates</a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <div class="px-3 pb-3 small text-secondary">Alerts start once {{ $minimum }} meetings of a class are recorded.</div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach ($alerts as $row)
                            <li class="list-group-item">
                                <div class="fw-semibold">{{ $row['class']->subject->code }} · {{ $row['class']->subject->title }}</div>
                                <div class="small text-secondary">
                                    Absent {{ $row['counts']['absent'] }} of {{ $row['meetings'] }} meetings. Attendance {{ Portal::percent($row['percentage']) }}.
                                    <a href="{{ route('student.attendance.show', $row['class']) }}">See dates</a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
