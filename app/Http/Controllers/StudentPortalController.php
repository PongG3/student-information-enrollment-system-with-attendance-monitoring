<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\ClassMeeting;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\Requirement;
use App\Models\Student;
use App\Models\StudentRequirement;
use App\Models\Term;
use App\Services\AttendanceStats;
use App\Services\StudentPortalData;
use Illuminate\Http\Request;
use App\Support\Like;

/**
 * The student portal. Every page is read-only (GET only) and shows only the
 * signed-in student's own information, taken from the StudentPortal middleware.
 */
class StudentPortalController extends Controller
{
    private function student(Request $request): Student
    {
        return $request->attributes->get('student');
    }

    public function dashboard(Request $request)
    {
        $student = $this->student($request);
        $term = Term::with('academicYear')->where('is_current', true)->first();
        $classes = StudentPortalData::enrolledClasses($student, $term);
        $attendance = StudentPortalData::attendance($student, $classes);

        $today = now()->dayOfWeekIso;
        $todaysClasses = $classes
            ->flatMap(fn (ClassOffering $class) => $class->meetings
                ->where('day_of_week', $today)
                ->map(fn (ClassMeeting $meeting) => ['class' => $class, 'meeting' => $meeting]))
            ->sortBy(fn ($item) => $item['meeting']->start_time)
            ->values();

        $openRequests = $term
            ? Enrollment::where('student_id', $student->id)
                ->whereIn('status', [Enrollment::STATUS_PENDING, Enrollment::STATUS_APPROVED])
                ->whereHas('classOffering', fn ($q) => $q->where('term_id', $term->id))
                ->count()
            : 0;

        $hour = now()->hour;
        $greeting = ($hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening')) . ', ' . $student->first_name . '.';

        return view('student.dashboard', compact('student', 'term', 'classes', 'attendance', 'todaysClasses', 'openRequests', 'greeting'));
    }

    public function profile(Request $request)
    {
        $student = $this->student($request);
        $student->load('guardians');

        $requirements = Requirement::where('is_active', true)->orderBy('name')->get()->map(function (Requirement $requirement) use ($student) {
            return [
                'requirement' => $requirement,
                'status' => StudentRequirement::where('student_id', $student->id)
                    ->where('requirement_id', $requirement->id)->value('status') ?? StudentRequirement::STATUS_PENDING,
            ];
        });

        return view('student.profile', compact('student', 'requirements'));
    }

    /** Classes on offer. Browsing only: the registrar adds classes to a student. */
    public function courses(Request $request)
    {
        $student = $this->student($request);
        $request->validate(['term' => ['nullable', 'integer'], 'q' => ['nullable', 'string', 'max:60']]);

        $terms = Term::with('academicYear')->whereNull('closed_at')->orderByDesc('start_date')->get();
        $term = StudentPortalData::pickTerm($terms, $request->input('term'));
        $search = trim((string) $request->input('q', ''));

        $classes = $term
            ? ClassOffering::with(['subject', 'teacher', 'meetings'])
                ->withCount(['enrollments as seats_taken' => fn ($q) => $q->holdingSeat()])
                ->where('term_id', $term->id)
                ->where('status', '!=', ClassOffering::STATUS_CANCELLED)
                ->when($search !== '', function ($query) use ($search) {
                    $like = Like::contains($search);
                    $query->where(fn ($w) => $w->where('class_code', 'like', $like)
                        ->orWhereHas('subject', fn ($s) => $s->where('code', 'like', $like)->orWhere('title', 'like', $like)));
                })
                ->orderBy('class_code')->get()
            : collect();

        $mine = Enrollment::where('student_id', $student->id)
            ->whereIn('class_offering_id', $classes->pluck('id')->all())
            ->pluck('status', 'class_offering_id');

        return view('student.courses', compact('terms', 'term', 'classes', 'mine', 'search'));
    }

    public function enrollments(Request $request)
    {
        $student = $this->student($request);
        $request->validate(['term' => ['nullable', 'string', 'max:10']]);

        $terms = StudentPortalData::historyTerms($student);
        $all = $request->input('term') === 'all';
        $term = $all ? null : StudentPortalData::pickTerm($terms, $request->input('term'));

        $enrollments = Enrollment::with(['classOffering.subject', 'classOffering.teacher', 'classOffering.meetings', 'classOffering.term.academicYear'])
            ->where('student_id', $student->id)
            ->when($term, fn ($q) => $q->whereHas('classOffering', fn ($c) => $c->where('term_id', $term->id)))
            ->latest('requested_at')->latest('id')
            ->get();

        $enrolledUnits = (float) $enrollments->where('status', Enrollment::STATUS_ENROLLED)
            ->sum(fn ($e) => (float) $e->classOffering->subject->units);

        return view('student.enrollments', compact('terms', 'term', 'all', 'enrollments', 'enrolledUnits'));
    }

    public function schedule(Request $request)
    {
        $student = $this->student($request);
        $terms = StudentPortalData::historyTerms($student);
        $term = StudentPortalData::pickTerm($terms, $request->input('term'));
        $classes = StudentPortalData::enrolledClasses($student, $term);

        $byDay = [];
        foreach ($classes as $class) {
            foreach ($class->meetings as $meeting) {
                $byDay[$meeting->day_of_week][] = ['class' => $class, 'meeting' => $meeting];
            }
        }
        ksort($byDay);
        foreach ($byDay as $day => $items) {
            usort($items, fn ($a, $b) => strcmp($a['meeting']->start_time, $b['meeting']->start_time));
            $byDay[$day] = $items;
        }

        return view('student.schedule', [
            'terms' => $terms, 'term' => $term, 'classes' => $classes, 'byDay' => $byDay,
            'units' => StudentPortalData::units($classes),
        ]);
    }

    public function attendance(Request $request)
    {
        $student = $this->student($request);
        $terms = StudentPortalData::historyTerms($student);
        $term = StudentPortalData::pickTerm($terms, $request->input('term'));
        $classes = StudentPortalData::enrolledClasses($student, $term);

        return view('student.attendance', [
            'terms' => $terms, 'term' => $term,
            'attendance' => StudentPortalData::attendance($student, $classes),
        ]);
    }

    /** Dated attendance of one class. Only classes the student is enrolled in can be opened. */
    public function attendanceClass(Request $request, int $class)
    {
        $student = $this->student($request);

        $offering = ClassOffering::with(['subject', 'teacher', 'meetings', 'term.academicYear'])
            ->whereKey($class)
            ->whereHas('enrollments', fn ($q) => $q
                ->where('student_id', $student->id)
                ->where('status', Enrollment::STATUS_ENROLLED))
            ->firstOrFail();

        $records = AttendanceRecord::with('session')
            ->where('student_id', $student->id)
            ->whereHas('session', fn ($q) => $q->where('class_offering_id', $offering->id))
            ->get()
            ->sortByDesc(fn ($record) => $record->session->session_date)
            ->values();

        $counts = AttendanceStats::counts($records->countBy('status')->all());
        $rules = AttendanceStats::rules();

        return view('student.attendance-class', [
            'class' => $offering,
            'records' => $records,
            'counts' => $counts,
            'percentage' => AttendanceStats::percentage($counts, $rules),
            'flagged' => AttendanceStats::isFlagged($counts, $rules),
            'rules' => $rules,
        ]);
    }
}
