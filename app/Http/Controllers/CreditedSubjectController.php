<?php

namespace App\Http\Controllers;

use App\Models\CreditedSubject;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Credited subjects: a transferee's subjects already passed at another school. They count as completed for
 * prerequisites, and the student is not asked to take them again.
 */
class CreditedSubjectController extends Controller
{
    public function store(Request $request, Student $student)
    {
        $data = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id',
                Rule::unique('credited_subjects', 'subject_id')->where('student_id', $student->id)],
            'school' => ['required', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'subject_id.required' => 'Choose the subject to credit.',
            'subject_id.unique' => 'This subject is already credited.',
            'school.required' => 'Write the school where the subject was taken.',
        ]);

        $subject = Subject::findOrFail($data['subject_id']);

        // A subject the student is taking (or took) here is not credited from elsewhere.
        $here = Enrollment::where('student_id', $student->id)
            ->whereIn('status', [Enrollment::STATUS_PENDING, Enrollment::STATUS_APPROVED, Enrollment::STATUS_ENROLLED])
            ->whereHas('classOffering', fn ($q) => $q->where('subject_id', $subject->id))->exists();
        if ($here) {
            return back()->with('tab', 'enrollments')->withErrors(['subject_id' => "{$subject->code} is already requested or taken here, so it cannot be credited."])->withInput();
        }

        $credit = CreditedSubject::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'school' => trim($data['school']),
            'note' => isset($data['note']) ? trim($data['note']) ?: null : null,
            'credited_by' => $request->user()->id,
        ]);

        AuditLogger::log('student.subject_credited', $student,
            "Credited {$subject->code} to {$student->student_number} (taken at {$credit->school})", null,
            ['subject' => $subject->code, 'school' => $credit->school, 'note' => $credit->note]);

        return back()->with('tab', 'enrollments')->with('success', "{$subject->code} credited. It now counts as completed for prerequisites.");
    }

    public function destroy(Student $student, CreditedSubject $credit)
    {
        abort_unless((int) $credit->student_id === (int) $student->id, 404);
        $code = $credit->subject->code;
        $credit->delete();

        AuditLogger::log('student.subject_uncredited', $student, "Removed the credit for {$code} from {$student->student_number}",
            ['subject' => $code, 'school' => $credit->school], null);

        return back()->with('tab', 'enrollments')->with('success', "Credit for {$code} removed.");
    }
}
