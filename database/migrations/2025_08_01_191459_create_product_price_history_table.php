<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_price_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->decimal('precio_original', 10, 2);
            $table->decimal('precio_promocional', 10, 2);
            $table->timestamp('fec_ul_mo')->nullable();
            $table->unsignedBigInteger('upload_id');
            $table->timestamps();
            
            // Índices básicos
            $table->index('product_id');
            $table->index('upload_id');
            $table->index('fec_ul_mo');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_price_history');
    }
};