<x-app-layout :title="$meta['title']" :subtitle="$meta['description']">
    @php
        $query = array_filter($filters, fn ($value) => $value !== null && $value !== '');
        $submitted = ! empty(request()->query());
    @endphp

    <div class="card-sies p-3 mb-4">
        <form method="GET" action="{{ route('reports.show', $slug) }}" class="row g-3 align-items-end">
            @foreach ($fields as $field)
                <div class="col-sm-6 col-lg-3">
                    @if ($field['type'] === 'checkbox')
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="{{ $field['name'] }}" value="1" id="f-{{ $field['name'] }}" @checked(! empty($filters[$field['name']]))>
                            <label class="form-check-label" for="f-{{ $field['name'] }}">{{ $field['label'] }}</label>
                        </div>
                    @else
                        <label class="form-label small text-secondary mb-1" for="f-{{ $field['name'] }}">{{ $field['label'] }}</label>
                        @if ($field['type'] === 'select')
                            <select id="f-{{ $field['name'] }}" name="{{ $field['name'] }}" class="form-select">
                                @foreach ($field['options'] as $value => $label)
                                    <option value="{{ $value }}" @selected((string) ($filters[$field['name']] ?? '') === (string) $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        @else
                            <input id="f-{{ $field['name'] }}" type="{{ $field['type'] }}" name="{{ $field['name'] }}"
                                   value="{{ $filters[$field['name']] ?? '' }}" class="form-control"
                                   @if ($field['type'] === 'number') min="1" max="100" @endif
                                   @if ($field['type'] === 'text') maxlength="20" @endif>
                        @endif
                    @endif
                    @error($field['name'])<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
            @endforeach
            <div class="col-auto">
                <button class="btn btn-navy">Show report</button>
                <a href="{{ route('reports.show', $slug) }}" class="btn btn-link">Reset</a>
            </div>
        </form>
    </div>

    @if ($problems && $submitted)
        <div class="alert alert-warning">
            @foreach ($problems as $problem)<div>{{ $problem }}</div>@endforeach
        </div>
    @elseif ($problems)
        <div class="card-sies p-4 text-center text-secondary">Fill in the filters above and press <strong>Show report</strong>.</div>
    @endif

    @if ($report)
        @php $shown = array_slice($report->rows, 0, $previewLimit); @endphp
        <div class="card-sies">
            <div class="card-header-sies">
                <div>
                    <h2 class="h6 fw-bold mb-1">{{ $report->title }}</h2>
                    <div class="small text-secondary">
                        @foreach ($report->filters as $label => $value){{ $label }}: {{ $value }}@if (! $loop->last) · @endif @endforeach
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-secondary btn-sm-sies" target="_blank" href="{{ route('reports.print', [$slug] + $query) }}">Print</a>
                    <a class="btn btn-outline-secondary btn-sm-sies" href="{{ route('reports.pdf', [$slug] + $query) }}">Download PDF</a>
                    <a class="btn btn-navy btn-sm-sies" href="{{ route('reports.excel', [$slug] + $query) }}">Download Excel</a>
                </div>
            </div>

            @if ($report->summary)
                <div class="d-flex flex-wrap gap-4 px-3 pb-2 small">
                    @foreach ($report->summary as $label => $value)
                        <div><span class="text-secondary">{{ $label }}</span> <strong>{{ $value }}</strong></div>
                    @endforeach
                </div>
            @endif

            @if ($report->note)<div class="px-3 pb-2 small text-secondary">{{ $report->note }}</div>@endif

            <div class="table-responsive">
                @include('reports._table', ['report' => $report, 'rows' => $shown])
            </div>

            @if (count($report->rows) > $previewLimit)
                <div class="p-3 small text-secondary">
                    Showing the first {{ $previewLimit }} of {{ count($report->rows) }} rows. Print, PDF and Excel include every row.
                </div>
            @endif
        </div>
    @endif
</x-app-layout>
