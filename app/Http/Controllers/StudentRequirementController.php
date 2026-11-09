<?php

namespace App\Http\Controllers;

use App\Models\Requirement;
use App\Models\Student;
use App\Models\StudentRequirement;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentRequirementController extends Controller
{
    public function update(Request $request, Student $student, Requirement $requirement)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                StudentRequirement::STATUS_PENDING,
                StudentRequirement::STATUS_SUBMITTED,
                StudentRequirement::STATUS_VERIFIED,
            ])],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $row = StudentRequirement::firstOrNew(['student_id' => $student->id, 'requirement_id' => $requirement->id]);
        $previous = $row->status ?? StudentRequirement::STATUS_PENDING;
        $status = $data['status'];

        $row->status = $status;
        $row->remarks = $data['remarks'] ?? $row->remarks;

        if ($status === StudentRequirement::STATUS_PENDING) {
            $row->submitted_at = null;
        } elseif ($row->submitted_at === null) {
            $row->submitted_at = now();
        }

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
}
