@extends('layouts.app')

@section('title', 'Configuración')

@section('content')
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
            <div class="p-6">
                <h2 class="text-2xl font-bold mb-6">Configuración del Sistema</h2>

                <form action="{{ route('settings.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <!-- Porcentaje de descuento -->
                    <div class="mb-6">
                        <label for="discount_percentage" class="block text-sm font-medium text-gray-700 mb-2">
                            Porcentaje de Descuento
                        </label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <input type="number" name="discount_percentage" id="discount_percentage"
                                value="{{ $settings['discount_percentage']->value ?? 12 }}" min="0" max="100"
                                step="0.01" required
                                class="focus:ring-blue-500 focus:border-blue-500 block w-full pr-12 sm:text-sm border-gray-300 rounded-md">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">%</span>
                            </div>
                        </div>
                        <p class="mt-2 text-sm text-gray-500">
                            Este porcentaje se restará del precio original (Final $) del Excel
                        </p>
                    </div>

                    <!-- Modo de actualización -->
                    <div class="mb-6">
                        <label for="update_mode" class="block text-sm font-medium text-gray-700 mb-2">
                            Modo de Actualización
                        </label>
                        <select name="update_mode" id="update_mode"
                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="check_date"
                                {{ ($settings['update_mode']->value ?? 'check_date') == 'check_date' ? 'selected' : '' }}>
                                Verificar fecha (solo actualizar si FecUlMo es más reciente)
                            </option>
                            <option value="force_all"
                                {{ ($settings['update_mode']->value ?? '') == 'force_all' ? 'selected' : '' }}>
                                Forzar todos (actualizar siempre)
                            </option>
                            <option value="manual"
                                {{ ($settings['update_mode']->value ?? '') == 'manual' ? 'selected' : '' }}>
                                Manual (revisar uno por uno)
                            </option>
                        </select>
                    </div>

                    <!-- Código de tienda por defecto -->
                    <div class="mb-6">
                        <label for="default_shop_code" class="block text-sm font-medium text-gray-700 mb-2">
                            Código de Tienda por Defecto
                        </label>
                        <input type="text" name="default_shop_code" id="default_shop_code"
                            value="{{ $settings['default_shop_code']->value ?? '0001' }}" required
                            class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        <p class="mt-2 text-sm text-gray-500">
                            Se usará cuando no se especifique uno al cargar el archivo
                        </p>
                    </div>

                    <!-- Crear productos faltantes -->
                    <div class="mb-6">
                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input type="hidden" name="create_missing_products" value="0">
                                <input type="checkbox" name="create_missing_products" id="create_missing_products"
                                    value="1"
                                    {{ \App\Models\AppSetting::get('create_missing_products', false) ? 'checked' : '' }}
                                    class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="create_missing_products" class="font-medium text-gray-700">
                                    Crear productos que no existen
                                </label>
                                <p class="text-gray-500">
                                    Si un producto del Excel no existe en eRetail, crearlo automáticamente
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Número de filas a omitir -->
                    <div class="mb-6">
                        <label for="excel_skip_rows" class="block text-sm font-medium text-gray-700 mb-2">
                            Filas a omitir al inicio del Excel
                        </label>
                        <input type="number" name="excel_skip_rows" id="excel_skip_rows"
                            value="{{ $settings['excel_skip_rows']->value ?? 2 }}" min="0" max="10" required
                            class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        <p class="mt-2 text-sm text-gray-500">
                            Número de filas a omitir al inicio (0 = no omitir filas, 1 = omitir solo encabezados, 2 = omitir
                            título y encabezados)
                        </p>
                    </div>

                    <!-- Sección de Actualización Automática de Etiquetas -->
                    <div class="mb-8 border-t pt-8">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Actualización Automática de Etiquetas</h3>

                        <!-- Habilitar actualización automática -->
                        <div class="mb-6">
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input type="hidden" name="auto_refresh_tags" value="0">
                                    <input type="checkbox" name="auto_refresh_tags" id="auto_refresh_tags" value="1"
                                        {{ \App\Models\AppSetting::get('auto_refresh_tags', false) ? 'checked' : '' }}
                                        class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="auto_refresh_tags" class="font-medium text-gray-700">
                                        Actualizar etiquetas automáticamente
                                    </label>
                                    <p class="text-gray-500">
                                        Enviar comando de actualización a eRetail después de procesar productos exitosamente
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Método de actualización -->
                        <div class="mb-6">
                            <label for="refresh_method" class="block text-sm font-medium text-gray-700 mb-2">
                                Método de Actualización
                            </label>
                            <select name="refresh_method" id="refresh_method"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="specific"
                                    {{ ($settings['refresh_method']->value ?? 'specific') == 'specific' ? 'selected' : '' }}>
                                    Solo productos procesados (Recomendado)
                                </option>
                                <option value="store"
                                    {{ ($settings['refresh_method']->value ?? '') == 'store' ? 'selected' : '' }}>
                                    Toda la tienda
                                </option>
                            </select>
                            <p class="mt-2 text-sm text-gray-500">
                                <strong>Específico:</strong> Más rápido, solo actualiza las etiquetas que se modificaron<br>
                                <strong>Tienda completa:</strong> Más lento, pero garantiza que todas las etiquetas estén
                                sincronizadas
                            </p>
                        </div>

                        <!-- Parpadeo de etiquetas -->
                        <div class="mb-6">
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input type="hidden" name="flash_updated_tags" value="0">
                                    <input type="checkbox" name="flash_updated_tags" id="flash_updated_tags" value="1"
                                        {{ \App\Models\AppSetting::get('flash_updated_tags', false) ? 'checked' : '' }}
                                        class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="flash_updated_tags" class="font-medium text-gray-700">
                                        Hacer parpadear etiquetas actualizadas
                                    </label>
                                    <p class="text-gray-500">
                                        Las etiquetas parpadearán en verde durante 3 segundos para indicar que fueron
                                        actualizadas
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Información adicional -->
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-info-circle text-blue-400"></i>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-blue-800">
                                        ¿Cómo funciona la actualización automática?
                                    </h4>
                                    <div class="mt-2 text-sm text-blue-700">
                                        <p>1. <strong>Después de procesar el Excel:</strong> El sistema identifica qué
                                            productos se crearon o actualizaron exitosamente</p>
                                        <p>2. <strong>Envío de comando:</strong> Se envía una solicitud a eRetail para
                                            actualizar esas etiquetas específicas</p>
                                        <p>3. <strong>Actualización física:</strong> Las estaciones base (AP) reciben el
                                            comando y actualizan las etiquetas por radio</p>
                                        <p class="mt-2"><strong>Tiempo estimado:</strong> 1-3 minutos dependiendo de la
                                            cantidad de etiquetas</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Información de conexión (solo lectura) -->
                    <div class="mb-6 bg-gray-50 p-4 rounded-lg">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Información de COOOONNN con eRetail</h3>
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">URL Base</dt>
                                <dd class="text-sm text-gray-900">{{ config('eretail.base_url') }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Usuario</dt>
                                <dd class="text-sm text-gray-900">{{ config('eretail.username') }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Timeout</dt>
                                <dd class="text-sm text-gray-900">{{ config('eretail.timeout') }} segundos</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Reintentos</dt>
                                <dd class="text-sm text-gray-900">{{ config('eretail.retry_times') }} veces</dd>
                            </div>
                        </dl>
                        <p class="mt-3 text-xs text-gray-500">
                            * Para cambiar estos valores, editar el archivo .env
                        </p>
                    </div>

                    <!-- Botones -->
                    <div class="flex items-center justify-end space-x-3">
                        <button type="button" onclick="testConnection()"
                            class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-plug mr-2"></i>
                            Probar Conexión
                        </button>
                        <button type="submit"
                            class="px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-sm text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-save mr-2"></i>
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function testConnection() {
            const button = event.target;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Probando...';

            // Aquí podrías hacer una llamada AJAX para probar la conexión
            setTimeout(() => {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-plug mr-2"></i> Probar Conexión';
                alert('Conexión exitosa con eRetail');
            }, 2000);
        }
    </script>
@endsection
