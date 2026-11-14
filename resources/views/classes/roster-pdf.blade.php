<!DOCTYPE html>
{{-- Printable class list (officially enrolled students) with blank columns. Rendered to PDF by dompdf. --}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Class list · {{ $class->class_code }}</title>
    <style>
        @page { margin: 28px 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        table { border-collapse: collapse; width: 100%; }
        .letterhead td { border: 0; padding: 0; vertical-align: middle; }
        .letterhead .logo { width: 50px; }
        .letterhead img { height: 40px; }
        .school { font-size: 13px; font-weight: bold; }
        .address { color: #444; }
        .title { text-align: center; font-size: 12px; font-weight: bold; letter-spacing: 1px; margin: 8px 0 8px; }
        .facts td { border: 1px solid #bbb; padding: 3px 5px; }
        .facts .label { background: #eef1f6; width: 13%; color: #333; }
        .list { margin-top: 8px; }
        .list th, .list td { border: 1px solid #999; padding: 4px 4px; text-align: left; }
        .list th { background: #e3e8f1; font-size: 8px; }
        .list td { height: 15px; }
        .num { text-align: center; width: 4%; }
        .blank { width: 7%; }
        .foot { margin-top: 12px; color: #555; font-size: 8px; }
        .signs { margin-top: 28px; }
        .signs td { border: 0; text-align: center; padding: 0 30px; }
        .line { border-top: 1px solid #111; padding-top: 3px; }
    </style>
</head>
<body>
    <table class="letterhead"><tr>
        @if ($logo)<td class="logo"><img src="{{ $logo }}" alt=""></td>@endif
        <td><div class="school">{{ $school }}</div>@if ($address)<div class="address">{{ $address }}</div>@endif</td>
    </tr></table>

    <div class="title">CLASS LIST</div>

    <table class="facts">
        <tr>
            <td class="label">Subject</td><td><strong>{{ $class->subject->code }}</strong> · {{ $class->subject->title }}</td>
            <td class="label">Schedule code</td><td>{{ $class->class_code }}</td>
        </tr>
        <tr>
            <td class="label">Block</td><td>{{ $class->block_label }}</td>
            <td class="label">Term</td><td>{{ $class->term->name }} · {{ $class->term->academicYear->name }}</td>
        </tr>
        <tr>
            <td class="label">Schedule</td><td>@foreach ($schedule as $line)<div>{{ $line }}</div>@endforeach</td>
            <td class="label">Teacher</td><td>{{ $class->teacher?->name }}</td>
        </tr>
    </table>

    <table class="list">
        <thead>
            <tr>
                <th class="num">#</th>
                <th style="width: 13%">Student ID</th>
                <th>Name</th>
                <th style="width: 11%">Program / year</th>
                <th style="width: 17%">Signature</th>
                @foreach (range(1, 4) as $day)<th class="blank">Date: ____</th>@endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($students as $i => $student)
                <tr>
                    <td class="num">{{ $i + 1 }}</td>
                    <td>{{ $student->student_number }}</td>
                    <td>{{ $student->last_name }}, {{ $student->first_name }}{{ $student->middle_name ? ' ' . mb_substr($student->middle_name, 0, 1) . '.' : '' }}</td>
                    <td>{{ $student->program?->code }} {{ $student->year_level }}</td>
                    <td></td>
                    @foreach (range(1, 4) as $day)<td></td>@endforeach
                </tr>
            @empty
                <tr><td colspan="9" style="text-align: center; color: #666">No officially enrolled students yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="foot">
        {{ $students->count() }} officially enrolled {{ \Illuminate\Support\Str::plural('student', $students->count()) }} (pending and approved requests are not listed).
        Printed {{ $generatedAt->format('F j, Y g:i A') }} by {{ $generatedBy }} from the SIES Campus Portal.
    </div>

    <table class="signs"><tr>
        <td style="width: 50%"><div class="line">Teacher's signature</div></td>
        <td style="width: 50%"><div class="line">Date</div></td>
    </tr></table>
</body>
</html>
