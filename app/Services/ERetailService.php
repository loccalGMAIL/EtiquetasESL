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
     * Realizar petición autenticada
     */
    // private function authenticatedRequest($method, $endpoint, $options = [])
    // {
    //     if (!$this->token) {
    //         $this->login();
    //     }

    //     $options['headers'] = array_merge($options['headers'] ?? [], [
    //         'Authorization' => 'Bearer ' . $this->token
    //     ]);

    //     try {
    //         $response = $this->client->request($method, $endpoint, $options);
    //         return json_decode($response->getBody()->getContents(), true);

    //     } catch (GuzzleException $e) {
    //         // Si el error es 401, intentar login de nuevo
    //         if ($e->getCode() === 401) {
    //             Log::info('Token expirado, reautenticando...');
    //             Cache::forget('eretail_token');
    //             $this->token = null;
    //             $this->login();

    //             // Reintentar la petición
    //             $options['headers']['Authorization'] = 'Bearer ' . $this->token;
    //             $response = $this->client->request($method, $endpoint, $options);
    //             return json_decode($response->getBody()->getContents(), true);
    //         }

    //         throw $e;
    //     }
    // }

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
                'body_preview' => substr($responseBody, 0, 500) // Primeros 500 caracteres
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
     * Buscar producto por código de barras
     */
    public function findProduct($codBarras, $shopCode = null)
    {
        $shopCode = $shopCode ?? $this->config['default_shop_code'];

        try {
            $response = $this->authenticatedRequest('POST', '/api/Goods/getList', [
                'json' => [
                    'pageIndex' => 1,
                    'pageSize' => 1,
                    'shopCode' => $shopCode,
                    'goodsCode' => $codBarras
                ]
            ]);

            if ($response['code'] === 0 && isset($response['body']['itemList']) && count($response['body']['itemList']) > 0) {
                return $response['body']['itemList'][0];
            }

            return null;

        } catch (GuzzleException $e) {
            Log::error("Error buscando producto {$codBarras}: " . $e->getMessage());
            throw new ERetailException("Error al buscar producto: " . $e->getMessage());
        }
    }

    /**
     * Crear o actualizar productos
     */
    // public function saveProducts($products)
    // {
    //     try {
    //         $response = $this->authenticatedRequest('POST', '/api/goods/saveList?NR=false', [
    //             'json' => $products
    //         ]);

    //         if ($response['code'] === 0) {
    //             return [
    //                 'success' => true,
    //                 'message' => $response['message'] ?? 'Productos guardados correctamente',
    //                 'body' => $response['body'] ?? null
    //             ];
    //         }

    //         throw new ERetailException($response['message'] ?? 'Error al guardar productos');

    //     } catch (GuzzleException $e) {
    //         Log::error('Error guardando productos: ' . $e->getMessage());
    //         throw new ERetailException('Error al guardar productos: ' . $e->getMessage());
    //     }
    // }

    public function saveProducts($products)
    {
        try {
            // ✅ LOG DEL JSON QUE SE ENVIARÁ
            Log::info('JSON enviado a eRetail', [
                'url' => '/api/goods/saveList?NR=false',
                'productos_count' => count($products),
                'json_preview' => json_encode(array_slice($products, 0, 2), JSON_PRETTY_PRINT) // Solo 2 para no saturar logs
            ]);

            $response = $this->authenticatedRequest('POST', '/api/goods/saveList?NR=false', [
                'json' => $products
            ]);

            // ✅ LOG DE LA RESPUESTA COMPLETA
            Log::info('Respuesta completa de eRetail', [
                'response' => $response
            ]);

            if ($response['code'] === 0) {
                return [
                    'success' => true,
                    'message' => $response['message'] ?? 'Productos guardados correctamente',
                    'body' => $response['body'] ?? null
                ];
            }

            // ✅ LOG DEL ERROR
            Log::error('Error en respuesta de eRetail', [
                'code' => $response['code'],
                'message' => $response['message'] ?? 'Sin mensaje de error'
            ]);

            throw new ERetailException($response['message'] ?? 'Error al guardar productos');

        } catch (GuzzleException $e) {
            Log::error('Error HTTP enviando productos a eRetail', [
                'status_code' => $e->getCode(),
                'message' => $e->getMessage(),
                'response_body' => method_exists($e, 'getResponse') && $e->getResponse()
                    ? $e->getResponse()->getBody()->getContents()
                    : 'Sin respuesta'
            ]);
            throw new ERetailException('Error al enviar productos: ' . $e->getMessage());
        }
    }


    /**
     * Construir array de producto para eRetail
     */
    // public function buildProductData($productInfo, $shopCode = null)
    // {
    //     $shopCode = $shopCode ?? $this->config['default_shop_code'];

    //     return [
    //         'shopCode' => $shopCode,
    //         'template' => 'REG', // Template regular
    //         'items' => [
    //             $shopCode,                          // [0] Código tienda cliente
    //             $productInfo['cod_barras'],         // [1] Código único
    //             $productInfo['descripcion'],        // [2] Nombre producto
    //             $productInfo['cod_barras'],         // [3] UPC1
    //             '',                                 // [4] UPC2
    //             '',                                 // [5] UPC3
    //             (string)$productInfo['precio_original'],    // [6] Precio original
    //             (string)$productInfo['precio_descuento'],   // [7] Precio promocional
    //             '',                                 // [8] Precio miembro
    //             'Argentina',                        // [9] Origen
    //             'Unidad',                          // [10] Especificación
    //             'UN',                              // [11] Unidad
    //             '',                                // [12] Grado
    //             '',                                // [13] Fecha inicio promo
    //             '',                                // [14] Fecha fin promo
    //             '',                                // [15] Código QR
    //             'Sistema',                         // [16] Responsable precio
    //             '0',                               // [17] Inventario
    //             '',                                // [18-26] Campos adicionales vacíos
    //             '', '', '', '', '', '', '', ''
    //         ]
    //     ];
    // }
    public function buildProductData($productInfo, $shopCode = null)
    {
        $shopCode = $shopCode ?? $this->config['default_shop_code'];

        return [
            'shopCode' => $shopCode,
            'template' => 'REG',
            'items' => [
                $shopCode,                              // [0] Código tienda cliente
                $productInfo['cod_barras'],             // [1] Código único
                $productInfo['descripcion'],            // [2] Nombre producto
                $productInfo['cod_barras'],             // [3] UPC1
                '',                                     // [4] UPC2
                '',                                     // [5] UPC3
                (string) $productInfo['precio_original'],     // [6] ✅ Precio original (SIN descuento)
                (string) $productInfo['precio_promocional'],  // [7] ✅ Precio promocional (CON descuento)
                '',                                     // [8] Precio miembro
                'Argentina',                            // [9] Origen
                'Unidad',                              // [10] Especificación
                'UN',                                  // [11] Unidad
                '',                                    // [12] Grado
                '',                                    // [13] Fecha inicio promo
                '',                                    // [14] Fecha fin promo
                '',                                    // [15] Código QR
                'Sistema',                             // [16] Responsable precio
                '0',                                   // [17] Inventario
                '',                                    // [18-26] Campos adicionales vacíos
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
}