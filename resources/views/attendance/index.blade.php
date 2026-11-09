<x-app-layout :title="$isStaff ? 'Attendance Monitoring' : 'Attendance'"
    :subtitle="$isStaff ? 'Daily, weekly and monthly attendance across classes.' : 'Attendance for your classes. Open a class to record or review a sheet.'">
    @php
        $query = array_filter(['term' => $term?->id, 'class_offering' => $classId]);
        $percentFormat = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format($value, 1), '0'), '.') . '%';
        $totalMarked = array_sum($counts);
    @endphp

    <div class="card-sies p-3 mb-4">
        <form method="GET" class="filter-bar">
            <select name="term" class="form-select" onchange="this.form.submit()" aria-label="Term">
                @foreach ($terms as $option)
                    <option value="{{ $option->id }}" @selected($term && $term->id === $option->id)>
                        {{ $option->name }} · {{ $option->academicYear->name }}{{ $option->is_current ? ' (current)' : '' }}
                    </option>
                @endforeach
            </select>
            <select name="class_offering" class="form-select" aria-label="Class">
                <option value="">All classes</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected($classId === $class->id)>{{ $class->class_code }} · {{ $class->subject->code }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control" aria-label="From date">
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control" aria-label="To date">
            <button class="btn btn-outline-secondary btn-sm-sies">Show</button>
        </form>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-3 small">
            <span class="text-secondary">Quick range:</span>
            <div class="segmented" role="group" aria-label="Quick range">
                @foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month'] as $key => $label)
                    <a @class(['segmented-item', 'is-active' => $range === $key]) href="{{ route('attendance.index', $query + ['range' => $key]) }}" @if ($range === $key) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
            </div>
            <span class="text-secondary align-self-center ms-2">Showing {{ $from->format('M j') }}@if (! $from->isSameDay($to)) to {{ $to->format('M j, Y') }}@else, {{ $from->format('Y') }}@endif</span>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([['Students marked', $totalMarked, 'Records in this period'], ['Present', $counts['present'], ''], ['Absent', $counts['absent'], ''], ['Late', $counts['late'], ''], ['Excused', $counts['excused'], '']] as [$label, $value, $note])
            <div class="col-6 col-md-4 col-xl">
                <div class="stat-card">
                    <div class="stat-label">{{ $label }}</div>
                    <div class="stat-value">{{ $value }}</div>
                    @if ($note)<div class="stat-note">{{ $note }}</div>@endif
                </div>
            </div>
        @endforeach
        <div class="col-6 col-md-4 col-xl">
            <div class="stat-card">
                <div class="stat-label">Attendance</div>
                <div class="stat-value">{{ $percentFormat($percentage) }}</div>
                <div class="stat-note">Present and late count; excused follows settings</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card-sies">
                <div class="card-header-sies"><h2 class="h6 fw-bold mb-0">Attendance sheets</h2></div>
                @if ($sessions->isEmpty())
                    <x-empty-state icon="check" message="No attendance sheets were recorded in this period." />
                @else
                    <div class="table-responsive">
                        <table class="table table-sies table-hover align-middle">
                            <thead><tr><th>Date</th><th>Class</th><th>Teacher</th><th class="text-center">P</th><th class="text-center">L</th><th class="text-center">A</th><th class="text-center">E</th><th class="text-end"></th></tr></thead>
                            <tbody>
                                @foreach ($sessions as $session)
                                    <tr>
                                        <td class="text-nowrap fw-semibold">{{ $session->session_date->format('D, M j') }}</td>
                                        <td>{{ $session->classOffering->class_code }}<div class="text-secondary small">{{ $session->classOffering->subject->code }}</div></td>
                                        <td class="small">{{ $session->classOffering->teacher->name }}</td>
                                        <td class="text-center">{{ $session->present_count }}</td>
                                        <td class="text-center">{{ $session->late_count }}</td>
                                        <td class="text-center">{{ $session->absent_count }}</td>
                                        <td class="text-center">{{ $session->excused_count }}</td>
                                        <td class="text-end"><a href="{{ route('attendance.sheet', ['class_offering' => $session->class_offering_id, 'date' => $session->session_date->toDateString()]) }}" class="btn btn-outline-secondary btn-sm-sies">Open</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-3 pt-2 small text-secondary">P present · L late · A absent · E excused</div>
                    <x-table-footer :items="$sessions" noun="sheet" />
                @endif
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card-sies">
                <div class="card-header-sies">
                    <h2 class="h6 fw-bold mb-0">Frequent absences</h2>
                    <span class="text-secondary small">over {{ $threshold }}% this term</span>
                </div>
                @if ($flagged->isEmpty())
                    <x-empty-state icon="check" message="No student is over the limit." />
                @else
                    <ul class="list-group list-group-flush">
                        @foreach ($flagged as $row)
                            <li class="list-group-item">
                                <div class="fw-semibold small">{{ $row['student']->full_name }}</div>
                                <div class="text-secondary small">{{ $row['student']->student_number }} · {{ $row['class']->class_code }} ({{ $row['class']->subject->code }})</div>
                                <div class="small mt-1">
                                    <span class="badge text-bg-danger">{{ $percentFormat($row['absence_rate']) }} absent</span>
                                    <span class="text-secondary">{{ $row['counts']['absent'] }} absent · {{ $row['counts']['late'] }} late · attendance {{ $percentFormat($row['percentage']) }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <div class="px-3 py-2 small text-secondary border-top">Flagged after at least {{ \App\Services\AttendanceStats::MIN_MEETINGS_FOR_ALERT }} recorded meetings.</div>
            </div>
        </div>
    </div>
</x-app-layout>
