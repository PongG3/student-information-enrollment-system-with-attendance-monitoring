@use('App\Services\StudentPortalData', 'Portal')
<x-app-layout title="My Attendance" subtitle="Your attendance for each class, as recorded by your teachers.">
    <div class="card-sies p-3 mb-4">
        <form method="GET" class="filter-bar">
            <select name="term" class="form-select" onchange="this.form.submit()" aria-label="Term">
                @foreach ($terms as $option)
                    <option value="{{ $option->id }}" @selected($term && $term->id === $option->id)>
                        {{ $option->name }} · {{ $option->academicYear->name }}{{ $option->is_current ? ' (current)' : '' }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    @php $overall = $attendance['overall']; @endphp
    <div class="row g-3 mb-4">
        @foreach ([['Overall', Portal::percent($attendance['percentage'])], ['Present', $overall['present']], ['Late', $overall['late']], ['Absent', $overall['absent']], ['Excused', $overall['excused']]] as [$label, $value])
            <div class="col-6 col-md-4 col-xl">
                <div class="stat-card"><div class="stat-label">{{ $label }}</div><div class="stat-value">{{ $value }}</div></div>
            </div>
        @endforeach
    </div>

    <div class="card-sies">
        <div class="card-header-sies">
            <h2 class="h6 fw-bold mb-0">By class</h2>
            <span class="small text-secondary">
                {{ $attendance['rules']['late'] ? 'Late counts as attended' : 'Late does not count as attended' }} ·
                Excused {{ $attendance['rules']['excused'] === 'excluded' ? 'is left out' : 'counts as attended' }} ·
                alert over {{ $attendance['rules']['threshold'] }}% absent
            </span>
        </div>

        @if ($attendance['rows']->isEmpty())
            <x-empty-state icon="classes" message="You are not enrolled in any class this term." />
        @else
            <div class="table-responsive">
                <table class="table table-sies table-hover align-middle mb-0">
                    <thead><tr><th scope="col">Class</th><th scope="col" class="text-center">Meetings</th><th scope="col" class="text-center">Present</th><th scope="col" class="text-center">Late</th><th scope="col" class="text-center">Absent</th><th scope="col" class="text-center">Excused</th><th scope="col">Attendance</th><th scope="col"></th></tr></thead>
                    <tbody>
                        @foreach ($attendance['rows'] as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row['class']->subject->code }}<div class="text-secondary small fw-normal">{{ $row['class']->subject->title }} · {{ $row['class']->class_code }}</div></td>
                                <td class="text-center">{{ $row['meetings'] }}</td>
                                <td class="text-center">{{ $row['counts']['present'] }}</td>
                                <td class="text-center">{{ $row['counts']['late'] }}</td>
                                <td class="text-center">{{ $row['counts']['absent'] }}</td>
                                <td class="text-center">{{ $row['counts']['excused'] }}</td>
                                <td class="text-nowrap">
                                    <span class="fw-semibold">{{ Portal::percent($row['percentage']) }}</span>
                                    @if ($row['flagged'])<span class="badge rounded-pill text-bg-danger ms-1">Frequent absence</span>@endif
                                </td>
                                <td class="text-end"><a href="{{ route('student.attendance.show', $row['class']) }}" class="btn btn-outline-secondary btn-sm-sies">See dates</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
