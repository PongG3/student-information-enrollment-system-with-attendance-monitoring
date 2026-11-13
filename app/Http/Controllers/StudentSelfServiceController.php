<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * What a student may change on their own record: contact details (right away) and a new photo
 * (waits for the registrar's approval). Everything official stays with the registrar.
 */
class StudentSelfServiceController extends Controller
{
    private function student(Request $request): Student
    {
        return $request->attributes->get('student') ?? Student::where('user_id', $request->user()->id)->firstOrFail();
    }

    public function updateContact(Request $request)
    {
        $student = $this->student($request);
        $data = $request->validate([
            'contact_number' => ['nullable', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
        ], ['contact_number.regex' => 'Enter a valid phone number.']);

        $data = array_map(fn ($value) => ($value = trim((string) $value)) === '' ? null : $value, $data);
        $old = $student->only(array_keys($data));
        $student->update($data);

        $changed = array_keys(array_diff_assoc(array_map('strval', $data), array_map('strval', $old)));
        if ($changed !== []) {
            AuditLogger::log('student.contact_updated', $student, "{$student->full_name} updated their contact details", $old, $data);
        }

        return redirect()->route('student.profile')->with($changed === [] ? 'info' : 'success', $changed === [] ? 'Nothing changed.' : 'Contact details saved.');
    }

    public function uploadPhoto(Request $request)
    {
        $student = $this->student($request);
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048', 'dimensions:min_width=200,min_height=200,max_width=4000,max_height=4000'],
        ], [
            'photo.max' => 'The photo may not be larger than 2 MB.',
            'photo.dimensions' => 'Use a photo at least 200 × 200 pixels.',
        ]);

        // Replaces any photo still waiting; the official one stays until the registrar approves.
        if ($student->pending_photo_path) {
            Storage::disk('local')->delete($student->pending_photo_path);
        }
        $student->update([
            'pending_photo_path' => $request->file('photo')->store('student-photos/pending', 'local'),
            'pending_photo_at' => now(),
            'photo_rejected_reason' => null,   // a new photo replaces the last rejection
            'photo_rejected_at' => null,
        ]);
        AuditLogger::log('student.photo_submitted', $student, "{$student->full_name} submitted a new photo for approval");

        return redirect()->route('student.profile')->with('success', 'Photo sent. The registrar will check it before it becomes your official photo.');
    }

    public function cancelPhoto(Request $request)
    {
        $student = $this->student($request);
        if ($student->pending_photo_path) {
            Storage::disk('local')->delete($student->pending_photo_path);
            $student->update(['pending_photo_path' => null, 'pending_photo_at' => null]);
        }

        return redirect()->route('student.profile')->with('success', 'Photo request withdrawn.');
    }

    /** The student's own photo: the official one, or (?pending=1) the one waiting for approval. */
    public function photo(Request $request)
    {
        $student = $this->student($request);
        $path = $request->boolean('pending') ? $student->pending_photo_path : $student->photo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=600']);
    }
}
