<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Term;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AcademicYearController extends Controller
{
    public function index()
    {
        $years = AcademicYear::with(['terms' => fn ($q) => $q->orderBy('start_date')])
            ->orderByDesc('start_date')
            ->get();

        return view('academic-years.index', compact('years'));
    }

    public function create()
    {
        return view('academic-years.form', ['year' => new AcademicYear()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $year = AcademicYear::create($data);

        AuditLogger::log('academic_year.created', $year, "Created academic year {$year->name}", null, $data);

        return redirect()->route('academic-years.index')->with('success', 'Academic year added.');
    }

    public function edit(AcademicYear $academicYear)
    {
        if ($academicYear->isClosed()) {
            return redirect()->route('academic-years.index')->with('error', 'A closed academic year cannot be edited. Reopen it first.');
        }

        return view('academic-years.form', ['year' => $academicYear]);
    }

    public function update(Request $request, AcademicYear $academicYear)
    {
        if ($academicYear->isClosed()) {
            return redirect()->route('academic-years.index')->with('error', 'A closed academic year cannot be edited. Reopen it first.');
        }

        $data = $this->validated($request, $academicYear);
        $old = [
            'name' => $academicYear->name,
            'start_date' => $academicYear->start_date->toDateString(),
            'end_date' => $academicYear->end_date->toDateString(),
        ];
        $academicYear->update($data);

        AuditLogger::log('academic_year.updated', $academicYear, "Updated academic year {$academicYear->name}", $old, $data);

        return redirect()->route('academic-years.index')->with('success', 'Academic year updated.');
    }

    public function makeCurrent(AcademicYear $academicYear)
    {
        if ($academicYear->isClosed()) {
            return back()->with('error', 'A closed academic year cannot be made current.');
        }

        DB::transaction(function () use ($academicYear) {
            AcademicYear::where('is_current', true)->update(['is_current' => false]);
            $academicYear->update(['is_current' => true]);

            // Terms of other years can no longer be current or open for enrollment.
            Term::where('academic_year_id', '!=', $academicYear->id)
                ->update(['is_current' => false, 'enrollment_open' => false]);
        });

        AuditLogger::log('academic_year.current', $academicYear, "Set {$academicYear->name} as the current academic year");

        return back()->with('success', "{$academicYear->name} is now the current academic year. Choose its current term next.");
    }

    public function close(AcademicYear $academicYear)
    {
        if ($academicYear->isClosed()) {
            return back()->with('error', 'This academic year is already closed.');
        }

        DB::transaction(function () use ($academicYear) {
            $academicYear->terms()->whereNull('closed_at')->update([
                'closed_at' => now(), 'is_current' => false, 'enrollment_open' => false,
            ]);
            $academicYear->update(['closed_at' => now(), 'is_current' => false]);
        });

        AuditLogger::log('academic_year.closed', $academicYear, "Closed academic year {$academicYear->name} and all its terms");

        return back()->with('success', 'Academic year closed. Its records are now locked.');
    }

    public function reopen(AcademicYear $academicYear)
    {
        if (! $academicYear->isClosed()) {
            return back()->with('error', 'This academic year is not closed.');
        }

        $academicYear->update(['closed_at' => null]);

        AuditLogger::log('academic_year.reopened', $academicYear, "Reopened academic year {$academicYear->name}");

        return back()->with('success', 'Academic year reopened. Its terms stay closed until you reopen them.');
    }

    private function validated(Request $request, ?AcademicYear $year = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'regex:/^\d{4}-\d{4}$/', Rule::unique('academic_years', 'name')->ignore($year)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ], ['name.regex' => 'Use the format 2026-2027.']);

        [$first, $second] = explode('-', $data['name']);
        if ((int) $second !== (int) $first + 1) {
            throw ValidationException::withMessages(['name' => 'The second year must follow the first, for example 2026-2027.']);
        }

        $overlaps = AcademicYear::query()
            ->when($year, fn ($q) => $q->where('id', '!=', $year->id))
            ->where('start_date', '<=', $data['end_date'])
            ->where('end_date', '>=', $data['start_date'])
            ->exists();
        if ($overlaps) {
            throw ValidationException::withMessages(['start_date' => 'These dates overlap another academic year.']);
        }

        // The year must still contain all of its terms.
        if ($year && $year->terms()->where(function ($q) use ($data) {
            $q->where('start_date', '<', $data['start_date'])->orWhere('end_date', '>', $data['end_date']);
        })->exists()) {
            throw ValidationException::withMessages(['start_date' => 'The year must still contain all of its terms. Edit the terms first.']);
        }

        return $data;
    }
}
