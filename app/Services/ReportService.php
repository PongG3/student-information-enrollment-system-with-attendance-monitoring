<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassMeeting;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Reports\Report;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Builds every report from the database. One method per report; all of them return a Report
 * that can be shown on screen, printed, or exported to PDF and Excel.
 */
class ReportService
{
    public const CATALOG = [
        'student-master-list' => [
            'title' => 'Student master list',
            'description' => 'Every student record with program, year level and status.',
            'group' => 'Students and enrollment',
            'roles' => ['admin', 'registrar'],
        ],
        'enrollment' => [
            'title' => 'Enrollment report',
            'description' => 'Enrollment requests by class and status for a term or academic year.',
            'group' => 'Students and enrollment',
            'roles' => ['admin', 'registrar'],
        ],
        'attendance-daily' => [
            'title' => 'Daily attendance report',
            'description' => 'Who was present, late, absent or excused on one day.',
            'group' => 'Attendance',
            'roles' => ['admin', 'registrar', 'teacher'],
        ],
        'attendance-monthly' => [
            'title' => 'Monthly attendance report',
            'description' => 'Attendance counts and percentages per student and class for a month.',
            'group' => 'Attendance',
            'roles' => ['admin', 'registrar', 'teacher'],
        ],
        'student-history' => [
            'title' => 'Student attendance history',
            'description' => 'Every attendance record of one student, with totals.',
            'group' => 'Attendance',
            'roles' => ['admin', 'registrar', 'teacher'],
        ],
        'absentees' => [
            'title' => 'Absentee report',
            'description' => 'Students with absences, worst first, with frequent-absence alerts.',
            'group' => 'Attendance',
            'roles' => ['admin', 'registrar', 'teacher'],
        ],
        'late' => [
            'title' => 'Late students report',
            'description' => 'Students who arrived late, most often first.',
            'group' => 'Attendance',
            'roles' => ['admin', 'registrar', 'teacher'],
        ],
        'missing-sheets' => [
            'title' => 'Missing attendance sheets',
            'description' => 'Past class meetings with no attendance sheet, oldest first.',
            'group' => 'Attendance',
            'roles' => ['admin', 'registrar', 'teacher'],
        ],
        'class-summary' => [
            'title' => 'Class attendance summary',
            'description' => 'One line per class: sheets, totals, attendance percentage and alerts.',
            'group' => 'Attendance',
            'roles' => ['admin', 'registrar', 'teacher'],
        ],
    ];

    /** Reports this user may open. */
    public static function available(User $user): array
    {
        return array_filter(self::CATALOG, fn ($meta) => in_array($user->role, $meta['roles'], true));
    }

    public static function schoolName(): string
    {
        return (string) Setting::get('school_name', 'School');
    }

    // ------------------------------------------------------------------ filters

    /** Validation rules for the filter form of a report. */
    public static function rules(string $slug): array
    {
        $statuses = $slug === 'enrollment'
            ? Enrollment::STATUSES
            : [Student::STATUS_ACTIVE, Student::STATUS_INACTIVE, Student::STATUS_GRADUATED, Student::STATUS_WITHDRAWN];

        return [
            'year' => ['nullable', 'integer', 'exists:academic_years,id'],
            'term' => ['nullable', function ($attribute, $value, $fail) {
                if ($value !== 'all' && ! Term::whereKey($value)->exists()) {
                    $fail('Choose a valid term.');
                }
            }],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'class_offering' => ['nullable', 'integer', 'exists:class_offerings,id'],
            'program' => ['nullable', 'integer', 'exists:programs,id'],
            'year_level' => ['nullable', 'integer', 'between:1,6'],
            'section' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', Rule::in($statuses)],
            'student' => ['nullable', 'string', 'max:20'],
            'min' => ['nullable', 'integer', 'between:1,100'],
            'archived' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The filter form of a report: which fields to show, with their choices.
     *
     * @return array<int, array{name: string, label: string, type: string, options?: array}>
     */
    public static function fields(string $slug, User $user, array $f): array
    {
        $defaultAll = self::defaultsToAllTerms($slug);

        $year = ['name' => 'year', 'label' => 'Academic year', 'type' => 'select', 'options' => ['' => 'Any academic year'] + AcademicYear::orderByDesc('start_date')->pluck('name', 'id')->all()];
        $term = ['name' => 'term', 'label' => 'Term', 'type' => 'select', 'options' => self::termOptions($defaultAll)];
        $class = ['name' => 'class_offering', 'label' => 'Class', 'type' => 'select', 'options' => ['' => 'All classes'] + self::classOptions($user, $f, $defaultAll)];
        $from = ['name' => 'from', 'label' => 'From', 'type' => 'date'];
        $to = ['name' => 'to', 'label' => 'To', 'type' => 'date'];

        return match ($slug) {
            'student-master-list' => [
                ['name' => 'program', 'label' => 'Program', 'type' => 'select', 'options' => ['' => 'All programs'] + Program::orderBy('code')->pluck('code', 'id')->all()],
                ['name' => 'year_level', 'label' => 'Year level', 'type' => 'select', 'options' => ['' => 'All year levels'] + array_combine(range(1, 6), array_map(fn ($n) => "Year {$n}", range(1, 6)))],
                ['name' => 'section', 'label' => 'Block section', 'type' => 'select', 'options' => ['' => 'All sections'] + Student::withTrashed()->whereNotNull('section')->distinct()->orderBy('section')->pluck('section')->mapWithKeys(fn ($s) => [$s => "Section {$s}"])->all()],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['' => 'All statuses', 'active' => 'Active', 'inactive' => 'Inactive', 'graduated' => 'Graduated', 'withdrawn' => 'Withdrawn']],
                ['name' => 'year', 'label' => 'Enrolled in academic year', 'type' => 'select', 'options' => $year['options']],
                ['name' => 'term', 'label' => 'Enrolled in term', 'type' => 'select', 'options' => ['' => 'Any term', 'all' => 'Any term'] + array_slice(self::termOptions(true), 1, null, true)],
                ['name' => 'archived', 'label' => 'Include archived students', 'type' => 'checkbox'],
            ],
            'enrollment' => [
                $year, $term,
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['' => 'All statuses'] + array_combine(Enrollment::STATUSES, array_map('ucfirst', Enrollment::STATUSES))],
                ['name' => 'program', 'label' => 'Program', 'type' => 'select', 'options' => ['' => 'All programs'] + Program::orderBy('code')->pluck('code', 'id')->all()],
            ],
            'attendance-daily' => [['name' => 'date', 'label' => 'Date', 'type' => 'date'], $year, $term, $class],
            'attendance-monthly' => [['name' => 'month', 'label' => 'Month', 'type' => 'month'], $year, $term, $class],
            'student-history' => [['name' => 'student', 'label' => 'Student ID', 'type' => 'text'], $from, $to, $year],
            'absentees' => [$year, $term, $class, $from, $to, ['name' => 'min', 'label' => 'At least this many absences', 'type' => 'number']],
            'late' => [$year, $term, $class, $from, $to, ['name' => 'min', 'label' => 'At least this many late arrivals', 'type' => 'number']],
            'class-summary' => [$year, $term, $class, $from, $to],
            'missing-sheets' => [$year, $term, $class, $from, $to],
            default => [],
        };
    }

    // ------------------------------------------------------------------ building

    /** @throws ValidationException when a required filter is missing or wrong */
    public static function build(string $slug, User $user, array $f): Report
    {
        return match ($slug) {
            'student-master-list' => self::studentMasterList($f),
            'enrollment' => self::enrollment($user, $f),
            'attendance-daily' => self::daily($user, $f),
            'attendance-monthly' => self::monthly($user, $f),
            'student-history' => self::history($user, $f),
            'absentees' => self::exceptions($slug, $user, $f),
            'late' => self::exceptions($slug, $user, $f),
            'class-summary' => self::classSummary($user, $f),
            'missing-sheets' => self::missingSheets($user, $f),
        };
    }

    private static function studentMasterList(array $f): Report
    {
        $filtered = ! empty($f['year']) || ! empty($f['term']);
        $termIds = $filtered ? self::termIds($f, true) : [];

        $students = Student::query()->with('program')
            ->when(! empty($f['archived']), fn ($q) => $q->withTrashed())
            ->when(! empty($f['program']), fn ($q) => $q->where('program_id', (int) $f['program']))
            ->when(! empty($f['year_level']), fn ($q) => $q->where('year_level', (int) $f['year_level']))
            ->when(! empty($f['section']), fn ($q) => $q->where('section', mb_strtoupper($f['section'])))
            ->when(! empty($f['status']), fn ($q) => $q->where('status', $f['status']))
            ->when($filtered, fn ($q) => $q->whereHas('enrollments', fn ($e) => $e
                ->where('status', Enrollment::STATUS_ENROLLED)
                ->whereHas('classOffering', fn ($c) => $c->whereIn('term_id', $termIds))))
            ->orderBy('last_name')->orderBy('first_name')->get();

        $rows = $students->map(fn (Student $s) => [
            $s->student_number, $s->last_name, $s->first_name, $s->middle_name, $s->gender,
            $s->birth_date->format('Y-m-d'), $s->program->code, $s->year_level, $s->section, ucfirst($s->student_type),
            $s->trashed() ? 'Archived' : ucfirst($s->status), $s->email, $s->contact_number,
        ])->all();

        $summary = ['Total students' => $students->count()];
        foreach ($students->groupBy(fn ($s) => $s->trashed() ? 'Archived' : ucfirst($s->status)) as $status => $group) {
            $summary[$status] = $group->count();
        }

        return new Report(
            'student-master-list', 'Student master list',
            array_filter([
                'Program' => ! empty($f['program']) ? Program::find($f['program'])?->code : null,
                'Year level' => ! empty($f['year_level']) ? 'Year ' . $f['year_level'] : null,
                'Section' => ! empty($f['section']) ? mb_strtoupper($f['section']) : null,
                'Status' => ! empty($f['status']) ? ucfirst($f['status']) : null,
                'Enrolled in' => $filtered ? self::scopeLabel($f, true) : null,
                'Archived students' => ! empty($f['archived']) ? 'included' : null,
            ]),
            ['Student ID', 'Last name', 'First name', 'Middle name', 'Gender', 'Birth date', 'Program', 'Year', 'Section', 'Type', 'Status', 'Email', 'Contact number'],
            $rows, $summary, 'landscape',
        );
    }

    private static function enrollment(User $user, array $f): Report
    {
        $termIds = self::termIds($f, false);

        $enrollments = Enrollment::with(['student.program', 'classOffering.subject', 'classOffering.term', 'reviewer'])
            ->whereHas('classOffering', fn ($q) => $q->whereIn('term_id', $termIds))
            ->when(! empty($f['status']), fn ($q) => $q->where('status', $f['status']))
            ->when(! empty($f['program']), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('program_id', (int) $f['program'])))
            ->get()
            ->sortBy(fn ($e) => $e->classOffering->class_code . '|' . $e->student->last_name . '|' . $e->student->first_name)
            ->values();

        $rows = $enrollments->map(fn (Enrollment $e) => [
            $e->classOffering->class_code,
            $e->classOffering->subject->code . ' · ' . $e->classOffering->subject->title,
            rtrim(rtrim(number_format((float) $e->classOffering->subject->units, 1), '0'), '.'),
            $e->student->student_number, $e->student->full_name, $e->student->program->code, $e->student->year_level,
            ucfirst($e->status), $e->requested_at?->format('Y-m-d'), $e->reviewer?->name, $e->status_reason,
        ])->all();

        $summary = ['Total requests' => $enrollments->count()];
        foreach (Enrollment::STATUSES as $status) {
            $summary[ucfirst($status)] = $enrollments->where('status', $status)->count();
        }
        $summary['Units of enrolled classes'] = rtrim(rtrim(number_format((float) $enrollments->where('status', Enrollment::STATUS_ENROLLED)
            ->sum(fn ($e) => (float) $e->classOffering->subject->units), 1), '0'), '.') ?: '0';

        return new Report(
            'enrollment', 'Enrollment report',
            array_filter([
                'Period' => self::scopeLabel($f, false),
                'Status' => ! empty($f['status']) ? ucfirst($f['status']) : null,
                'Program' => ! empty($f['program']) ? Program::find($f['program'])?->code : null,
            ]),
            ['Class', 'Subject', 'Units', 'Student ID', 'Student', 'Program', 'Year', 'Status', 'Requested', 'Reviewed by', 'Reason'],
            $rows, $summary, 'landscape',
        );
    }

    private static function daily(User $user, array $f): Report
    {
        $date = Carbon::createFromFormat('Y-m-d', $f['date'] ?? today()->toDateString())->startOfDay();
        $classes = self::scopedClasses($user, self::termIds($f, true), $f);

        $records = AttendanceRecord::with(['student', 'session.classOffering.subject', 'session.classOffering.teacher'])
            ->whereHas('session', fn ($q) => $q->whereDate('session_date', $date)->whereIn('class_offering_id', $classes->pluck('id')))
            ->get()
            ->sortBy(fn ($r) => $r->session->classOffering->class_code . '|' . $r->student->last_name . '|' . $r->student->first_name)
            ->values();

        $rows = $records->map(fn (AttendanceRecord $r) => [
            $r->session->classOffering->class_code,
            $r->session->classOffering->subject->code,
            $r->session->classOffering->teacher->name,
            $r->student->student_number, $r->student->full_name, ucfirst($r->status), $r->remarks,
        ])->all();

        $counts = AttendanceStats::counts($records->countBy('status')->all());

        return new Report(
            'attendance-daily', 'Daily attendance report',
            array_filter(['Date' => $date->format('l, F j, Y'), 'Period' => self::scopeLabel($f, true), 'Class' => self::classLabel($f)]),
            ['Class', 'Subject', 'Teacher', 'Student ID', 'Student', 'Status', 'Remarks'],
            $rows, self::attendanceSummary($counts, ['Attendance sheets' => $records->pluck('attendance_session_id')->unique()->count()]), 'landscape',
        );
    }

    private static function monthly(User $user, array $f): Report
    {
        $month = Carbon::createFromFormat('Y-m-d', ($f['month'] ?? today()->format('Y-m')) . '-01')->startOfDay();
        $from = $month->copy();
        $to = min($month->copy()->endOfMonth()->startOfDay(), today());

        $classes = self::scopedClasses($user, self::termIds($f, true), $f);
        $aggregate = self::aggregate($classes->pluck('id')->all(), $from, $to);

        $rows = self::studentClassRows($aggregate, $classes, function ($class, $student, $counts, $rules) {
            return [
                $class->class_code, $class->subject->code, $student->student_number, $student->full_name,
                $counts['present'], $counts['late'], $counts['absent'], $counts['excused'], array_sum($counts),
                self::pct(AttendanceStats::percentage($counts, $rules)),
            ];
        });

        $totals = self::sumCounts($aggregate);

        return new Report(
            'attendance-monthly', 'Monthly attendance report',
            array_filter(['Month' => $month->format('F Y'), 'Period' => self::scopeLabel($f, true), 'Class' => self::classLabel($f)]),
            ['Class', 'Subject', 'Student ID', 'Student', 'Present', 'Late', 'Absent', 'Excused', 'Meetings', 'Attendance'],
            $rows, self::attendanceSummary($totals), 'landscape',
        );
    }

    private static function history(User $user, array $f): Report
    {
        $number = trim((string) ($f['student'] ?? ''));

        if ($number === '') {
            throw ValidationException::withMessages(['student' => 'Enter a student ID.']);
        }

        $student = Student::withTrashed()->with('program')->where('student_number', $number)->first();

        if (! $student) {
            throw ValidationException::withMessages(['student' => 'No student has that ID.']);
        }

        $classes = self::scopedClasses($user, self::termIds($f, true), []);
        $from = ! empty($f['from']) ? Carbon::createFromFormat('Y-m-d', $f['from'])->startOfDay() : null;
        $to = ! empty($f['to']) ? Carbon::createFromFormat('Y-m-d', $f['to'])->startOfDay() : null;

        $records = AttendanceRecord::with(['session.classOffering.subject'])
            ->where('student_id', $student->id)
            ->whereHas('session', fn ($q) => $q
                ->whereIn('class_offering_id', $classes->pluck('id'))
                ->when($from, fn ($s) => $s->whereDate('session_date', '>=', $from->toDateString()))
                ->when($to, fn ($s) => $s->whereDate('session_date', '<=', $to->toDateString())))
            ->get()
            ->sortBy(fn ($r) => $r->session->session_date->toDateString() . '|' . $r->session->classOffering->class_code)
            ->values();

        $rows = $records->map(fn (AttendanceRecord $r) => [
            $r->session->session_date->format('Y-m-d'), $r->session->session_date->format('l'),
            $r->session->classOffering->class_code, $r->session->classOffering->subject->code . ' · ' . $r->session->classOffering->subject->title,
            ucfirst($r->status), $r->remarks,
        ])->all();

        $counts = AttendanceStats::counts($records->countBy('status')->all());

        return new Report(
            'student-history', 'Student attendance history',
            array_filter([
                'Student' => $student->full_name . ' (' . $student->student_number . ')',
                'Program' => $student->program->code . ' · Year ' . $student->year_level,
                'From' => $from?->format('M j, Y'), 'To' => $to?->format('M j, Y'),
                'Academic year' => ! empty($f['year']) ? AcademicYear::find($f['year'])?->name : null,
            ]),
            ['Date', 'Day', 'Class', 'Subject', 'Status', 'Remarks'],
            $rows, self::attendanceSummary($counts), 'portrait',
            $user->role === 'teacher' ? 'Only records from your own classes are shown.' : null,
        );
    }

    /** Absentee and late-student reports share one shape. */
    private static function exceptions(string $slug, User $user, array $f): Report
    {
        $isLate = $slug === 'late';
        $key = $isLate ? 'late' : 'absent';
        $min = (int) ($f['min'] ?? 1);

        $classes = self::scopedClasses($user, self::termIds($f, false), $f);
        $from = ! empty($f['from']) ? Carbon::createFromFormat('Y-m-d', $f['from'])->startOfDay() : null;
        $to = ! empty($f['to']) ? Carbon::createFromFormat('Y-m-d', $f['to'])->startOfDay() : null;

        $aggregate = self::aggregate($classes->pluck('id')->all(), $from, $to);
        $rules = AttendanceStats::rules();
        $students = Student::withTrashed()->with('program')->whereIn('id', collect($aggregate)->map(fn ($v, $k) => (int) explode('|', $k)[1])->unique())->get()->keyBy('id');
        $classMap = $classes->keyBy('id');

        $entries = collect($aggregate)
            ->map(function ($raw, $k) use ($students, $classMap, $rules) {
                [$classId, $studentId] = array_map('intval', explode('|', $k));
                $counts = AttendanceStats::counts($raw);

                return ['class' => $classMap[$classId], 'student' => $students[$studentId], 'counts' => $counts,
                    'flag' => AttendanceStats::isFlagged($counts, $rules), 'absence' => AttendanceStats::absenceRate($counts, $rules)];
            })
            ->filter(fn ($e) => $e['counts'][$key] >= $min)
            ->sortBy(fn ($e) => sprintf('%05d', 99999 - $e['counts'][$key]) . '|' . $e['student']->last_name . '|' . $e['student']->first_name)
            ->values();

        $rows = $entries->map(function ($e) use ($isLate) {
            $meetings = array_sum($e['counts']);
            $base = [$e['student']->student_number, $e['student']->full_name, $e['student']->program->code, $e['class']->class_code, $e['class']->subject->code];

            return $isLate
                ? array_merge($base, [$e['counts']['late'], $e['counts']['absent'], $meetings, $meetings ? self::pct(round($e['counts']['late'] / $meetings * 100, 1)) : '—'])
                : array_merge($base, [$e['counts']['absent'], $e['counts']['late'], $e['counts']['excused'], $meetings, self::pct($e['absence']), $e['flag'] ? 'Frequent absences' : '']);
        })->all();

        $headings = $isLate
            ? ['Student ID', 'Student', 'Program', 'Class', 'Subject', 'Times late', 'Absences', 'Meetings', 'Late rate']
            : ['Student ID', 'Student', 'Program', 'Class', 'Subject', 'Absences', 'Late', 'Excused', 'Meetings', 'Absence rate', 'Alert'];

        return new Report(
            $slug, $isLate ? 'Late students report' : 'Absentee report',
            array_filter([
                'Period' => self::scopeLabel($f, false), 'Class' => self::classLabel($f),
                'From' => $from?->format('M j, Y'), 'To' => $to?->format('M j, Y'),
                'Listed' => $isLate ? "at least {$min} late arrival(s)" : "at least {$min} absence(s)",
            ]),
            $headings, $rows,
            $isLate
                ? ['Students listed' => $entries->pluck('student.id')->unique()->count(), 'Total late arrivals' => $entries->sum(fn ($e) => $e['counts']['late'])]
                : ['Students listed' => $entries->pluck('student.id')->unique()->count(), 'Total absences' => $entries->sum(fn ($e) => $e['counts']['absent']),
                    'Frequent-absence alerts' => $entries->where('flag', true)->count()],
            'landscape',
            $isLate ? null : 'Alert: absent in more than ' . $rules['threshold'] . '% of at least ' . AttendanceStats::MIN_MEETINGS_FOR_ALERT . ' meetings.',
        );
    }

    private static function missingSheets(User $user, array $f): Report
    {
        $classes = self::scopedClasses($user, self::termIds($f, false), $f)->load('meetings');
        $from = ! empty($f['from']) ? Carbon::createFromFormat('Y-m-d', $f['from'])->startOfDay() : null;
        $to = ! empty($f['to']) ? Carbon::createFromFormat('Y-m-d', $f['to'])->startOfDay() : null;

        $missing = MissingSheets::find($classes, $from, $to);

        $rows = $missing->map(fn ($row) => [
            $row['date']->format('Y-m-d'), $row['date']->format('l'), $row['class']->class_code,
            $row['class']->subject->code . ' · ' . $row['class']->subject->title, $row['class']->teacher->name,
        ])->all();

        return new Report(
            'missing-sheets', 'Missing attendance sheets',
            array_filter(['Period' => self::scopeLabel($f, false), 'Class' => self::classLabel($f), 'From' => $from?->format('M j, Y'), 'To' => $to?->format('M j, Y')]),
            ['Date', 'Day', 'Class', 'Subject', 'Teacher'],
            $rows,
            ['Missing sheets' => count($rows), 'Classes affected' => $missing->pluck('class.id')->unique()->count()],
            'portrait',
            'Counts class meetings before today with no sheet, skipping no-class days and cancelled classes. Attendance percentages leave these meetings out.',
        );
    }

    private static function classSummary(User $user, array $f): Report
    {
        $classes = self::scopedClasses($user, self::termIds($f, false), $f);
        $ids = $classes->pluck('id')->all();
        $from = ! empty($f['from']) ? Carbon::createFromFormat('Y-m-d', $f['from'])->startOfDay() : null;
        $to = ! empty($f['to']) ? Carbon::createFromFormat('Y-m-d', $f['to'])->startOfDay() : null;

        $aggregate = self::aggregate($ids, $from, $to);
        $rules = AttendanceStats::rules();

        $enrolled = Enrollment::whereIn('class_offering_id', $ids)->where('status', Enrollment::STATUS_ENROLLED)
            ->selectRaw('class_offering_id, count(*) as total')->groupBy('class_offering_id')->pluck('total', 'class_offering_id');

        $sheets = AttendanceSession::whereIn('class_offering_id', $ids)
            ->when($from, fn ($q) => $q->whereDate('session_date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('session_date', '<=', $to->toDateString()))
            ->selectRaw('class_offering_id, count(*) as total')->groupBy('class_offering_id')->pluck('total', 'class_offering_id');

        $perClass = [];
        foreach ($aggregate as $k => $raw) {
            [$classId] = array_map('intval', explode('|', $k));
            $counts = AttendanceStats::counts($raw);

            foreach ($counts as $status => $n) {
                $perClass[$classId]['counts'][$status] = ($perClass[$classId]['counts'][$status] ?? 0) + $n;
            }
            $perClass[$classId]['flagged'] = ($perClass[$classId]['flagged'] ?? 0) + (AttendanceStats::isFlagged($counts, $rules) ? 1 : 0);
        }

        $rows = $classes->sortBy('class_code')->map(function (ClassOffering $class) use ($perClass, $enrolled, $sheets, $rules) {
            $counts = AttendanceStats::counts($perClass[$class->id]['counts'] ?? []);

            return [
                $class->class_code, $class->subject->code . ' · ' . $class->subject->title, $class->teacher->name,
                (int) ($enrolled[$class->id] ?? 0), (int) ($sheets[$class->id] ?? 0),
                $counts['present'], $counts['late'], $counts['absent'], $counts['excused'],
                self::pct(AttendanceStats::percentage($counts, $rules)), (int) ($perClass[$class->id]['flagged'] ?? 0),
            ];
        })->values()->all();

        return new Report(
            'class-summary', 'Class attendance summary',
            array_filter(['Period' => self::scopeLabel($f, false), 'Class' => self::classLabel($f), 'From' => $from?->format('M j, Y'), 'To' => $to?->format('M j, Y')]),
            ['Class', 'Subject', 'Teacher', 'Enrolled', 'Sheets', 'Present', 'Late', 'Absent', 'Excused', 'Attendance', 'Alerts'],
            $rows, self::attendanceSummary(self::sumCounts($aggregate), ['Classes' => $classes->count()]), 'landscape',
        );
    }

    // ------------------------------------------------------------------ helpers

    private static function defaultsToAllTerms(string $slug): bool
    {
        return in_array($slug, ['attendance-daily', 'attendance-monthly', 'student-history', 'student-master-list'], true);
    }

    private static function termOptions(bool $defaultAll): array
    {
        $terms = Term::with('academicYear')->orderByDesc('start_date')->get()
            ->mapWithKeys(fn ($t) => [$t->id => $t->name . ' · ' . $t->academicYear->name])->all();

        return $defaultAll
            ? ['' => 'All terms'] + $terms
            : ['' => 'Current term', 'all' => 'All terms'] + $terms;
    }

    private static function classOptions(User $user, array $f, bool $defaultAll): array
    {
        return self::scopedClasses($user, self::termIds($f, $defaultAll), [])
            ->sortBy('class_code')->mapWithKeys(fn ($c) => [$c->id => $c->class_code . ' · ' . $c->subject->code])->all();
    }

    /** Which terms a report covers: the chosen term, else the chosen year, else the default. */
    private static function termIds(array $f, bool $defaultAll): array
    {
        $term = $f['term'] ?? null;
        $year = $f['year'] ?? null;

        if ($term && $term !== 'all') {
            return [(int) $term];
        }

        if (! $term && ! $year && ! $defaultAll) {
            $current = Term::current();

            return $current ? [$current->id] : Term::pluck('id')->all();
        }

        return Term::query()->when($year, fn ($q) => $q->where('academic_year_id', (int) $year))->pluck('id')->all();
    }

    private static function scopeLabel(array $f, bool $defaultAll): string
    {
        $term = $f['term'] ?? null;
        $year = $f['year'] ?? null;

        if ($term && $term !== 'all') {
            $model = Term::with('academicYear')->find($term);

            return $model ? $model->name . ' · ' . $model->academicYear->name : 'Selected term';
        }

        if ($year) {
            return 'Academic year ' . (AcademicYear::find($year)?->name ?? '');
        }

        if (! $term && ! $defaultAll) {
            $current = Term::with('academicYear')->where('is_current', true)->first();

            return $current ? $current->name . ' · ' . $current->academicYear->name . ' (current)' : 'All terms';
        }

        return 'All terms';
    }

    private static function classLabel(array $f): ?string
    {
        return ! empty($f['class_offering']) ? ClassOffering::with('subject')->find($f['class_offering'])?->class_code : null;
    }

    /** Classes in the chosen terms. A teacher only ever gets their own. */
    private static function scopedClasses(User $user, array $termIds, array $f): Collection
    {
        return ClassOffering::with(['subject', 'teacher', 'term'])
            ->whereIn('term_id', $termIds)
            ->when($user->role === 'teacher', fn ($q) => $q->where('teacher_id', $user->id))
            ->when(! empty($f['class_offering']), fn ($q) => $q->where('id', (int) $f['class_offering']))
            ->get();
    }

    /**
     * Status counts per student and class: ['classId|studentId' => ['present' => n, ...]].
     *
     * @param  int[]  $classIds
     */
    private static function aggregate(array $classIds, ?Carbon $from, ?Carbon $to): array
    {
        $rows = DB::table('attendance_records as r')
            ->join('attendance_sessions as s', 's.id', '=', 'r.attendance_session_id')
            ->whereIn('s.class_offering_id', $classIds)
            ->when($from, fn ($q) => $q->whereDate('s.session_date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('s.session_date', '<=', $to->toDateString()))
            ->select('s.class_offering_id', 'r.student_id', 'r.status', DB::raw('count(*) as total'))
            ->groupBy('s.class_offering_id', 'r.student_id', 'r.status')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->class_offering_id . '|' . $row->student_id][$row->status] = (int) $row->total;
        }

        return $grouped;
    }

    private static function sumCounts(array $aggregate): array
    {
        $totals = AttendanceStats::counts([]);

        foreach ($aggregate as $raw) {
            foreach (AttendanceStats::counts($raw) as $status => $n) {
                $totals[$status] += $n;
            }
        }

        return $totals;
    }

    /** One row per student and class, sorted by class then student name. */
    private static function studentClassRows(array $aggregate, Collection $classes, callable $row): array
    {
        $rules = AttendanceStats::rules();
        $classMap = $classes->keyBy('id');
        $students = Student::withTrashed()->whereIn('id', collect($aggregate)->map(fn ($v, $k) => (int) explode('|', $k)[1])->unique())->get()->keyBy('id');

        return collect($aggregate)
            ->map(function ($raw, $k) use ($classMap, $students, $row, $rules) {
                [$classId, $studentId] = array_map('intval', explode('|', $k));

                return [
                    'sort' => $classMap[$classId]->class_code . '|' . $students[$studentId]->last_name . '|' . $students[$studentId]->first_name,
                    'row' => $row($classMap[$classId], $students[$studentId], AttendanceStats::counts($raw), $rules),
                ];
            })
            ->sortBy('sort')->pluck('row')->values()->all();
    }

    private static function attendanceSummary(array $counts, array $extra = []): array
    {
        return $extra + [
            'Present' => $counts['present'], 'Late' => $counts['late'], 'Absent' => $counts['absent'], 'Excused' => $counts['excused'],
            'Attendance' => self::pct(AttendanceStats::percentage($counts)),
        ];
    }

    private static function pct(?float $value): string
    {
        return $value === null ? '—' : rtrim(rtrim(number_format($value, 1), '0'), '.') . '%';
    }
}
