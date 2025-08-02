<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('original_filename');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            
            // Estadísticas básicas
            $table->integer('total_products')->default(0);
            $table->integer('processed_products')->default(0);
            $table->integer('created_products')->default(0);
            $table->integer('updated_products')->default(0);
            $table->integer('failed_products')->default(0);
            $table->integer('skipped_products')->default(0);
            
            // Estadísticas de variantes
            $table->integer('total_variants_processed')->default(0);
            $table->integer('new_variants_created')->default(0);
            $table->integer('existing_variants_updated')->default(0);
            $table->integer('variants_skipped')->default(0);
            $table->integer('price_changes_recorded')->default(0);
            
            // Usuario y configuración
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('shop_code')->default('0001');
            $table->text('error_message')->nullable();
            
            $table->timestamps();
            
            // Índices básicos
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('uploads');
    }
};