<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Support\AuditLogger;
use App\Support\StudentEmail;
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
        $student->loadMissing('user');
        $data = $request->validate([
            'contact_number' => ['nullable', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            // The email is also the sign-in email, so it cannot be removed and must not belong to another account.
            'email' => ['required', 'email', 'max:255', function ($attribute, $value, $fail) use ($student) {
                if (! StudentEmail::available($value, $student)) {
                    $fail('Another account already uses this email.');
                }
            }],
            'address' => ['nullable', 'string', 'max:500'],
        ], [
            'contact_number.regex' => 'Enter a valid phone number.',
            'email.required' => 'Your email is needed: you sign in with it and receive the portal\'s messages there.',
        ]);

        $data = array_map(fn ($value) => ($value = trim((string) $value)) === '' ? null : $value, $data);
        $newEmail = mb_strtolower($data['email']);
        unset($data['email']);

        // Contact number and address change at once.
        $old = $student->only(array_keys($data));
        $student->update($data);
        $changed = array_keys(array_diff_assoc(array_map('strval', $data), array_map('strval', $old)));
        if ($changed !== []) {
            AuditLogger::log('student.contact_updated', $student, "{$student->full_name} updated their contact details", $old, $data);
        }

        // A new email waits for the student to confirm it from that inbox.
        $current = mb_strtolower((string) ($student->user?->email ?? $student->email));
        if ($newEmail !== $current && $newEmail !== $student->pending_email) {
            StudentEmail::request($student, $newEmail);

            return redirect()->route('student.profile')->with('success',
                ($changed ? 'Contact details saved. ' : '') . "We sent a confirmation link to {$newEmail}. Your email changes when you open it.");
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

    /**
     * The confirmation link from "Confirm your new email". Signed and tied to the address it was sent to; works
     * whether or not the student is signed in on this device.
     */
    public function confirmEmail(Request $request, Student $student)
    {
        $valid = $student->pending_email && hash_equals(StudentEmail::hash($student), (string) $request->query('h'))
            && StudentEmail::available($student->pending_email, $student);
        if (! $valid) {
            return redirect()->route($request->user() ? 'student.profile' : 'login')
                ->with('error', 'That confirmation link is no longer valid. Change your email again on My Profile to get a new one.');
        }

        $new = $student->pending_email;
        StudentEmail::confirm($student);

        return $request->user()
            ? redirect()->route('student.profile')->with('success', "Your email is now {$new}. Use it the next time you sign in.")
            : redirect()->route('login')->with('status', "Your email is now {$new}. Sign in with it.");
    }

    /** Drop a requested email change that was not confirmed. */
    public function cancelEmail(Request $request)
    {
        $student = $this->student($request);
        $student->forceFill(['pending_email' => null, 'pending_email_at' => null])->save();

        return redirect()->route('student.profile')->with('info', 'Email change cancelled. Your email stays the same.');
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
