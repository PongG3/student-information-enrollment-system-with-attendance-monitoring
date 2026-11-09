<x-app-layout :title="'Attendance summary · ' . $class->class_code"
    :subtitle="$class->subject->code . ' · ' . $class->subject->title . ' · ' . $class->term->name . ' · ' . $class->term->academicYear->name"
    :back="route('classes.show', $class)" back-label="Back to class">
    @php
        $percentFormat = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format($value, 1), '0'), '.') . '%';
    @endphp

    <div class="card-sies p-3 mb-4">
        <form method="GET" class="filter-bar">
            <label class="small text-secondary" for="from">From</label>
            <input id="from" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control">
            <label class="small text-secondary" for="to">To</label>
            <input id="to" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control">
            <button class="btn btn-outline-secondary btn-sm-sies">Show</button>
            <a href="{{ route('attendance.sheet', ['class_offering' => $class->id]) }}" class="btn btn-navy btn-sm-sies ms-auto">Open attendance sheet</a>
        </form>
    </div>

    <div class="card-sies">
        <div class="card-header-sies">
            <h2 class="h6 fw-bold mb-0">Students</h2>
            <span class="small text-secondary">
                Late {{ $rules['late'] ? 'counts' : 'does not count' }} as attended ·
                Excused {{ $rules['excused'] === 'excluded' ? 'is left out' : 'counts as attended' }} ·
                alert over {{ $rules['threshold'] }}% absent
            </span>
        </div>

        @if ($rows->isEmpty())
            <x-empty-state message="No enrolled students yet." />
        @else
            <div class="table-responsive">
                <table class="table table-sies table-hover align-middle">
                    <thead><tr><th>Student</th><th class="text-center">Meetings</th><th class="text-center">Present</th><th class="text-center">Late</th><th class="text-center">Absent</th><th class="text-center">Excused</th><th>Attendance</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $row['student']->full_name }}</div>
                                    <div class="text-secondary small">{{ $row['student']->student_number }}</div>
                                </td>
                                <td class="text-center">{{ $row['meetings'] }}</td>
                                <td class="text-center">{{ $row['counts']['present'] }}</td>
                                <td class="text-center">{{ $row['counts']['late'] }}</td>
                                <td class="text-center">{{ $row['counts']['absent'] }}</td>
                                <td class="text-center">{{ $row['counts']['excused'] }}</td>
                                <td class="text-nowrap">
                                    <span class="fw-semibold">{{ $percentFormat($row['percentage']) }}</span>
                                    @if ($row['flagged']) <span class="badge text-bg-danger ms-1">Frequent absences</span> @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
