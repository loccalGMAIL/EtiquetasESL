<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_last_updates', function (Blueprint $table) {
            $table->id();
            $table->string('cod_barras')->unique();
            $table->timestamp('last_update_date');
            $table->decimal('last_price', 10, 2)->nullable();
            $table->string('last_description')->nullable();
            $table->unsignedBigInteger('last_upload_id')->nullable();
            $table->timestamps();
            
            $table->index('cod_barras');
            $table->index('last_update_date');
            $table->foreign('last_upload_id')
                  ->references('id')
                  ->on('uploads')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_last_updates');
    }
};