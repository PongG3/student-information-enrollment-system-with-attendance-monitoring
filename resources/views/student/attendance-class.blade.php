@use('App\Services\StudentPortalData', 'Portal')
<x-app-layout :title="'Attendance · ' . $class->subject->code"
    :subtitle="$class->subject->title . ' · ' . $class->class_code . ' · ' . $class->term->name . ' · ' . $class->term->academicYear->name"
    :back="route('student.attendance', ['term' => $class->term_id])" back-label="Back to my attendance">
    <div class="row g-3 mb-4">
        @foreach ([['Attendance', Portal::percent($percentage)], ['Present', $counts['present']], ['Late', $counts['late']], ['Absent', $counts['absent']], ['Excused', $counts['excused']]] as [$label, $value])
            <div class="col-6 col-md-4 col-xl">
                <div class="stat-card"><div class="stat-label">{{ $label }}</div><div class="stat-value">{{ $value }}</div></div>
            </div>
        @endforeach
    </div>

    @if ($flagged)
        <div class="alert alert-danger">You have been absent in more than {{ $rules['threshold'] }}% of this class's meetings. Please talk to your teacher.</div>
    @endif

    <div class="card-sies">
        <div class="card-header-sies"><h2 class="h6 fw-bold mb-0">Meetings</h2></div>
        @if ($records->isEmpty())
            <x-empty-state message="No attendance has been recorded for you in this class yet." />
        @else
            <div class="table-responsive">
                <table class="table table-sies align-middle mb-0">
                    <thead><tr><th>Date</th><th>Status</th><th>Remarks</th></tr></thead>
                    <tbody>
                        @foreach ($records as $record)
                            <tr>
                                <td>{{ $record->session->session_date->format('D, M j, Y') }}</td>
                                <td><x-status-badge :value="$record->status" /></td>
                                <td class="text-secondary">{{ $record->remarks ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
