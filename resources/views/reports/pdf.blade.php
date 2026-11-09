<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title }}</title>
    <style>
        @page { margin: 28px 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        h1 { font-size: 14px; margin: 0; }
        h2 { font-size: 12px; margin: 3px 0 6px; }
        .meta { color: #444; margin-bottom: 8px; line-height: 1.5; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #999; padding: 3px 4px; text-align: left; vertical-align: top; }
        th { background: #e9edf5; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .summary { margin-top: 10px; }
        .summary span { margin-right: 14px; }
        table.letterhead { width: auto; margin-bottom: 4px; }
        table.letterhead td { border: 0; padding: 0; vertical-align: middle; }
        table.letterhead td.logo { padding-right: 8px; }
        table.letterhead img { height: 40px; }
        .address { color: #444; font-size: 9px; margin-top: 1px; }
    </style>
</head>
<body>
    @php($logo = \App\Support\SchoolBranding::logoDataUri())
    @php($address = \App\Support\SchoolBranding::address())
    <table class="letterhead"><tr>
        @if ($logo)<td class="logo"><img src="{{ $logo }}" alt=""></td>@endif
        <td>
            <h1>{{ $school }}</h1>
            @if ($address)<div class="address">{{ $address }}</div>@endif
        </td>
    </tr></table>
    <h2>{{ $report->title }}</h2>
    <div class="meta">
        @foreach ($report->filters as $label => $value)<div>{{ $label }}: {{ $value }}</div>@endforeach
        <div>Generated {{ $generatedAt->format('M j, Y g:i A') }} by {{ $generatedBy }}</div>
    </div>

    @include('reports._table', ['report' => $report, 'rows' => $report->rows, 'class' => ''])

    @if ($report->summary)
        <div class="summary">
            @foreach ($report->summary as $label => $value)<span>{{ $label }}: <strong>{{ $value }}</strong></span>@if (! $loop->last)&nbsp;&nbsp;·&nbsp;&nbsp;@endif @endforeach
        </div>
    @endif
    @if ($report->note)<p class="meta">{{ $report->note }}</p>@endif
</body>
</html>
