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
     * Procesar array de productos
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
    $codigoIndex = array_search('codigo', $headers);
    $descripcionIndex = array_search('descripcion', $headers);
    $finalIndex = array_search('final', $headers);
    $fecUlMoIndex = array_search('fec_ul_mo', $headers);

    Log::info("Índices de columnas", [
        'cod_barras' => $codBarrasIndex,
        'codigo' => $codigoIndex,
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

    // 🔥 FILTRAR FILAS VACÍAS ANTES DE CONTAR
    $validRows = [];
    foreach ($rows as $index => $row) {
        if (!$this->isEmptyRow($row)) {
            $validRows[] = $row;
        } else {
            Log::debug("Fila " . ($index + $skipRows + 1) . " vacía detectada y excluida del conteo");
        }
    }

    // ✅ CONTAR SOLO LAS FILAS VÁLIDAS
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

    // 🔥 PROCESAR CADA FILA VÁLIDA - BUCLE PRINCIPAL MODIFICADO
    foreach ($validRows as $index => $row) {
        $rowNumber = $index + $skipRows + 1; // Número real de fila en Excel

        try {
            // ✅ EXTRAER AMBOS CÓDIGOS
            $codBarrasRaw = $codBarrasIndex !== false ? $this->cleanValue($row[$codBarrasIndex] ?? '') : '';
            $codigoRaw = $codigoIndex !== false ? $this->cleanValue($row[$codigoIndex] ?? '') : '';

            // ✅ LÓGICA DE DECISIÓN Y FALLBACK
            $identificadorPrincipal = $this->determineIdentifier($codBarrasRaw, $codigoRaw, $rowNumber);
            
            // ✅ CONSTRUIR DATOS DEL PRODUCTO
            $productData = [
                'cod_barras_original' => $codBarrasRaw,
                'codigo' => $codigoRaw,
                'cod_barras' => $identificadorPrincipal, // Para BD
                'identificador_principal' => $identificadorPrincipal,
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

            // 🔥 PROCESAR PRODUCTO Y OBTENER EL LOG CREADO
            $log = $this->processSingleProduct($productData);

            // 🔥 SOLO AGREGAR AL BATCH SI ESTÁ PENDING (necesita enviarse a eRetail)
            if ($log && $log->status === 'pending') {
                $productsBatch[] = [
                    'id' => $log->id,                    // 🔥 ID único del registro en BD
                    'cod_barras' => $log->cod_barras,
                    'codigo' => $log->codigo,
                    'descripcion' => $log->descripcion,
                    'precio_final' => $log->precio_final,
                    'precio_calculado' => $log->precio_calculado,
                    'action' => $log->action
                ];
                
                Log::debug("Producto agregado al batch", [
                    'log_id' => $log->id,
                    'action' => $log->action
                ]);
            } else {
                Log::debug("Producto omitido del batch", [
                    'log_id' => $log->id ?? 'N/A',
                    'status' => $log->status ?? 'N/A',
                    'reason' => 'No está en estado pending o ya está actualizado'
                ]);
            }

            // 🔥 PROCESAR EN LOTES DE 50 (solo si hay productos en el batch)
            if (count($productsBatch) >= 50) {
                Log::info("Enviando batch de " . count($productsBatch) . " productos pendientes a eRetail");
                $this->sendBatchToERetail($productsBatch);
                $productsBatch = []; // Limpiar batch
            }

            $processedCount++;

            // Actualizar progreso cada 10 productos
            if ($processedCount % 10 == 0) {
                $this->upload->update(['processed_products' => $processedCount]);
                Log::info("Progreso: {$processedCount}/{$totalProducts} productos procesados");
            }

        } catch (\Exception $e) {
            Log::warning("Error procesando fila {$rowNumber}: " . $e->getMessage());

            // Registrar error en la base de datos
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
            $processedCount++;
        }
    }

    // 🔥 ENVIAR ÚLTIMOS PRODUCTOS (si quedan en el batch)
    if (!empty($productsBatch)) {
        Log::info("Enviando último batch de " . count($productsBatch) . " productos pendientes a eRetail");
        $this->sendBatchToERetail($productsBatch);
    }

    // Actualizar conteo final
    $this->upload->update(['processed_products' => $processedCount]);
    Log::info("=== PROCESAMIENTO COMPLETADO ===");
    Log::info("Total procesados: {$processedCount} de {$totalProducts}");
    
    // Log final de estadísticas
    Log::info("Estadísticas finales", [
        'productos_en_ultimo_batch' => count($productsBatch),
        'total_procesado' => $processedCount,
        'productos_esperados' => $totalProducts
    ]);
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
    try {
        // ✅ VERIFICAR SOLO LOCALMENTE si necesita actualización
        $lastUpdate = ProductLastUpdate::where('cod_barras', $productData['cod_barras'])->first();
        
        $needsUpdate = true;
        $action = 'created'; // Por defecto es nuevo
        $skipReason = null;

        if ($lastUpdate) {
            $action = 'updated'; // Ya existe localmente
            
            // Verificar si necesita actualización por fecha
            if (AppSetting::get('update_mode') === 'check_date') {
                $needsUpdate = $lastUpdate->needsUpdate($productData['fec_ul_mo']);
                
                if (!$needsUpdate) {
                    $skipReason = 'already_updated';
                    $action = 'skipped';
                }
            }
        }

        // 🔥 CREAR REGISTRO EN BD
        $log = ProductUpdateLog::create([
            'upload_id' => $this->upload->id,
            'cod_barras' => $productData['cod_barras'],
            'codigo' => $productData['codigo'],
            'descripcion' => $productData['descripcion'],
            'precio_final' => $productData['precio_final'],
            'precio_calculado' => $productData['precio_descuento'],
            'precio_anterior_eretail' => null, // Se llenará cuando se envíe a eRetail
            'fec_ul_mo' => $productData['fec_ul_mo'],
            'action' => $needsUpdate ? $action : 'skipped',
            'status' => $needsUpdate ? 'pending' : 'skipped', // 🔥 CLAVE: pending = se enviará a eRetail
            'skip_reason' => $skipReason
        ]);

        // ✅ ACTUALIZAR CONTADORES
        if (!$needsUpdate) {
            $this->upload->increment('skipped_products');
        }

        Log::debug("Producto procesado", [
            'log_id' => $log->id,
            'cod_barras' => $productData['cod_barras'],
            'action' => $log->action,
            'status' => $log->status,
            'needs_update' => $needsUpdate
        ]);

        return $log;

    } catch (\Exception $e) {
        Log::error("Error procesando producto individual", [
            'product_data' => $productData,
            'error' => $e->getMessage()
        ]);
        throw $e;
    }
}

/**
 * 🔥 MÉTODO sendBatchToERetail FINAL SIMPLE - ExcelProcessorService.php
 * 
 * FLUJO SIMPLE: Tomar productos 'pending' de BD y enviarlos a eRetail
 * 
 * Reemplazar este método en app/Services/ExcelProcessorService.php
 */
private function sendBatchToERetail($productsBatch)
{
    Log::info("=== ENVIANDO BATCH A ERETAIL ===");
    Log::info("Cantidad de productos en batch: " . count($productsBatch));

    try {
        // 🔥 OBTENER IDs de los productos del batch para buscar en BD
        $productIds = array_column($productsBatch, 'id');
        
        // 🔥 OBTENER PRODUCTOS PENDIENTES DESDE LA BASE DE DATOS
        $dbProducts = ProductUpdateLog::where('upload_id', $this->upload->id)
            ->whereIn('id', $productIds)
            ->where('status', 'pending')
            ->get();

        if ($dbProducts->isEmpty()) {
            Log::warning("No se encontraron productos pendientes en BD");
            return false;
        }

        Log::info("Productos pendientes obtenidos de BD: " . $dbProducts->count());

        // 🔥 CONSTRUIR ARRAY PARA eRETAIL
        $eRetailProducts = [];
        
        foreach ($dbProducts as $dbProduct) {
            $eRetailProducts[] = $this->eRetailService->buildProductData([
                'id' => $dbProduct->id,                   // 🔥 ID único del registro en BD
                'codigo' => $dbProduct->codigo,           // Posición [4]: Código interno
                'cod_barras' => $dbProduct->cod_barras,   // Posición [5]: Código de barras
                'descripcion' => $dbProduct->descripcion,
                'precio_original' => $dbProduct->precio_final,
                'precio_promocional' => $dbProduct->precio_calculado
            ]);
        }

        // ✅ LOG PARA VERIFICAR ESTRUCTURA
        Log::info('Enviando a eRetail', [
            'productos_count' => count($eRetailProducts),
            'primera_estructura' => [
                'goodsCode_posicion_1' => $eRetailProducts[0]['items'][1] ?? 'N/A',
                'codigo_interno_posicion_4' => $eRetailProducts[0]['items'][4] ?? 'N/A', 
                'cod_barras_posicion_5' => $eRetailProducts[0]['items'][5] ?? 'N/A'
            ]
        ]);

        // 🔥 ENVIAR A eRETAIL
        $result = $this->eRetailService->saveProducts($eRetailProducts);

        // ✅ PROCESAR RESULTADO
        $isSuccess = isset($result['success']) && $result['success'] === true;

        Log::info('Respuesta de eRetail', [
            'success' => $isSuccess,
            'message' => $result['message'] ?? 'Sin mensaje'
        ]);

        if ($isSuccess) {
            // ✅ MARCAR PRODUCTOS COMO EXITOSOS
            $dbProducts->each(function($product) {
                $product->update(['status' => 'success']);
            });

            // ✅ INCREMENTAR CONTADORES POR ACCIÓN
            $createdCount = $dbProducts->where('action', 'created')->count();
            $updatedCount = $dbProducts->where('action', 'updated')->count();
            
            if ($createdCount > 0) {
                $this->upload->increment('created_products', $createdCount);
            }
            if ($updatedCount > 0) {
                $this->upload->increment('updated_products', $updatedCount);
            }

            Log::info("✅ Batch enviado exitosamente", [
                'total' => $dbProducts->count(),
                'created' => $createdCount,
                'updated' => $updatedCount
            ]);

        } else {
            // ❌ MARCAR PRODUCTOS COMO FALLIDOS
            $dbProducts->each(function($product) use ($result) {
                $product->update([
                    'status' => 'failed',
                    'error_message' => $result['message'] ?? 'Error desconocido en eRetail'
                ]);
            });

            $this->upload->increment('failed_products', $dbProducts->count());
            Log::error("❌ Error enviando batch: " . ($result['message'] ?? 'Error desconocido'));
        }

        return $isSuccess;

    } catch (\Exception $e) {
        Log::error("❌ Excepción enviando batch a eRetail", [
            'error' => $e->getMessage()
        ]);

        // ❌ MARCAR PRODUCTOS COMO FALLIDOS
        if (isset($productIds)) {
            ProductUpdateLog::where('upload_id', $this->upload->id)
                ->whereIn('id', $productIds)
                ->where('status', 'pending')
                ->update([
                    'status' => 'failed',
                    'error_message' => 'Excepción: ' . $e->getMessage()
                ]);

            $this->upload->increment('failed_products', count($productIds));
        }

        throw $e;
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