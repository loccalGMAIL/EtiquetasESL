<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductLastUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'cod_barras',
        'codigo',
        'last_update_date',
        'last_price',
        'last_description',
        'last_upload_id'
    ];

    protected $casts = [
        'last_update_date' => 'datetime',
        'last_price' => 'decimal:2'
    ];

    /**
     * Relación con el último upload
     */
    public function lastUpload()
    {
        return $this->belongsTo(Upload::class, 'last_upload_id');
    }

    /**
     * Obtener o crear registro para un producto
     */
    public static function findOrCreateByCodBarras($codBarras)
    {
        return self::firstOrCreate(
            ['cod_barras' => $codBarras],
            ['last_update_date' => now()]
        );
    }

    /**
     * Verificar si un producto necesita actualización
     */
    public function needsUpdate($newDate)
    {
        if (!$this->last_update_date) {
            return true;
        }

        return $newDate > $this->last_update_date;
    }

    /**
     * Actualizar información del producto
     */
    public function updateFromLog(ProductUpdateLog $log)
    {
        $this->update([
            'last_update_date' => $log->fec_ul_mo,
            'last_price' => $log->precio_calculado,
            'last_description' => $log->descripcion,
            'last_upload_id' => $log->upload_id
        ]);
    }

    /**
     * Scope para productos no actualizados en X días
     */
    public function scopeNotUpdatedInDays($query, $days)
    {
        return $query->where('last_update_date', '<', now()->subDays($days));
    }

    /**
     * Buscar producto por codigo interno y descripcion
     */
    public static function findByCodigoAndDescription($codigo, $descripcion)
    {
        return self::where('codigo', $codigo)
            ->where('last_description', $descripcion)
            ->first();
    }

    /**
     * Verificar si el código de barras cambió
     */
    public function hasBarCodeChanged($newBarCode)
    {
        return $this->cod_barras !== $newBarCode;
    }
}