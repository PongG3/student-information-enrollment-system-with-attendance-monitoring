<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The staff data privacy policy (the school's internal rules for handling personal data, RA 10173).
 *
 * The administrator (for the Data Protection Officer) edits and publishes it. Once published, every
 * staff account (administrator, registrar, teacher) must agree to the current version before using
 * the system; publishing a new version asks everyone again. Students see the privacy notice instead.
 */
class PrivacyPolicy
{
    public const TEXT = 'privacy_policy_text';
    public const VERSION = 'privacy_policy_version';
    public const STAFF = ['admin', 'registrar', 'teacher'];

    /** The policy text (Markdown); the suggested text until the school writes its own. */
    public static function text(): string
    {
        $text = trim((string) Setting::get(self::TEXT, ''));

        return $text !== '' ? $text : self::suggested();
    }

    /** Safe HTML for display: Markdown with any HTML in it shown as text. */
    public static function html(): string
    {
        return Str::markdown(self::text(), ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }

    /** The published version (a timestamp), or null while it is still a draft. */
    public static function version(): ?string
    {
        $version = trim((string) Setting::get(self::VERSION, ''));

        return $version !== '' ? $version : null;
    }

    public static function published(): bool
    {
        return self::version() !== null;
    }

    /** Must this account agree before going on? */
    public static function needsAgreement(User $user): bool
    {
        return in_array($user->role, self::STAFF, true)
            && self::published()
            && $user->privacy_policy_version !== self::version();
    }

    public static function suggested(): string
    {
        return <<<'MD'
## Purpose
This policy sets the rules every staff member follows when handling personal data in the SIES Campus Portal, as required by the Data Privacy Act of 2012 (Republic Act No. 10173).

## Who may see what
- Administrators and registrar staff may open student records for enrollment, records and reports.
- Teachers may see only the students in their own classes, for attendance.
- Look up a record only when your work needs it. Curiosity is not a reason.

## Using the data
- Use personal data only for school purposes: registration, enrollment, schedules, attendance and contacting guardians.
- Do not copy, photograph or share student information outside the system for personal use.
- Do not give your password to anyone, and do not sign in for someone else.

## Exports, printouts and devices
- Treat exported files (CSV, Excel, PDF) and printed reports as confidential. Keep them only as long as needed, then delete or shred them.
- Lock or sign out of the computer when you leave it. Do not save your password in shared computers.

## Keeping records
- Records of students who leave are archived, not deleted, for as long as school records must be kept.
- Correct wrong information when a student or guardian points it out.

## If something goes wrong
- If data may have leaked (a lost printout, a shared password, a stolen device), report it to the Data Protection Officer at once. The school must notify the National Privacy Commission within 72 hours of learning of a serious breach.

## Questions
Ask the school's Data Protection Officer or the registrar's office.
MD;
    }
}
