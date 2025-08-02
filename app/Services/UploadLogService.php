<?php

namespace App\Services;

use App\Models\UploadProcessLog;
use App\Models\Upload;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UploadLogService
{
    /**
     * 🔥 CREAR LOG DE PROCESAMIENTO
     */
    public function createProcessLog($uploadId, $variantId, $action, $status = 'pending', $rowNumber = null, $errorMessage = null)
    {
        $log = UploadProcessLog::create([
            'upload_id' => $uploadId,
            'product_variant_id' => $variantId,
            'action' => $action,
            'status' => $status,
            'row_number' => $rowNumber,
            'error_message' => $errorMessage
        ]);

        Log::debug("Log de procesamiento creado", [
            'log_id' => $log->id,
            'upload_id' => $uploadId,
            'variant_id' => $variantId,
            'action' => $action,
            'status' => $status
        ]);

        return $log;
    }

    /**
     * 🔥 OBTENER LOGS POR UPLOAD
     */
    public function getLogsByUpload($uploadId)
    {
        return UploadProcessLog::where('upload_id', $uploadId)
            ->with(['productVariant.product', 'upload'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 🔥 OBTENER LOGS PENDIENTES
     */
    public function getPendingLogs($uploadId, $limit = null)
    {
        $query = UploadProcessLog::where('upload_id', $uploadId)
            ->where('status', 'pending')
            ->with(['productVariant']);

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * 🔥 OBTENER LOGS FALLIDOS
     */
    public function getFailedLogs($uploadId)
    {
        return UploadProcessLog::where('upload_id', $uploadId)
            ->where('status', 'failed')
            ->with(['productVariant.product'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 🔥 MARCAR LOGS COMO EXITOSOS
     */
    public function markAsSuccessful($logIds)
    {
        $updatedCount = UploadProcessLog::whereIn('id', $logIds)
            ->where('status', 'pending')
            ->update(['status' => 'success']);

        Log::info("Logs marcados como exitosos", [
            'log_ids_count' => count($logIds),
            'updated_count' => $updatedCount
        ]);

        return $updatedCount;
    }

    /**
     * 🔥 MARCAR LOGS COMO FALLIDOS
     */
    public function markAsFailed($logIds, $errorMessage = null)
    {
        $updatedCount = UploadProcessLog::whereIn('id', $logIds)
            ->where('status', 'pending')
            ->update([
                'status' => 'failed',
                'error_message' => $errorMessage
            ]);

        Log::error("Logs marcados como fallidos", [
            'log_ids_count' => count($logIds),
            'updated_count' => $updatedCount,
            'error_message' => $errorMessage
        ]);

        return $updatedCount;
    }

    /**
     * 🔥 MARCAR LOGS POR VARIANTES COMO EXITOSOS
     */
    public function markVariantsAsSuccessful($uploadId, $variantIds)
    {
        $updatedCount = UploadProcessLog::where('upload_id', $uploadId)
            ->whereIn('product_variant_id', $variantIds)
            ->where('status', 'pending')
            ->update(['status' => 'success']);

        Log::info("Logs de variantes marcados como exitosos", [
            'upload_id' => $uploadId,
            'variant_ids_count' => count($variantIds),
            'updated_count' => $updatedCount
        ]);

        return $updatedCount;
    }

    /**
     * 🔥 MARCAR LOGS POR VARIANTES COMO FALLIDOS
     */
    public function markVariantsAsFailed($uploadId, $variantIds, $errorMessage = null)
    {
        $updatedCount = UploadProcessLog::where('upload_id', $uploadId)
            ->whereIn('product_variant_id', $variantIds)
            ->where('status', 'pending')
            ->update([
                'status' => 'failed',
                'error_message' => $errorMessage
            ]);

        Log::error("Logs de variantes marcados como fallidos", [
            'upload_id' => $uploadId,
            'variant_ids_count' => count($variantIds),
            'updated_count' => $updatedCount,
            'error_message' => $errorMessage
        ]);

        return $updatedCount;
    }

    /**
     * 🔥 OBTENER ESTADÍSTICAS DE UPLOAD
     */
    public function getUploadStats($uploadId)
    {
        $logs = UploadProcessLog::where('upload_id', $uploadId)->get();

        $stats = [
            'total_logs' => $logs->count(),
            'created' => $logs->where('action', 'created')->count(),
            'updated' => $logs->where('action', 'updated')->count(),
            'skipped' => $logs->where('action', 'skipped')->count(),
            'success' => $logs->where('status', 'success')->count(),
            'failed' => $logs->where('status', 'failed')->count(),
            'pending' => $logs->where('status', 'pending')->count(),
            'success_rate' => 0,
            'error_rate' => 0
        ];

        $processedTotal = $stats['success'] + $stats['failed'];
        if ($processedTotal > 0) {
            $stats['success_rate'] = round(($stats['success'] / $processedTotal) * 100, 2);
            $stats['error_rate'] = round(($stats['failed'] / $processedTotal) * 100, 2);
        }

        return $stats;
    }

    /**
     * 🔥 OBTENER ERRORES MÁS COMUNES
     */
    public function getCommonErrors($uploadId = null, $limit = 10)
    {
        $query = UploadProcessLog::where('status', 'failed')
            ->whereNotNull('error_message');

        if ($uploadId) {
            $query->where('upload_id', $uploadId);
        }

        return $query
            ->select('error_message', DB::raw('COUNT(*) as count'))
            ->groupBy('error_message')
            ->orderBy('count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 🔥 REINTENTAR LOGS FALLIDOS
     */
    public function retryFailedLogs($uploadId, $limitRetries = null)
    {
        $query = UploadProcessLog::where('upload_id', $uploadId)
            ->where('status', 'failed');

        if ($limitRetries) {
            $query->limit($limitRetries);
        }

        $failedLogs = $query->get();
        
        if ($failedLogs->isEmpty()) {
            Log::info("No hay logs fallidos para reintentar", ['upload_id' => $uploadId]);
            return 0;
        }

        $retryCount = $failedLogs->count();
        
        // Marcar como pendientes para reintento
        UploadProcessLog::whereIn('id', $failedLogs->pluck('id'))
            ->update([
                'status' => 'pending',
                'error_message' => null
            ]);

        Log::info("Logs fallidos marcados para reintento", [
            'upload_id' => $uploadId,
            'retry_count' => $retryCount
        ]);

        return $retryCount;
    }

    /**
     * 🔥 OBTENER PROGRESO DE PROCESAMIENTO
     */
    public function getProcessingProgress($uploadId)
    {
        $upload = Upload::find($uploadId);
        
        if (!$upload) {
            throw new \Exception("Upload no encontrado: {$uploadId}");
        }

        $logs = UploadProcessLog::where('upload_id', $uploadId)->get();
        $totalProducts = $upload->total_products ?? $logs->count();

        $processed = $logs->whereIn('status', ['success', 'failed'])->count();
        $pending = $logs->where('status', 'pending')->count();
        $success = $logs->where('status', 'success')->count();
        $failed = $logs->where('status', 'failed')->count();

        $progress = [
            'upload_id' => $uploadId,
            'upload_status' => $upload->status,
            'total_products' => $totalProducts,
            'processed' => $processed,
            'pending' => $pending,
            'success' => $success,
            'failed' => $failed,
            'progress_percentage' => $totalProducts > 0 ? round(($processed / $totalProducts) * 100, 2) : 0,
            'success_rate' => $processed > 0 ? round(($success / $processed) * 100, 2) : 0,
            'is_complete' => $pending === 0 && $processed === $totalProducts,
            'has_errors' => $failed > 0
        ];

        return $progress;
    }

    /**
     * 🔥 OBTENER RESUMEN DE ERRORES DETALLADO
     */
    public function getDetailedErrorSummary($uploadId)
    {
        $failedLogs = UploadProcessLog::where('upload_id', $uploadId)
            ->where('status', 'failed')
            ->with(['productVariant'])
            ->get();

        $errorGroups = $failedLogs->groupBy('error_message');
        
        $summary = [];
        foreach ($errorGroups as $errorMessage => $logs) {
            $summary[] = [
                'error_message' => $errorMessage,
                'count' => $logs->count(),
                'percentage' => round(($logs->count() / $failedLogs->count()) * 100, 2),
                'sample_variants' => $logs->take(3)->map(function($log) {
                    return [
                        'variant_id' => $log->product_variant_id,
                        'codigo_interno' => $log->productVariant?->codigo_interno,
                        'descripcion' => $log->productVariant?->descripcion,
                        'row_number' => $log->row_number
                    ];
                })->toArray()
            ];
        }

        // Ordenar por cantidad de errores
        usort($summary, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        return [
            'upload_id' => $uploadId,
            'total_errors' => $failedLogs->count(),
            'unique_error_types' => count($summary),
            'error_groups' => $summary
        ];
    }

    /**
     * 🔥 LIMPIAR LOGS ANTIGUOS
     */
    public function cleanOldLogs($diasAntiguedad = 90, $dry_run = true)
    {
        $fechaLimite = Carbon::now()->subDays($diasAntiguedad);
        
        $query = UploadProcessLog::whereHas('upload', function($q) use ($fechaLimite) {
            $q->where('created_at', '<', $fechaLimite);
        });
        
        $count = $query->count();
        
        Log::info("Logs antiguos encontrados", [
            'logs_antiguos' => $count,
            'fecha_limite' => $fechaLimite->format('Y-m-d H:i:s'),
            'dry_run' => $dry_run
        ]);
        
        if (!$dry_run && $count > 0) {
            $deleted = $query->delete();
            Log::warning("Logs antiguos eliminados", [
                'logs_eliminados' => $deleted
            ]);
            return $deleted;
        }
        
        return $count;
    }

    /**
     * 🔥 EXPORTAR LOGS A CSV
     */
    public function exportLogsToCSV($uploadId, $status = null)
    {
        $query = UploadProcessLog::where('upload_id', $uploadId)
            ->with(['productVariant.product', 'upload']);

        if ($status) {
            $query->where('status', $status);
        }

        $logs = $query->orderBy('created_at')->get();

        $csvData = [];
        $csvData[] = [
            'Log ID',
            'Variant ID',
            'Código Interno',
            'Descripción',
            'Código Barras',
            'Acción',
            'Estado',
            'Fila Excel',
            'Error',
            'Fecha Procesamiento'
        ];

        foreach ($logs as $log) {
            $variant = $log->productVariant;
            $csvData[] = [
                $log->id,
                $log->product_variant_id,
                $variant?->codigo_interno ?? 'N/A',
                $variant?->descripcion ?? 'N/A',
                $variant?->cod_barras ?? 'N/A',
                $log->action,
                $log->status,
                $log->row_number ?? 'N/A',
                $log->error_message ?? '',
                $log->created_at->format('Y-m-d H:i:s')
            ];
        }

        return $csvData;
    }
}