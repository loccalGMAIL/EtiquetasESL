<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ERetailService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class TestERetailConnection extends Command
{
    protected $signature = 'eretail:test {--debug : Mostrar información detallada}';
    protected $description = 'Probar conexión con eRetail';

    public function handle()
    {
        $debug = $this->option('debug');
        
        $this->info('====================================');
        $this->info('Probando conexión con eRetail');
        $this->info('====================================');
        
        // Mostrar configuración actual
        $config = config('eretail');
        $this->table(['Parámetro', 'Valor'], [
            ['URL Base', $config['base_url']],
            ['Usuario', $config['username']],
            ['Password', str_repeat('*', strlen($config['password']))],
            ['Timeout', $config['timeout'] . ' segundos'],
        ]);
        
        // 1. Probar conexión básica de red
        $this->info("\n1. Probando conexión de red...");
        $this->testNetworkConnection($config['base_url'], $debug);
        
        // 2. Probar endpoint /api/hello
        $this->info("\n2. Probando endpoint de test...");
        $this->testHelloEndpoint($config['base_url'], $debug);
        
        // 3. Probar autenticación
        $this->info("\n3. Probando autenticación...");
        $this->testAuthentication($config, $debug);
        
        return 0;
    }
    
    private function testNetworkConnection($baseUrl, $debug)
    {
        $parsed = parse_url($baseUrl);
        $host = $parsed['host'] ?? 'localhost';
        $port = $parsed['port'] ?? 80;
        
        if ($debug) {
            $this->line("   Conectando a {$host}:{$port}");
        }
        
        $connection = @fsockopen($host, $port, $errno, $errstr, 5);
        
        if ($connection) {
            $this->info("   ✓ Conexión de red exitosa");
            fclose($connection);
        } else {
            $this->error("   ✗ No se puede conectar a {$host}:{$port}");
            $this->error("   Error: {$errstr} (código: {$errno})");
            
            // Sugerencias
            $this->warn("\n   Posibles soluciones:");
            $this->line("   - Verificar que eRetail esté ejecutándose");
            $this->line("   - Verificar la IP y puerto en el archivo .env");
            $this->line("   - Verificar firewall/antivirus");
            $this->line("   - Probar con: ping {$host}");
        }
    }
    
    private function testHelloEndpoint($baseUrl, $debug)
    {
        try {
            $client = new Client([
                'base_uri' => $baseUrl,
                'timeout' => 5,
                'verify' => false, // Deshabilitar verificación SSL por ahora
                'http_errors' => false
            ]);
            
            $fullUrl = rtrim($baseUrl, '/') . '/api/hello';
            
            if ($debug) {
                $this->line("   GET {$fullUrl}");
            }
            
            $response = $client->get('/api/hello');
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            
            if ($debug) {
                $this->line("   Status Code: {$statusCode}");
                $this->line("   Response: {$body}");
            }
            
            if ($statusCode === 200 && $body === 'OK') {
                $this->info("   ✓ Endpoint de test respondió correctamente");
            } else {
                $this->error("   ✗ Respuesta inesperada");
                $this->line("   Status: {$statusCode}");
                $this->line("   Body: {$body}");
            }
            
        } catch (GuzzleException $e) {
            $this->error("   ✗ Error al conectar: " . $e->getMessage());
            
            if ($debug) {
                $this->error("   Detalles: " . $e->getTraceAsString());
            }
        } catch (\Exception $e) {
            $this->error("   ✗ Error inesperado: " . $e->getMessage());
        }
    }
    
    private function testAuthentication($config, $debug)
    {
        try {
            $client = new Client([
                'base_uri' => $config['base_url'],
                'timeout' => 10,
                'verify' => false,
                'http_errors' => false
            ]);
            
            $payload = [
                'userName' => $config['username'],
                'password' => $config['password']
            ];
            
            if ($debug) {
                $this->line("   POST /api/login");
                $this->line("   Payload: " . json_encode($payload));
            }
            
            $response = $client->post('/api/login', [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ]);
            
            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);
            
            if ($debug) {
                $this->line("   Status Code: {$statusCode}");
                $this->line("   Response: " . json_encode($body, JSON_PRETTY_PRINT));
            }
            
            if ($statusCode === 200 && isset($body['code']) && $body['code'] === 0) {
                $this->info("   ✓ Autenticación exitosa");
                $this->line("   Token recibido: " . substr($body['body'], 0, 20) . "...");
            } else {
                $this->error("   ✗ Error de autenticación");
                $this->line("   Código: " . ($body['code'] ?? 'N/A'));
                $this->line("   Mensaje: " . ($body['message'] ?? 'Sin mensaje'));
            }
            
        } catch (\Exception $e) {
            $this->error("   ✗ Error: " . $e->getMessage());
        }
    }
}