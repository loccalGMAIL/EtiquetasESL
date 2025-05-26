<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'description',
        'type'
    ];

    /**
     * Obtener el valor de una configuración
     */
    public static function get($key, $default = null)
    {
        return Cache::remember("setting_{$key}", 3600, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            
            if (!$setting) {
                return $default;
            }

            // Convertir según el tipo
            return self::castValue($setting->value, $setting->type);
        });
    }

    /**
     * Establecer el valor de una configuración
     */
    public static function set($key, $value)
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        Cache::forget("setting_{$key}");
        
        return $setting;
    }

    /**
     * Convertir valor según su tipo
     */
    private static function castValue($value, $type)
    {
        switch ($type) {
            case 'integer':
                return (int) $value;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                return json_decode($value, true);
            case 'float':
                return (float) $value;
            default:
                return $value;
        }
    }

    /**
     * Limpiar caché al actualizar
     */
    protected static function booted()
    {
        static::saved(function ($setting) {
            Cache::forget("setting_{$setting->key}");
        });

        static::deleted(function ($setting) {
            Cache::forget("setting_{$setting->key}");
        });
    }

    /**
     * Obtener todas las configuraciones como array
     */
    public static function getAllAsArray()
    {
        return Cache::remember('all_settings', 3600, function () {
            $settings = [];
            $allSettings = self::query()->get(); // Cambiado aquí también
            
            foreach ($allSettings as $setting) {
                $settings[$setting->key] = self::castValue($setting->value, $setting->type);
            }
            
            return $settings;
        });
    }
}