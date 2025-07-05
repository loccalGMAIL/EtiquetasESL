<?php

namespace App\Services;

use App\Models\Upload;
use App\Models\ProductUpdateLog;
use App\Models\ProductLastUpdate;
use App\Models\AppSetting;
use App\Services\ERetailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;


class ExcelProcessorService
{
    private $eRetailService;
    private $upload;
    private $discountPercentage;
    private $skipRows;

    public function __construct(ERetailService $eRetailService)
    {
        $this->eRetailService = $eRetailService;
        $this->discountPercentage = AppSetting::get('discount_percentage', 12);
        $this->skipRows = AppSetting::get('excel_skip_rows', 2);
    }

    /**
     * Procesar array de productos - VERSIÓN CORREGIDA
     */
    private function processProducts($rows)
    {
        // Log de inicio
        Log::info("=== INICIANDO PROCESAMIENTO DE PRODUCTOS ===");
        Log::info("Total de filas recibidas: " . count($rows));

        // ✅ CONFIGURACIÓN DE FILAS A OMITIR
        $skipRows = $this->skipRows ?? 3; // Por defecto omitir 3 filas
        $minRows = $skipRows + 1; // Al menos 1 fila de datos

        if (count($rows) < $minRows) {
            throw new \Exception("El archivo debe tener al menos {$minRows} filas ({$skipRows} de encabezado + 1 de datos)");
        }

        // ✅ OBTENER ENCABEZADOS ANTES DE ELIMINAR FILAS
        $headerRowIndex = $skipRows - 1; // La última fila omitida contiene los encabezados
        $headers = $this->normalizeHeaders($rows[$headerRowIndex]);
        Log::info("Headers encontrados: " . json_encode($headers));

        // Validar que existan los campos necesarios
        $this->validateHeaders($headers);

        // Obtener índices de columnas
        $codBarrasIndex = array_search('cod_barras', $headers);
        $codigoIndex = array_search('codigo', $headers); // Nuevo: código interno
        $descripcionIndex = array_search('descripcion', $headers);
        $finalIndex = array_search('final', $headers);
        $fecUlMoIndex = array_search('fec_ul_mo', $headers);

        Log::info("Índices de columnas", [
            'cod_barras' => $codBarrasIndex,
            'codigo' => $codigoIndex, // Nuevo: código interno
            'descripcion' => $descripcionIndex,
            'final' => $finalIndex,
            'fec_ul_mo' => $fecUlMoIndex
        ]);

        // ✅ ELIMINAR LAS FILAS DE ENCABEZADO
        for ($i = 0; $i < $skipRows; $i++) {
            unset($rows[$i]);
        }

        // ✅ REINDEXAR EL ARRAY (IMPORTANTE!)
        $rows = array_values($rows);

        // 🔥 NUEVA CORRECCIÓN: FILTRAR FILAS VACÍAS ANTES DE CONTAR
        $validRows = [];
        foreach ($rows as $index => $row) {
            if (!$this->isEmptyRow($row)) {
                $validRows[] = $row;
            } else {
                Log::debug("Fila " . ($index + $skipRows + 1) . " vacía detectada y excluida del conteo");
            }
        }

        // ✅ AHORA SÍ CONTAR SOLO LAS FILAS VÁLIDAS
        $totalProducts = count($validRows);
        $this->upload->update(['total_products' => $totalProducts]);

        Log::info("Configuración de procesamiento", [
            'filas_omitidas' => $skipRows,
            'filas_totales_despues_encabezados' => count($rows),
            'filas_vacias_filtradas' => count($rows) - count($validRows),
            'filas_validas_a_procesar' => $totalProducts,
            'fila_encabezados_original' => $headerRowIndex + 1
        ]);

        Log::info("Total de productos a procesar: {$totalProducts}");

        if ($totalProducts === 0) {
            throw new \Exception('No se encontraron productos válidos para procesar');
        }

        // ✅ AUTENTICACIÓN CON eRETAIL
        Log::info("Autenticando con eRetail...");
        try {
            $this->eRetailService->login();
            Log::info("Autenticación exitosa con eRetail");
        } catch (\Exception $e) {
            Log::error("Error de autenticación con eRetail: " . $e->getMessage());
            throw new \Exception("No se pudo conectar con eRetail: " . $e->getMessage());
        }

        $productsBatch = [];
        $processedCount = 0;

        // ✅ PROCESAR CADA FILA VÁLIDA (ya filtradas las vacías)
        foreach ($validRows as $index => $row) {
            $rowNumber = $index + $skipRows + 1; // Número real de fila en Excel

            try {
                // Extraer datos
                $productData = [
                    'cod_barras' => $this->cleanValue($row[$codBarrasIndex] ?? ''),
                    'codigo' => $this->cleanValue($row[$codigoIndex] ?? ''), 
                    'descripcion' => $this->cleanValue($row[$descripcionIndex] ?? ''),
                    'precio_final' => $this->parsePrice($row[$finalIndex] ?? 0),
                    'fec_ul_mo' => $this->parseDate($row[$fecUlMoIndex] ?? null)
                ];

                // Log de los primeros productos para debug
                if ($processedCount < 3) {
                    Log::info("Fila {$rowNumber} - Producto: " . json_encode($productData));
                }

                // Validar datos del producto
                $this->validateProduct($productData);

                // Calcular precio con descuento
                $productData['precio_descuento'] = round($productData['precio_final'] * (1 - $this->discountPercentage / 100), 2);
                $productData['precio_original'] = $productData['precio_final'];

                if ($processedCount < 3) {
                    Log::info("Precios - Original: {$productData['precio_original']}, Con descuento: {$productData['precio_descuento']}");
                }

                // Procesar producto
                $this->processSingleProduct($productData);

                // Agregar al batch para eRetail
                $productsBatch[] = $productData;

                // Procesar en lotes de 50
                if (count($productsBatch) >= 50) {
                    Log::info("Enviando batch de " . count($productsBatch) . " productos a eRetail");
                    $this->sendBatchToERetail($productsBatch);
                    $productsBatch = [];
                }

                $processedCount++;

                // Actualizar progreso cada 10 productos
                if ($processedCount % 10 == 0) {
                    $this->upload->update(['processed_products' => $processedCount]);
                    Log::info("Progreso: {$processedCount}/{$totalProducts} productos procesados");
                }

            } catch (\Exception $e) {
                Log::warning("Error procesando fila {$rowNumber}: " . $e->getMessage());

                // Registrar error
                ProductUpdateLog::create([
                    'upload_id' => $this->upload->id,
                    'cod_barras' => $productData['cod_barras'] ?? 'DESCONOCIDO',
                    'descripcion' => $productData['descripcion'] ?? '',
                    'precio_final' => $productData['precio_final'] ?? 0,
                    'precio_calculado' => $productData['precio_descuento'] ?? 0,
                    'fec_ul_mo' => $productData['fec_ul_mo'] ?? null,
                    'action' => 'skipped',
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ]);

                $this->upload->increment('failed_products');
            }
        }

        // Enviar últimos productos
        if (!empty($productsBatch)) {
            Log::info("Enviando último batch de " . count($productsBatch) . " productos a eRetail");
            $this->sendBatchToERetail($productsBatch);
        }

        // Actualizar conteo final
        $this->upload->update(['processed_products' => $processedCount]);
        Log::info("=== PROCESAMIENTO COMPLETADO ===");
        Log::info("Total procesados: {$processedCount} de {$totalProducts}");
    }

    /**
     * Procesar un producto individual
     */
    private function processSingleProduct($productData)
    {
        // Buscar última actualización
        $lastUpdate = ProductLastUpdate::where('cod_barras', $productData['cod_barras'])->first();

        // Verificar si necesita actualización
        $needsUpdate = true;
        $action = 'created';
        $skipReason = null;

        if ($lastUpdate) {
            $action = 'updated';

            if (AppSetting::get('update_mode') === 'check_date') {
                $needsUpdate = $lastUpdate->needsUpdate($productData['fec_ul_mo']);

                if (!$needsUpdate) {
                    $skipReason = 'already_updated';
                }
            }
        }

        // Crear log
        $log = ProductUpdateLog::create([
            'upload_id' => $this->upload->id,
            'cod_barras' => $productData['cod_barras'],
            'codigo' => $productData['codigo'],  
            'descripcion' => $productData['descripcion'],
            'precio_final' => $productData['precio_final'],
            'precio_calculado' => $productData['precio_descuento'],
            'precio_anterior_eretail' => $lastUpdate->last_price ?? null,
            'fec_ul_mo' => $productData['fec_ul_mo'],
            'action' => $needsUpdate ? $action : 'skipped',
            'status' => $needsUpdate ? 'pending' : 'skipped',
            'skip_reason' => $skipReason
        ]);

        if (!$needsUpdate) {
            $this->upload->increment('skipped_products');
        }

        return $log;
    }

    /**
     * Enviar lote de productos a eRetail
     */
    private function sendBatchToERetail($products)
    {
        Log::info("=== ENVIANDO BATCH A ERETAIL ===");
        Log::info("Cantidad de productos: " . count($products));

        try {
            $eRetailProducts = [];

            foreach ($products as $product) {
                // Buscar si existe en eRetail
                $existingProduct = $this->eRetailService->findProduct($product['cod_barras']);

                // ✅ CORRECCIÓN: Pasar precios correctos
                $eRetailProducts[] = $this->eRetailService->buildProductData([
                    'cod_barras' => $product['cod_barras'],
                    'codigo' => $product['codigo'],
                    'descripcion' => $product['descripcion'],
                    'precio_original' => $product['precio_final'],      // Sin descuento (mayor)
                    'precio_promocional' => $product['precio_descuento'] // Con descuento (menor)
                ]);

                // Actualizar log según si existe o no
                ProductUpdateLog::where('upload_id', $this->upload->id)
                    ->where('cod_barras', $product['cod_barras'])
                    ->where('status', 'pending')
                    ->update([
                        'action' => $existingProduct ? 'updated' : 'created',
                        'precio_anterior_eretail' => isset($existingProduct['items'][7]) ? $existingProduct['items'][7] : null
                    ]);
            }

            // ✅ AÑADIR LOG PARA DEBUG
            Log::info('Enviando a eRetail', [
                'productos_count' => count($eRetailProducts),
                'sample_product' => $eRetailProducts[0] ?? null
            ]);

            // Enviar a eRetail
            $result = $this->eRetailService->saveProducts($eRetailProducts);

            // ✅ LOG DEL RESULTADO
            Log::info('Respuesta de eRetail', [
                'success' => $result['success'],
                'message' => $result['message'] ?? 'Sin mensaje'
            ]);
            if ($result['success']) {
                Log::info("Productos enviados exitosamente a eRetail");

                // Marcar como exitosos
                DB::transaction(function () use ($products) {
                    foreach ($products as $product) {
                        // Actualizar log
                        $log = ProductUpdateLog::where('upload_id', $this->upload->id)
                            ->where('cod_barras', $product['cod_barras'])
                            ->where('status', 'pending')
                            ->first();

                        if ($log) {
                            $log->update(['status' => 'success']);

                            // Actualizar última actualización
                            ProductLastUpdate::updateOrCreate(
                                ['cod_barras' => $product['cod_barras']],
                                [
                                    'codigo' => $product['codigo'],
                                    'last_update_date' => $product['fec_ul_mo'],
                                    'last_price' => $product['precio_descuento'],
                                    'last_description' => $product['descripcion'],
                                    'last_upload_id' => $this->upload->id
                                ]
                            );

                            // Incrementar contadores
                            if ($log->action === 'created') {
                                $this->upload->increment('created_products');
                            } else {
                                $this->upload->increment('updated_products');
                            }
                        }
                    }
                });
            } else {
                Log::error("Error enviando productos a eRetail: " . ($result['message'] ?? 'Sin mensaje'));
                throw new \Exception($result['message'] ?? 'Error desconocido al enviar a eRetail');
            }

        } catch (\Exception $e) {
            Log::error("=== ERROR ENVIANDO BATCH ===");
            // Log::error("Mensaje: " . $e->getMessage());
            Log::error("Error enviando batch a eRetail: " . $e->getMessage(), [
                'productos_count' => count($products),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e; // Re-lanzar para que se marque el upload como fallido
        }
    }

/**
 * Normalizar encabezados
 */
private function normalizeHeaders($headers)
{
    return array_map(function ($header) {
        // Convertir a minúsculas y remover espacios
        $normalized = strtolower(trim($header));

        // Mapeo de nombres conocidos
        $mappings = [
            // === CÓDIGOS DE BARRA ===
            'cód.barras' => 'cod_barras',           // ✅ NUEVO: del archivo de producción
            'cod.barras' => 'cod_barras',
            'cod barras' => 'cod_barras',
            'codigo de barras' => 'cod_barras',
            'cod_barras' => 'cod_barras',
            
            // === CÓDIGO INTERNO ===
            'código' => 'codigo',                   // ✅ NUEVO: código interno del sistema
            'codigo' => 'codigo',
            
            // === DESCRIPCIÓN ===
            'descripción' => 'descripcion',
            'descripcion' => 'descripcion',
            'nombre' => 'descripcion',
            'producto' => 'descripcion',
            
            // === PRECIO FINAL ===
            'fina ($)' => 'final',                  // ✅ NUEVO: del archivo de producción
            'final ($)' => 'final',
            'final($)' => 'final',
            'precio final' => 'final',
            'final' => 'final',
            'precio' => 'final',
            
            // === FECHA ÚLTIMA MODIFICACIÓN ===
            'ultmodif' => 'fec_ul_mo',              // ✅ NUEVO: del archivo de producción
            'feculmo' => 'fec_ul_mo',
            'fec ul mo' => 'fec_ul_mo',
            'fecha ultima modificacion' => 'fec_ul_mo',
            'fecha' => 'fec_ul_mo',
            'fec_ul_mo' => 'fec_ul_mo',
            'fecha modificacion' => 'fec_ul_mo'
        ];

        return $mappings[$normalized] ?? $normalized;
    }, $headers);
}

/**
 * Validar encabezados requeridos
 */
private function validateHeaders($headers)
{
    $required = ['cod_barras', 'codigo', 'descripcion', 'final', 'fec_ul_mo']; // ✅ Agregado 'codigo'
    $missing = array_diff($required, $headers);

    if (!empty($missing)) {
        Log::error("Columnas faltantes: " . implode(', ', $missing));
        Log::error("Columnas encontradas: " . implode(', ', $headers));
        throw new \Exception('Faltan columnas requeridas: ' . implode(', ', $missing));
    }
}
    /**
     * ✅ MEJORAR LA FUNCIÓN DE DETECCIÓN DE FILAS VACÍAS
     */
    private function isEmptyRow($row)
    {
        // Si la fila es null o no es array, está vacía
        if (!is_array($row) || empty($row)) {
            return true;
        }

        // Filtrar valores que no sean null, vacíos o solo espacios
        $nonEmptyValues = array_filter($row, function ($value) {
            if (is_null($value)) {
                return false;
            }

            // Convertir a string y limpiar espacios
            $cleanValue = trim(strval($value));

            // Considerar vacío si es string vacío o solo contiene espacios/caracteres especiales
            return $cleanValue !== '' && $cleanValue !== '0' && !preg_match('/^[\s\r\n\t]*$/', $cleanValue);
        });

        // Si no hay valores válidos, la fila está vacía
        return empty($nonEmptyValues);
    }

    /**
     * Limpiar valor
     */
    private function cleanValue($value)
    {
        if (is_null($value)) {
            return '';
        }
        return trim(str_replace(["\n", "\r", "\t"], ' ', $value));
    }

    /**
     * Parsear precio
     */
    private function parsePrice($value)
    {
        if (empty($value)) {
            return 0;
        }

        // Si es numérico, devolverlo
        if (is_numeric($value)) {
            return (float) $value;
        }

        // Remover símbolos de moneda y espacios
        $value = str_replace(['$', ' ', '.'], '', $value);

        // Reemplazar coma por punto si es decimal
        $value = str_replace(',', '.', $value);

        return (float) $value;
    }

    /**
     * Parsear fecha
     */
    private function parseDate($value)
    {
        if (empty($value)) {
            return null;
        }

        try {
            // Si es un número (timestamp de Excel)
            if (is_numeric($value)) {
                // Excel almacena fechas como días desde 1900-01-01
                // Pero hay un bug histórico donde 1900 se considera bisiesto
                $excelBaseDate = Carbon::create(1900, 1, 1);
                $days = intval($value) - 2; // -2 por el bug de Excel
                return $excelBaseDate->addDays($days);
            }

            // Si es una cadena, intentar varios formatos
            $dateString = trim($value);

            // Intentar varios formatos comunes
            $formats = [
                'Y-m-d H:i:s',
                'd/m/Y H:i:s',
                'd-m-Y H:i:s',
                'Y-m-d',
                'd/m/Y',
                'd-m-Y',
                'm/d/Y',
                'm-d-Y',
                'Y/m/d',
                'd.m.Y',
                'm.d.Y',
                'Y.m.d'
            ];

            foreach ($formats as $format) {
                try {
                    $date = Carbon::createFromFormat($format, $dateString);
                    if ($date) {
                        return $date;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            // Si ningún formato funciona, intentar parse automático
            return Carbon::parse($dateString);

        } catch (\Exception $e) {
            Log::warning("No se pudo parsear fecha: '{$value}' - " . $e->getMessage());
            throw new \Exception("Formato de fecha inválido: {$value}");
        }
    }

    /**
     * Validar datos del producto
     */
    private function validateProduct($productData)
    {
        if (empty($productData['cod_barras'])) {
            throw new \Exception('Código de barras vacío');
        }

        if (empty($productData['codigo'])) {                           // ✅ NUEVO
        throw new \Exception('Código interno no puede estar vacío');
        }
        if (empty($productData['descripcion'])) {
            throw new \Exception('Descripción vacía');
        }

        if ($productData['precio_final'] <= 0) {
            throw new \Exception('Precio debe ser mayor a 0');
        }

        if (is_null($productData['fec_ul_mo'])) {
            throw new \Exception('Fecha de última modificación inválida');
        }
    }

    /**
     * Procesar archivo Excel - VERSIÓN CON AUTO-ACTUALIZACIÓN
     */
    public function processFile($filePath, $uploadId)
    {
        $this->upload = Upload::find($uploadId);

        if (!$this->upload) {
            throw new \Exception('Upload no encontrado');
        }

        try {
            // Marcar como procesando
            $this->upload->update(['status' => 'processing']);

            // Leer archivo Excel usando PhpSpreadsheet
            // $fullPath = $filePath;
            $fullPath = storage_path('app/private/' . $filePath);

            if (!file_exists($fullPath)) {
                throw new \Exception('Archivo no encontrado: ' . $fullPath);
            }

            $spreadsheet = IOFactory::load($fullPath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray(null, true, true, false);

            if (empty($data)) {
                throw new \Exception('El archivo está vacío');
            }

            // Procesar productos
            $this->processProducts($data);

            // ✅ NUEVO: Actualizar etiquetas automáticamente
            $this->autoRefreshTags();

            // Marcar como completado
            $this->upload->update(['status' => 'completed']);

            Log::info("Procesamiento completado para upload {$uploadId}");

        } catch (\Exception $e) {
            Log::error("Error procesando archivo: " . $e->getMessage());

            $this->upload->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * ✅ NUEVA FUNCIÓN: Actualizar etiquetas automáticamente después del procesamiento
     */
    private function autoRefreshTags()
    {
        // Verificar si está habilitada la actualización automática
        if (!AppSetting::get('auto_refresh_tags', true)) {
            Log::info("Actualización automática de etiquetas deshabilitada en configuración");
            return;
        }

        try {
            Log::info("=== INICIANDO ACTUALIZACIÓN AUTOMÁTICA DE ETIQUETAS ===");

            // Obtener todos los productos procesados exitosamente
            $successfulProducts = ProductUpdateLog::where('upload_id', $this->upload->id)
                ->where('status', 'success')
                ->whereIn('action', ['created', 'updated'])
                ->pluck('cod_barras')
                ->unique()
                ->values()
                ->toArray();

            if (empty($successfulProducts)) {
                Log::info("No hay productos exitosos para actualizar etiquetas");
                return;
            }

            Log::info("Productos a actualizar", [
                'cantidad' => count($successfulProducts),
                'shop_code' => $this->upload->shop_code,
                'primeros_5' => array_slice($successfulProducts, 0, 5)
            ]);

            $refreshMethod = AppSetting::get('refresh_method', 'specific');

            if ($refreshMethod === 'specific') {
                // Actualizar solo productos procesados (RECOMENDADO)
                $result = $this->eRetailService->refreshSpecificTags($successfulProducts, $this->upload->shop_code);
                Log::info("Método: Actualización específica de " . count($successfulProducts) . " productos");
            } else {
                // Actualizar toda la tienda
                $result = $this->eRetailService->refreshAllStoreTags($this->upload->shop_code);
                Log::info("Método: Actualización de toda la tienda");
            }

            if ($result['success']) {
                Log::info("✅ Actualización de etiquetas iniciada correctamente", [
                    'message' => $result['message']
                ]);

                // Opcional: Hacer parpadear etiquetas para indicar actualización
                if (AppSetting::get('flash_updated_tags', false)) {
                    try {
                        $this->eRetailService->flashTags($successfulProducts, $this->upload->shop_code, 'G', 3);
                        Log::info("💡 Etiquetas configuradas para parpadear en verde por 3 segundos");
                    } catch (\Exception $e) {
                        Log::warning("Error configurando parpadeo de etiquetas: " . $e->getMessage());
                    }
                }

                // Registrar estadística de actualización
                Log::info("📊 Resumen de actualización automática", [
                    'upload_id' => $this->upload->id,
                    'productos_creados' => $this->upload->created_products,
                    'productos_actualizados' => $this->upload->updated_products,
                    'etiquetas_a_actualizar' => count($successfulProducts),
                    'metodo_actualizacion' => $refreshMethod,
                    'parpadeo_habilitado' => AppSetting::get('flash_updated_tags', false)
                ]);

            } else {
                Log::error("❌ Error en actualización de etiquetas", [
                    'message' => $result['message'] ?? 'Sin mensaje'
                ]);
            }

        } catch (\Exception $e) {
            Log::error("💥 Error en actualización automática de etiquetas: " . $e->getMessage(), [
                'upload_id' => $this->upload->id,
                'trace' => $e->getTraceAsString()
            ]);
            // No lanzar excepción para no afectar el procesamiento principal
        }
    }

    /**
     * Actualizar etiquetas automáticamente después del procesamiento
     */
    // private function autoRefreshTags()
    // {
    //     try {
    //         Log::info("=== INICIANDO ACTUALIZACIÓN AUTOMÁTICA DE ETIQUETAS ===");

    //         // Obtener todos los productos procesados exitosamente
    //         $successfulProducts = ProductUpdateLog::where('upload_id', $this->upload->id)
    //             ->where('status', 'success')
    //             ->whereIn('action', ['created', 'updated'])
    //             ->pluck('cod_barras')
    //             ->unique()
    //             ->values()
    //             ->toArray();

    //         if (empty($successfulProducts)) {
    //             Log::info("No hay productos exitosos para actualizar etiquetas");
    //             return;
    //         }

    //         Log::info("Productos a actualizar", [
    //             'cantidad' => count($successfulProducts),
    //             'primeros_5' => array_slice($successfulProducts, 0, 5)
    //         ]);

    //         // Opción A: Actualizar etiquetas específicas (RECOMENDADO)
    //         $result = $this->eRetailService->refreshSpecificTags($successfulProducts, $this->upload->shop_code);

    //         if ($result['success']) {
    //             Log::info("✅ Actualización de etiquetas iniciada correctamente");

    //             // Opcional: Hacer parpadear las etiquetas para indicar actualización
    //             // $this->eRetailService->flashTags($successfulProducts, $this->upload->shop_code, 'G', 5);
    //         }

    //         // Opción B: Actualizar toda la tienda (menos eficiente pero más seguro)
    //         // $this->eRetailService->refreshAllStoreTags($this->upload->shop_code);

    //     } catch (\Exception $e) {
    //         Log::error("Error en actualización automática de etiquetas: " . $e->getMessage());
    //         // No lanzar excepción para no afectar el procesamiento principal
    //     }
    // }

}