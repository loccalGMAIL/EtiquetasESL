<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('codigo_interno', 255);
            $table->string('cod_barras', 255);
            $table->string('descripcion', 500);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Índices básicos
            $table->index('product_id');
            $table->index('codigo_interno');
            $table->index('cod_barras');
            $table->index('is_active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_variants');
    }
};