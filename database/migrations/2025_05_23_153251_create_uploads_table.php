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
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])
                  ->default('pending');
            $table->integer('total_products')->default(0);
            $table->integer('processed_products')->default(0);
            $table->integer('created_products')->default(0);
            $table->integer('updated_products')->default(0);
            $table->integer('failed_products')->default(0);
            $table->integer('skipped_products')->default(0);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('shop_code')->default('0001');
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('uploads');
    }
};