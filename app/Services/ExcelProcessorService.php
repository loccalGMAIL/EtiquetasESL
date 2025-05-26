<?php
// app/Services/ExcelProcessorService.php (archivo completo)

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
    
    public function __construct(ERetailService $eRetailService)
    {
        $this->eRetailService = $eRetailService;
        $this->discountPercentage = AppSetting::get('discount_percentage', 12);
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
        Log::info("Procesando productos...");
        
        // Obtener encabezados
        $headers = $this->normalizeHeaders($rows[0]);
        Log::info("Headers encontrados: " . json_encode($headers));
        
        // Validar que existan los campos necesarios
        $this->validateHeaders($headers);
        
        // Obtener índices de columnas
        $codBarrasIndex = array_search('cod_barras', $headers);
        $descripcionIndex = array_search('descripcion', $headers);
        $finalIndex = array_search('final', $headers);
        $fecUlMoIndex = array_search('fec_ul_mo', $headers);
        
        Log::info("Índices de columnas - CodBarras: {$codBarrasIndex}, Descripcion: {$descripcionIndex}, Final: {$finalIndex}, FecUlMo: {$fecUlMoIndex}");
        
        // Remover fila de encabezados
        unset($rows[0]);
        
        $totalProducts = count($rows);
        $this->upload->update(['total_products' => $totalProducts]);
        Log::info("Total de productos a procesar: {$totalProducts}");
        
        // Primero, autenticarse con eRetail
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
        $rowNumber = 1; // Empezamos en 1 porque ya quitamos los headers
        
        foreach ($rows as $index => $row) {
            $rowNumber++;
            
            try {
                // Validar fila
                if ($this->isEmptyRow($row)) {
                    Log::debug("Fila {$rowNumber} vacía, omitiendo");
                    continue;
                }
                
                // Extraer datos
                $productData = [
                    'cod_barras' => $this->cleanValue($row[$codBarrasIndex] ?? ''),
                    'descripcion' => $this->cleanValue($row[$descripcionIndex] ?? ''),
                    'precio_final' => $this->parsePrice($row[$finalIndex] ?? 0),
                    'fec_ul_mo' => $this->parseDate($row[$fecUlMoIndex] ?? null)
                ];
                
                Log::debug("Fila {$rowNumber} - Producto: " . json_encode($productData));
                
                // Validar datos del producto
                $this->validateProduct($productData);
                
                // Calcular precio con descuento
                $productData['precio_descuento'] = round($productData['precio_final'] * (1 - $this->discountPercentage / 100), 2);
                $productData['precio_original'] = $productData['precio_final'];
                
                Log::debug("Precio original: {$productData['precio_original']}, Precio con descuento: {$productData['precio_descuento']}");
                
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
        Log::info("Procesamiento terminado. Total procesados: {$processedCount}");
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
                Log::debug("Preparando producto {$product['cod_barras']} para envío");
                
                // Buscar si existe en eRetail
                $existingProduct = null;
                try {
                    $existingProduct = $this->eRetailService->findProduct($product['cod_barras']);
                    if ($existingProduct) {
                        Log::debug("Producto {$product['cod_barras']} encontrado en eRetail");
                    } else {
                        Log::debug("Producto {$product['cod_barras']} NO encontrado en eRetail, será creado");
                    }
                } catch (\Exception $e) {
                    Log::warning("Error buscando producto {$product['cod_barras']}: " . $e->getMessage());
                }
                
                $eRetailProducts[] = $this->eRetailService->buildProductData([
                    'cod_barras' => $product['cod_barras'],
                    'descripcion' => $product['descripcion'],
                    'precio_original' => $product['precio_original'],
                    'precio_descuento' => $product['precio_descuento']
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
            
            // Enviar a eRetail
            Log::info("Enviando " . count($eRetailProducts) . " productos a eRetail...");
            $result = $this->eRetailService->saveProducts($eRetailProducts);
            
            Log::info("Respuesta de eRetail: " . json_encode($result));
            
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
            Log::error("Mensaje: " . $e->getMessage());
            
            // Marcar productos como fallidos
            foreach ($products as $product) {
                ProductUpdateLog::where('upload_id', $this->upload->id)
                    ->where('cod_barras', $product['cod_barras'])
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage()
                    ]);
                    
                $this->upload->increment('failed_products');
            }
            
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
            $header = strtolower(trim($header));
            
            // Mapeo de nombres conocidos
            $mappings = [
                'cod.barras' => 'cod_barras',
                'cod barras' => 'cod_barras',
                'codigo de barras' => 'cod_barras',
                'código' => 'cod_barras',
                'codigo' => 'cod_barras',
                'cod_barras' => 'cod_barras',
                'descripción' => 'descripcion',
                'descripcion' => 'descripcion',
                'nombre' => 'descripcion',
                'producto' => 'descripcion',
                'final ($)' => 'final',
                'final($)' => 'final',
                'precio final' => 'final',
                'final' => 'final',
                'precio' => 'final',
                'feculmo' => 'fec_ul_mo',
                'fec ul mo' => 'fec_ul_mo',
                'fecha ultima modificacion' => 'fec_ul_mo',
                'fecha' => 'fec_ul_mo',
                'fec_ul_mo' => 'fec_ul_mo',
                'fecha modificacion' => 'fec_ul_mo'
            ];
            
            return $mappings[$header] ?? $header;
        }, $headers);
    }
    
    /**
     * Validar encabezados requeridos
     */
    private function validateHeaders($headers)
    {
        $required = ['cod_barras', 'descripcion', 'final', 'fec_ul_mo'];
        $missing = array_diff($required, $headers);
        
        if (!empty($missing)) {
            Log::error("Columnas faltantes: " . implode(', ', $missing));
            Log::error("Columnas encontradas: " . implode(', ', $headers));
            throw new \Exception('Faltan columnas requeridas: ' . implode(', ', $missing));
        }
    }
    
    /**
     * Verificar si una fila está vacía
     */
    private function isEmptyRow($row)
    {
        return empty(array_filter($row, function ($value) {
            return !is_null($value) && $value !== '';
        }));
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