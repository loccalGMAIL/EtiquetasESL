<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_interno', 255);
            $table->decimal('precio_actual', 10, 2)->default(0.00);
            $table->timestamp('last_price_update')->nullable();
            $table->timestamps();
            
            // Índices básicos
            $table->unique('codigo_interno');
            $table->index('precio_actual');
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};