<?php

namespace App\Support;

/**
 * Builds search patterns for SQL LIKE.
 *
 * The value is always sent to the database as a bound parameter, so it can never become SQL.
 * On top of that, the LIKE wildcards % and _ (and the escape character \) in what the user typed
 * are escaped, so searching for "%" looks for a real percent sign instead of matching everything.
 */
final class Like
{
    /** Pattern matching values that contain the text anywhere. */
    public static function contains(?string $text): string
    {
        return '%' . self::escape(trim((string) $text)) . '%';
    }

    /** The text with % _ and \ made literal. */
    public static function escape(string $text): string
    {
        return addcslashes($text, '%_\\');
    }
}
