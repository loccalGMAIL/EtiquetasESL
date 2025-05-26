<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_update_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('upload_id');
            $table->string('cod_barras');
            $table->string('descripcion');
            $table->decimal('precio_final', 10, 2);
            $table->decimal('precio_calculado', 10, 2);
            $table->decimal('precio_anterior_eretail', 10, 2)->nullable();
            $table->timestamp('fec_ul_mo')->nullable();
            $table->enum('action', ['created', 'updated', 'skipped']);
            $table->enum('status', ['success', 'failed', 'skipped'])->default('success');
            $table->string('skip_reason')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->foreign('upload_id')->references('id')->on('uploads')->onDelete('cascade');
            $table->index('cod_barras');
            $table->index('status');
            $table->index(['upload_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_update_logs');
    }
};