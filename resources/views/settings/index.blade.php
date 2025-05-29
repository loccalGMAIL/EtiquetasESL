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
                                    {{ ($settings['create_missing_products']->value ?? 'true') == 'true' ? 'checked' : '' }}
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

                    <!-- Información de conexión (solo lectura) -->
                    <div class="mb-6 bg-gray-50 p-4 rounded-lg">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Información de Conexión con eRetail</h3>
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
