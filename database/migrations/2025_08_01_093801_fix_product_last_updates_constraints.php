<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Arregla el problema de códigos de barras duplicados:
     * 1. Elimina restricción UNIQUE de cod_barras
     * 2. Agrega índice único compuesto para codigo + last_description
     */
    public function up(): void
    {
        Schema::table('product_last_updates', function (Blueprint $table) {
            // 🔥 1. ELIMINAR RESTRICCIÓN UNIQUE DE cod_barras (si existe)
            try {
                $table->dropUnique(['cod_barras']); // Intenta eliminar índice único por nombre de columna
            } catch (\Exception $e) {
                // Si falla, intenta eliminar por nombre de índice específico
                try {
                    $table->dropIndex('product_last_updates_cod_barras_unique');
                } catch (\Exception $e2) {
                    // Log del error pero continúa la migración
                    \Log::info('No se pudo eliminar restricción de cod_barras: ' . $e2->getMessage());
                }
            }
            
            // 🔥 2. AGREGAR ÍNDICE ÚNICO COMPUESTO (codigo + descripcion)
            // Esto asegura que no haya productos duplicados realmente únicos
            $table->unique(['codigo', 'last_description'], 'unique_product_codigo_descripcion');
            
            // 🔥 3. AGREGAR ÍNDICE NORMAL para cod_barras (para performance)
            // Permite duplicados pero mantiene velocidad de consulta
            $table->index('cod_barras', 'idx_cod_barras');
        });
    }

    /**
     * Reverse the migrations.
     * 
     * Revierte los cambios en caso de rollback
     */
    public function down(): void
    {
        Schema::table('product_last_updates', function (Blueprint $table) {
            // Revertir cambios
            
            // 1. Eliminar índice único compuesto
            $table->dropUnique('unique_product_codigo_descripcion');
            
            // 2. Eliminar índice normal de cod_barras
            $table->dropIndex('idx_cod_barras');
            
            // 3. NOTA: No re-agregamos UNIQUE a cod_barras porque causaría problemas
            // Si necesitas revertir completamente, hazlo manualmente
        });
    }
};