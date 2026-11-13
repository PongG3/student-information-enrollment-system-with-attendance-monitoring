<!DOCTYPE html>
{{-- Everything held about one student (Data Privacy Act access request). Rendered to PDF by dompdf. --}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Personal data · {{ $student->full_name }}</title>
    <style>
        @page { margin: 28px 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        h1 { font-size: 14px; margin: 0; }
        h2 { font-size: 11px; margin: 14px 0 5px; padding-bottom: 2px; border-bottom: 1px solid #999; }
        .meta { color: #444; margin: 4px 0 8px; line-height: 1.5; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #bbb; padding: 3px 4px; text-align: left; vertical-align: top; }
        th { background: #e9edf5; }
        table.facts th { width: 28%; }
        tr { page-break-inside: avoid; }
        .none { color: #666; font-style: italic; }
        .note { color: #444; margin-top: 12px; }
    </style>
</head>
<body>
    <h1>{{ $school }}: personal data held about a student</h1>
    <div class="meta">
        Prepared {{ $generatedAt->format('F j, Y g:i A') }} by {{ $generatedBy }}, under the Data Privacy Act of 2012 (RA 10173).
        It lists every record about this student in the SIES Campus Portal.
    </div>

    <h2>Student record</h2>
    <table class="facts">
        @foreach ([
            'Student ID' => $student->student_number,
            'Name' => $student->full_name,
            'Gender' => $student->gender,
            'Date of birth' => $student->birth_date?->format('F j, Y'),
            'Program / year / block' => $student->program?->code . ' · Year ' . $student->year_level . ($student->section ? ' · ' . $student->section : ''),
            'Student type' => ucfirst((string) $student->student_type),
            'Status' => $student->trashed() ? 'Archived' : ucfirst((string) $student->status),
            'Email (record)' => $student->email,
            'Contact number' => $student->contact_number,
            'Address' => $student->address,
            'Previous school' => $student->previous_school,
            'Portal account' => $student->user ? $student->user->email . ($student->user->is_active ? '' : ' (inactive)') : 'None',
            'Photo on file' => $student->photo_path ? 'Yes' : 'No',
            'Privacy consent' => $student->privacy_consent_at?->format('F j, Y g:i A') ?? 'Not recorded',
            'Record created' => $student->created_at?->format('F j, Y'),
        ] as $label => $value)
            <tr><th>{{ $label }}</th><td>{{ $value ?: '—' }}</td></tr>
        @endforeach
    </table>

    <h2>Parents / guardians</h2>
    @if ($student->guardians->isEmpty())
        <div class="none">None recorded.</div>
    @else
        <table>
            <tr><th>Name</th><th>Relationship</th><th>Contact number</th><th>Email</th><th>Address</th><th>Emergency</th></tr>
            @foreach ($student->guardians as $g)
                <tr><td>{{ $g->full_name }}</td><td>{{ $g->relationship }}</td><td>{{ $g->contact_number }}</td><td>{{ $g->email ?: '—' }}</td><td>{{ $g->address ?: '—' }}</td><td>{{ $g->is_emergency_contact ? 'Yes' : 'No' }}</td></tr>
            @endforeach
        </table>
    @endif

    <h2>Admission requirements</h2>
    @if ($requirements->isEmpty())
        <div class="none">None recorded.</div>
    @else
        <table>
            <tr><th>Requirement</th><th>Status</th><th>Submitted</th><th>Verified</th><th>Scan on file</th></tr>
            @foreach ($requirements as $r)
                <tr><td>{{ $r->requirement?->name }}</td><td>{{ ucfirst($r->status) }}</td><td>{{ $r->submitted_at?->format('M j, Y') ?? '—' }}</td><td>{{ $r->verified_at?->format('M j, Y') ?? '—' }}</td><td>{{ $r->file_path ? 'Yes' : 'No' }}</td></tr>
            @endforeach
        </table>
    @endif

    <h2>Enrollment</h2>
    @if ($enrollments->isEmpty())
        <div class="none">None recorded.</div>
    @else
        <table>
            <tr><th>Term</th><th>Class</th><th>Status</th><th>Requested</th><th>Reason</th></tr>
            @foreach ($enrollments as $e)
                <tr>
                    <td>{{ $e->classOffering?->term?->name }} · {{ $e->classOffering?->term?->academicYear?->name }}</td>
                    <td>{{ $e->classOffering?->subject?->code }} · {{ $e->classOffering?->class_code }}</td>
                    <td>{{ ucfirst($e->status) }}</td>
                    <td>{{ $e->requested_at?->format('M j, Y') ?? '—' }}</td>
                    <td>{{ $e->status_reason ?: '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>Attendance ({{ $attendance->count() }} records)</h2>
    @if ($attendance->isEmpty())
        <div class="none">None recorded.</div>
    @else
        <table>
            <tr><th>Date</th><th>Class</th><th>Status</th><th>Remarks</th></tr>
            @foreach ($attendance as $a)
                <tr><td>{{ $a->session->session_date->format('M j, Y') }}</td><td>{{ $a->session->classOffering?->subject?->code }}</td><td>{{ ucfirst($a->status) }}</td><td>{{ $a->remarks ?: '—' }}</td></tr>
            @endforeach
        </table>
    @endif

    <h2>Excuse letters</h2>
    @if ($excuses->isEmpty())
        <div class="none">None sent.</div>
    @else
        <table>
            <tr><th>Date</th><th>Class</th><th>Reason</th><th>Decision</th><th>Teacher's note</th></tr>
            @foreach ($excuses as $x)
                <tr><td>{{ $x->date->format('M j, Y') }}</td><td>{{ $x->classOffering?->subject?->code }}</td><td>{{ $x->reason }}</td><td>{{ ucfirst($x->status) }}</td><td>{{ $x->decision_note ?: '—' }}</td></tr>
            @endforeach
        </table>
    @endif

    <h2>Changes to this record</h2>
    @if ($history->isEmpty())
        <div class="none">None recorded.</div>
    @else
        <table>
            <tr><th>When</th><th>By</th><th>What</th></tr>
            @foreach ($history as $log)
                <tr><td>{{ $log->created_at->format('M j, Y g:i A') }}</td><td>{{ $log->user?->name ?? 'System' }}</td><td>{{ $log->description }}</td></tr>
            @endforeach
        </table>
    @endif

    <div class="note">
        To have any of this corrected, contact the registrar's office{{ \App\Support\PrivacyContact::line() ? ' or the Data Protection Officer: ' . \App\Support\PrivacyContact::line() : '' }}.
    </div>
</body>
</html>
