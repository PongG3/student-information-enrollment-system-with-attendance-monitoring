<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Sign-in accounts for students, handled from the student's own record by the registrar
 * or administrator. Archived students cannot be changed (their record is read-only).
 */
class StudentAccountController extends Controller
{
    public function store(Request $request, Student $student)
    {
        if ($student->user_id) {
            return back()->with('error', 'This student already has an account.');
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $password = Str::password(12, true, true, false, false);

        $user = DB::transaction(function () use ($student, $data, $password) {
            $user = new User();
            $user->forceFill([
                'name' => trim($student->first_name . ' ' . $student->last_name),
                'email' => strtolower($data['email']),
                'role' => 'student',
                'password' => Hash::make($password),
                'is_active' => true,
                'must_change_password' => true,
                'email_verified_at' => now(),
            ])->save();

            // One email per student: the record takes the sign-in email too.
            $student->forceFill(['user_id' => $user->id, 'email' => $user->email, 'pending_email' => null, 'pending_email_at' => null])->save();

            return $user;
        });

        AuditLogger::log('student.account_created', $student, "Created portal account for {$student->full_name} ({$student->student_number})", null, [
            'email' => $user->email,
        ]);

        Notify::temporaryPassword($user, true);

        return redirect()->route('students.show', $student)
            ->with('success', 'Portal account created. A link to choose a password was emailed; give the student the temporary password below if they need to sign in now.')
            ->with('temp_password', $password)
            ->with('temp_for', $user->email);
    }

    public function resetPassword(Request $request, Student $student)
    {
        $user = $this->accountOf($student);

        $password = Str::password(12, true, true, false, false);
        $user->forceFill(['password' => Hash::make($password), 'must_change_password' => true])->save();
        $this->endSessions($user);

        AuditLogger::log('student.account_password_reset', $student, "Reset portal password of {$student->full_name} ({$student->student_number})");

        Notify::temporaryPassword($user, false);

        return redirect()->route('students.show', $student)
            ->with('success', 'Password reset. A link to choose a new password was emailed; give the student the temporary password below if they need to sign in now.')
            ->with('temp_password', $password)
            ->with('temp_for', $user->email);
    }

    public function toggleStatus(Request $request, Student $student)
    {
        $user = $this->accountOf($student);
        $user->forceFill(['is_active' => ! $user->is_active])->save();

        if (! $user->is_active) {
            $this->endSessions($user);
        }

        AuditLogger::log(
            $user->is_active ? 'student.account_activated' : 'student.account_deactivated',
            $student,
            ($user->is_active ? 'Activated' : 'Deactivated') . " portal account of {$student->full_name} ({$student->student_number})",
        );

        return redirect()->route('students.show', $student)
            ->with('success', $user->is_active ? 'Account activated.' : 'Account deactivated.');
    }

    private function accountOf(Student $student): User
    {
        return User::where('role', 'student')->findOrFail($student->user_id);
    }

    /** Sign the student out of every device. */
    private function endSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
    }
}
