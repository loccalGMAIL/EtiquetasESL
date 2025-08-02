<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        // 🔥 SERVICIOS PRINCIPALES - NUEVA ARQUITECTURA
        
        // ERetailService - Comunicación con eRetail
        $this->app->singleton(\App\Services\ERetailService::class, function ($app) {
            return new \App\Services\ERetailService();
        });

        // ExcelProcessorService - Procesamiento de archivos Excel
        $this->app->singleton(\App\Services\ExcelProcessorService::class, function ($app) {
            return new \App\Services\ExcelProcessorService(
                $app->make(\App\Services\ERetailService::class)
            );
        });

        // 🔥 SERVICIOS ESPECIALIZADOS - NUEVA ARQUITECTURA

        // ProductService - Gestión de productos maestros
        $this->app->singleton(\App\Services\ProductService::class, function ($app) {
            return new \App\Services\ProductService();
        });

        // ProductVariantService - Gestión de variantes
        $this->app->singleton(\App\Services\ProductVariantService::class, function ($app) {
            return new \App\Services\ProductVariantService();
        });

        // PriceHistoryService - Gestión de histórico de precios
        // $this->app->singleton(\App\Services\PriceHistoryService::class, function ($app) {
        //     return new \App\Services\PriceHistoryService();
        // });

        // UploadLogService - Gestión de logs de procesamiento
        $this->app->singleton(\App\Services\UploadLogService::class, function ($app) {
            return new \App\Services\UploadLogService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}