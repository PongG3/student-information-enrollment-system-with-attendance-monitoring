<?php

namespace App\Http\Controllers;

use App\Models\ClassMeeting;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Term;
use App\Services\ScheduleChecker;
use App\Support\AuditLogger;
use App\Support\SchoolBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * The Certificate of Registration (study load): one term's officially enrolled classes for a student, with
 * schedule, room, teacher and units, as a printable PDF. Only "enrolled" classes appear; pending and approved
 * requests are not yet official.
 */
class RegistrationFormController extends Controller
{
    /** Registrar / administrator: any student's form. */
    public function staff(Request $request, Student $student)
    {
        return $this->make($request, $student, route('students.show', $student));
    }

    /** A student: their own form only (the record comes from the signed-in account). */
    public function mine(Request $request)
    {
        $student = $request->attributes->get('student') ?? Student::where('user_id', $request->user()->id)->firstOrFail();

        return $this->make($request, $student, route('student.schedule'));
    }

    private function make(Request $request, Student $student, string $back)
    {
        $request->validate(['term' => ['nullable', 'integer']]);

        $term = $this->term($student, $request->integer('term') ?: null);
        if (! $term) {
            return redirect($back)->with('error', 'There is no term with enrolled classes for a registration form yet.');
        }

        $enrollments = Enrollment::with(['classOffering.subject', 'classOffering.teacher', 'classOffering.meetings', 'classOffering.program'])
            ->where('student_id', $student->id)
            ->where('status', Enrollment::STATUS_ENROLLED)
            ->whereHas('classOffering', fn ($q) => $q->where('term_id', $term->id))
            ->get()
            ->sortBy(fn ($e) => $e->classOffering->subject->code)->values();

        if ($enrollments->isEmpty()) {
            return redirect($back)->with('error', "No officially enrolled classes in {$term->name} · {$term->academicYear->name}, so there is no registration form yet.");
        }

        $rows = $enrollments->map(function (Enrollment $enrollment) {
            $class = $enrollment->classOffering;
            $meetings = $class->meetings->sortBy(['day_of_week', 'start_time'])->values();

            return [
                'code' => $class->class_code,
                'subject' => $class->subject->code,
                'title' => $class->subject->title,
                'units' => (float) $class->subject->units,
                'block' => $class->block_label,
                // One line per meeting: "Mon 8:00 AM–9:30 AM".
                'times' => $meetings->map(fn (ClassMeeting $m) => substr(ClassMeeting::DAYS[$m->day_of_week], 0, 3) . ' ' . ScheduleChecker::time($m->start_time) . '–' . ScheduleChecker::time($m->end_time))->all(),
                'rooms' => $meetings->map(fn (ClassMeeting $m) => $m->room ?: 'TBA')->all(),
                'teacher' => $class->teacher?->name ?? 'TBA',
            ];
        });

        $student->loadMissing('program');
        $data = [
            'student' => $student,
            'term' => $term,
            'rows' => $rows,
            'totalUnits' => $rows->sum('units'),
            'enrolledOn' => $enrollments->min('enrolled_at'),
            'school' => SchoolBranding::hasRealName() ? SchoolBranding::name() : 'SIES Campus Portal',
            'address' => SchoolBranding::address(),
            'logo' => SchoolBranding::logoDataUri(),
            'generatedAt' => now(),
            'generatedBy' => $request->user()->name,
        ];

        AuditLogger::log('student.registration_form', $student,
            "Registration form of {$student->full_name} ({$student->student_number}) for {$term->name} · {$term->academicYear->name} printed by {$request->user()->name}");

        return Pdf::loadView('registration.form', $data)->setPaper('a4')
            ->download('registration-form-' . $student->student_number . '-' . str($term->academicYear->name . ' ' . $term->name)->slug() . '.pdf');
    }

    /** The chosen term, else the current one, else the latest term in which the student was enrolled. */
    private function term(Student $student, ?int $termId): ?Term
    {
        if ($termId) {
            return Term::with('academicYear')->find($termId);
        }

        $current = Term::with('academicYear')->where('is_current', true)->first();
        $hasCurrent = $current && Enrollment::where('student_id', $student->id)->where('status', Enrollment::STATUS_ENROLLED)
            ->whereHas('classOffering', fn ($q) => $q->where('term_id', $current->id))->exists();

        return $hasCurrent ? $current : Term::with('academicYear')
            ->whereHas('classOfferings.enrollments', fn ($q) => $q->where('student_id', $student->id)->where('status', Enrollment::STATUS_ENROLLED))
            ->orderByDesc('start_date')->first() ?? $current;
    }
}
