<x-app-layout :title="$isStaff ? 'Attendance Monitoring' : 'Attendance'"
    :subtitle="$isStaff ? 'Daily, weekly and monthly attendance across classes.' : 'Take attendance for today, catch up on missed sheets, and review your classes below.'">
    @php
        $query = array_filter(['term' => $term?->id, 'class_offering' => $classId]);
        $percentFormat = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format($value, 1), '0'), '.') . '%';
        $totalMarked = array_sum($counts);
        $oneDay = $from->isSameDay($to);
        // Export: the reports already make PDF and Excel; open them with this page's filters.
        $reportQuery = $query + ['from' => $from->toDateString(), 'to' => $to->toDateString()];
        $dailyQuery = $query + ['date' => $from->toDateString()];
        // Nothing today? Offer the next wider range instead of a dead end.
        $wider = match ($range) { 'today' => ['week', 'Show this week'], 'week' => ['month', 'Show this month'], default => null };
    @endphp

    <x-slot:actions>
        <div class="dropdown">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <x-icon name="file" :size="16" /> Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h3 class="dropdown-header">Class summary · {{ $oneDay ? $from->format('M j') : $from->format('M j') . '–' . $to->format('M j') }}</h3></li>
                <li><a class="dropdown-item" data-download href="{{ route('reports.pdf', ['class-summary'] + $reportQuery) }}">PDF</a></li>
                <li><a class="dropdown-item" data-download href="{{ route('reports.excel', ['class-summary'] + $reportQuery) }}">Excel</a></li>
                <li><a class="dropdown-item" target="_blank" href="{{ route('reports.print', ['class-summary'] + $reportQuery) }}">Print</a></li>
                @if ($oneDay)
                    <li><hr class="dropdown-divider"></li>
                    <li><h3 class="dropdown-header">Daily report · every student, {{ $from->format('M j') }}</h3></li>
                    <li><a class="dropdown-item" data-download href="{{ route('reports.pdf', ['attendance-daily'] + $dailyQuery) }}">PDF</a></li>
                    <li><a class="dropdown-item" data-download href="{{ route('reports.excel', ['attendance-daily'] + $dailyQuery) }}">Excel</a></li>
                @endif
            </ul>
        </div>
    </x-slot:actions>

    @if ($take)
        {{-- Teachers: where attendance is taken. Today's classes first, then the oldest meetings still missing a sheet. --}}
        <div class="row g-3 mb-4">
            @php $showMissed = $take['missed']->isNotEmpty() || $take['locked'] > 0; @endphp
            <div class="col-lg-{{ $showMissed ? 7 : 12 }}">
                <div class="card-sies h-100">
                    <div class="card-header-sies">
                        <h2 class="h6 fw-bold mb-0">Take attendance · today</h2>
                        <span class="small text-secondary">{{ today()->format('D, M j') }}</span>
                    </div>
                    @if ($take['today']['classes']->isEmpty())
                        <x-empty-state icon="calendar" :message="$take['today']['note'] ?? 'No classes meet today.'">
                            <a href="{{ route('teacher.classes') }}" class="btn btn-outline-secondary btn-sm-sies">My classes</a>
                        </x-empty-state>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($take['today']['classes']->sortBy(fn ($row) => $row['recorded'] ? 1 : 0)->values() as $row)
                                <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                                    <div class="min-w-0">
                                        <span class="fw-semibold">{{ $row['class']->subject->code }}</span>
                                        <span class="small text-secondary">· {{ $row['class']->class_code }} · {{ $row['class']->scheduleSummary() }}</span>
                                    </div>
                                    <div class="text-nowrap">
                                        @if ($row['recorded'])
                                            <x-status-badge value="verified" label="Recorded" />
                                            <a href="{{ route('attendance.sheet', ['class_offering' => $row['class']->id, 'date' => today()->toDateString()]) }}" class="btn btn-outline-secondary btn-sm-sies ms-1">Open</a>
                                        @else
                                            <a href="{{ route('attendance.sheet', ['class_offering' => $row['class']->id, 'date' => today()->toDateString()]) }}" class="btn btn-navy btn-sm-sies">Take attendance</a>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
            @if ($showMissed)
                <div class="col-lg-5">
                    <div class="card-sies h-100">
                        <div class="card-header-sies"><h2 class="h6 fw-bold mb-0">Still to record</h2><span class="small text-secondary">oldest first</span></div>
                        @if ($take['missed']->isEmpty())
                            <x-empty-state icon="check" tone="good" message="Nothing you can still record is missing." />
                        @else
                            <ul class="list-group list-group-flush">
                                @foreach ($take['missed'] as $row)
                                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                                        <div class="small"><span class="fw-semibold">{{ $row['class']->subject->code }}</span> · {{ $row['date']->format('D, M j') }}</div>
                                        <a href="{{ route('attendance.sheet', ['class_offering' => $row['class']->id, 'date' => $row['date']->toDateString()]) }}" class="btn btn-outline-secondary btn-sm-sies">Record</a>
                                    </li>
                                @endforeach
                            </ul>
                            @if ($take['missedMore'] > 0)
                                <div class="px-3 pt-2 small text-secondary">and {{ $take['missedMore'] }} more</div>
                            @endif
                        @endif
                        @if ($take['locked'] > 0)
                            <div class="px-3 py-2 small text-secondary border-top">
                                {{ $take['locked'] }} older {{ \Illuminate\Support\Str::plural('meeting', $take['locked']) }} (more than {{ $take['window'] }} days ago) can only be recorded by an administrator.
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="card-sies p-3 mb-4">
        {{-- Term and class apply at once; quick ranges are links; Apply is only for your own From–To dates. --}}
        <form method="GET" class="filter-bar">
            <select name="term" class="form-select" onchange="this.form.submit()" aria-label="Term">
                @foreach ($terms as $option)
                    <option value="{{ $option->id }}" @selected($term && $term->id === $option->id)>
                        {{ $option->name }} · {{ $option->academicYear->name }}{{ $option->is_current ? ' (current)' : '' }}
                    </option>
                @endforeach
            </select>
            @if (in_array($range, ['today', 'week', 'month'], true))
                {{-- Keeps the quick range when the term or class changes; editing a date drops it (custom dates). --}}
                <input type="hidden" name="range" value="{{ $range }}" data-quick-range>
            @endif
            <select name="class_offering" class="form-select" onchange="this.form.submit()" aria-label="Class">
                <option value="">All classes</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected($classId === $class->id)>{{ $class->class_code }} · {{ $class->subject->code }}</option>
                @endforeach
            </select>
            <label class="date-field">
                <span>From</span>
                <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control" aria-label="From date" oninput="this.form.querySelector('[data-quick-range]')?.remove()">
            </label>
            <label class="date-field">
                <span>To</span>
                <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control" aria-label="To date" oninput="this.form.querySelector('[data-quick-range]')?.remove()">
            </label>
            <button class="btn btn-outline-secondary btn-sm-sies">Apply</button>
        </form>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-3 small">
            <span class="text-secondary">Quick range:</span>
            <div class="segmented" role="group" aria-label="Quick range">
                @foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month'] as $key => $label)
                    <a @class(['segmented-item', 'is-active' => $range === $key]) href="{{ route('attendance.index', $query + ['range' => $key]) }}" @if ($range === $key) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
            </div>
            @unless (in_array($range, ['today', 'week', 'month'], true))
                <span class="badge rounded-pill text-bg-light border">Custom dates</span>
            @endunless
            <span class="text-secondary align-self-center ms-2">Showing {{ $oneDay ? $from->format('l, F j, Y') : $from->format('M j') . ' to ' . $to->format('M j, Y') }}</span>
        </div>
    </div>

    <div class="row g-3 mb-4">
        {{-- Each status has its colour (same as the sheet): green present, red absent, amber late, blue excused. --}}
        @foreach ([
            ['Students marked', $totalMarked, 'Records in this period', 'neutral'],
            ['Present', $counts['present'], $totalMarked ? $percentFormat($counts['present'] / $totalMarked * 100) . ' of records' : '', 'present'],
            ['Absent', $counts['absent'], $totalMarked ? $percentFormat($counts['absent'] / $totalMarked * 100) . ' of records' : '', 'absent'],
            ['Late', $counts['late'], $totalMarked ? $percentFormat($counts['late'] / $totalMarked * 100) . ' of records' : '', 'late'],
            ['Excused', $counts['excused'], $totalMarked ? $percentFormat($counts['excused'] / $totalMarked * 100) . ' of records' : '', 'excused'],
        ] as [$label, $value, $note, $tone])
            <div class="col-6 col-md-4 col-xl">
                <div class="stat-card stat-tone stat-tone-{{ $tone }}">
                    <div class="stat-label">@if ($tone !== 'neutral')<span class="stat-dot" aria-hidden="true"></span>@endif{{ $label }}</div>
                    <div class="stat-value">{{ $value }}</div>
                    @if ($note)<div class="stat-note">{{ $note }}</div>@endif
                </div>
            </div>
        @endforeach
        <div class="col-6 col-md-4 col-xl">
            <div class="stat-card stat-tone stat-tone-rate">
                <div class="stat-label">Attendance rate (%)</div>
                <div class="stat-value">{{ $percentFormat($percentage) }}</div>
                <div class="stat-note">{{ $percentage === null ? 'Shows once attendance is recorded' : \App\Services\AttendanceStats::policy() }}</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card-sies">
                <div class="card-header-sies"><h2 class="h6 fw-bold mb-0">Attendance sheets</h2></div>
                @if ($sessions->isEmpty())
                    <x-empty-state icon="calendar" :message="$range === 'today' ? 'No attendance sheets yet today.' : 'No attendance sheets were recorded in this period.'">
                        @if ($wider)
                            <a href="{{ route('attendance.index', $query + ['range' => $wider[0]]) }}" class="btn btn-outline-secondary btn-sm-sies">{{ $wider[1] }}</a>
                        @endif
                        @if (auth()->user()->role !== 'registrar')
                            <a href="{{ route('dashboard') }}" class="btn btn-navy btn-sm-sies">Today's classes</a>
                        @endif
                    </x-empty-state>
                @else
                    <div class="table-responsive" data-fit-viewport>
                        <table class="table table-sies table-hover align-middle">
                            <thead><tr><th scope="col">Date</th><th scope="col">Class</th><th scope="col">Teacher</th><th scope="col" class="text-center">P</th><th scope="col" class="text-center">L</th><th scope="col" class="text-center">A</th><th scope="col" class="text-center">E</th><th scope="col" class="text-end"></th></tr></thead>
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
                    <x-empty-state icon="check" tone="good" message="Good news: no student is over the absence limit." />
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
