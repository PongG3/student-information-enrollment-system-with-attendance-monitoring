@use('App\Services\StudentPortalData', 'Portal')
<x-app-layout>
    <div class="welcome-banner mb-3">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <div class="eyebrow">{{ now()->format('l, F j, Y') }}</div>
                <h1 class="welcome-title mb-1">{{ $greeting }}</h1>
                <p class="mb-0 opacity-75">{{ $student->student_number }} · {{ $student->program->code }} · Year {{ $student->year_level }}</p>
            </div>
            <div class="col-lg-4">
                <div class="small opacity-75">Current term</div>
                @if ($term)
                    <div class="fw-semibold">{{ $term->name }} · {{ $term->academicYear->name }}</div>
                    <div class="small opacity-75">Enrollment is {{ $term->enrollment_open ? 'open' : 'closed' }}</div>
                @else
                    <div class="fw-semibold">Not set</div>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([
            ['My classes', $classes->count(), $term ? $term->name : 'No current term', route('student.schedule')],
            ['Units', Portal::number(Portal::units($classes)), 'This term', route('student.enrollments')],
            ['Attendance', Portal::percent($attendance['percentage']), 'This term, to date', route('student.attendance')],
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
                    <x-empty-state message="No classes today." />
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
                @php $alerts = $attendance['rows']->where('flagged', true); @endphp
                @if ($alerts->isEmpty())
                    <x-empty-state message="No attendance concerns. Keep it up." />
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
