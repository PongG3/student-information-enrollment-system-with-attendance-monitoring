<?php

namespace App\Support;

use App\Models\Setting;
use Throwable;

/**
 * How long a signed-in person may stay idle before being signed out.
 * Set by the administrator in System Settings; SESSION_LIFETIME in .env is the fallback.
 */
final class SessionTimeout
{
    public const SETTING = 'session_lifetime_minutes';
    public const MIN = 5;
    public const MAX = 480;

    public static function minutes(): int
    {
        $fallback = (int) config('session.lifetime', 120);

        try {
            $value = Setting::get(self::SETTING);
        } catch (Throwable) {
            return $fallback; // database not ready yet (fresh install, migrations)
        }

        if ($value === null || ! ctype_digit($value)) {
            return $fallback;
        }

        return max(self::MIN, min(self::MAX, (int) $value));
    }
}
