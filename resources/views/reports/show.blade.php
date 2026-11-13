<x-app-layout :title="$meta['title']" :subtitle="$meta['description']" :back="route('reports.index')" back-label="All reports">
    @php
        $query = array_filter($filters, fn ($value) => $value !== null && $value !== '');
        $submitted = ! empty(request()->query());
        // Once a report is on screen the filters move into the Change filters dialog (the report header lists them)
        // to leave room for the rows.
        $filtersInDialog = $report && ! $errors->any();
    @endphp

    @if ($filtersInDialog)
        <div class="modal fade" id="reportFiltersModal" tabindex="-1" aria-labelledby="reportFiltersTitle" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title h5 fw-bold" id="reportFiltersTitle">Change filters</h2>
                            <div class="small text-secondary">{{ $meta['title'] }}</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @include('reports._filters', ['formId' => 'reportFilters', 'col' => 'col-sm-6', 'inDialog' => true])
                    </div>
                    <div class="modal-footer">
                        <a href="{{ route('reports.show', $slug) }}" class="btn btn-link me-auto">Reset</a>
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" form="reportFilters" class="btn btn-navy px-4">Show report</button>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="card-sies p-3 mb-3">
            @include('reports._filters', ['formId' => 'reportFilters', 'col' => 'col-sm-6 col-lg-3', 'inDialog' => false])
        </div>
    @endif

    @if ($problems && $submitted)
        <div class="alert alert-warning">
            @foreach ($problems as $problem)<div>{{ $problem }}</div>@endforeach
        </div>
    @elseif ($problems)
        <div class="card-sies"><x-empty-state icon="chart" message="Fill in the filters above and press Show report." /></div>
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
                    <button type="button" class="btn btn-outline-secondary btn-sm-sies" data-bs-toggle="modal" data-bs-target="#reportFiltersModal"
                        aria-haspopup="dialog"><x-icon name="sliders" :size="14" /> Change filters</button>
                    <a class="btn btn-outline-secondary btn-sm-sies" target="_blank" href="{{ route('reports.print', [$slug] + $query) }}">Print</a>
                    <a class="btn btn-outline-secondary btn-sm-sies" data-download href="{{ route('reports.pdf', [$slug] + $query) }}">Download PDF</a>
                    <a class="btn btn-navy btn-sm-sies" data-download href="{{ route('reports.excel', [$slug] + $query) }}">Download Excel</a>
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
            @if (($meta['group'] ?? null) === 'Attendance')
                <div class="px-3 pb-2 small text-secondary">How percentages are counted: {{ \App\Services\AttendanceStats::policy() }}.</div>
            @endif

            <div class="table-responsive" data-fit-viewport>
                @include('reports._table', ['report' => $report, 'rows' => $shown, 'class' => 'table table-sies table-hover align-middle report-table'])
            </div>

            @if (count($report->rows) > $previewLimit)
                <div class="p-3 small text-secondary">
                    Showing the first {{ $previewLimit }} of {{ count($report->rows) }} rows. Print, PDF and Excel include every row.
                </div>
            @endif
        </div>
    @endif
</x-app-layout>
