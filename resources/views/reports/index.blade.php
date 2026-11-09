<x-app-layout title="Reports" subtitle="Open a report, choose its filters, then print it or save it as a PDF or an Excel file.">
    @foreach ($groups as $group => $reports)
        <h2 class="eyebrow text-secondary mb-2 mt-1">{{ $group }}</h2>
        <div class="row g-3 mb-4">
            @foreach ($reports as $report)
                <div class="col-md-6 col-xl-4">
                    <a href="{{ route('reports.show', $report['slug']) }}" class="report-card">
                        <span class="section-icon" aria-hidden="true"><x-icon :name="str_contains($report['slug'], 'attendance') || in_array($report['slug'], ['absentees', 'late', 'class-summary', 'student-history'], true) ? 'check' : 'students'" :size="18" /></span>
                        <span class="min-w-0">
                            <span class="report-card-title">{{ $report['title'] }}</span>
                            <span class="report-card-text">{{ $report['description'] }}</span>
                            <span class="report-card-go">Open report →</span>
                        </span>
                    </a>
                </div>
            @endforeach
        </div>
    @endforeach
</x-app-layout>
