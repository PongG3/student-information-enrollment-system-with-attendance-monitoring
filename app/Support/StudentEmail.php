<?php

namespace App\Support;

use App\Models\Student;
use App\Models\User;
use App\Notifications\Notice;
use Illuminate\Support\Facades\URL;

/**
 * One email per student: the address on the student record is also the portal sign-in email and where the
 * system's emails go. This keeps the two copies (students.email and users.email) the same.
 */
final class StudentEmail
{
    /** Is this address free to use for this student's account (no other account signs in with it)? */
    public static function available(string $email, Student $student): bool
    {
        return ! User::whereRaw('lower(email) = ?', [mb_strtolower($email)])
            ->when($student->user_id, fn ($q) => $q->where('id', '!=', $student->user_id))
            ->exists();
    }

    /** Staff changed the email (student record or User Management): apply it to both copies at once. */
    public static function sync(Student $student, ?string $email): void
    {
        $email = $email ? mb_strtolower(trim($email)) : null;
        $student->forceFill(['email' => $email, 'pending_email' => null, 'pending_email_at' => null])->saveQuietly();
        if ($email && $student->user && $student->user->email !== $email && self::available($email, $student)) {
            $student->user->forceFill(['email' => $email])->save();
        }
    }

    /**
     * A student asked for a new email: nothing changes yet. A link goes to the NEW address; opening it proves the
     * inbox is theirs and switches both copies. (A typo can never lock them out of their account.)
     */
    public static function request(Student $student, string $email): void
    {
        $email = mb_strtolower(trim($email));
        $student->forceFill(['pending_email' => $email, 'pending_email_at' => now()])->save();

        Notify::to([$email], new Notice(
            'Confirm your new email',
            [
                'You asked to use this address for your SIES Campus Portal account. Open the link below to confirm it.',
                'After that you sign in with this email, and the portal\'s messages and password-reset links come here.',
                'The link expires in 24 hours. If you did not ask for this, ignore this email; nothing changes.',
            ],
            'Confirm my new email', self::confirmUrl($student), "Hello {$student->first_name},",
        ));
    }

    public static function confirmUrl(Student $student): string
    {
        return URL::temporarySignedRoute('student.email.confirm', now()->addDay(), [
            'student' => $student->id,
            'h' => self::hash($student),
        ]);
    }

    /** Ties the link to the address it was sent to: asking for another address makes older links useless. */
    public static function hash(Student $student): string
    {
        return substr(hash('sha256', $student->id . '|' . $student->pending_email . '|' . config('app.key')), 0, 24);
    }

    /** The link was opened: switch to the new address and tell the old one. */
    public static function confirm(Student $student): void
    {
        $student->loadMissing('user');
        $old = $student->user?->email ?? $student->email;
        $new = $student->pending_email;

        $student->forceFill(['email' => $new, 'pending_email' => null, 'pending_email_at' => null])->save();
        $student->user?->forceFill(['email' => $new])->save();

        AuditLogger::log('student.email_changed', $student, "{$student->full_name} confirmed a new email", ['email' => $old], ['email' => $new]);

        if ($old && $old !== $new) {
            Notify::to([$old], new Notice(
                'Your portal email was changed',
                [
                    "Your SIES Campus Portal account now uses {$new} to sign in and for messages.",
                    'If you did not make this change, contact the registrar\'s office right away.',
                ],
                null, null, "Hello {$student->first_name},",
            ));
        }
    }
}
