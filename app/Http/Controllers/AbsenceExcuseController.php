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
        ], [
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

        $file = $request->file('file');
        $path = $file?->store('excuse-letters', 'local');
        if ($path && $existing?->file_path) {
            Storage::disk('local')->delete($existing->file_path);
        }

        $excuse = AbsenceExcuse::updateOrCreate(
            ['student_id' => $student->id, 'class_offering_id' => $offering->id, 'date' => $data['date']],
            array_filter([
                'reason' => trim($data['reason']),
                'file_path' => $path,
                'file_name' => $file ? mb_substr($file->getClientOriginalName(), 0, 255) : null,
                'status' => AbsenceExcuse::STATUS_PENDING,
            ], fn ($value) => $value !== null),
        );

        AuditLogger::log('excuse.sent', $excuse, "Excuse letter sent for {$offering->class_code} on {$data['date']}");

        return back()->with('success', 'Excuse letter sent. Your teacher will review it on the attendance sheet.');
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
