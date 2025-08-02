<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\ERetailException;

class ERetailService
{
    private $client;
    private $token;
    private $config;

    public function __construct()
    {
        $this->config = config('eretail');

        $this->client = new Client([
            'base_uri' => $this->config['base_url'],
            'timeout' => $this->config['timeout'],
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);
    }

    /**
     * Autenticación con eRetail
     */
    public function login()
    {
        try {
            // Intentar obtener token del cache
            $this->token = Cache::get('eretail_token');

            if ($this->token) {
                return true;
            }

            $response = $this->client->post('/api/login', [
                'json' => [
                    'userName' => $this->config['username'],
                    'password' => $this->config['password']
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if ($data['code'] === 0) {
                $this->token = $data['body'];
                // Guardar token en cache por 5 horas (el token dura 6)
                Cache::put('eretail_token', $this->token, now()->addHours(5));

                Log::info('eRetail login successful');
                return true;
            }

            throw new ERetailException($data['message'] ?? 'Login failed');

        } catch (GuzzleException $e) {
            Log::error('eRetail login error: ' . $e->getMessage());
            throw new ERetailException('Error de conexión con eRetail: ' . $e->getMessage());
        }
    }

    /**
     * 🔥 CREAR O ACTUALIZAR PRODUCTOS - ACTUALIZADO PARA NUEVA ARQUITECTURA
     */
    public function saveProducts($products)
    {
        try {
            // Log del JSON que se enviará
            Log::info('JSON enviado a eRetail - Nueva Arquitectura', [
                'url' => '/api/goods/saveList?NR=false',
                'productos_count' => count($products),
                'arquitectura' => 'ProductVariant',
                'json_preview' => json_encode(array_slice($products, 0, 2), JSON_PRETTY_PRINT)
            ]);

            $response = $this->authenticatedRequest('POST', '/api/goods/saveList?NR=false', [
                'json' => $products
            ]);

            // Log de la respuesta completa
            Log::info('Respuesta completa de eRetail', [
                'response' => $response,
                'arquitectura' => 'ProductVariant'
            ]);

            if ($response['code'] === 0) {
                return [
                    'success' => true,
                    'message' => $response['message'] ?? 'Productos guardados correctamente',
                    'body' => $response['body'] ?? null
                ];
            }

            // Log del error
            Log::error('Error en respuesta de eRetail', [
                'code' => $response['code'],
                'message' => $response['message'] ?? 'Sin mensaje de error',
                'arquitectura' => 'ProductVariant'
            ]);

            throw new ERetailException($response['message'] ?? 'Error al guardar productos');

        } catch (GuzzleException $e) {
            Log::error('Error HTTP enviando productos a eRetail', [
                'status_code' => $e->getCode(),
                'message' => $e->getMessage(),
                'arquitectura' => 'ProductVariant',
                'response_body' => ($e instanceof \GuzzleHttp\Exception\RequestException && $e->getResponse())
                    ? $e->getResponse()->getBody()->getContents()
                    : 'Sin respuesta'
            ]);
            throw new ERetailException('Error al enviar productos: ' . $e->getMessage());
        }
    }

    /**
     * 🔥 CONSTRUIR ARRAY DE PRODUCTO PARA eRETAIL - ACTUALIZADO PARA ProductVariant
     * 
     * CRÍTICO: Ahora recibe ProductVariant.id como 'id' que se usa como goodsCode
     */
    public function buildProductData($productInfo, $shopCode = null)
    {
        $shopCode = $shopCode ?? $this->config['default_shop_code'];

        // Validar que tenemos el ID de ProductVariant
        if (!isset($productInfo['id']) || empty($productInfo['id'])) {
            throw new ERetailException('ProductVariant ID es requerido para buildProductData');
        }

        // Construir descripción con código de barras
        $descripcion = $productInfo['descripcion'] ?? '';
        $codigoBarras = $productInfo['cod_barras'] ?? '';
        
        if (!empty($codigoBarras)) {
            $descripcion = $descripcion . ' - CB: ' . $codigoBarras;
        }

        // 🔥 CRÍTICO: productInfo['id'] ahora es ProductVariant.id (ESTABLE)
        $goodsCode = $productInfo['id']; // ← ProductVariant.id

        Log::debug('Construyendo producto para eRetail', [
            'variant_id_como_goodsCode' => $goodsCode,
            'codigo_interno' => $productInfo['codigo'] ?? 'N/A',
            'cod_barras' => $codigoBarras,
            'descripcion_truncada' => substr($descripcion, 0, 50),
            'precio_original' => $productInfo['precio_original'] ?? 0,
            'precio_promocional' => $productInfo['precio_promocional'] ?? 0
        ]);

        return [
            'shopCode' => $shopCode,
            'template' => 'REG',
            'items' => [
                $shopCode,                                          // [0] Código tienda cliente
                (string) $goodsCode,                               // [1] 🔥 ProductVariant.id como goodsCode
                $descripcion,                                       // [2] Nombre producto + Codigo de barras
                '',                                                // [3] GoodsShortForm
                $productInfo['codigo'] ?? '',                      // [4] UPC1 - Código interno
                $codigoBarras,                                     // [5] UPC2 - Código de barras
                '',                                                // [6] UPC3
                (string) ($productInfo['precio_original'] ?? 0),   // [7] Precio original (SIN descuento)
                (string) ($productInfo['precio_promocional'] ?? 0), // [8] Precio promocional (CON descuento)
                '',                                                // [9] Precio miembro
                'Argentina',                                       // [10] Origen
                'Unidad',                                         // [11] Especificación
                'UN',                                             // [12] Unidad
                '',                                               // [13] Grado
                '',                                               // [14] Fecha inicio promo
                '',                                               // [15] Fecha fin promo
                '',                                               // [16] Código QR
                'Sistema',                                        // [17] Responsable precio
                '0',                                              // [18] Inventario
                '',                                               // [19-26] Campos adicionales vacíos
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                ''
            ]
        ];
    }

    /**
     * Realizar petición autenticada
     */
    private function authenticatedRequest($method, $endpoint, $options = [])
    {
        if (!$this->token) {
            Log::info('Token no encontrado, autenticando...');
            $this->login();
        }

        $options['headers'] = array_merge($options['headers'] ?? [], [
            'Authorization' => 'Bearer ' . $this->token
        ]);

        try {
            Log::info('Haciendo petición a eRetail', [
                'method' => $method,
                'endpoint' => $endpoint,
                'has_token' => !empty($this->token)
            ]);

            $response = $this->client->request($method, $endpoint, $options);
            $responseBody = $response->getBody()->getContents();

            Log::info('Respuesta HTTP de eRetail', [
                'status_code' => $response->getStatusCode(),
                'body_preview' => substr($responseBody, 0, 500)
            ]);

            return json_decode($responseBody, true);

        } catch (GuzzleException $e) {
            // Si el error es 401, intentar login de nuevo
            if ($e->getCode() === 401) {
                Log::warning('Token expirado (401), reautenticando...');
                Cache::forget('eretail_token');
                $this->token = null;
                $this->login();

                // Reintentar la petición
                $options['headers']['Authorization'] = 'Bearer ' . $this->token;
                $response = $this->client->request($method, $endpoint, $options);
                return json_decode($response->getBody()->getContents(), true);
            }

            throw $e;
        }
    }

    /**
     * 🔥 BUSCAR PRODUCTO POR CÓDIGO DE BARRAS - ACTUALIZADO
     */
    public function findProduct($codBarras, $shopCode = null)
    {
        $shopCode = $shopCode ?? $this->config['default_shop_code'];

        try {
            Log::info('Buscando producto en eRetail', [
                'cod_barras' => $codBarras,
                'shop_code' => $shopCode
            ]);

            $response = $this->authenticatedRequest('POST', '/api/Goods/getList', [
                'json' => [
                    'pageIndex' => 1,
                    'pageSize' => 1,
                    'shopCode' => $shopCode,
                    'goodsCode' => $codBarras
                ]
            ]);

            if ($response['code'] === 0 && isset($response['body']['itemList']) && count($response['body']['itemList']) > 0) {
                Log::info('Producto encontrado en eRetail', [
                    'cod_barras' => $codBarras,
                    'goodsCode_encontrado' => $response['body']['itemList'][0]['goodsCode'] ?? 'N/A'
                ]);
                return $response['body']['itemList'][0];
            }

            Log::info('Producto no encontrado en eRetail', [
                'cod_barras' => $codBarras
            ]);
            return null;

        } catch (GuzzleException $e) {
            Log::error("Error buscando producto {$codBarras}: " . $e->getMessage());
            throw new ERetailException("Error al buscar producto: " . $e->getMessage());
        }
    }

    /**
     * 🔥 BUSCAR PRODUCTO POR ProductVariant.id (goodsCode)
     * 
     * Nuevo método para buscar por el ID estable de ProductVariant
     */
    public function findProductByVariantId($variantId, $shopCode = null)
    {
        $shopCode = $shopCode ?? $this->config['default_shop_code'];

        try {
            Log::info('Buscando producto por ProductVariant.id en eRetail', [
                'variant_id' => $variantId,
                'shop_code' => $shopCode
            ]);

            $response = $this->authenticatedRequest('POST', '/api/Goods/getList', [
                'json' => [
                    'pageIndex' => 1,
                    'pageSize' => 1,
                    'shopCode' => $shopCode,
                    'goodsCode' => (string) $variantId
                ]
            ]);

            if ($response['code'] === 0 && isset($response['body']['itemList']) && count($response['body']['itemList']) > 0) {
                Log::info('Producto encontrado por ProductVariant.id', [
                    'variant_id' => $variantId,
                    'goodsCode_encontrado' => $response['body']['itemList'][0]['goodsCode'] ?? 'N/A'
                ]);
                return $response['body']['itemList'][0];
            }

            Log::info('Producto no encontrado por ProductVariant.id', [
                'variant_id' => $variantId
            ]);
            return null;

        } catch (GuzzleException $e) {
            Log::error("Error buscando producto por variant ID {$variantId}: " . $e->getMessage());
            throw new ERetailException("Error al buscar producto por variant ID: " . $e->getMessage());
        }
    }

    /**
     * Test de conexión
     */
    public function testConnection()
    {
        try {
            $response = $this->client->get('/api/hello');
            return $response->getBody()->getContents() === 'OK';
        } catch (GuzzleException $e) {
            return false;
        }
    }

    /**
     * 🔥 ACTUALIZAR ETIQUETAS ESPECÍFICAS - ACTUALIZADO PARA ProductVariant
     * 
     * Ahora acepta array de ProductVariant IDs
     */
    public function refreshSpecificTags($variantIds, $shopCode = null)
    {
        $shopCode = $shopCode ?? $this->config['default_shop_code'];

        try {
            Log::info('Refrescando etiquetas específicas', [
                'variant_ids_count' => count($variantIds),
                'shop_code' => $shopCode,
                'primeros_3_ids' => array_slice($variantIds, 0, 3)
            ]);

            // Crear array de productos para refresh
            $refreshData = [];
            foreach ($variantIds as $variantId) {
                $refreshData[] = [
                    'shopCode' => $shopCode,
                    'tagID' => '',
                    'goodsCode' => (string) $variantId, // ProductVariant.id como goodsCode
                    'goodsName' => '',
                    'template' => 'REG',
                    'items' => []
                ];
            }

            $response = $this->authenticatedRequest('POST', '/api/esl/tag/pushList', [
                'json' => $refreshData
            ]);

            if ($response['code'] === 0) {
                Log::info('Etiquetas refrescadas exitosamente', [
                    'variant_ids_count' => count($variantIds),
                    'message' => $response['message'] ?? 'Sin mensaje'
                ]);
                return true;
            }

            Log::error('Error refrescando etiquetas', [
                'code' => $response['code'],
                'message' => $response['message'] ?? 'Sin mensaje'
            ]);
            
            return false;

        } catch (GuzzleException $e) {
            Log::error('Error HTTP refrescando etiquetas: ' . $e->getMessage());
            throw new ERetailException('Error al refrescar etiquetas: ' . $e->getMessage());
        }
    }

    /**
     * 🔥 VALIDAR QUE PRODUCTO EXISTE EN eRETAIL POR ProductVariant.id
     */
    public function validateProductExists($variantId, $shopCode = null)
    {
        try {
            $product = $this->findProductByVariantId($variantId, $shopCode);
            return $product !== null;
        } catch (\Exception $e) {
            Log::error("Error validando existencia del producto variant ID {$variantId}: " . $e->getMessage());
            return false;
        }
    }
}