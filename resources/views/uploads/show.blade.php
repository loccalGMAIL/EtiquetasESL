@extends('layouts.app')

@section('title', 'Detalles Upload #' . $upload->id)

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header con información general -->
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-6">
            <div class="p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-2xl font-bold mb-2">
                            Upload #{{ $upload->id }}
                        </h2>
                        <p class="text-gray-600">{{ $upload->original_filename }}</p>
                        <p class="text-sm text-gray-500">
                            Cargado el {{ $upload->created_at->format('d/m/Y H:i') }}
                        </p>
                    </div>

                    <div class="text-right">
                        @php
                            $statusColors = [
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'processing' => 'bg-blue-100 text-blue-800',
                                'completed' => 'bg-green-100 text-green-800',
                                'failed' => 'bg-red-100 text-red-800',
                            ];
                            $statusLabels = [
                                'pending' => 'Pendiente',
                                'processing' => 'Procesando',
                                'completed' => 'Completado',
                                'failed' => 'Error',
                            ];
                        @endphp
                        <span
                            class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full {{ $statusColors[$upload->status] }}">
                            {{ $statusLabels[$upload->status] }}
                        </span>

                        <div class="mt-4 space-x-2">
                            <a href="{{ route('uploads.download', $upload) }}"
                                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <i class="fas fa-download mr-2"></i>
                                Descargar Original
                            </a>
                            <a href="{{ route('uploads.report', $upload) }}"
                                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <i class="fas fa-file-pdf mr-2"></i>
                                Reporte
                            </a>
                            @if ($upload->status === 'completed' && $statistics['creados'] + $statistics['actualizados'] > 0)
                                <form action="{{ route('uploads.refresh-tags', $upload) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit"
                                        class="inline-flex items-center px-3 py-2 border border-green-300 shadow-sm text-sm leading-4 font-medium rounded-md text-green-700 bg-white hover:bg-green-50"
                                        onclick="return confirm('¿Actualizar las etiquetas de los productos procesados?')">
                                        <i class="fas fa-sync mr-2"></i>
                                        Actualizar Etiquetas
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                @if ($upload->error_message)
                    <div class="mt-4 bg-red-50 border-l-4 border-red-400 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700">
                                    {{ $upload->error_message }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">
                        Total
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                        {{ number_format($statistics['total']) }}
                    </dd>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">
                        Procesados
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-blue-600">
                        {{ number_format($statistics['procesados']) }}
                    </dd>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">
                        Creados
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-green-600">
                        {{ number_format($statistics['creados']) }}
                    </dd>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">
                        Actualizados
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-yellow-600">
                        {{ number_format($statistics['actualizados']) }}
                    </dd>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">
                        Omitidos
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-600">
                        {{ number_format($statistics['omitidos']) }}
                    </dd>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">
                        Errores
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-red-600">
                        {{ number_format($statistics['errores']) }}
                    </dd>
                </div>
            </div>
        </div>

        <!-- Barra de progreso -->
        @if ($upload->status === 'processing')
            <div class="bg-white shadow rounded-lg p-6 mb-6" x-data="progressUpdater()">
                <h3 class="text-lg font-medium mb-4">Progreso</h3>
                <div class="w-full bg-gray-200 rounded-full h-4">
                    <div class="bg-blue-600 h-4 rounded-full transition-all duration-500" :style="`width: ${progress}%`">
                    </div>
                </div>
                <p class="text-sm text-gray-600 mt-2">
                    <span x-text="processed"></span> de <span x-text="total"></span> productos procesados
                </p>
            </div>
        @endif

        <!-- Logs detallados -->
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium mb-4">Detalle de Productos</h3>

                <!-- Filtros -->
                <div class="mb-4 flex space-x-4">
                    <select
                        class="rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                        onchange="window.location.href='{{ route('uploads.show', $upload) }}?status=' + this.value">
                        <option value="">Todos los estados</option>
                        <option value="success" {{ request('status') == 'success' ? 'selected' : '' }}>Exitosos</option>
                        <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Con errores</option>
                        <option value="skipped" {{ request('status') == 'skipped' ? 'selected' : '' }}>Omitidos</option>
                    </select>

                    <select
                        class="rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                        onchange="window.location.href='{{ route('uploads.show', $upload) }}?action=' + this.value">
                        <option value="">Todas las acciones</option>
                        <option value="created" {{ request('action') == 'created' ? 'selected' : '' }}>Creados</option>
                        <option value="updated" {{ request('action') == 'updated' ? 'selected' : '' }}>Actualizados
                        </option>
                        <option value="skipped" {{ request('action') == 'skipped' ? 'selected' : '' }}>Omitidos</option>
                    </select>
                </div>

                <!-- Tabla de logs -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Código
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Descripción
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Precios
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Fecha Últ. Mod.
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acción
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estado
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($logs as $log)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $log->cod_barras }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ Str::limit($log->descripcion, 50) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div>Original: ${{ number_format($log->precio_final, 2) }}</div>
                                        <div class="text-green-600">Desc: ${{ number_format($log->precio_calculado, 2) }}
                                        </div>
                                        @if ($log->precio_anterior_eretail)
                                            <div class="text-xs text-gray-400">Anterior:
                                                ${{ number_format($log->precio_anterior_eretail, 2) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $log->fec_ul_mo ? $log->fec_ul_mo->format('d/m/Y H:i') : '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @php
                                            $actionColors = [
                                                'created' => 'bg-green-100 text-green-800',
                                                'updated' => 'bg-yellow-100 text-yellow-800',
                                                'skipped' => 'bg-gray-100 text-gray-800',
                                            ];
                                            $actionLabels = [
                                                'created' => 'Creado',
                                                'updated' => 'Actualizado',
                                                'skipped' => 'Omitido',
                                            ];
                                        @endphp
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $actionColors[$log->action] ?? 'bg-gray-100 text-gray-800' }}">
                                            {{ $actionLabels[$log->action] ?? $log->action }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if ($log->status === 'success')
                                            <span class="text-green-600">
                                                <i class="fas fa-check-circle"></i> Exitoso
                                            </span>
                                        @elseif($log->status === 'failed')
                                            <span class="text-red-600" title="{{ $log->error_message }}">
                                                <i class="fas fa-times-circle"></i> Error
                                            </span>
                                        @else
                                            <span class="text-gray-600">
                                                <i class="fas fa-minus-circle"></i> {{ $log->skip_reason }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                        No hay registros
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $logs->links() }}
                </div>
            </div>
        </div>
    </div>

    @if ($upload->status === 'processing')
        <script>
            function progressUpdater() {
                return {
                    progress: {{ $statistics['progreso'] }},
                    processed: {{ $statistics['procesados'] }},
                    total: {{ $statistics['total'] }},

                    init() {
                        // Actualizar cada 2 segundos
                        setInterval(() => {
                            fetch('{{ route('api.uploads.status', $upload->id) }}')
                                .then(response => response.json())
                                .then(data => {
                                    this.progress = data.progress.percentage;
                                    this.processed = data.progress.processed;
                                    this.total = data.progress.total;

                                    if (data.status === 'completed' || data.status === 'failed') {
                                        location.reload();
                                    }
                                });
                        }, 2000);
                    }
                }
            }
        </script>
    @endif
@endsection
