<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    /** All settings are kept together under this cache key. */
    public const CACHE_KEY = 'settings.all';

    protected $fillable = ['name', 'value'];

    protected static function booted(): void
    {
        // Any change through the model refreshes the cached copy.
        $forget = fn () => Cache::memo()->forget(self::CACHE_KEY);
        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * Every setting as name => value, loaded with one query and then cached.
     * memo() also keeps it in memory for the rest of the request, so pages that read
     * several settings do not go back to the cache store each time.
     *
     * @return array<string, ?string>
     */
    public static function values(): array
    {
        return Cache::memo()->rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'name')->all());
    }

    public static function get(string $name, ?string $default = null): ?string
    {
        return static::values()[$name] ?? $default;
    }

    public static function put(string $name, ?string $value): void
    {
        static::updateOrCreate(['name' => $name], ['value' => $value]);
    }
}
