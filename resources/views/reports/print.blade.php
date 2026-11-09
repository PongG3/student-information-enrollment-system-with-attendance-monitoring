<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report->title }} · {{ $school }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #111; margin: 24px; }
        h1 { font-size: 18px; margin: 0; }
        h2 { font-size: 15px; margin: 4px 0 8px; }
        .meta { color: #444; margin-bottom: 12px; line-height: 1.5; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #e9edf5; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .summary { margin-top: 14px; }
        .summary span { display: inline-block; margin-right: 18px; }
        .bar { margin-bottom: 14px; }
        @page { size: A4 {{ $report->orientation }}; margin: 12mm; }
        @media print { .bar { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="bar">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
    </div>

    <h1>{{ $school }}</h1>
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

    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
