<?php

namespace App\Services;

use App\Models\Upload;
use App\Models\ProductUpdateLog;
use App\Models\ProductLastUpdate;
use App\Models\AppSetting;
use App\Services\ERetailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
require 'vendor/autoload.php';

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
        $this->upload = Upload::find($uploadId);
        
        if (!$this->upload) {
            throw new \Exception('Upload no encontrado');
        }
        
        try {
            // Marcar como procesando
            $this->upload->update(['status' => 'processing']);
            
            // Leer archivo Excel usando PhpSpreadsheet
            $fullPath = $filePath;
            
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
     * Procesar array de productos
     */
    private function processProducts($rows)
    {
        // Obtener encabezados
        $headers = $this->normalizeHeaders($rows[0]);
        
        // Validar que existan los campos necesarios
        $this->validateHeaders($headers);
        
        // Obtener índices de columnas
        $codBarrasIndex = array_search('cod_barras', $headers);
        $descripcionIndex = array_search('descripcion', $headers);
        $finalIndex = array_search('final', $headers);
        $fecUlMoIndex = array_search('fec_ul_mo', $headers);
        
        // Remover fila de encabezados
        unset($rows[0]);
        
        $totalProducts = count($rows);
        $this->upload->update(['total_products' => $totalProducts]);
        
        $productsBatch = [];
        $processedCount = 0;
        
        foreach ($rows as $index => $row) {
            try {
                // Validar fila
                if ($this->isEmptyRow($row)) {
                    continue;
                }
                
                // Extraer datos
                $productData = [
                    'cod_barras' => $this->cleanValue($row[$codBarrasIndex] ?? ''),
                    'descripcion' => $this->cleanValue($row[$descripcionIndex] ?? ''),
                    'precio_final' => $this->parsePrice($row[$finalIndex] ?? 0),
                    'fec_ul_mo' => $this->parseDate($row[$fecUlMoIndex] ?? null)
                ];
                
                // Validar datos del producto
                $this->validateProduct($productData);
                
                // Calcular precio con descuento
                $productData['precio_descuento'] = round($productData['precio_final'] * (1 - $this->discountPercentage / 100), 2);
                
                // Procesar producto
                $this->processSingleProduct($productData);
                
                // Agregar al batch para eRetail
                $productsBatch[] = $productData;
                
                // Procesar en lotes de 50
                if (count($productsBatch) >= 50) {
                    $this->sendBatchToERetail($productsBatch);
                    $productsBatch = [];
                }
                
                $processedCount++;
                
                // Actualizar progreso cada 10 productos
                if ($processedCount % 10 == 0) {
                    $this->upload->update(['processed_products' => $processedCount]);
                }
                
            } catch (\Exception $e) {
                Log::warning("Error procesando fila {$index}: " . $e->getMessage());
                
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
            $this->sendBatchToERetail($productsBatch);
        }
        
        // Actualizar conteo final
        $this->upload->update(['processed_products' => $processedCount]);
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
        try {
            $eRetailProducts = [];
            
            foreach ($products as $product) {
                // Buscar si existe en eRetail
                $existingProduct = $this->eRetailService->findProduct($product['cod_barras']);
                
                $eRetailProducts[] = $this->eRetailService->buildProductData([
                    'cod_barras' => $product['cod_barras'],
                    'descripcion' => $product['descripcion'],
                    'precio_original' => $product['precio_final'],
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
            $result = $this->eRetailService->saveProducts($eRetailProducts);
            
            if ($result['success']) {
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
            }
            
        } catch (\Exception $e) {
            Log::error("Error enviando batch a eRetail: " . $e->getMessage());
            
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
                'descripción' => 'descripcion',
                'descripcion' => 'descripcion',
                'final ($)' => 'final',
                'final($)' => 'final',
                'precio final' => 'final',
                'final' => 'final',
                'feculmo' => 'fec_ul_mo',
                'fec ul mo' => 'fec_ul_mo',
                'fecha ultima modificacion' => 'fec_ul_mo',
                'fecha' => 'fec_ul_mo'
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
        
        // Remover símbolos de moneda y espacios
        $value = preg_replace('/[^0-9.,]/', '', $value);
        
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
                $unix = ($value - 25569) * 86400;
                return Carbon::createFromTimestamp($unix);
            }
            
            // Intentar varios formatos
            $formats = [
                'Y-m-d H:i:s',
                'd/m/Y H:i:s',
                'd-m-Y H:i:s',
                'Y-m-d',
                'd/m/Y',
                'd-m-Y'
            ];
            
            foreach ($formats as $format) {
                try {
                    $date = Carbon::createFromFormat($format, $value);
                    if ($date) {
                        return $date;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
            
            // Si ningún formato funciona, intentar parse automático
            return Carbon::parse($value);
            
        } catch (\Exception $e) {
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