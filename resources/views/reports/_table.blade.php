@php $numeric = $report->numericColumns(); @endphp
<table class="{{ $class ?? 'table table-sies table-hover align-middle' }}">
    <thead>
        <tr>
            @foreach ($report->headings as $i => $heading)
                <th @if (in_array($i, $numeric, true)) style="text-align: right" @endif>{{ $heading }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                @foreach ($row as $i => $cell)
                    <td @if (in_array($i, $numeric, true)) style="text-align: right" @endif>{{ $cell === null || $cell === '' ? '—' : $cell }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($report->headings) }}" style="text-align: center; padding: 18px">Nothing to show for these filters.</td></tr>
        @endforelse
    </tbody>
</table>
