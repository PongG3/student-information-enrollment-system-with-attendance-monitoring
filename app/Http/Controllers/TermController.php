<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Term;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TermController extends Controller
{
    public function create(Request $request)
    {
        return view('terms.form', [
            'term' => new Term(['academic_year_id' => $request->input('academic_year')]),
            'years' => AcademicYear::whereNull('closed_at')->orderByDesc('start_date')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $term = Term::create($data);

        AuditLogger::log('term.created', $term, "Created term {$term->name}", null, $data);

        return redirect()->route('academic-years.index')->with('success', 'Term added.');
    }

    public function edit(Term $term)
    {
        if ($term->isClosed()) {
            return redirect()->route('academic-years.index')->with('error', 'A closed term cannot be edited. Reopen it first.');
        }

        return view('terms.form', [
            'term' => $term,
            'years' => AcademicYear::whereNull('closed_at')->orderByDesc('start_date')->get(),
        ]);
    }

    public function update(Request $request, Term $term)
    {
        if ($term->isClosed()) {
            return redirect()->route('academic-years.index')->with('error', 'A closed term cannot be edited. Reopen it first.');
        }

        $data = $this->validated($request, $term);
        $old = [
            'name' => $term->name,
            'start_date' => $term->start_date->toDateString(),
            'end_date' => $term->end_date->toDateString(),
        ];
        $term->update($data);

        AuditLogger::log('term.updated', $term, "Updated term {$term->name}", $old, $data);

        return redirect()->route('academic-years.index')->with('success', 'Term updated.');
    }

    public function makeCurrent(Term $term)
    {
        $term->load('academicYear');

        if ($term->isClosed() || $term->academicYear->isClosed()) {
            return back()->with('error', 'A closed term cannot be made current.');
        }

        DB::transaction(function () use ($term) {
            // Only one current term: other terms lose "current" and cannot stay open for enrollment.
            Term::where('id', '!=', $term->id)->update(['is_current' => false, 'enrollment_open' => false]);
            $term->update(['is_current' => true]);

            // The term's year becomes the current year.
            AcademicYear::where('id', '!=', $term->academic_year_id)->update(['is_current' => false]);
            $term->academicYear->update(['is_current' => true]);
        });

        AuditLogger::log('term.current', $term, "Set {$term->name} ({$term->academicYear->name}) as the current term");

        return back()->with('success', "{$term->name} is now the current term.");
    }

    public function toggleEnrollment(Term $term)
    {
        if ($term->isClosed() || ! $term->is_current) {
            return back()->with('error', 'Enrollment can only be opened for the current, open term.');
        }

        $term->update(['enrollment_open' => ! $term->enrollment_open]);

        AuditLogger::log(
            $term->enrollment_open ? 'term.enrollment_opened' : 'term.enrollment_closed',
            $term,
            ($term->enrollment_open ? 'Opened' : 'Closed') . " enrollment for {$term->name}",
        );

        return back()->with('success', $term->enrollment_open ? 'Enrollment is now open.' : 'Enrollment is now closed.');
    }

    public function close(Term $term)
    {
        if ($term->isClosed()) {
            return back()->with('error', 'This term is already closed.');
        }

        $term->update(['closed_at' => now(), 'is_current' => false, 'enrollment_open' => false]);

        AuditLogger::log('term.closed', $term, "Closed term {$term->name}");

        return back()->with('success', 'Term closed. Its records are now locked.');
    }

    public function reopen(Term $term)
    {
        $term->load('academicYear');

        if (! $term->isClosed()) {
            return back()->with('error', 'This term is not closed.');
        }
        if ($term->academicYear->isClosed()) {
            return back()->with('error', 'Reopen the academic year first.');
        }

        $term->update(['closed_at' => null]);

        AuditLogger::log('term.reopened', $term, "Reopened term {$term->name}");

        return back()->with('success', 'Term reopened.');
    }

    private function validated(Request $request, ?Term $term = null): array
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'name' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);

        $year = AcademicYear::findOrFail($data['academic_year_id']);

        if ($year->isClosed()) {
            throw ValidationException::withMessages(['academic_year_id' => 'This academic year is closed.']);
        }
        if ($term && $term->academic_year_id !== (int) $year->id) {
            throw ValidationException::withMessages(['academic_year_id' => 'A term cannot be moved to another academic year.']);
        }

        $others = Term::where('academic_year_id', $year->id)->when($term, fn ($q) => $q->where('id', '!=', $term->id));

        if ((clone $others)->where('name', $data['name'])->exists()) {
            throw ValidationException::withMessages(['name' => 'This academic year already has a term with that name.']);
        }

        if ($data['start_date'] < $year->start_date->toDateString() || $data['end_date'] > $year->end_date->toDateString()) {
            throw ValidationException::withMessages([
                'start_date' => "Term dates must fall inside the academic year ({$year->start_date->toDateString()} to {$year->end_date->toDateString()}).",
            ]);
        }

        if ((clone $others)->where('start_date', '<=', $data['end_date'])->where('end_date', '>=', $data['start_date'])->exists()) {
            throw ValidationException::withMessages(['start_date' => 'These dates overlap another term in the same academic year.']);
        }

        return $data;
    }
}
