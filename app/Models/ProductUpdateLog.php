<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductUpdateLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'upload_id',
        'cod_barras',
        'codigo',
        'descripcion',
        'precio_final',
        'precio_calculado',
        'precio_anterior_eretail',
        'fec_ul_mo',
        'action',
        'status',
        'skip_reason',
        'error_message'
    ];

    protected $casts = [
        'fec_ul_mo' => 'datetime',
        'precio_final' => 'decimal:2',
        'precio_calculado' => 'decimal:2',
        'precio_anterior_eretail' => 'decimal:2'
    ];

    /**
     * Relación con el upload
     */
    public function upload()
    {
        return $this->belongsTo(Upload::class);
    }

    /**
     * Scope para logs exitosos
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope para logs fallidos
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope para productos creados
     */
    public function scopeCreated($query)
    {
        return $query->where('action', 'created');
    }

    /**
     * Scope para productos actualizados
     */
    public function scopeUpdated($query)
    {
        return $query->where('action', 'updated');
    }

    /**
     * Calcula el porcentaje de descuento aplicado
     */
    public function getDiscountPercentageAttribute()
    {
        if ($this->precio_final == 0) {
            return 0;
        }
        
        return round((($this->precio_final - $this->precio_calculado) / $this->precio_final) * 100, 2);
    }
}