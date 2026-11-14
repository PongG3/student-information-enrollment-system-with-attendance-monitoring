<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Move students up one year level at the end of an academic year (1st year -> 2nd year ...). The registrar sees
 * the list first and can untick anyone who stays (irregular, on leave, repeating). Students already in the last
 * year level are never moved.
 */
class StudentPromotionController extends Controller
{
    public const LAST_RUN = 'students_promoted_at';

    public function create(Request $request)
    {
        $data = $request->validate([
            'program' => ['nullable', 'integer', 'exists:programs,id'],
            'last_year' => ['nullable', 'integer', 'between:1,6'],
        ]);
        $lastYear = (int) ($data['last_year'] ?? 4);

        $students = Student::with('program')->where('status', Student::STATUS_ACTIVE)
            ->when($data['program'] ?? null, fn ($q, $id) => $q->where('program_id', $id))
            ->orderBy('program_id')->orderBy('year_level')->orderBy('last_name')->orderBy('first_name')
            ->get();

        $lastRun = Setting::get(self::LAST_RUN);
        [$when, $who] = $lastRun ? array_pad(explode('|', $lastRun, 2), 2, null) : [null, null];

        return view('students.promote', [
            'students' => $students,
            'groups' => $students->groupBy(fn ($s) => $s->program?->code . ' · Year ' . $s->year_level),
            'programs' => Program::orderBy('code')->get(),
            'program' => $data['program'] ?? null,
            'lastYear' => $lastYear,
            'lastRunAt' => $when ? Carbon::parse($when) : null,
            'lastRunBy' => $who,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'students' => ['required', 'array', 'min:1', 'max:5000'],
            'students.*' => ['integer'],
            'last_year' => ['required', 'integer', 'between:1,6'],
            'confirm' => ['accepted'],
        ], [
            'students.required' => 'Tick at least one student to move up.',
            'confirm.accepted' => 'Tick the box to confirm.',
        ]);
        $lastYear = (int) $data['last_year'];

        $moved = DB::transaction(function () use ($data, $lastYear) {
            $students = Student::whereIn('id', $data['students'])->where('status', Student::STATUS_ACTIVE)
                ->where('year_level', '<', $lastYear)->lockForUpdate()->get();
            foreach ($students as $student) {
                $student->forceFill(['year_level' => $student->year_level + 1])->save();
            }

            return $students;
        });

        $by = $request->user()->name;
        Setting::put(self::LAST_RUN, now()->toDateTimeString() . '|' . $by);
        AuditLogger::log('student.promoted', null, "Moved {$moved->count()} student(s) up one year level (last year level {$lastYear})", null,
            ['students' => $moved->pluck('student_number')->all()]);

        return redirect()->route('students.index')->with('success',
            $moved->count() . ' ' . ($moved->count() === 1 ? 'student' : 'students') . ' moved up one year level.');
    }
}
