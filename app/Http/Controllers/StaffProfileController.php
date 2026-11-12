<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ClassOffering;
use App\Models\Enrollment;
use App\Models\Term;
use App\Models\User;
use App\Services\ScheduleChecker;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * "My Profile" for staff (administrators, registrar, teachers). Name, email and role are kept in User
 * Management; the staff member keeps their department, office, contact number and photo up to date.
 * Students see a teacher's name, photo and department only.
 */
class StaffProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $term = Term::with('academicYear')->where('is_current', true)->first();

        // Teachers: this term's classes, students and weekly hours.
        $teaching = null;
        if ($user->role === 'teacher' && $term) {
            $classes = ClassOffering::with(['subject', 'meetings'])
                ->where('teacher_id', $user->id)->where('term_id', $term->id)
                ->where('status', '!=', ClassOffering::STATUS_CANCELLED)
                ->withCount(['enrollments as enrolled_count' => fn ($q) => $q->where('status', Enrollment::STATUS_ENROLLED)])
                ->orderBy('class_code')->get();

            $minutes = $classes->sum(fn ($class) => $class->meetings->sum(
                fn ($m) => Carbon::parse($m->end_time)->diffInMinutes(Carbon::parse($m->start_time), true)));

            $teaching = [
                'classes' => $classes,
                'students' => Enrollment::whereIn('class_offering_id', $classes->pluck('id'))->where('status', Enrollment::STATUS_ENROLLED)->distinct()->count('student_id'),
                'hours' => round($minutes / 60, 1),
            ];
        }

        return view('profile.staff', [
            'user' => $user,
            'term' => $term,
            'teaching' => $teaching,
            'activity' => AuditLog::where('user_id', $user->id)->whereNotIn('action', ['login', 'logout'])
                ->latest('created_at')->latest('id')->limit(5)->get(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'department' => ['nullable', 'string', 'max:120'],
            'office' => ['nullable', 'string', 'max:120'],
            'contact_number' => ['nullable', 'regex:/^[0-9+\-\s()]{7,20}$/'],
        ], ['contact_number.regex' => 'Enter a valid phone number.']);

        $data = array_map(fn ($value) => ($value = trim((string) $value)) === '' ? null : $value, $data);
        $old = $user->only(array_keys($data));
        $user->forceFill($data)->save();

        if ($user->wasChanged()) {
            AuditLogger::log('profile.updated', $user, "{$user->name} updated their profile", $old, $data);
        }

        return redirect()->route('profile.show')->with('success', $user->wasChanged() ? 'Profile saved.' : 'Nothing changed.');
    }

    /** Staff photos need no approval: staff accounts are trusted, and the change is recorded. */
    public function uploadPhoto(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048', 'dimensions:min_width=200,min_height=200,max_width=4000,max_height=4000'],
        ], [
            'photo.max' => 'The photo may not be larger than 2 MB.',
            'photo.dimensions' => 'Use a photo at least 200 × 200 pixels.',
        ]);

        $old = $user->photo_path;
        $user->forceFill(['photo_path' => $request->file('photo')->store('staff-photos', 'local')])->save();
        if ($old) {
            Storage::disk('local')->delete($old);
        }
        AuditLogger::log('profile.photo_changed', $user, "{$user->name} changed their profile photo");

        return redirect()->route('profile.show')->with('success', 'Photo updated.');
    }

    public function removePhoto(Request $request)
    {
        $user = $request->user();
        if ($user->photo_path) {
            Storage::disk('local')->delete($user->photo_path);
            $user->forceFill(['photo_path' => null])->save();
            AuditLogger::log('profile.photo_removed', $user, "{$user->name} removed their profile photo");
        }

        return redirect()->route('profile.show')->with('success', 'Photo removed.');
    }

    /** A staff member's photo: any signed-in user may see it (students see their teachers). */
    public function photo(User $user)
    {
        abort_unless(in_array($user->role, ['admin', 'registrar', 'teacher'], true)
            && $user->photo_path && Storage::disk('local')->exists($user->photo_path), 404);

        return Storage::disk('local')->response($user->photo_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    /** "8:00 AM to 9:30 AM" for the class list. */
    public static function time(string $value): string
    {
        return ScheduleChecker::time($value);
    }
}
