<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Upload;
use App\Models\ProductVariant;

class UploadProcessLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'upload_id',
        'product_variant_id',
        'action',
        'status',
        'price_changed',
        'barcode_changed',
        'error_message'
    ];

    protected $casts = [
        'upload_id' => 'integer',
        'product_variant_id' => 'integer',
        'price_changed' => 'boolean',
        'barcode_changed' => 'boolean'
    ];

    /**
     * Relación con el upload
     */
    public function upload()
    {
        return $this->belongsTo(Upload::class);
    }

    /**
     * Relación con la variante de producto procesada
     */
    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Crear log de procesamiento
     */
    public static function createProcessLog($uploadId, $productVariantId, $action, $priceChanged = false, $barcodeChanged = false, $errorMessage = null)
    {
        return self::create([
            'upload_id' => $uploadId,
            'product_variant_id' => $productVariantId,
            'action' => $action,
            'status' => $errorMessage ? 'failed' : 'success',
            'price_changed' => $priceChanged,
            'barcode_changed' => $barcodeChanged,
            'error_message' => $errorMessage
        ]);
    }

    /**
     * Marcar como exitoso
     */
    public function markAsSuccess()
    {
        $this->update([
            'status' => 'success',
            'error_message' => null
        ]);
    }

    /**
     * Marcar como fallido
     */
    public function markAsFailed($errorMessage)
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage
        ]);
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
     * Scope para logs pendientes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope para variantes creadas
     */
    public function scopeCreated($query)
    {
        return $query->where('action', 'created');
    }

    /**
     * Scope para variantes actualizadas
     */
    public function scopeUpdated($query)
    {
        return $query->where('action', 'updated');
    }

    /**
     * Scope para variantes omitidas
     */
    public function scopeSkipped($query)
    {
        return $query->where('action', 'skipped');
    }

    /**
     * Scope para logs con cambios de precio
     */
    public function scopeWithPriceChanges($query)
    {
        return $query->where('price_changed', true);
    }

    /**
     * Scope para logs con cambios de código de barras
     */
    public function scopeWithBarcodeChanges($query)
    {
        return $query->where('barcode_changed', true);
    }

    /**
     * Scope para logs por upload
     */
    public function scopeByUpload($query, $uploadId)
    {
        return $query->where('upload_id', $uploadId);
    }

    /**
     * Verificar si fue exitoso
     */
    public function isSuccessful()
    {
        return $this->status === 'success';
    }

    /**
     * Verificar si falló
     */
    public function isFailed()
    {
        return $this->status === 'failed';
    }

    /**
     * Verificar si está pendiente
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Obtener resumen de estadísticas por upload
     */
    public static function getUploadStatistics($uploadId)
    {
        $logs = self::where('upload_id', $uploadId)->get();

        return [
            'total' => $logs->count(),
            'successful' => $logs->where('status', 'success')->count(),
            'failed' => $logs->where('status', 'failed')->count(),
            'pending' => $logs->where('status', 'pending')->count(),
            'actions' => [
                'created' => $logs->where('action', 'created')->count(),
                'updated' => $logs->where('action', 'updated')->count(),
                'skipped' => $logs->where('action', 'skipped')->count()
            ],
            'changes' => [
                'price_changes' => $logs->where('price_changed', true)->count(),
                'barcode_changes' => $logs->where('barcode_changed', true)->count()
            ]
        ];
    }

    /**
     * Obtener logs detallados para un upload con información de variantes
     */
    public static function getDetailedLogsForUpload($uploadId)
    {
        return self::with(['productVariant.product'])
                   ->where('upload_id', $uploadId)
                   ->orderBy('created_at', 'desc')
                   ->get()
                   ->map(function ($log) {
                       return [
                           'log_id' => $log->id,
                           'variant_id' => $log->productVariant->id,
                           'goodsCode' => $log->productVariant->id, // Para eRetail
                           'codigo_interno' => $log->productVariant->codigo_interno,
                           'cod_barras' => $log->productVariant->cod_barras,
                           'descripcion' => $log->productVariant->descripcion,
                           'precio_actual' => $log->productVariant->product->precio_actual ?? 0,
                           'action' => $log->action,
                           'status' => $log->status,
                           'price_changed' => $log->price_changed,
                           'barcode_changed' => $log->barcode_changed,
                           'error_message' => $log->error_message,
                           'processed_at' => $log->created_at
                       ];
                   });
    }

    /**
     * Obtener errores agrupados por tipo
     */
    public static function getErrorSummary($uploadId)
    {
        $failedLogs = self::where('upload_id', $uploadId)
                          ->where('status', 'failed')
                          ->get();

        $errorGroups = $failedLogs->groupBy('error_message')
                                  ->map(function ($group, $error) {
                                      return [
                                          'error' => $error,
                                          'count' => $group->count(),
                                          'variants' => $group->pluck('product_variant_id')->toArray()
                                      ];
                                  });

        return $errorGroups->values();
    }

    /**
     * Obtener información completa del log para debugging
     */
    public function getDebugInfo()
    {
        return [
            'log_id' => $this->id,
            'upload_id' => $this->upload_id,
            'variant_info' => $this->productVariant ? $this->productVariant->getDebugInfo() : null,
            'action' => $this->action,
            'status' => $this->status,
            'changes' => [
                'price_changed' => $this->price_changed,
                'barcode_changed' => $this->barcode_changed
            ],
            'error_message' => $this->error_message,
            'timestamps' => [
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at
            ]
        ];
    }
}