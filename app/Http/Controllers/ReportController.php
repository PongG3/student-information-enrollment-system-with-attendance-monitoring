<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Reports\Report;
use App\Services\ReportService;
use App\Support\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        return view('reports.index', [
            'groups' => collect(ReportService::available($request->user()))
                ->map(fn ($meta, $slug) => $meta + ['slug' => $slug])
                ->groupBy('group'),
        ]);
    }

    /** The report on screen, with its filter form. */
    public function show(Request $request, string $slug)
    {
        $this->authorizeReport($request, $slug);
        $filters = $this->filters($request, $slug);

        $report = null;
        $problems = [];

        try {
            $report = ReportService::build($slug, $request->user(), $filters);
        } catch (ValidationException $e) {
            $problems = collect($e->errors())->flatten()->all();
        }

        return view('reports.show', [
            'slug' => $slug,
            'meta' => ReportService::CATALOG[$slug],
            'fields' => ReportService::fields($slug, $request->user(), $filters),
            'filters' => $filters,
            'report' => $report,
            'problems' => $problems,
            'previewLimit' => 300,
        ]);
    }

    public function print(Request $request, string $slug)
    {
        $report = $this->prepare($request, $slug, 'printed');

        return view('reports.print', $this->documentData($request, $report));
    }

    public function pdf(Request $request, string $slug)
    {
        $report = $this->prepare($request, $slug, 'exported as PDF');

        return Pdf::loadView('reports.pdf', $this->documentData($request, $report))
            ->setPaper('a4', $report->orientation)
            ->download($this->filename($slug) . '.pdf');
    }

    public function excel(Request $request, string $slug)
    {
        $report = $this->prepare($request, $slug, 'exported as Excel');

        return Excel::download(
            new ReportExport($report, ReportService::schoolName(), $request->user()->name),
            $this->filename($slug) . '.xlsx',
        );
    }

    /** Check access, build the report, and record that someone took a copy. */
    private function prepare(Request $request, string $slug, string $action): Report
    {
        $this->authorizeReport($request, $slug);
        $filters = $this->filters($request, $slug);
        $report = ReportService::build($slug, $request->user(), $filters);

        AuditLogger::log(
            'report.exported',
            null,
            "{$report->title} {$action} (" . count($report->rows) . ' rows)',
            null,
            ['filters' => $report->filters],
        );

        return $report;
    }

    private function documentData(Request $request, Report $report): array
    {
        return [
            'report' => $report,
            'school' => ReportService::schoolName(),
            'generatedBy' => $request->user()->name,
            'generatedAt' => now(),
        ];
    }

    private function authorizeReport(Request $request, string $slug): void
    {
        abort_unless(isset(ReportService::CATALOG[$slug]), 404);
        abort_unless(in_array($request->user()->role, ReportService::CATALOG[$slug]['roles'], true), 403);
    }

    private function filters(Request $request, string $slug): array
    {
        return $request->validate(ReportService::rules($slug));
    }

    private function filename(string $slug): string
    {
        return $slug . '-' . today()->toDateString();
    }
}
