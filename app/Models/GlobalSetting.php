<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlobalSetting extends Model
{
    use HasFactory;
    
    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key, with an optional default.
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Set a setting value by key, creating if it doesn't exist.
     */
    public static function set(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Get all settings as a plain object for easy access in views.
     */
    public static function getSettings(): object
    {
        return (object) [
            'enable_new_registrations' => (bool) static::get('enable_new_registrations', true),
            'base_delivery_fee'        => (float) static::get('base_delivery_fee', 50.0),
        ];
    }
}
