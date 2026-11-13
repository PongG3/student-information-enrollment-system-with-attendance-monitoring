<?php

namespace App\Support;

use App\Models\Setting;

/** The school's Data Protection Officer (System Settings), for the privacy notice and data exports. */
class PrivacyContact
{
    public static function name(): string
    {
        return self::get('dpo_name');
    }

    public static function email(): string
    {
        return self::get('dpo_email');
    }

    /** "Name (email)", "Name", "email", or "" when nothing is set. */
    public static function line(): string
    {
        return trim(self::name() . (self::email() ? ' (' . self::email() . ')' : ''));
    }

    private static function get(string $key): string
    {
        try {
            return trim((string) Setting::get($key, ''));
        } catch (\Throwable $e) {
            return '';   // the sign-in page must load even before migrations
        }
    }
}
