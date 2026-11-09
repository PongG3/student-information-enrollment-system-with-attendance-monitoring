{{-- Dashboard for the registrar and teachers. The administrator's is dashboard/admin.blade.php. --}}
@php
    $percent = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format($value, 1), '0'), '.') . '%';
    $role = auth()->user()->role;
@endphp
<x-app-layout>
    @include('dashboard._header')

    @include('dashboard._attention')

    @include('dashboard._cards')

    {{-- ===== Today's attendance ===== --}}
    <div class="row g-3 mb-3">
        <div class="{{ $isTeacher ? 'col-lg-7' : 'col-lg-6' }}">
            @include('dashboard._today')
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
                        <h2 class="h6 fw-bold mb-0">Recent enrollment activity</h2>
                        <a href="{{ route('enrollments.index') }}" class="small fw-semibold text-decoration-none">View all →</a>
                    </div>

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
