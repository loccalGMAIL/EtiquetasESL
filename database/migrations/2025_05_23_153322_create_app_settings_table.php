<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->string('description')->nullable();
            $table->string('type')->default('string'); // string, integer, boolean, json
            $table->timestamps();

            $table->index('key');
        });

        // Insertar configuraciones por defecto
        $this->insertDefaultSettings();
    }

    private function insertDefaultSettings()
    {
        $settings = [
            [
                'key' => 'discount_percentage',
                'value' => '12',
                'description' => 'Porcentaje de descuento a aplicar',
                'type' => 'integer'
            ],
            [
                'key' => 'update_mode',
                'value' => 'check_date',
                'description' => 'Modo de actualización: check_date, force_all, manual',
                'type' => 'string'
            ],
            [
                'key' => 'default_shop_code',
                'value' => '0001',
                'description' => 'Código de tienda por defecto',
                'type' => 'string'
            ],
            [
                'key' => 'default_template',
                'value' => 'REG',
                'description' => 'Plantilla por defecto para productos nuevos',
                'type' => 'string'
            ],
            [
                'key' => 'create_missing_products',
                'value' => 'true',
                'description' => 'Crear productos que no existen en eRetail',
                'type' => 'boolean'
            ],
            [
                'key' => 'excel_skip_rows',
                'value' => '2',
                'description' => 'Número de filas a omitir al inicio del Excel (0 = no omitir)',
                'type' => 'integer'
            ]
        ];

        foreach ($settings as $setting) {
            DB::table('app_settings')->insert([
                'key' => $setting['key'],
                'value' => $setting['value'],
                'description' => $setting['description'],
                'type' => $setting['type'],
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('app_settings');
    }
};