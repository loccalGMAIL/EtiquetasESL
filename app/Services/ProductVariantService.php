<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\UploadProcessLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class ProductVariantService
{
    /**
     * 🔥 CREAR O ACTUALIZAR VARIANTE - SIN HISTÓRICO DE PRECIOS
     */
    public function createOrUpdateVariant($variantData, $uploadId = null)
    {
        DB::beginTransaction();

        try {
            // Validar datos requeridos
            $this->validateVariantData($variantData);

            // 1. Buscar o crear PRODUCTO MAESTRO
            $product = $this->ensureProductExists($variantData);

            // 2. Buscar o crear VARIANTE
            $variant = ProductVariant::firstOrCreate(
                [
                    'codigo_interno' => $variantData['codigo_interno'],
                    'descripcion' => $variantData['descripcion']
                ],
                [
                    'product_id' => $product->id,
                    'cod_barras' => $variantData['cod_barras'] ?? null,
                    'is_active' => true
                ]
            );

            $action = $variant->wasRecentlyCreated ? 'created' : 'updated';

            // 3. Si es actualización, actualizar precios directamente (SIN HISTÓRICO)
            // if (!$variant->wasRecentlyCreated) {
            //     $variant->update([
            //         'precio_final' => $variantData['precio_final'],
            //         'precio_calculado' => $variantData['precio_calculado'] ?? $variantData['precio_final'],
            //         'fec_ul_mo' => $variantData['fec_ul_mo'] ?? now()
            //     ]);
            // }
            $variant = ProductVariant::firstOrCreate(
                [
                    'codigo_interno' => $variantData['codigo_interno'],
                    'descripcion' => $variantData['descripcion']
                ],
                [
                    'product_id' => $product->id,
                    'cod_barras' => $variantData['cod_barras'] ?? null,
                    'is_active' => true
                ]
            );

            // 4. Actualizar precio del producto maestro
            // $product->update(['precio_actual' => $variantData['precio_final']]);
            $product->update([
                'precio_final' => $variantData['precio_final'],
                'precio_calculado' => $variantData['precio_calculado'] ?? $variantData['precio_final'],
                'last_price_update' => now()
            ]);

            DB::commit();

            Log::info("Variante procesada exitosamente", [
                'variant_id' => $variant->id,
                'codigo_interno' => $variantData['codigo_interno'],
                'action' => $action,
                'precio_final' => $variantData['precio_final']
            ]);

            return [
                'variant' => $variant,
                'action' => $action,
                'product' => $product
            ];

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Error procesando variante", [
                'codigo_interno' => $variantData['codigo_interno'] ?? 'N/A',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 🔥 BUSCAR VARIANTES POR CÓDIGO DE BARRAS
     */
    public function findVariantsByBarcode($codBarras)
    {
        return ProductVariant::where('cod_barras', $codBarras)
            ->with(['product'])
            ->get();
    }

    /**
     * 🔥 BUSCAR VARIANTES POR CÓDIGO INTERNO
     */
    public function findVariantsByCodigoInterno($codigoInterno)
    {
        return ProductVariant::where('codigo_interno', $codigoInterno)
            ->with(['product'])
            ->get();
    }

    /**
     * 🔥 BUSCAR VARIANTE ESPECÍFICA
     */
    public function findVariant($codigoInterno, $descripcion)
    {
        return ProductVariant::where('codigo_interno', $codigoInterno)
            ->where('descripcion', $descripcion)
            ->with(['product'])
            ->first();
    }

    /**
     * 🔥 OBTENER VARIANTES PARA eRETAIL (con logs pendientes)
     */
    public function getVariantsForERetail($uploadId, $limit = 50)
    {
        return ProductVariant::whereHas('uploadProcessLogs', function ($query) use ($uploadId) {
            $query->where('upload_id', $uploadId)
                ->where('status', 'pending');
        })
            ->with([
                'uploadProcessLogs' => function ($query) use ($uploadId) {
                    $query->where('upload_id', $uploadId)
                        ->where('status', 'pending');
                }
            ])
            ->limit($limit)
            ->get();
    }

    /**
     * 🔥 ACTUALIZAR PRECIOS DE VARIANTES DEL MISMO PRODUCTO - SIN HISTÓRICO
     */
    public function updateProductVariantsPrices($codigoInterno, $nuevoPrecio, $uploadId = null)
    {
        $variants = $this->findVariantsByCodigoInterno($codigoInterno);

        $updatedCount = 0;

        foreach ($variants as $variant) {
            $precioAnterior = $variant->precio_final;

            if ($precioAnterior != $nuevoPrecio) {
                // Actualizar precio directamente (sin histórico)
                $variant->update([
                    'precio_final' => $nuevoPrecio,
                    'precio_calculado' => $nuevoPrecio // o aplicar descuento si es necesario
                ]);

                $updatedCount++;

                Log::info("Precio actualizado para variante", [
                    'variant_id' => $variant->id,
                    'codigo_interno' => $codigoInterno,
                    'precio_anterior' => $precioAnterior,
                    'precio_nuevo' => $nuevoPrecio
                ]);
            }
        }

        return $updatedCount;
    }

    /**
     * 🔥 OBTENER ESTADÍSTICAS DE VARIANTES PARA UPLOAD
     */
    public function getUploadVariantStats($uploadId)
    {
        $logs = UploadProcessLog::where('upload_id', $uploadId)->get();

        return [
            'total_variants' => $logs->count(),
            'created_variants' => $logs->where('action', 'created')->count(),
            'updated_variants' => $logs->where('action', 'updated')->count(),
            'success_variants' => $logs->where('status', 'success')->count(),
            'failed_variants' => $logs->where('status', 'failed')->count(),
            'pending_variants' => $logs->where('status', 'pending')->count()
        ];
    }

    /**
     * 🔥 MARCAR VARIANTES COMO EXITOSAS EN eRETAIL
     */
    public function markVariantsAsSuccessful($variantIds, $uploadId)
    {
        $updatedCount = UploadProcessLog::where('upload_id', $uploadId)
            ->whereIn('product_variant_id', $variantIds)
            ->where('status', 'pending')
            ->update(['status' => 'success']);

        Log::info("Variantes marcadas como exitosas", [
            'upload_id' => $uploadId,
            'variant_ids_count' => count($variantIds),
            'updated_logs' => $updatedCount
        ]);

        return $updatedCount;
    }

    /**
     * 🔥 MARCAR VARIANTES COMO FALLIDAS EN eRETAIL
     */
    public function markVariantsAsFailed($variantIds, $uploadId, $errorMessage = null)
    {
        $updatedCount = UploadProcessLog::where('upload_id', $uploadId)
            ->whereIn('product_variant_id', $variantIds)
            ->where('status', 'pending')
            ->update([
                'status' => 'failed',
                'error_message' => $errorMessage ?? 'Error en eRetail'
            ]);

        Log::error("Variantes marcadas como fallidas", [
            'upload_id' => $uploadId,
            'variant_ids_count' => count($variantIds),
            'updated_logs' => $updatedCount,
            'error_message' => $errorMessage
        ]);

        return $updatedCount;
    }

    /**
     * 🔥 OBTENER DUPLICADOS DE CÓDIGOS DE BARRAS
     */
    public function getDuplicateBarcodes()
    {
        return DB::table('product_variants')
            ->select('cod_barras', DB::raw('COUNT(*) as count'))
            ->whereNotNull('cod_barras')
            ->where('cod_barras', '!=', '')
            ->groupBy('cod_barras')
            ->having('count', '>', 1)
            ->get();
    }

    /**
     * 🔥 LIMPIAR VARIANTES HUÉRFANAS (sin producto maestro)
     */
    public function cleanOrphanedVariants()
    {
        $orphanedCount = ProductVariant::whereDoesntHave('product')->count();

        if ($orphanedCount > 0) {
            Log::warning("Encontradas {$orphanedCount} variantes huérfanas");
            ProductVariant::whereDoesntHave('product')->delete();
            Log::info("Eliminadas {$orphanedCount} variantes huérfanas");
        }

        return $orphanedCount;
    }

    /**
     * MÉTODOS PRIVADOS DE APOYO
     */

    /**
     * Validar datos de variante
     */
    private function validateVariantData($variantData)
    {
        $required = ['codigo_interno', 'descripcion', 'precio_final'];

        foreach ($required as $field) {
            if (!isset($variantData[$field]) || empty($variantData[$field])) {
                throw new \InvalidArgumentException("Campo requerido faltante: {$field}");
            }
        }

        if (!is_numeric($variantData['precio_final']) || $variantData['precio_final'] < 0) {
            throw new \InvalidArgumentException("Precio final debe ser un número positivo");
        }
    }

    /**
     * Asegurar que existe el producto maestro
     */
    private function ensureProductExists($variantData)
    {
        return Product::firstOrCreate(
            ['codigo_interno' => $variantData['codigo_interno']],
            [
                'precio_final' => $variantData['precio_final'],
                'precio_calculado' => $variantData['precio_calculado'] ?? $variantData['precio_final'],
                'last_price_update' => now()
            ]
        );
    }
}