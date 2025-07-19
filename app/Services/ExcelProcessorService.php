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
     * Procesar archivo Excel
     */
    public function processFile($filePath, $uploadId)
    {
        Log::info("=== INICIANDO PROCESAMIENTO ===");
        Log::info("Upload ID: {$uploadId}");
        Log::info("Archivo: {$filePath}");

        $this->upload = Upload::find($uploadId);

        if (!$this->upload) {
            Log::error("Upload {$uploadId} no encontrado en la base de datos");
            throw new \Exception('Upload no encontrado');
        }

        try {
            // Marcar como procesando
            $this->upload->update(['status' => 'processing']);
            Log::info("Estado actualizado a 'processing'");

            // Leer archivo Excel
            $fullPath = storage_path('app/private/' . $filePath);
            Log::info("Ruta completa del archivo: {$fullPath}");

            if (!file_exists($fullPath)) {
                Log::error("Archivo no existe en: {$fullPath}");
                throw new \Exception('Archivo no encontrado: ' . $fullPath);
            }

            Log::info("Cargando archivo Excel...");
            $spreadsheet = IOFactory::load($fullPath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray(null, true, true, false);

            Log::info("Archivo cargado. Total de filas: " . count($data));

            if (empty($data)) {
                throw new \Exception('El archivo está vacío');
            }

            // Procesar productos
            $this->processProducts($data);

            // Marcar como completado
            $this->upload->update(['status' => 'completed']);
            Log::info("=== PROCESAMIENTO COMPLETADO ===");

        } catch (\Exception $e) {
            Log::error("=== ERROR EN PROCESAMIENTO ===");
            Log::error("Mensaje: " . $e->getMessage());
            Log::error("Trace: " . $e->getTraceAsString());

            $this->upload->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            throw $e;
        }
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

        // ✅ OBTENER ÍNDICES DE AMBAS COLUMNAS
        $codBarrasIndex = array_search('cod_barras', $headers);
        $codigoIndex = array_search('codigo', $headers);  // NUEVO
        $descripcionIndex = array_search('descripcion', $headers);
        $finalIndex = array_search('final', $headers);
        $fecUlMoIndex = array_search('fec_ul_mo', $headers);

        Log::info("Índices de columnas", [
            'cod_barras' => $codBarrasIndex,
            'codigo' => $codigoIndex,  // NUEVO LOG
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
                // ✅ EXTRAER AMBOS CÓDIGOS
                $codBarrasRaw = $codBarrasIndex !== false ? $this->cleanValue($row[$codBarrasIndex] ?? '') : '';
                $codigoRaw = $codigoIndex !== false ? $this->cleanValue($row[$codigoIndex] ?? '') : '';

                // ✅ LÓGICA DE DECISIÓN Y FALLBACK
                $identificadorPrincipal = $this->determineIdentifier($codBarrasRaw, $codigoRaw, $rowNumber);
                
                // ✅ ESTRATEGIA: Mantener ambos códigos, usar fallback para cod_barras
                $productData = [
                    'cod_barras_original' => $codBarrasRaw,  // Código de barras original (puede estar vacío)
                    'codigo' => $codigoRaw,                  // Código interno original
                    'cod_barras' => $identificadorPrincipal, // Para BD: código de barras O código interno como fallback
                    'identificador_principal' => $identificadorPrincipal, // Para eRetail posición [1]
                    'descripcion' => $this->cleanValue($row[$descripcionIndex] ?? ''),
                    'precio_final' => $this->parsePrice($row[$finalIndex] ?? 0),
                    'fec_ul_mo' => $this->parseDate($row[$fecUlMoIndex] ?? null)
                ];

                // Log de los primeros productos para debug
                if ($processedCount < 3) {
                    Log::info("Fila {$rowNumber} - Producto: " . json_encode([
                        'cod_barras_original' => $productData['cod_barras_original'],
                        'codigo' => $productData['codigo'],
                        'cod_barras_final' => $productData['cod_barras'],
                        'descripcion' => $productData['descripcion']
                    ]));
                }

                // ✅ VALIDACIÓN
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
                    'codigo' => $productData['codigo'] ?? '',
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
     * ✅ NUEVA FUNCIÓN: Determinar qué identificador usar
     */
    private function determineIdentifier($codBarras, $codigo, $rowNumber)
    {
        // ✅ LÓGICA PRINCIPAL: ¿Tiene código de barras válido?
        $hasValidBarcode = !empty($codBarras) && 
                          trim($codBarras) !== '' && 
                          trim($codBarras) !== ' ' &&
                          strlen(trim($codBarras)) > 0;

        $hasValidCodigo = !empty($codigo) && 
                         trim($codigo) !== '' && 
                         strlen(trim($codigo)) > 0;

        if ($hasValidBarcode) {
            Log::debug("Fila {$rowNumber}: Usando código de barras: '{$codBarras}'");
            return trim($codBarras);
        } elseif ($hasValidCodigo) {
            Log::info("Fila {$rowNumber}: Sin código de barras, usando código interno como fallback: '{$codigo}'");
            return trim($codigo);
        } else {
            throw new \Exception("Fila {$rowNumber}: Producto sin código de barras ni código interno válido");
        }
    }

    /**
     * ✅ processSingleProduct con ambos códigos
     */
    private function processSingleProduct($productData)
    {
        // ✅ BÚSQUEDA: Usar el identificador principal (que puede ser código de barras o interno)
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

        // ✅ CREAR LOG CON AMBOS CAMPOS
        $log = ProductUpdateLog::create([
            'upload_id' => $this->upload->id,
            'cod_barras' => $productData['cod_barras'],  // El identificador final (puede ser código interno como fallback)
            'codigo' => $productData['codigo'],          // El código interno original
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
 * ✅ MÉTODO sendBatchToERetail CON DEBUG COMPLETO
 * Reemplazar este método en ExcelProcessorService.php
 */
private function sendBatchToERetail($products)
{
    Log::info("=== ENVIANDO BATCH A ERETAIL ===");
    Log::info("Cantidad de productos: " . count($products));

    try {
        $eRetailProducts = [];

        foreach ($products as $product) {
            // ✅ USAR EL IDENTIFICADOR PRINCIPAL PARA eRETAIL
            $existingProduct = $this->eRetailService->findProduct($product['identificador_principal']);

            // ✅ CONSTRUIR DATOS PARA eRETAIL
            $eRetailProducts[] = $this->eRetailService->buildProductData([
                'cod_barras' => $product['identificador_principal'],  // Posición [1]: El identificador principal
                'codigo' => $product['codigo'],                       // Posición [4]: El código interno
                'descripcion' => $product['descripcion'],
                'precio_original' => $product['precio_final'],
                'precio_promocional' => $product['precio_descuento']
            ]);

            // ✅ ACTUALIZAR LOG
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

        // ✅ LOG DEL RESULTADO COMPLETO
        Log::info('=== RESPUESTA COMPLETA DE ERETAIL ===', [
            'result_completo' => $result,
            'success_type' => gettype($result['success'] ?? null),
            'success_value' => $result['success'] ?? 'NO_DEFINIDO',
            'message' => $result['message'] ?? 'Sin mensaje'
        ]);

        // ✅ DEBUG: Verificar condición de éxito
        $isSuccess = isset($result['success']) && $result['success'] === true;
        Log::info("=== VERIFICACIÓN DE ÉXITO ===", [
            'isset_success' => isset($result['success']),
            'success_raw' => $result['success'] ?? 'NO_EXISTE',
            'success_strict_true' => $result['success'] === true,
            'is_success_final' => $isSuccess
        ]);
        
        if ($isSuccess) {
            Log::info("✅ PRODUCTOS ENVIADOS EXITOSAMENTE - INICIANDO ACTUALIZACIÓN POSTERIOR");

            // ✅ DEBUG: Verificar productos antes de transacción
            Log::info("=== PRODUCTOS PARA ACTUALIZAR ===", [
                'cantidad' => count($products),
                'primer_producto' => [
                    'cod_barras' => $products[0]['cod_barras'] ?? 'NO_DEFINIDO',
                    'codigo' => $products[0]['codigo'] ?? 'NO_DEFINIDO',
                    'descripcion' => $products[0]['descripcion'] ?? 'NO_DEFINIDO'
                ]
            ]);

            // ✅ MARCAR COMO EXITOSOS CON DEBUG COMPLETO
            try {
                DB::transaction(function () use ($products) {
                    Log::info("=== INICIANDO TRANSACCIÓN ===");
                    
                    foreach ($products as $index => $product) {
                        Log::info("--- Procesando producto {$index} ---", [
                            'cod_barras' => $product['cod_barras'],
                            'upload_id' => $this->upload->id
                        ]);

                        // ✅ BUSCAR LOG CON DEBUG
                        $logQuery = ProductUpdateLog::where('upload_id', $this->upload->id)
                            ->where('cod_barras', $product['cod_barras'])
                            ->where('status', 'pending');
                        
                        Log::info("Query del log", [
                            'sql' => $logQuery->toSql(),
                            'bindings' => $logQuery->getBindings()
                        ]);

                        $log = $logQuery->first();

                        if ($log) {
                            Log::info("✅ Log encontrado", [
                                'log_id' => $log->id,
                                'current_status' => $log->status,
                                'action' => $log->action
                            ]);

                            // Actualizar log a success
                            $updateResult = $log->update(['status' => 'success']);
                            Log::info("Resultado actualización log", ['success' => $updateResult]);

                            // ✅ ACTUALIZAR ÚLTIMA ACTUALIZACIÓN CON DEBUG
                            try {
                                $lastUpdateData = [
                                    'codigo' => $product['codigo'] ?? '',  // Mantener el código interno
                                    'last_update_date' => $product['fec_ul_mo'],
                                    'last_price' => $product['precio_descuento'],
                                    'last_description' => $product['descripcion'],
                                    'last_upload_id' => $this->upload->id
                                ];

                                Log::info("Datos para ProductLastUpdate", [
                                    'cod_barras' => $product['cod_barras'],
                                    'data' => $lastUpdateData
                                ]);

                                $lastUpdate = ProductLastUpdate::updateOrCreate(
                                    ['cod_barras' => $product['cod_barras']],
                                    $lastUpdateData
                                );

                                Log::info("✅ ProductLastUpdate actualizado", [
                                    'id' => $lastUpdate->id,
                                    'was_recently_created' => $lastUpdate->wasRecentlyCreated
                                ]);

                            } catch (\Exception $e) {
                                Log::error("❌ Error en ProductLastUpdate", [
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString()
                                ]);
                                throw $e;
                            }

                            // Incrementar contadores
                            if ($log->action === 'created') {
                                $this->upload->increment('created_products');
                                Log::info("Incrementado created_products");
                            } else {
                                $this->upload->increment('updated_products');
                                Log::info("Incrementado updated_products");
                            }
                        } else {
                            Log::warning("❌ LOG NO ENCONTRADO", [
                                'upload_id' => $this->upload->id,
                                'cod_barras' => $product['cod_barras'],
                                'status_buscado' => 'pending'
                            ]);

                            // ✅ DEBUG: Buscar todos los logs de este upload
                            $allLogs = ProductUpdateLog::where('upload_id', $this->upload->id)->get();
                            Log::info("Todos los logs del upload", [
                                'count' => $allLogs->count(),
                                'logs' => $allLogs->map(function($l) {
                                    return [
                                        'id' => $l->id,
                                        'cod_barras' => $l->cod_barras,
                                        'status' => $l->status,
                                        'action' => $l->action
                                    ];
                                })->toArray()
                            ]);
                        }
                    }
                    
                    Log::info("=== TRANSACCIÓN COMPLETADA ===");
                });

                Log::info("✅ ACTUALIZACIÓN POSTERIOR COMPLETADA EXITOSAMENTE");

            } catch (\Exception $e) {
                Log::error("❌ ERROR EN TRANSACCIÓN", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

        } else {
            Log::error("❌ eRetail reportó fallo", [
                'result' => $result,
                'message' => $result['message'] ?? 'Sin mensaje de error'
            ]);
            throw new \Exception($result['message'] ?? 'Error desconocido al enviar a eRetail');
        }

    } catch (\Exception $e) {
        Log::error("=== ERROR ENVIANDO BATCH ===");
        Log::error("Error enviando batch a eRetail: " . $e->getMessage(), [
            'productos_count' => count($products),
            'trace' => $e->getTraceAsString()
        ]);

        throw $e; // Re-lanzar para que se marque el upload como fallido
    }
}


    /**
     * ✅ MODIFICAR normalizeHeaders() - Separar código de barras del código interno
     */
    private function normalizeHeaders($headers)
    {
        return array_map(function ($header) {
            // Convertir a minúsculas y remover espacios
            $header = strtolower(trim($header));

            // Mapeo de nombres conocidos
            $mappings = [
                // ✅ CÓDIGOS DE BARRAS (mantener igual)
                'cód.barras' => 'cod_barras',
                'cod.barras' => 'cod_barras',
                'cod barras' => 'cod_barras',
                'codigo de barras' => 'cod_barras',
                'cod_barras' => 'cod_barras',
                
                // ✅ CÓDIGO INTERNO (NUEVO - separado del código de barras)
                'código' => 'codigo',  // ¡CAMBIO AQUÍ! Antes era 'cod_barras'
                'codigo' => 'codigo',  // ¡CAMBIO AQUÍ! Antes era 'cod_barras'
                'codigo interno' => 'codigo',
                'cod interno' => 'codigo',
                
                // Resto igual...
                'descripción' => 'descripcion',
                'descripcion' => 'descripcion',
                'nombre' => 'descripcion',
                'producto' => 'descripcion',
                'final ($)' => 'final',
                'final($)' => 'final',
                'fina ($)' => 'final',
                'precio final' => 'final',
                'final' => 'final',
                'precio' => 'final',
                'feculmo' => 'fec_ul_mo',
                'fec ul mo' => 'fec_ul_mo',
                'fecha ultima modificacion' => 'fec_ul_mo',
                'fecha' => 'fec_ul_mo',
                'fec_ul_mo' => 'fec_ul_mo',
                'ultmodif' => 'fec_ul_mo',
                'fecha modificacion' => 'fec_ul_mo'
            ];

            return $mappings[$header] ?? $header;
        }, $headers);
    }

    /**
     * ✅ MODIFICAR validateHeaders() - Requerir al menos uno de los códigos
     */
    private function validateHeaders($headers)
    {
        // ✅ Campos básicos requeridos (CAMBIO: 'cod_barras' ya no es requerido)
        $required = ['descripcion', 'final', 'fec_ul_mo'];
        $missing = array_diff($required, $headers);

        if (!empty($missing)) {
            Log::error("Columnas faltantes: " . implode(', ', $missing));
            Log::error("Columnas encontradas: " . implode(', ', $headers));
            throw new \Exception('Faltan columnas requeridas: ' . implode(', ', $missing));
        }

        // ✅ NUEVA LÓGICA: Verificar que al menos uno de los códigos esté presente
        $hasBarcode = in_array('cod_barras', $headers);
        $hasCodigo = in_array('codigo', $headers);
        
        if (!$hasBarcode && !$hasCodigo) {
            Log::error("Sin columnas de identificación: " . implode(', ', $headers));
            throw new \Exception('Debe existir al menos una columna: "cod_barras" o "codigo"');
        }

        Log::info("Validación de headers exitosa", [
            'tiene_cod_barras' => $hasBarcode,
            'tiene_codigo' => $hasCodigo
        ]);
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
 * Parsear y normalizar precios del Excel
 * Reemplazar la función parsePrice() existente
 */
private function parsePrice($value) 
{
    // Si es null o vacío, retornar 0
    if (empty($value) || $value === null) {
        return 0.00;
    }
    
    // Convertir a string y limpiar
    $cleaned = (string) $value;
    
    // Remover caracteres no numéricos excepto puntos y comas
    $cleaned = preg_replace('/[^0-9.,]/', '', $cleaned);
    
    // Si hay tanto coma como punto, asumir que la coma es separador de miles
    if (strpos($cleaned, ',') !== false && strpos($cleaned, '.') !== false) {
        // Ejemplo: 1,234.56 → 1234.56
        $cleaned = str_replace(',', '', $cleaned);
    } 
    // Si solo hay comas, podría ser separador decimal (formato europeo)
    elseif (strpos($cleaned, ',') !== false && strpos($cleaned, '.') === false) {
        // Solo si hay una coma y está cerca del final (formato decimal)
        $parts = explode(',', $cleaned);
        if (count($parts) == 2 && strlen($parts[1]) <= 2) {
            $cleaned = str_replace(',', '.', $cleaned);
        } else {
            // Múltiples comas = separador de miles, remover todas
            $cleaned = str_replace(',', '', $cleaned);
        }
    }
    
    // Convertir a float
    $price = floatval($cleaned);
    
    // Validar que el precio es válido
    if ($price < 0 || !is_numeric($price)) {
        Log::warning("Precio inválido detectado", [
            'valor_original' => $value,
            'valor_limpio' => $cleaned,
            'precio_parseado' => $price
        ]);
        return 0.00;
    }
    
    // Retornar redondeado a exactamente 2 decimales
    return round($price, 2);
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
     * ✅ VALIDACIÓN
     */
    private function validateProduct($productData)
    {
        if (empty($productData['cod_barras'])) {
            throw new \Exception('Producto sin identificador válido');
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
}