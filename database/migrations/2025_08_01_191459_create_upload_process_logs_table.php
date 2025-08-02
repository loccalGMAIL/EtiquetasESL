<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('upload_process_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('upload_id');
            $table->unsignedBigInteger('product_variant_id');
            $table->enum('action', ['created', 'updated', 'skipped']);
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->boolean('price_changed')->default(false);
            $table->boolean('barcode_changed')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            // Índices básicos
            $table->index('upload_id');
            $table->index('product_variant_id');
            $table->index('status');
            $table->index('action');
        });
    }

    public function down()
    {
        Schema::dropIfExists('upload_process_logs');
    }
};