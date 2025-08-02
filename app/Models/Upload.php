<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\UploadProcessLog;
use App\Models\ProductPriceHistory;

class Upload extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'original_filename',
        'status',

        // Estadísticas básicas
        'total_products',
        'processed_products',
        'created_products',
        'updated_products',
        'failed_products',
        'skipped_products',

        // Estadísticas de variantes (NUEVO)
        'total_variants_processed',
        'updated_variants',
        'price_changes_recorded',
        'created_variants',
        'updated_variants',
        'failed_variants',

        // Usuario y configuración
        'user_id',
        'shop_code',
        'error_message'
    ];

    protected $casts = [
        'created_variants' => 'integer',
        'updated_variants' => 'integer',
        'failed_variants' => 'integer',
        'total_products' => 'integer',
        'processed_products' => 'integer',
        'created_products' => 'integer',
        'updated_products' => 'integer',
        'failed_products' => 'integer',
        'skipped_products' => 'integer',
        'total_variants_processed' => 'integer',
        'price_changes_recorded' => 'integer',
        'user_id' => 'integer',
    ];

    /**
     * Relación con los logs de procesamiento (NUEVA)
     */
    public function processLogs()
    {
        return $this->hasMany(UploadProcessLog::class);
    }

    /**
     * Relación con el usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope para uploads pendientes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope para uploads en procesamiento
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope para uploads completados
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope para uploads fallidos
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Verifica si el upload está completo
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    /**
     * Verifica si el upload falló
     */
    public function isFailed()
    {
        return $this->status === 'failed';
    }

    /**
     * Verifica si el upload está en procesamiento
     */
    public function isProcessing()
    {
        return $this->status === 'processing';
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

    /**
     * Calcula el porcentaje de progreso de variantes
     */
    public function getVariantsProgressPercentageAttribute()
    {
        if ($this->total_variants_processed == 0) {
            return 0;
        }

        $completed = $this->created_variants + $this->updated_variants + $this->failed_variants;
        return round(($completed / $this->total_variants_processed) * 100, 2);
    }

    /**
     * Estadísticas resumidas para mostrar en UI
     */
    public function getStatisticsAttribute()
    {
        return [
            'products' => [
                'total' => $this->total_products,
                'processed' => $this->processed_products,
                'created' => $this->created_products,
                'updated' => $this->updated_products,
                'failed' => $this->failed_products,
                'skipped' => $this->skipped_products,
                'progress' => $this->progress_percentage
            ],
            'variants' => [
                'total_processed' => $this->total_variants_processed,
                'created' => $this->created_variants,
                'updated' => $this->updated_variants,
                'failed' => $this->failed_variants,
                'progress' => $this->variants_progress_percentage
            ],
            'prices' => [
                'changes_recorded' => $this->price_changes_recorded
            ]
        ];
    }

    /**
     * Actualizar estadísticas de productos
     */
    public function updateProductStats($created = 0, $updated = 0, $failed = 0, $skipped = 0)
    {
        $this->increment('processed_products');

        if ($created > 0)
            $this->increment('created_products', $created);
        if ($updated > 0)
            $this->increment('updated_products', $updated);
        if ($failed > 0)
            $this->increment('failed_products', $failed);
        if ($skipped > 0)
            $this->increment('skipped_products', $skipped);
    }

    /**
     * Actualizar estadísticas de variantes
     */
    public function updateVariantStats($created = 0, $updated = 0, $failed = 0)
    {
        $this->increment('total_variants_processed');

        if ($created > 0)
            $this->increment('created_variants', $created);
        if ($updated > 0)
            $this->increment('updated_variants', $updated);
        if ($failed > 0)
            $this->increment('failed_variants', $failed);
    }

    /**
     * Registrar cambio de precio
     */
    public function recordPriceChange()
    {
        $this->increment('price_changes_recorded');
    }

    /**
     * Marcar como completado
     */
    public function markAsCompleted()
    {
        $this->update(['status' => 'completed']);
    }

    /**
     * Marcar como fallido
     */
    public function markAsFailed($errorMessage = null)
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage
        ]);
    }

    /**
     * Marcar como en procesamiento
     */
    public function markAsProcessing()
    {
        $this->update(['status' => 'processing']);
    }
}