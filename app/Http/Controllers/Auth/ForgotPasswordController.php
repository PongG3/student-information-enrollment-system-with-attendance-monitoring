<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Notify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * "Forgot password": one field, a student number or an email.
 *  - Student number: the link goes to that student's email (their one email: sign-in and messages, confirmed
 *    from its inbox when they change it).
 *  - Email: the link goes to the account that signs in with it (teachers and staff; students may use it too).
 * The link works once and expires in 60 minutes. Only the owner can open their inbox, so knowing someone's
 * student number is not enough. The answer is always the same and never shows any part of an email.
 */
class ForgotPasswordController extends Controller
{
    /** Reset emails per account in 15 minutes (more are silently skipped). */
    private const PER_ACCOUNT = 3;

    public function create()
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ], [
            'identifier.required' => 'Enter your student number, or your email if you are a teacher or staff member.',
        ]);

        $user = $this->findAccount(trim($data['identifier']));

        if ($user) {
            $key = 'forgot-password:' . $user->id;
            if (! RateLimiter::tooManyAttempts($key, self::PER_ACCOUNT)) {
                RateLimiter::hit($key, 15 * 60);
                // Sent after the response, so a real account is not slower to answer than an unknown one.
                \Illuminate\Support\defer(fn () => Notify::passwordResetLink($user));
                AuditLogger::log('password.reset_requested', $user, "Password reset link requested for {$user->name}");
            }
        }

        return redirect()->route('password.request')->with('status',
            'If that matches an account, we sent a reset link to its email. It works once and expires in 60 minutes. Check your spam folder too.');
    }

    /** The active account for a student number or an email, if any. */
    private function findAccount(string $identifier): ?User
    {
        if (str_contains($identifier, '@')) {
            return User::where('email', mb_strtolower($identifier))->where('is_active', true)->first();
        }

        $student = Student::whereRaw('lower(student_number) = ?', [mb_strtolower($identifier)])->with('user')->first();

        return $student?->user && $student->user->is_active && $student->user->role === 'student' ? $student->user : null;
    }
}
