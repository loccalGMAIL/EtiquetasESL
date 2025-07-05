<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Agregar columna 'codigo' a product_update_logs
        Schema::table('product_update_logs', function (Blueprint $table) {
            $table->string('codigo')->after('cod_barras')->nullable();
            $table->index('codigo'); // Índice para búsquedas rápidas
        });

        // Agregar columna 'codigo' a product_last_updates
        Schema::table('product_last_updates', function (Blueprint $table) {
            $table->string('codigo')->after('cod_barras')->nullable();
            $table->index('codigo'); // Índice para búsquedas rápidas
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('product_update_logs', function (Blueprint $table) {
            $table->dropIndex(['codigo']);
            $table->dropColumn('codigo');
        });

        Schema::table('product_last_updates', function (Blueprint $table) {
            $table->dropIndex(['codigo']);
            $table->dropColumn('codigo');
        });
    }
};