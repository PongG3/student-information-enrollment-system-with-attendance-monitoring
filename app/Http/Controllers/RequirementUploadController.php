<?php

namespace App\Http\Controllers;

use App\Models\Requirement;
use App\Models\Student;
use App\Models\StudentRequirement;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Students upload a scan of an admission requirement (marks it "submitted"); the registrar checks the
 * file on the student's record and verifies it. A verified requirement can no longer be replaced.
 */
class RequirementUploadController extends Controller
{
    public function store(Request $request, Requirement $requirement)
    {
        $student = $request->attributes->get('student') ?? Student::where('user_id', $request->user()->id)->firstOrFail();
        abort_unless($requirement->is_active, 404);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'file.mimes' => 'Upload a PDF or a photo (JPG or PNG).',
            'file.max' => 'The file may not be larger than 5 MB.',
        ]);

        $row = StudentRequirement::firstOrNew(['student_id' => $student->id, 'requirement_id' => $requirement->id]);
        if ($row->status === StudentRequirement::STATUS_VERIFIED) {
            return back()->withErrors(['requirement' => "{$requirement->name} is already verified."]);
        }

        if ($row->file_path) {
            Storage::disk('local')->delete($row->file_path);
        }
        $file = $request->file('file');
        $row->fill([
            'file_path' => $file->store('requirements', 'local'),
            'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'status' => StudentRequirement::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'verified_by' => null,
            'verified_at' => null,
        ])->save();

        AuditLogger::log('requirement.uploaded', $student, "{$student->full_name} uploaded {$requirement->name}");

        return redirect()->route('student.profile')->with('success', "{$requirement->name} uploaded. The registrar will check it.");
    }

    /** The uploaded scan: the student who sent it, the registrar and administrators. */
    public function file(Request $request, StudentRequirement $studentRequirement)
    {
        $user = $request->user();
        $allowed = in_array($user->role, ['admin', 'registrar'], true)
            || ($user->role === 'student' && $studentRequirement->student?->user_id === $user->id);

        abort_unless($allowed && $studentRequirement->file_path && Storage::disk('local')->exists($studentRequirement->file_path), 404);

        // Downloaded, never shown inside the portal: a PDF opened on this site could run script.
        return Storage::disk('local')->download($studentRequirement->file_path, $studentRequirement->file_name ?? 'document');
    }
}
