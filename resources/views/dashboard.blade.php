{{-- Dashboard for the registrar and teachers. The administrator's is dashboard/admin.blade.php.
     Overview = today's work (fits one screen); Trends = the charts, one click away. --}}
@php
    $percent = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format($value, 1), '0'), '.') . '%';
    $role = auth()->user()->role;
@endphp
<x-app-layout>
    @include('dashboard._header')

    @include('dashboard._attention')

    @include('dashboard._cards')

    <ul class="form-tabs dashboard-tabs" role="tablist" aria-label="Dashboard views">
        <li role="presentation">
            <button type="button" class="form-tab active" role="tab" id="tab-overview" data-bs-toggle="tab" data-bs-target="#pane-overview"
                aria-controls="pane-overview" aria-selected="true"><x-icon name="dashboard" :size="16" /> Overview</button>
        </li>
        <li role="presentation">
            <button type="button" class="form-tab" role="tab" id="tab-trends" data-bs-toggle="tab" data-bs-target="#pane-trends"
                aria-controls="pane-trends" aria-selected="false"><x-icon name="chart" :size="16" /> Trends</button>
        </li>
    </ul>

    <div class="tab-content">
        {{-- ===== Overview: today and what needs following up ===== --}}
        <div class="tab-pane fade show active" id="pane-overview" role="tabpanel" aria-labelledby="tab-overview" tabindex="0">
            <div class="row g-3">
                <div class="col-lg-7">
                    @include('dashboard._today')
                </div>

                <div class="col-lg-5">
                    @if ($isTeacher)
                        <div class="card-sies h-100">
                            <div class="card-header-sies">
                                <h2 class="h6 fw-bold mb-0">Frequent absences in my classes</h2>
                                <span class="small text-secondary">over {{ \App\Services\AttendanceStats::rules()['threshold'] }}% this term</span>
                            </div>
                            @if ($flagged->isEmpty())
                                <x-empty-state icon="check" message="No student is over the limit." />
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
                    @else
                        <div class="card-sies h-100">
                            <div class="card-header-sies">
                                <h2 class="h6 fw-bold mb-0">Recent enrollment activity</h2>
                                <a href="{{ route('enrollments.index') }}" class="small fw-semibold text-decoration-none">View all →</a>
                            </div>

                            @if ($recentRequests->isEmpty())
                                <x-empty-state icon="clipboard" message="No enrollment requests yet." />
                            @else
                                <ul class="activity-list">
                                    @foreach ($recentRequests->take(5) as $latest)
                                        <li class="d-flex justify-content-between align-items-center gap-2">
                                            <div class="min-w-0">
                                                <div class="activity-text fw-semibold">{{ $latest->student->full_name }}</div>
                                                <div class="activity-meta">{{ $latest->classOffering->class_code }} · {{ $latest->classOffering->subject->code }} · {{ $latest->updated_at->format('M j') }}</div>
                                            </div>
                                            <x-status-badge :value="$latest->status" />
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ===== Trends: charts built from recorded data only ===== --}}
        <div class="tab-pane fade" id="pane-trends" role="tabpanel" aria-labelledby="tab-trends" tabindex="0">
            @php
                $statusChart = [
                    'type' => 'doughnut',
                    'centerLabel' => 'records',
                    'labels' => ['Present', 'Late', 'Absent', 'Excused'],
                    'datasets' => [['label' => 'Records', 'data' => array_values($termCounts), 'colors' => ['green', 'amber', 'red', 'blue']]],
                ];
                $attendanceChart = [
                    'type' => 'line',
                    'percent' => true,
                    'labels' => $attendanceTrend['labels'],
                    'datasets' => [['label' => 'Attendance', 'data' => $attendanceTrend['values'], 'color' => 'navy', 'fill' => true]],
                ];
            @endphp
            {{-- Registrar: all five charts in one row on wide screens, so the tab fits one screen. --}}
            <div class="row g-3">
                <div class="{{ $isTeacher ? 'col-lg-5' : 'col-md-6 col-xl' }}">
                    <x-chart title="Attendance by status" :config="$statusChart" :empty="array_sum($termCounts) === 0"
                        empty-message="No attendance has been recorded this term yet." note="This term, to date" :height="190" />
                </div>
                <div class="{{ $isTeacher ? 'col-lg-7' : 'col-md-6 col-xl' }}">
                    <x-chart title="Weekly attendance trend" :config="$attendanceChart" :empty="! $attendanceTrend['hasData']"
                        empty-message="Attendance trends appear after the first sheets are recorded." note="Percent, week starting" :height="190" />
                </div>

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
                    <div class="col-md-6 col-lg-4 col-xl">
                        <x-chart title="Requests by status" :config="$statusConfig" :empty="! $enrollmentStatus['hasData']"
                            empty-message="No requests in this term yet." note="Current term" :height="190" />
                    </div>
                    <div class="col-md-6 col-lg-4 col-xl">
                        <x-chart title="Monthly enrollment trend" :config="$trend" :empty="! $enrollmentTrend['hasData']"
                            empty-message="No enrollment requests have been recorded this academic year." note="Requests and enrolled students per month" :height="190" />
                    </div>
                    <div class="col-md-6 col-lg-4 col-xl">
                        <x-chart title="Students by program" :config="$programChart" :empty="! $byProgram['hasData']"
                            empty-message="No students are registered yet." note="Active students" :height="190" />
                    </div>
                @endunless
            </div>
        </div>
    </div>
</x-app-layout>
