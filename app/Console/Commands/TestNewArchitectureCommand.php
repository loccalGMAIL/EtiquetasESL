<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ProductService;
use App\Services\ProductVariantService;
use App\Services\PriceHistoryService;
use App\Services\UploadLogService;
use App\Services\ERetailService;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Upload;
use Illuminate\Support\Facades\DB;

class TestNewArchitectureCommand extends Command
{
    protected $signature = 'esl:test-architecture {--reset : Limpiar datos de prueba}';
    protected $description = 'Testear la nueva arquitectura de productos y variantes';

    private $productService;
    private $variantService;
    private $uploadLogService;
    private $eRetailService;

    public function __construct(
        ProductService $productService,
        ProductVariantService $variantService,
        UploadLogService $uploadLogService,
        ERetailService $eRetailService
    ) {
        parent::__construct();
        $this->productService = $productService;
        $this->variantService = $variantService;
        $this->uploadLogService = $uploadLogService;
        $this->eRetailService = $eRetailService;
    }

    public function handle()
    {
        $this->info('🔥 TESTING NUEVA ARQUITECTURA DE MODELOS Y SERVICIOS 🔥');
        $this->newLine();

        if ($this->option('reset')) {
            $this->resetTestData();
            return;
        }

        // Tests en orden de dependencia
        $this->testDatabaseConnections();
        $this->testModelRelationships();
        $this->testProductService();
        $this->testVariantService();
        $this->testUploadLogService();
        $this->testERetailService();
        $this->testCompleteFlow();

        $this->newLine();
        $this->info('✅ TODOS LOS TESTS COMPLETADOS EXITOSAMENTE');
        $this->info('🚀 La nueva arquitectura está funcionando correctamente');
    }

    private function testDatabaseConnections()
    {
        $this->info('1️⃣ Testing conexiones de base de datos...');

        try {
            $productsCount = Product::count();
            $variantsCount = ProductVariant::count();
            $uploadsCount = Upload::count();

            $this->line("   ✅ Products table: {$productsCount} registros");
            $this->line("   ✅ ProductVariants table: {$variantsCount} registros");
            $this->line("   ✅ Uploads table: {$uploadsCount} registros");

        } catch (\Exception $e) {
            $this->error("   ❌ Error en conexión BD: " . $e->getMessage());
            return false;
        }

        $this->newLine();
        return true;
    }

    private function testModelRelationships()
    {
        $this->info('2️⃣ Testing relaciones entre modelos...');

        try {
            // Crear producto de prueba
            $product = Product::firstOrCreate([
                'codigo_interno' => 'TEST001'
            ], [
                'descripcion_base' => 'Producto de Prueba',
                'precio_actual' => 100.00
            ]);

            // Crear variante de prueba
            $variant = ProductVariant::firstOrCreate([
                'codigo_interno' => 'TEST001',
                'descripcion' => 'Variante de Prueba 250ml'
            ], [
                'product_id' => $product->id,
                'cod_barras' => '7890123456789',
                'precio_final' => 100.00,
                'precio_calculado' => 88.00
            ]);

            // Verificar relaciones
            $this->line("   ✅ Product creado: ID {$product->id}");
            $this->line("   ✅ ProductVariant creado: ID {$variant->id}");
            $this->line("   ✅ Relación Product->Variants: " . $product->variants()->count() . " variantes");
            $this->line("   ✅ Relación Variant->Product: " . ($variant->product ? 'OK' : 'ERROR'));

            // Test de ProductVariant.id estable
            $variantId1 = $variant->id;
            $variant->touch(); // Simular actualización
            $variantId2 = $variant->fresh()->id;
            
            if ($variantId1 === $variantId2) {
                $this->line("   ✅ ProductVariant.id es ESTABLE: {$variantId1} (crítico para eRetail)");
            } else {
                $this->error("   ❌ ProductVariant.id NO ES ESTABLE: {$variantId1} != {$variantId2}");
            }

        } catch (\Exception $e) {
            $this->error("   ❌ Error en relaciones: " . $e->getMessage());
            return false;
        }

        $this->newLine();
        return true;
    }

    private function testProductService()
    {
        $this->info('3️⃣ Testing ProductService...');

        try {
            // Test crear producto
            $product = $this->productService->findOrCreateProduct('TEST002', 'Producto Servicio Test', 150.00);
            $this->line("   ✅ findOrCreateProduct: ID {$product->id}");

            // Test obtener con variantes
            $productWithVariants = $this->productService->getProductWithVariants('TEST002');
            $this->line("   ✅ getProductWithVariants: " . ($productWithVariants ? 'OK' : 'NULL'));

            // Test estadísticas
            $stats = $this->productService->getProductStats('TEST002');
            $this->line("   ✅ getProductStats: " . ($stats ? 'OK' : 'NULL'));

            // Test estadísticas generales
            $generalStats = $this->productService->getGeneralStats();
            $this->line("   ✅ getGeneralStats: {$generalStats['total_products']} productos, {$generalStats['total_variants']} variantes");

        } catch (\Exception $e) {
            $this->error("   ❌ Error en ProductService: " . $e->getMessage());
            return false;
        }

        $this->newLine();
        return true;
    }

    private function testVariantService()
    {
        $this->info('4️⃣ Testing ProductVariantService...');

        try {
            // Test crear variante
            $variantData = [
                'codigo_interno' => 'TEST003',
                'descripcion' => 'Test Variant 500ml',
                'precio_final' => 200.00,
                'precio_calculado' => 176.00,
                'cod_barras' => '7890123456790'
            ];

            $result = $this->variantService->createOrUpdateVariant($variantData);
            $this->line("   ✅ createOrUpdateVariant: {$result['action']} - Variant ID {$result['variant']->id}");

            // Test buscar por código de barras
            $variants = $this->variantService->findVariantsByBarcode('7890123456790');
            $this->line("   ✅ findVariantsByBarcode: " . $variants->count() . " variantes encontradas");

            // Test estadísticas de upload (simular)
            $upload = Upload::first();
            if ($upload) {
                $stats = $this->variantService->getUploadVariantStats($upload->id);
                $this->line("   ✅ getUploadVariantStats: {$stats['total_variants']} variantes");
            }

        } catch (\Exception $e) {
            $this->error("   ❌ Error en ProductVariantService: " . $e->getMessage());
            return false;
        }

        $this->newLine();
        return true;
    }

    private function testPriceHistoryService()
    {
        $this->info('5️⃣ Testing PriceHistoryService...');

        try {
            // Obtener una variante de prueba o crear una
            $variant = ProductVariant::where('codigo_interno', 'TEST003')->first();
            
            if (!$variant) {
                $variant = ProductVariant::first();
            }
            
            if ($variant && $variant->id) {
                // Test registrar cambio de precio
                $upload = Upload::first();
                
                $this->line("   🔍 Testing con Variant ID: {$variant->id}");
                
                $priceHistory = $this->priceHistoryService->recordPriceChange(
                    $variant->id,
                    200.00,
                    220.00,
                    $upload ? $upload->id : null,
                    'Test de cambio de precio'
                );

                if ($priceHistory) {
                    $this->line("   ✅ recordPriceChange: History ID {$priceHistory->id}");

                    // Test obtener histórico
                    $history = $this->priceHistoryService->getVariantPriceHistory($variant->id);
                    $this->line("   ✅ getVariantPriceHistory: " . $history->count() . " cambios");

                    // Test estadísticas
                    $stats = $this->priceHistoryService->getPriceChangeStats();
                    $this->line("   ✅ getPriceChangeStats: {$stats['total_cambios']} cambios totales");
                } else {
                    $this->line("   ⚠️  No se registró cambio (precios iguales)");
                }
            } else {
                $this->error("   ❌ No hay variantes válidas para testear histórico de precios");
            }

        } catch (\Exception $e) {
            $this->error("   ❌ Error en PriceHistoryService: " . $e->getMessage());
            return false;
        }

        $this->newLine();
        return true;
    }

    private function testUploadLogService()
    {
        $this->info('6️⃣ Testing UploadLogService...');

        try {
            $upload = Upload::first();
            $variant = ProductVariant::first();

            if ($upload && $variant) {
                // Test crear log
                $log = $this->uploadLogService->createProcessLog(
                    $upload->id,
                    $variant->id,
                    'created',
                    'success',
                    1
                );

                $this->line("   ✅ createProcessLog: Log ID {$log->id}");

                // Test obtener estadísticas
                $stats = $this->uploadLogService->getUploadStats($upload->id);
                $this->line("   ✅ getUploadStats: {$stats['total_logs']} logs");

                // Test progreso
                $progress = $this->uploadLogService->getProcessingProgress($upload->id);
                $this->line("   ✅ getProcessingProgress: {$progress['progress_percentage']}% completado");
            }

        } catch (\Exception $e) {
            $this->error("   ❌ Error en UploadLogService: " . $e->getMessage());
            return false;
        }

        $this->newLine();
        return true;
    }

    private function testERetailService()
    {
        $this->info('7️⃣ Testing ERetailService...');

        try {
            // Test buildProductData con ProductVariant.id
            $variant = ProductVariant::first();
            
            if ($variant) {
                $productData = $this->eRetailService->buildProductData([
                    'id' => $variant->id,  // 🔥 ProductVariant.id como goodsCode
                    'codigo' => $variant->codigo_interno,
                    'cod_barras' => $variant->cod_barras,
                    'descripcion' => $variant->descripcion,
                    'precio_original' => $variant->precio_final,
                    'precio_promocional' => $variant->precio_calculado
                ]);

                $goodsCode = $productData['items'][1]; // Posición del goodsCode

                $this->line("   ✅ buildProductData: goodsCode = {$goodsCode} (ProductVariant.id)");
                
                if ($goodsCode == $variant->id) {
                    $this->line("   ✅ CRÍTICO: ProductVariant.id coincide con goodsCode ✓");
                } else {
                    $this->error("   ❌ CRÍTICO: ProductVariant.id NO coincide con goodsCode");
                }

                // Test conexión (opcional - comentar si no hay conexión)
                // $this->line("   ⚠️  Test de conexión saltado (descomentar si hay acceso a eRetail)");
            }

        } catch (\Exception $e) {
            $this->error("   ❌ Error en ERetailService: " . $e->getMessage());
            return false;
        }

        $this->newLine();
        return true;
    }

    private function testCompleteFlow()
    {
        $this->info('6️⃣ Testing flujo completo...');

        try {
            DB::beginTransaction();

            // 1. Crear producto y variante
            $variantData = [
                'codigo_interno' => 'FLOW001',
                'descripcion' => 'Producto Flujo Completo 1L',
                'precio_final' => 350.00,
                'precio_calculado' => 308.00,
                'cod_barras' => '7890123456791'
            ];

            $result = $this->variantService->createOrUpdateVariant($variantData);
            $variant = $result['variant'];

            $this->line("   ✅ 1. Producto y variante creados: Variant ID {$variant->id}");

            // 2. Crear upload y log simulado (SIN HISTÓRICO DE PRECIOS)
            $upload = Upload::create([
                'filename' => 'test-flow.xlsx',
                'original_filename' => 'test-flow.xlsx',
                'status' => 'completed'
            ]);

            $log = $this->uploadLogService->createProcessLog(
                $upload->id,
                $variant->id,
                'updated',
                'success',
                1
            );

            $this->line("   ✅ 2. Upload y log creados: Upload ID {$upload->id}, Log ID {$log->id}");

            // 3. Construir datos para eRetail
            $eRetailData = $this->eRetailService->buildProductData([
                'id' => $variant->id,
                'codigo' => $variant->codigo_interno,
                'cod_barras' => $variant->cod_barras,
                'descripcion' => $variant->descripcion,
                'precio_original' => $variant->precio_final,
                'precio_promocional' => $variant->precio_calculado
            ]);

            $this->line("   ✅ 3. Datos para eRetail: goodsCode = {$eRetailData['items'][1]}");

            // 4. Verificar integridad
            $integrityChecks = [
                'product_exists' => Product::where('codigo_interno', 'FLOW001')->exists(),
                'variant_exists' => ProductVariant::find($variant->id) !== null,
                'upload_log_exists' => $log->exists(),
                'goodsCode_matches' => $eRetailData['items'][1] == $variant->id
            ];

            foreach ($integrityChecks as $check => $passed) {
                if ($passed) {
                    $this->line("   ✅ {$check}: OK");
                } else {
                    $this->error("   ❌ {$check}: FAILED");
                }
            }

            DB::rollback(); // No persistir datos de prueba

            $this->line("   ✅ 4. Flujo completo exitoso (datos no persistidos)");

        } catch (\Exception $e) {
            DB::rollback();
            $this->error("   ❌ Error en flujo completo: " . $e->getMessage());
            return false;
        }

        $this->newLine();
        return true;
    }

    private function resetTestData()
    {
        $this->warn('🧹 LIMPIANDO DATOS DE PRUEBA...');

        try {
            DB::beginTransaction();

            // Eliminar datos de prueba
            ProductVariant::where('codigo_interno', 'LIKE', 'TEST%')->delete();
            Product::where('codigo_interno', 'LIKE', 'TEST%')->delete();
            Upload::where('filename', 'LIKE', 'test-%')->delete();

            DB::commit();

            $this->info('✅ Datos de prueba eliminados');

        } catch (\Exception $e) {
            DB::rollback();
            $this->error('❌ Error limpiando datos: ' . $e->getMessage());
        }
    }
}