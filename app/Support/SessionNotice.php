<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Tells "your session timed out" apart from an ordinary visit to the sign-in page.
 *
 * Signing in sets a small cookie that outlives the session; signing out removes it.
 * If the cookie is still there when the sign-in page opens, the person never signed out:
 * their session ran out (idle timeout) and the notice is shown, once.
 */
final class SessionNotice
{
    public const COOKIE = 'sies_was_signed_in';

    /** How long the browser keeps the marker: longer than any session may last. */
    private const MINUTES = 60 * 24 * 7;

    public static function signedIn(): void
    {
        Cookie::queue(self::COOKIE, '1', self::MINUTES, null, null, null, true, false, 'lax');
    }

    public static function signedOut(): void
    {
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    /** True once after a session ended without signing out; the marker is cleared. */
    public static function sessionEnded(Request $request): bool
    {
        if (! $request->cookies->has(self::COOKIE)) {
            return false;
        }

        self::signedOut();

        return true;
    }
}
