<?php

namespace App\Http\Controllers;

use App\Models\Requirement;
use App\Models\Student;
use App\Models\StudentRequirement;
use App\Notifications\Notice;
use App\Support\AuditLogger;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StudentRequirementController extends Controller
{
    /** Mark a document submitted (handed in on paper) or verified. Sending it back goes through returnToStudent(). */
    public function update(Request $request, Student $student, Requirement $requirement)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([StudentRequirement::STATUS_SUBMITTED, StudentRequirement::STATUS_VERIFIED])],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $row = StudentRequirement::firstOrNew(['student_id' => $student->id, 'requirement_id' => $requirement->id]);
        $previous = $row->status ?? StudentRequirement::STATUS_PENDING;
        $status = $data['status'];

        $row->status = $status;
        $row->remarks = $data['remarks'] ?? $row->remarks;
        $row->submitted_at ??= now();

        if ($status === StudentRequirement::STATUS_VERIFIED) {
            $row->verified_by = $request->user()->id;
            $row->verified_at = now();
        } else {
            $row->verified_by = null;
            $row->verified_at = null;
        }

        $row->save();

        AuditLogger::log(
            'requirement.' . $status,
            $student,
            "{$requirement->name} for {$student->student_number} set to {$status}",
            ['status' => $previous],
            ['status' => $status],
        );

        return back()->with('success', "{$requirement->name} marked as {$status}.");
    }

    /**
     * Send a document back to the student (unreadable scan, wrong document, expired...). The reason is required,
     * the uploaded file is removed, the document goes back to pending, and the student is told why (profile + email).
     */
    public function returnToStudent(Request $request, Student $student, Requirement $requirement)
    {
        $data = $request->validate(
            ['reason' => ['required', 'string', 'min:5', 'max:500']],
            ['reason.required' => 'Tell the student why the document is being returned.', 'reason.min' => 'Write a reason of at least 5 characters.'],
        );
        $reason = trim($data['reason']);

        $row = StudentRequirement::where('student_id', $student->id)->where('requirement_id', $requirement->id)->first();
        if (! $row || ($row->status === StudentRequirement::STATUS_PENDING && ! $row->file_path)) {
            return back()->with('error', "{$requirement->name} has nothing to return: it was not submitted.");
        }

        $previous = $row->status;
        if ($row->file_path) {
            Storage::disk('local')->delete($row->file_path);
        }
        $row->forceFill([
            'status' => StudentRequirement::STATUS_PENDING,
            'submitted_at' => null,
            'verified_by' => null,
            'verified_at' => null,
            'file_path' => null,
            'file_name' => null,
            'returned_reason' => $reason,
            'returned_at' => now(),
            'returned_by' => $request->user()->id,
        ])->save();

        AuditLogger::log('requirement.returned', $student,
            "{$requirement->name} of {$student->student_number} returned to the student: {$reason}",
            ['status' => $previous], ['status' => StudentRequirement::STATUS_PENDING, 'reason' => $reason]);

        Notify::student($student, new Notice(
            "Document returned: {$requirement->name}",
            [
                "The registrar's office returned your {$requirement->name}.",
                "Reason: {$reason}",
                'Please upload a corrected copy from your profile, or bring the original to the registrar\'s office.',
            ],
            'Open my profile', route('student.profile'),
        ));

        return back()->with('success', "{$requirement->name} returned to {$student->first_name}. They were told why.");
    }
}
