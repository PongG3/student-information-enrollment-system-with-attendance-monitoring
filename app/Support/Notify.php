<?php

namespace App\Support;

use App\Models\Student;
use App\Models\User;
use App\Notifications\Notice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

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

    /**
     * A new account or a reset password. The email never carries a password: it has a link, good for 3 days and
     * for one use, where the person chooses their own. (The temporary password is handed over in person.)
     */
    public static function temporaryPassword(User $user, bool $newAccount): void
    {
        self::user($user, new Notice(
            $newAccount ? 'Your SIES Campus Portal account' : 'Your password was reset',
            [
                $newAccount ? 'An account was made for you.' : 'Your password was reset by the school.',
                "Your sign-in email is {$user->email}.",
                'Use the button below to choose your password. The link works once and expires in 3 days.',
                'If you did not expect this email, tell the registrar\'s office.',
            ],
            'Choose my password', self::setPasswordUrl($user),
        ));
    }

    /** "Forgot password": a link good for one hour and one use. */
    public static function passwordResetLink(User $user): void
    {
        self::user($user, new Notice(
            'Reset your password',
            [
                'Someone asked to reset the password of your SIES Campus Portal account. If it was you, use the button below.',
                'The link works once and expires in 60 minutes.',
                'If you did not ask for this, ignore this email: your password stays the same. Tell the registrar\'s office if it keeps happening.',
            ],
            'Choose a new password', self::setPasswordUrl($user, 60),
        ));
    }

    /** After any password change by link: so the owner notices a change they did not make. */
    public static function passwordChanged(User $user): void
    {
        self::user($user, new Notice(
            'Your password was changed',
            [
                'The password of your SIES Campus Portal account was just changed, and you were signed out on your other devices.',
                'If this was you, there is nothing to do.',
                'If it was not you, contact the registrar\'s office right away so they can lock the account.',
            ],
            'Sign in', route('login'),
        ));
    }

    /** A signed link tied to the password the account has now, so it stops working once the password changes. */
    public static function setPasswordUrl(User $user, int $minutes = 60 * 24 * 3): string
    {
        return URL::temporarySignedRoute('password.set', now()->addMinutes($minutes), [
            'user' => $user->id,
            'v' => self::fingerprint($user),
        ]);
    }

    public static function fingerprint(User $user): string
    {
        return substr(hash('sha256', (string) $user->password), 0, 20);
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
