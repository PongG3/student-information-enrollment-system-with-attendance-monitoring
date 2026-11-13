<?php

namespace App\Http\Controllers;

use App\Models\AbsenceExcuse;
use App\Models\AttendanceRecord;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\Student;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Excuse letters: a student explains an absence or late arrival; the teacher decides on the sheet. */
class AbsenceExcuseController extends Controller
{
    /** A student sends (or, while it waits, replaces) the letter for one recorded absence or late arrival. */
    public function store(Request $request, int $class)
    {
        $student = Student::where('user_id', $request->user()->id)->firstOrFail();
        $offering = ClassOffering::whereKey($class)
            ->whereHas('enrollments', fn ($q) => $q->where('student_id', $student->id)->where('status', Enrollment::STATUS_ENROLLED))
            ->firstOrFail();

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'all_day' => ['nullable', 'boolean'],
            // Several days off (e.g. a week of illness): every missed meeting from "date" to "until".
            'until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date', 'before_or_equal:today'],
        ], [
            'until.after_or_equal' => 'The last day must be on or after the first day.',
            'until.before_or_equal' => 'The last day cannot be in the future.',
            'reason.min' => 'Explain the reason in at least 10 characters.',
            'file.mimes' => 'Attach a PDF or a photo (JPG or PNG).',
            'file.max' => 'The file may not be larger than 5 MB.',
        ]);

        // Only for a meeting where the student was marked absent or late.
        $marked = AttendanceRecord::where('student_id', $student->id)
            ->whereIn('status', [AttendanceRecord::ABSENT, AttendanceRecord::LATE])
            ->whereHas('session', fn ($q) => $q->where('class_offering_id', $offering->id)->whereDate('session_date', $data['date']))
            ->exists();
        if (! $marked) {
            return back()->withErrors(['excuse' => 'You can only explain a meeting where you were marked absent or late.']);
        }

        $existing = AbsenceExcuse::where('student_id', $student->id)->where('class_offering_id', $offering->id)->whereDate('date', $data['date'])->first();
        if ($existing && $existing->status !== AbsenceExcuse::STATUS_PENDING) {
            return back()->withErrors(['excuse' => 'Your teacher already decided on this letter.']);
        }

        // Which meetings the letter covers: this one; plus, if asked, the other classes missed that day;
        // plus, with an end date, every missed meeting up to it (this class, or all classes with "all_day").
        $targets = [[$offering->id, $data['date']]];
        $until = $data['until'] ?? null;
        foreach (AbsenceExcuse::missedByDay($student) as $date => $classes) {
            $inRange = $date === $data['date'] || ($until && $date > $data['date'] && $date <= $until);
            if (! $inRange) {
                continue;
            }
            foreach (array_keys($classes) as $classId) {
                if ($classId === $offering->id || $request->boolean('all_day')) {
                    $targets[] = [$classId, $date];
                }
            }
        }
        $targets = collect($targets)->unique(fn ($t) => $t[0] . '|' . $t[1])->sortBy(fn ($t) => $t[1] . '|' . $t[0])->values();

        $file = $request->file('file');
        $upload = $file?->store('excuse-letters', 'local');
        $sent = [];

        foreach ($targets as $i => [$classId, $date]) {
            // Each meeting keeps its own copy of the file, so replacing one letter never affects another.
            $path = $upload === null ? null : ($i === 0 ? $upload : $this->copyOf($upload));
            $sent[] = $this->saveLetter($student, (int) $classId, $date, trim($data['reason']), $path, $file?->getClientOriginalName());
        }

        $codes = ClassOffering::with('subject')->whereIn('id', $targets->pluck(0))->get()->pluck('subject.code')->implode(', ');
        $days = $targets->pluck(1)->unique()->count();
        AuditLogger::log('excuse.sent', $sent[0], "Excuse letter sent for {$codes}: " . count($sent) . ' meeting(s) from ' . $data['date'] . ($until ? " to {$until}" : ''));

        return back()->with('success', count($sent) > 1
            ? 'Excuse letter submitted for ' . count($sent) . ' missed meetings' . ($days > 1 ? " over {$days} days" : '') . " ({$codes}). Each teacher reviews it on their attendance sheet."
            : 'Excuse letter submitted. Your teacher will review it on the attendance sheet.');
    }

    private function saveLetter(Student $student, int $classId, string $date, string $reason, ?string $path, ?string $fileName): AbsenceExcuse
    {
        $existing = AbsenceExcuse::where('student_id', $student->id)->where('class_offering_id', $classId)->whereDate('date', $date)->first();
        if ($path && $existing?->file_path) {
            Storage::disk('local')->delete($existing->file_path);
        }

        return AbsenceExcuse::updateOrCreate(
            ['student_id' => $student->id, 'class_offering_id' => $classId, 'date' => $date],
            array_filter([
                'reason' => $reason,
                'file_path' => $path,
                'file_name' => $fileName ? mb_substr($fileName, 0, 255) : null,
                'status' => AbsenceExcuse::STATUS_PENDING,
            ], fn ($value) => $value !== null),
        );
    }

    private function copyOf(string $path): string
    {
        $copy = 'excuse-letters/' . \Illuminate\Support\Str::random(40) . '.' . pathinfo($path, PATHINFO_EXTENSION);
        Storage::disk('local')->copy($path, $copy);

        return $copy;
    }

    /** The attached file: the student who sent it, the class's teacher, the registrar and administrators. */
    public function file(Request $request, AbsenceExcuse $excuse)
    {
        $user = $request->user();
        $allowed = match ($user->role) {
            'admin', 'registrar' => true,
            'teacher' => $excuse->classOffering->teacher_id === $user->id,
            'student' => $excuse->student->user_id === $user->id,
            default => false,
        };
        abort_unless($allowed && $excuse->file_path && Storage::disk('local')->exists($excuse->file_path), 404);

        return Storage::disk('local')->response($excuse->file_path, $excuse->file_name ?? 'excuse-letter');
    }
}
