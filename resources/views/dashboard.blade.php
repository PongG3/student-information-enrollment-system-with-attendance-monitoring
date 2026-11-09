@php
    $percent = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format($value, 1), '0'), '.') . '%';
    $role = auth()->user()->role;
@endphp
<x-app-layout>
    {{-- ===== Greeting, quick actions and term in one slim bar ===== --}}
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

    {{-- ===== Needs attention: one row of chips ===== --}}
    @if (count($attention) > 0)
        <div class="attention-bar mb-3" role="region" aria-label="Needs attention">
            <h2 class="attention-title"><x-icon name="alert" :size="16" /> Needs attention</h2>
            @foreach ($attention as $item)
                <a href="{{ $item['url'] }}" class="attention-item">
                    <span class="attention-count is-{{ $item['tone'] }}">{{ $item['count'] }}</span>
                    <span>{{ $item['label'] }}</span>
                    <span class="attention-go" aria-hidden="true"><x-icon name="arrow" :size="14" /></span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- ===== Cards ===== --}}
    @php
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
                        <span class="stat-icon"><x-icon :name="$icon" :size="16" /></span>
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

    {{-- ===== Today's attendance ===== --}}
    <div class="row g-3 mb-3">
        <div class="{{ $isTeacher ? 'col-lg-7' : 'col-lg-6' }}">
            <div class="card-sies h-100">
                <div class="card-header-sies">
                    <h2 class="h6 fw-bold mb-0">{{ $isTeacher ? "Today's classes" : "Today's attendance" }}</h2>
                    <span class="small text-secondary">{{ today()->format('D, M j') }}</span>
                </div>
                <div class="p-3">
                    <div class="row g-2 text-center mb-2">
                        @foreach ([['Present', $today['counts']['present'], 'success'], ['Late', $today['counts']['late'], 'warning'], ['Absent', $today['counts']['absent'], 'danger'], ['Excused', $today['counts']['excused'], 'info']] as [$label, $value, $variant])
                            <div class="col-3">
                                <div class="rounded-3 py-1 bg-{{ $variant }}-subtle">
                                    <div class="fs-5 fw-bold lh-sm">{{ $value }}</div>
                                    <div class="small text-secondary">{{ $label }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($today['note'])
                        <div class="alert alert-secondary py-2 small mb-3">{{ $today['note'] }}</div>
                    @endif

                    @if ($today['expected'] > 0)
                        @php
                            $progress = (int) round($today['recorded'] / $today['expected'] * 100);
                        @endphp
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Sheets recorded</span><span class="fw-semibold">{{ $today['recorded'] }} of {{ $today['expected'] }}</span>
                        </div>
                        <div class="progress mb-3" role="progressbar" aria-label="Sheets recorded today" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" style="height: 8px">
                            <div class="progress-bar" style="width: {{ $progress }}%"></div>
                        </div>

                        @php $canTake = in_array($role, ['admin', 'teacher'], true); @endphp
                        <ul class="list-group list-group-flush">
                            {{-- Classes still waiting for a sheet come first: that is what needs doing. --}}
                            @foreach ($today['classes']->sortBy(fn ($row) => $row['recorded'] ? 1 : 0)->values() as $row)
                                @php $sheetUrl = route('attendance.sheet', ['class_offering' => $row['class']->id, 'date' => today()->toDateString()]); @endphp
                                <li class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2">
                                    <div>
                                        <span class="fw-semibold">{{ $row['class']->class_code }}</span>
                                        <span class="small text-secondary">· {{ $row['class']->subject->code }} · {{ $row['class']->scheduleSummary() }}</span>
                                    </div>
                                    <div class="text-nowrap">
                                        @if ($row['recorded'])
                                            <x-status-badge value="verified" label="Recorded" />
                                            <a href="{{ $sheetUrl }}" class="btn btn-outline-secondary btn-sm-sies ms-1">Open</a>
                                        @else
                                            @unless ($isTeacher)
                                                <x-status-badge value="pending" label="Not yet" />
                                            @endunless
                                            @if ($canTake)
                                                <a href="{{ $sheetUrl }}" class="btn btn-navy btn-sm-sies ms-1">Take attendance</a>
                                            @endif
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        <div class="{{ $isTeacher ? 'col-lg-5' : 'col-lg-6' }}">
            @php
                $statusChart = [
                    'type' => 'doughnut',
                    'centerLabel' => 'records',
                    'labels' => ['Present', 'Late', 'Absent', 'Excused'],
                    'datasets' => [['label' => 'Records', 'data' => array_values($termCounts), 'colors' => ['green', 'amber', 'red', 'blue']]],
                ];
            @endphp
            <x-chart title="Attendance by status" :config="$statusChart" :empty="array_sum($termCounts) === 0"
                empty-message="No attendance has been recorded this term yet." note="This term, to date" :height="190" />
        </div>
    </div>

    {{-- ===== Trends ===== --}}
    <div class="row g-3 mb-3">
        @unless ($isTeacher)
            @php
                $trend = [
                    'type' => 'bar',
                    'labels' => $enrollmentTrend['labels'],
                    'datasets' => [
                        ['label' => 'Requests', 'data' => $enrollmentTrend['requests'], 'color' => 'blue'],
                        ['label' => 'Enrolled', 'data' => $enrollmentTrend['enrolled'], 'color' => 'teal'],
                    ],
                ];
            @endphp
            <div class="col-lg-6">
                <x-chart title="Monthly enrollment trend" :config="$trend" :empty="! $enrollmentTrend['hasData']"
                    empty-message="No enrollment requests have been recorded this academic year." note="Requests and enrolled students per month" :height="200" />
            </div>
        @endunless

        @php
            $attendanceChart = [
                'type' => 'line',
                'percent' => true,
                'labels' => $attendanceTrend['labels'],
                'datasets' => [['label' => 'Attendance', 'data' => $attendanceTrend['values'], 'color' => 'navy', 'fill' => true]],
            ];
        @endphp
        <div class="{{ $isTeacher ? 'col-12' : 'col-lg-6' }}">
            <x-chart title="Weekly attendance trend" :config="$attendanceChart" :empty="! $attendanceTrend['hasData']"
                empty-message="Attendance trends appear after the first sheets are recorded." note="Percent, week starting" :height="200" />
        </div>
    </div>

    @unless ($isTeacher)
        @php
            $statusConfig = [
                'type' => 'doughnut',
                'centerLabel' => 'requests',
                'labels' => $enrollmentStatus['labels'],
                'datasets' => [['label' => 'Requests', 'data' => $enrollmentStatus['values'], 'colors' => ['amber', 'blue', 'green', 'red', 'slate']]],
            ];
            $programChart = [
                'type' => 'bar',
                'horizontal' => true,
                'labels' => $byProgram['labels'],
                'datasets' => [['label' => 'Active students', 'data' => $byProgram['values'], 'color' => 'navy']],
            ];
        @endphp
        <div class="row g-3 mb-3">
            <div class="col-lg-4">
                <x-chart title="Requests by status" :config="$statusConfig" :empty="! $enrollmentStatus['hasData']"
                    empty-message="No requests in this term yet." note="Current term" :height="200" />
            </div>
            <div class="col-lg-4">
                <x-chart title="Students by program" :config="$programChart" :empty="! $byProgram['hasData']"
                    empty-message="No students are registered yet." note="Active students" :height="200" />
            </div>
            <div class="col-lg-4">
                <div class="card-sies h-100">
                    <div class="card-header-sies">
                        <h2 class="h6 fw-bold mb-0">{{ $role === 'admin' ? 'Recent activity' : 'Recent enrollment activity' }}</h2>
                        @if ($role === 'admin')
                            <a href="{{ route('audit-logs.index') }}" class="small fw-semibold text-decoration-none">View all →</a>
                        @else
                            <a href="{{ route('enrollments.index') }}" class="small fw-semibold text-decoration-none">View all →</a>
                        @endif
                    </div>

                    @if ($role === 'admin')
                        @if ($recent->isEmpty())
                            <x-empty-state message="No activity recorded yet." />
                        @else
                            <ul class="list-group list-group-flush">
                                @foreach ($recent as $log)
                                    <li class="list-group-item">
                                        <div class="small">{{ $log->description }}</div>
                                        <div class="text-secondary small">
                                            {{ $log->user?->name ?? 'System' }}@if ($log->role_label) ({{ $log->role_label }})@endif
                                            · {{ $log->created_at->format('M j, g:i A') }}
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @else
                        @if ($recentRequests->isEmpty())
                            <x-empty-state message="No enrollment requests yet." />
                        @else
                            <ul class="list-group list-group-flush">
                                @foreach ($recentRequests as $latest)
                                    <li class="list-group-item d-flex justify-content-between gap-2">
                                        <div>
                                            <div class="small fw-semibold">{{ $latest->student->full_name }}</div>
                                            <div class="text-secondary small">{{ $latest->classOffering->class_code }} · {{ $latest->classOffering->subject->code }} · {{ $latest->updated_at->format('M j') }}</div>
                                        </div>
                                        <div><x-status-badge :value="$latest->status" /></div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="card-sies">
            <div class="card-header-sies">
                <h2 class="h6 fw-bold mb-0">Frequent absences in my classes</h2>
                <span class="small text-secondary">over {{ \App\Services\AttendanceStats::rules()['threshold'] }}% this term</span>
            </div>
            @if ($flagged->isEmpty())
                <x-empty-state message="No student is over the limit." />
            @else
                <ul class="list-group list-group-flush">
                    @foreach ($flagged as $row)
                        <li class="list-group-item d-flex justify-content-between gap-2">
                            <div>
                                <div class="fw-semibold small">{{ $row['student']->full_name }}</div>
                                <div class="text-secondary small">{{ $row['student']->student_number }} · {{ $row['class']->class_code }} ({{ $row['class']->subject->code }})</div>
                            </div>
                            <div class="text-end small">
                                <span class="badge text-bg-danger">{{ $percent($row['absence_rate']) }} absent</span>
                                <div class="text-secondary">{{ $row['counts']['absent'] }} absent · {{ $row['counts']['late'] }} late</div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endunless
</x-app-layout>
