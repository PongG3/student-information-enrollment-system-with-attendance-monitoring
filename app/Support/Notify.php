<?php

namespace App\Support;

use App\Models\Student;
use App\Models\User;
use App\Notifications\Notice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Emails sent when something happens to a person (a request decided, an excuse letter decided, a photo
 * rejected, an absence alert, a new password). Sending never stops the action itself: a mail problem
 * is written to the log and the action goes on.
 */
class Notify
{
    /** To the student: their sign-in email, or else the email on their record. */
    public static function student(Student $student, Notice $notice): void
    {
        $email = $student->user?->email ?: $student->email;
        self::to($email ? [$email] : [], $notice->greeting ? $notice : self::greet($notice, $student->first_name));
    }

    /** To every guardian of the student who has an email. */
    public static function guardians(Student $student, Notice $notice): void
    {
        $emails = $student->guardians()->whereNotNull('email')->pluck('email')->all();
        self::to($emails, $notice);
    }

    public static function user(User $user, Notice $notice): void
    {
        self::to([$user->email], $notice->greeting ? $notice : self::greet($notice, strtok($user->name, ' ')));
    }

    /** A new account or a reset password: the temporary password must be changed at the first sign-in. */
    public static function temporaryPassword(User $user, string $password, bool $newAccount): void
    {
        self::user($user, new Notice(
            $newAccount ? 'Your SIES Campus Portal account' : 'Your password was reset',
            [
                $newAccount ? 'An account was made for you.' : 'Your password was reset by the school.',
                "Sign in with: {$user->email}",
                "Temporary password: {$password}",
                'You will be asked to choose your own password when you sign in. If you did not expect this email, tell the registrar\'s office.',
            ],
            'Sign in', route('login'),
        ));
    }

    /** @param  string[]  $emails */
    public static function to(array $emails, Notice $notice): void
    {
        foreach (array_unique(array_filter($emails)) as $email) {
            try {
                Notification::route('mail', $email)->notify($notice);
            } catch (\Throwable $e) {
                Log::warning('Email not sent: ' . $notice->subject, ['to' => $email, 'error' => $e->getMessage()]);
            }
        }
    }

    private static function greet(Notice $notice, string $name): Notice
    {
        $notice->greeting = "Hello {$name},";

        return $notice;
    }
}
