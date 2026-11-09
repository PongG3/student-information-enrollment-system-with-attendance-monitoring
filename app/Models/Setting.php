<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['name', 'value'];

    public static function get(string $name, ?string $default = null): ?string
    {
        $setting = static::where('name', $name)->first();

        return $setting?->value ?? $default;
    }

    public static function put(string $name, ?string $value): void
    {
        static::updateOrCreate(['name' => $name], ['value' => $value]);
    }
}
