{{-- Dashboard for the registrar and teachers. The administrator's is dashboard/admin.blade.php.
     Overview = today's work (fits one screen); Trends = the charts, one click away. --}}
@php
    $percent = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format($value, 1), '0'), '.') . '%';
    $role = auth()->user()->role;
@endphp
<x-app-layout tab-title="Dashboard">
    @include('dashboard._header')

    @include('dashboard._attention')

    @include('dashboard._cards')

    <ul class="form-tabs dashboard-tabs" role="tablist" aria-label="Dashboard views">
        <li role="presentation">
            @php $onTrends = request()->filled('trend_class'); @endphp
            <button type="button" @class(['form-tab', 'active' => ! $onTrends]) role="tab" id="tab-overview" data-bs-toggle="tab" data-bs-target="#pane-overview"
                aria-controls="pane-overview" aria-selected="{{ $onTrends ? 'false' : 'true' }}"><x-icon name="dashboard" :size="16" /> Overview</button>
        </li>
        <li role="presentation">
            <button type="button" @class(['form-tab', 'active' => $onTrends]) role="tab" id="tab-trends" data-bs-toggle="tab" data-bs-target="#pane-trends"
                aria-controls="pane-trends" aria-selected="{{ $onTrends ? 'true' : 'false' }}"><x-icon name="chart" :size="16" /> Trends</button>
        </li>
    </ul>

    <div class="tab-content">
        {{-- ===== Overview: today and what needs following up ===== --}}
        <div @class(['tab-pane', 'fade', 'show active' => ! $onTrends]) id="pane-overview" role="tabpanel" aria-labelledby="tab-overview" tabindex="0">
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
                                    @foreach ($recentRequests as $latest)
                                        <li class="d-flex justify-content-between align-items-center gap-2">
                                            <div class="min-w-0">
                                                <a href="{{ route('students.show', $latest['student']) }}" class="activity-text fw-semibold text-navy d-block">{{ $latest['student']->full_name }}</a>
                                                <div class="activity-meta text-truncate">
                                                    @if ($latest['subjects']->count() > 1){{ $latest['subjects']->count() }} classes: @endif{{ $latest['subjects']->implode(', ') }} · {{ $latest['date']->format('M j') }}
                                                </div>
                                            </div>
                                            <x-status-badge :value="$latest['status']" />
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ===== Trends: change over time, and who needs following up ===== --}}
        <div @class(['tab-pane', 'fade', 'show active' => $onTrends]) id="pane-trends" role="tabpanel" aria-labelledby="tab-trends" tabindex="0">
            @php
                $attendanceChart = [
                    'type' => 'line',
                    'percent' => true,
                    'labels' => $attendanceTrend['labels'],
                    'datasets' => [['label' => 'Attendance', 'data' => $attendanceTrend['values'], 'color' => 'navy', 'fill' => true]],
                ];
            @endphp
            @unless ($isTeacher)
                {{-- The registrar can narrow the weekly attendance trend to one class. --}}
                <form method="GET" class="filter-bar mb-3" aria-label="Trend filter">
                    <label class="date-field" for="trendClass"><span>Weekly attendance for</span></label>
                    <select name="trend_class" id="trendClass" class="form-select" onchange="this.form.submit()">
                        <option value="0">All classes</option>
                        @foreach ($trendClasses as $option)
                            <option value="{{ $option->id }}" @selected($trendClass === $option->id)>{{ $option->class_code }} · {{ $option->subject->code }}</option>
                        @endforeach
                    </select>
                </form>
            @endunless
            <div class="row g-3">
                <div class="{{ $isTeacher ? 'col-lg-6' : 'col-md-6 col-xl-4' }}">
                    <x-chart title="Weekly attendance" :config="$attendanceChart" :empty="! $attendanceTrend['hasData']"
                        empty-message="The trend appears once two weeks of attendance are recorded." note="Attendance rate, by week" :height="190" />
                </div>

                @if ($isTeacher)
                    @php
                        $classChart = [
                            'type' => 'bar',
                            'horizontal' => true,
                            'percent' => true,
                            'labels' => $byClass['labels'],
                            'datasets' => [['label' => 'Attendance', 'data' => $byClass['values'], 'color' => 'navy']],
                        ];
                    @endphp
                    <div class="col-lg-6">
                        <x-chart title="Attendance by class" :config="$classChart" :empty="! $byClass['hasData']"
                            empty-message="Each class appears once its first sheet is recorded." note="This term, lowest first" :height="190" />
                    </div>
                @else
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
                    <div class="col-md-6 col-xl-4">
                        <x-chart title="Enrollment by month" :config="$trend" :empty="! $enrollmentTrend['hasData']"
                            empty-message="No enrollment requests in this term yet." note="Requests and enrolled students, this term" :height="190" />
                    </div>

                    <div class="col-xl-4">
                        <div class="card-sies h-100">
                            <div class="card-header-sies">
                                <div>
                                    <h2 class="h6 fw-bold mb-1">Students to follow up</h2>
                                    <div class="small text-secondary">Absent in over {{ \App\Services\AttendanceStats::rules()['threshold'] }}% of meetings this term</div>
                                </div>
                                <a href="{{ route('reports.show', 'absentees') }}" class="small fw-semibold text-decoration-none text-nowrap">Full list →</a>
                            </div>
                            @if ($flagged->isEmpty())
                                <x-empty-state icon="check" message="No student is over the absence limit." />
                            @else
                                <ul class="list-group list-group-flush">
                                    @foreach ($flagged as $row)
                                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                                            <div class="min-w-0">
                                                <a href="{{ route('students.show', $row['student']) }}" class="fw-semibold small text-navy">{{ $row['student']->full_name }}</a>
                                                <div class="text-secondary small text-truncate">{{ $row['class']->class_code }} · {{ $row['class']->subject->code }}</div>
                                            </div>
                                            <div class="text-end small text-nowrap">
                                                <span class="badge text-bg-danger">{{ $percent($row['absence_rate']) }} absent</span>
                                                <div class="text-secondary">{{ $row['counts']['absent'] }} absent · {{ $row['counts']['late'] }} late</div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
            {{-- Percentages follow the attendance rules in System Settings; say so where they are shown. --}}
            <p class="small text-secondary mt-2 mb-0">How percentages are counted: {{ \App\Services\AttendanceStats::policy() }}.</p>
        </div>
    </div>
</x-app-layout>
