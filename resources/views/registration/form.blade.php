<!DOCTYPE html>
{{-- Certificate of Registration (study load) for one student and one term. Rendered to PDF by dompdf. --}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Certificate of Registration · {{ $student->full_name }}</title>
    <style>
        @page { margin: 30px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #111; }
        table { border-collapse: collapse; width: 100%; }
        .letterhead { width: 100%; margin-bottom: 6px; }
        .letterhead td { border: 0; padding: 0; vertical-align: middle; }
        .letterhead .logo { width: 52px; }
        .letterhead img { height: 44px; }
        .school { font-size: 14px; font-weight: bold; }
        .address { color: #444; font-size: 9px; margin-top: 1px; }
        .title { text-align: center; margin: 10px 0 2px; font-size: 13px; font-weight: bold; letter-spacing: 1px; }
        .subtitle { text-align: center; color: #333; margin-bottom: 10px; }
        .facts td { border: 1px solid #bbb; padding: 4px 6px; }
        .facts .label { background: #eef1f6; width: 16%; color: #333; }
        .load { margin-top: 10px; }
        .load th, .load td { border: 1px solid #999; padding: 4px 5px; text-align: left; vertical-align: top; }
        .load th { background: #e3e8f1; font-size: 8.5px; }
        .load .num { text-align: center; }
        .load .total td { background: #eef1f6; font-weight: bold; }
        .small { color: #555; font-size: 8.5px; }
        .signs { margin-top: 38px; }
        .signs td { border: 0; width: 50%; text-align: center; padding: 0 20px; }
        .line { border-top: 1px solid #111; padding-top: 3px; }
        .foot { margin-top: 22px; color: #555; font-size: 8px; line-height: 1.5; }
    </style>
</head>
<body>
    <table class="letterhead"><tr>
        @if ($logo)<td class="logo"><img src="{{ $logo }}" alt=""></td>@endif
        <td>
            <div class="school">{{ $school }}</div>
            @if ($address)<div class="address">{{ $address }}</div>@endif
        </td>
    </tr></table>

    <div class="title">CERTIFICATE OF REGISTRATION</div>
    <div class="subtitle">{{ $term->name }} · Academic Year {{ $term->academicYear->name }}</div>

    <table class="facts">
        <tr>
            <td class="label">Student ID</td><td>{{ $student->student_number }}</td>
            <td class="label">Program</td><td>{{ $student->program?->code }} · {{ $student->program?->name }}</td>
        </tr>
        <tr>
            <td class="label">Name</td><td><strong>{{ $student->last_name }}, {{ $student->first_name }}{{ $student->middle_name ? ' ' . $student->middle_name : '' }}{{ $student->suffix ? ' ' . $student->suffix : '' }}</strong></td>
            <td class="label">Year / block</td><td>Year {{ $student->year_level }}{{ $student->section ? ' · Block ' . $student->section : '' }}</td>
        </tr>
        <tr>
            <td class="label">Student type</td><td>{{ ucfirst((string) $student->student_type) }}</td>
            <td class="label">Status</td><td>Officially enrolled{{ $enrolledOn ? ' · ' . $enrolledOn->format('M j, Y') : '' }}</td>
        </tr>
    </table>

    <table class="load">
        <thead>
            <tr>
                <th style="width: 11%">Schedule code</th>
                <th style="width: 9%">Subject</th>
                <th>Description</th>
                <th class="num" style="width: 6%">Units</th>
                <th style="width: 19%">Days and time</th>
                <th style="width: 9%">Room</th>
                <th style="width: 16%">Teacher</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['code'] }}</td>
                    <td>{{ $row['subject'] }}</td>
                    <td>{{ $row['title'] }}<div class="small">{{ $row['block'] }}</div></td>
                    <td class="num">{{ rtrim(rtrim(number_format($row['units'], 1), '0'), '.') }}</td>
                    <td>@foreach ($row['times'] as $time)<div>{{ $time }}</div>@endforeach</td>
                    <td>@foreach ($row['rooms'] as $room)<div>{{ $room }}</div>@endforeach</td>
                    <td>{{ $row['teacher'] }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="3">Total: {{ $rows->count() }} {{ \Illuminate\Support\Str::plural('subject', $rows->count()) }}</td>
                <td class="num">{{ rtrim(rtrim(number_format($totalUnits, 1), '0'), '.') }}</td>
                <td colspan="3"></td>
            </tr>
        </tbody>
    </table>

    <table class="signs"><tr>
        <td><div class="line">Student's signature over printed name</div></td>
        <td><div class="line">Registrar</div></td>
    </tr></table>

    <div class="foot">
        Valid for {{ $term->name }} · {{ $term->academicYear->name }} only. Lists officially enrolled classes; pending or approved requests are not included.<br>
        Printed {{ $generatedAt->format('F j, Y g:i A') }} by {{ $generatedBy }} from the SIES Campus Portal.
    </div>
</body>
</html>
