<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The school's name, address and logo, as set in System Settings.
 * Used by the sign-in page and by printed and PDF reports.
 */
final class SchoolBranding
{
    public const LOGO_SETTING = 'school_logo_path';
    public const ADDRESS_SETTING = 'school_address';
    public const PLACEHOLDER_NAMES = ['', 'School', 'School Name', 'Your School'];

    /** Never throws: the sign-in page must load even before the database is ready. */
    private static function setting(string $name): string
    {
        try {
            return trim((string) Setting::get($name, ''));
        } catch (Throwable) {
            return '';
        }
    }

    public static function name(): string
    {
        return self::setting('school_name');
    }

    public static function hasRealName(): bool
    {
        return ! in_array(self::name(), self::PLACEHOLDER_NAMES, true);
    }

    public static function address(): string
    {
        return self::setting(self::ADDRESS_SETTING);
    }

    /** Path of the logo on the private disk, when one is uploaded and still there. */
    public static function logoPath(): ?string
    {
        $path = self::setting(self::LOGO_SETTING);

        return $path !== '' && Storage::disk('local')->exists($path) ? $path : null;
    }

    /** Public address of the logo; changes whenever a new logo is uploaded, so browsers never show an old one. */
    public static function logoUrl(): ?string
    {
        $path = self::logoPath();

        return $path ? route('school.logo', ['v' => substr(md5($path), 0, 8)]) : null;
    }

    /** The logo embedded in the page itself, for PDF files (the PDF maker does not fetch URLs). */
    public static function logoDataUri(): ?string
    {
        $path = self::logoPath();
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('local');

        return 'data:' . $disk->mimeType($path) . ';base64,' . base64_encode($disk->get($path));
    }
}
