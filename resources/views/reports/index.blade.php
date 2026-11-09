<x-app-layout title="Reports" subtitle="Open a report, choose its filters, then print it or save it as a PDF or an Excel file.">
    @foreach ($groups as $group => $reports)
        <h2 class="h6 fw-bold text-uppercase text-secondary mb-3 mt-2">{{ $group }}</h2>
        <div class="row g-3 mb-4">
            @foreach ($reports as $report)
                <div class="col-md-6 col-xl-4">
                    <a href="{{ route('reports.show', $report['slug']) }}" class="text-decoration-none">
                        <div class="card-sies p-3 h-100">
                            <div class="fw-bold text-dark">{{ $report['title'] }}</div>
                            <div class="small text-secondary mt-1">{{ $report['description'] }}</div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endforeach
</x-app-layout>
