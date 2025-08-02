<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class ProductService
{
    /**
     * 🔥 BUSCAR O CREAR PRODUCTO MAESTRO
     */
    public function findOrCreateProduct($codigoInterno, $descripcionBase = null, $precioActual = null)
    {
        $product = Product::where('codigo_interno', $codigoInterno)->first();

        if (!$product) {
            $product = Product::create([
                'codigo_interno' => $codigoInterno,
                'precio_final' => $precioActual ?? 0,
                'precio_calculado' => $precioActual ?? 0,
                'last_price_update' => now()
            ]);

            Log::info("Producto maestro creado", [
                'product_id' => $product->id,
                'codigo_interno' => $codigoInterno,
                'descripcion_base' => $descripcionBase
            ]);
        }

        return $product;
    }

    /**
     * 🔥 OBTENER PRODUCTO CON SUS VARIANTES
     */
    public function getProductWithVariants($codigoInterno)
    {
        return Product::where('codigo_interno', $codigoInterno)
            ->with([
                'variants' => function ($query) {
                    $query->orderBy('descripcion');
                }
            ])
            ->first();
    }

    /**
     * 🔥 ACTUALIZAR PRECIO ACTUAL DEL PRODUCTO MAESTRO
     * 
     * Actualiza el precio basado en la variante más cara o un precio específico
     */
    public function updateProductPrice($codigoInterno, $nuevoPrecio = null)
    {
        $product = Product::where('codigo_interno', $codigoInterno)->first();

        if (!$product) {
            throw new \Exception("Producto maestro no encontrado: {$codigoInterno}");
        }

        // Si no se especifica precio, usar el más alto de las variantes
        if ($nuevoPrecio === null) {
            $maxPrice = $product->variants()->max('precio_final');
            $nuevoPrecio = $maxPrice ?? $product->precio_final;
        }

        $precioAnterior = $product->precio_final;

        $product->update([
            'precio_final' => $nuevoPrecio,
            'precio_calculado' => $nuevoPrecio,
            'last_price_update' => now()
        ]);

        Log::info("Precio del producto maestro actualizado", [
            'product_id' => $product->id,
            'codigo_interno' => $codigoInterno,
            'precio_anterior' => $precioAnterior,
            'precio_nuevo' => $nuevoPrecio
        ]);

        return $product;
    }

    /**
     * 🔥 OBTENER PRODUCTOS SIN VARIANTES
     */
    public function getProductsWithoutVariants()
    {
        return Product::whereDoesntHave('variants')->get();
    }

    /**
     * 🔥 OBTENER ESTADÍSTICAS DEL PRODUCTO
     */
    public function getProductStats($codigoInterno)
    {
        $product = Product::where('codigo_interno', $codigoInterno)
            ->with(['variants'])
            ->first();

        if (!$product) {
            return null;
        }

        $variants = $product->variants;

        return [
            'product_id' => $product->id,
            'codigo_interno' => $product->codigo_interno,
            // 'descripcion_base' => $product->descripcion_base,
            // 'precio_actual' => $product->precio_actual,
            'total_variants' => $variants->count(),
            'precio_final' => $product->precio_final,
            'precio_calculado' => $product->precio_calculado,
            'last_price_update' => $product->last_price_update,
            // 'precio_min' => $variants->min('precio_final'),
            // 'precio_max' => $variants->max('precio_final'),
            // 'precio_promedio' => $variants->avg('precio_final'),
            'variants_with_barcode' => $variants->whereNotNull('cod_barras')->count(),
            'variants_without_barcode' => $variants->whereNull('cod_barras')->count(),
            // 'fec_ul_mo' => $product->fec_ul_mo
        ];
    }

    /**
     * 🔥 BUSCAR PRODUCTOS POR DESCRIPCIÓN
     */
    public function searchProductsByDescription($searchTerm, $limit = 20)
    {
        // return Product::where('descripcion_base', 'LIKE', "%{$searchTerm}%")
        return Product::where('codigo_interno', 'LIKE', "%{$searchTerm}%")
            ->orWhereHas('variants', function ($q) use ($searchTerm) {
                $q->where('descripcion', 'LIKE', "%{$searchTerm}%");
            })
            ->with(['variants'])
            ->orderBy('descripcion_base')
            ->limit($limit)
            ->get();
    }

    /**
     * 🔥 OBTENER PRODUCTOS CON PRECIOS DESACTUALIZADOS
     * 
     * Productos cuyo precio_actual no coincide con el precio máximo de sus variantes
     */
    public function getProductsWithOutdatedPrices()
    {
        return Product::whereHas('variants')
            ->get()
            ->filter(function ($product) {
                $maxVariantPrice = $product->variants->max('precio_final');
                return $product->precio_actual != $maxVariantPrice;
            });
    }

    /**
     * 🔥 SINCRONIZAR PRECIOS DE PRODUCTOS MAESTROS
     * 
     * Actualiza precio_actual basado en el precio máximo de las variantes
     */
    public function synchronizeProductPrices()
    {
        $products = Product::whereHas('variants')->get();
        $updatedCount = 0;

        foreach ($products as $product) {
            $maxVariantPrice = $product->variants->max('precio_final');

            if ($product->precio_actual != $maxVariantPrice) {
                $product->update([
                    'precio_actual' => $maxVariantPrice,
                    'fec_ul_mo' => now()
                ]);

                $updatedCount++;

                Log::info("Precio sincronizado", [
                    'product_id' => $product->id,
                    'codigo_interno' => $product->codigo_interno,
                    'precio_anterior' => $product->precio_actual,
                    'precio_sincronizado' => $maxVariantPrice
                ]);
            }
        }

        Log::info("Sincronización de precios completada", [
            'productos_actualizados' => $updatedCount,
            'productos_totales' => $products->count()
        ]);

        return $updatedCount;
    }

    /**
     * 🔥 CONSOLIDAR PRODUCTO (UNIFICAR VARIANTES DUPLICADAS)
     * 
     * Útil para limpiar datos cuando hay variantes muy similares
     */
    public function consolidateProduct($codigoInterno, $dry_run = true)
    {
        $product = $this->getProductWithVariants($codigoInterno);

        if (!$product) {
            throw new \Exception("Producto no encontrado: {$codigoInterno}");
        }

        $variants = $product->variants;
        $consolidationPlan = [];

        // Agrupar variantes por descripción similar
        $groupedVariants = $variants->groupBy(function ($variant) {
            // Normalizar descripción para agrupar
            return strtolower(trim(preg_replace('/\s+/', ' ', $variant->descripcion)));
        });

        foreach ($groupedVariants as $normalizedDesc => $variantGroup) {
            if ($variantGroup->count() > 1) {
                $consolidationPlan[$normalizedDesc] = [
                    'variants_count' => $variantGroup->count(),
                    'variants' => $variantGroup->map(function ($v) {
                        return [
                            'id' => $v->id,
                            'descripcion' => $v->descripcion,
                            'cod_barras' => $v->cod_barras,
                            'precio_final' => $v->precio_final
                        ];
                    })->toArray(),
                    'recommended_action' => 'merge_or_review'
                ];
            }
        }

        if (!$dry_run && !empty($consolidationPlan)) {
            // Aquí iría la lógica de consolidación real
            Log::warning("Consolidación real no implementada aún - usar dry_run para revisar", [
                'codigo_interno' => $codigoInterno,
                'plan' => $consolidationPlan
            ]);
        }

        return [
            'product' => $product,
            'total_variants' => $variants->count(),
            'consolidation_plan' => $consolidationPlan,
            'needs_consolidation' => !empty($consolidationPlan)
        ];
    }

    /**
     * 🔥 OBTENER RESUMEN GENERAL DE PRODUCTOS
     */
    public function getGeneralStats()
    {
        $totalProducts = Product::count();
        $productsWithVariants = Product::whereHas('variants')->count();
        $productsWithoutVariants = $totalProducts - $productsWithVariants;
        $totalVariants = ProductVariant::count();
        $variantsWithBarcode = ProductVariant::whereNotNull('cod_barras')->count();

        return [
            'total_products' => $totalProducts,
            'products_with_variants' => $productsWithVariants,
            'products_without_variants' => $productsWithoutVariants,
            'total_variants' => $totalVariants,
            'variants_with_barcode' => $variantsWithBarcode,
            'variants_without_barcode' => $totalVariants - $variantsWithBarcode,
            'avg_variants_per_product' => $productsWithVariants > 0 ? round($totalVariants / $productsWithVariants, 2) : 0,
            'products_with_outdated_prices' => $this->getProductsWithOutdatedPrices()->count()
        ];
    }

    /**
     * 🔥 ELIMINAR PRODUCTO Y SUS VARIANTES
     */
    public function deleteProduct($codigoInterno, $force = false)
    {
        $product = Product::where('codigo_interno', $codigoInterno)
            ->with(['variants'])
            ->first();

        if (!$product) {
            throw new \Exception("Producto no encontrado: {$codigoInterno}");
        }

        $variantsCount = $product->variants->count();

        // Verificar si tiene variantes y no es forzado
        if ($variantsCount > 0 && !$force) {
            throw new \Exception("El producto tiene {$variantsCount} variantes. Use force=true para eliminar todo.");
        }

        DB::beginTransaction();

        try {
            // Eliminar variantes primero (por foreign key)
            $product->variants()->delete();

            // Eliminar producto
            $product->delete();

            DB::commit();

            Log::warning("Producto eliminado", [
                'codigo_interno' => $codigoInterno,
                'variants_deleted' => $variantsCount,
                'forced' => $force
            ]);

            return [
                'deleted' => true,
                'variants_deleted' => $variantsCount
            ];

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Error eliminando producto", [
                'codigo_interno' => $codigoInterno,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}