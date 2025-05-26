<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'original_filename',
        'status',
        'total_products',
        'processed_products',
        'created_products',
        'updated_products',
        'failed_products',
        'skipped_products',
        'user_id',
        'shop_code',
        'error_message'
    ];

    /**
     * Relación con los logs de productos
     */
    public function productLogs()
    {
        return $this->hasMany(ProductUpdateLog::class);
    }

    /**
     * Scope para uploads pendientes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Verifica si el upload está completo
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    /**
     * Calcula el porcentaje de progreso
     */
    public function getProgressPercentageAttribute()
    {
        if ($this->total_products == 0) {
            return 0;
        }
        
        return round(($this->processed_products / $this->total_products) * 100, 2);
    }
}