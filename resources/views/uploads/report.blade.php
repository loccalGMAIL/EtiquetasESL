{{-- resources/views/uploads/report.blade.php --}}
@extends('layouts.app')

@section('title', 'Reporte Upload #' . $upload->id)

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white shadow-xl rounded-lg p-8">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold">Reporte de Procesamiento</h1>
                <p class="text-gray-600 mt-2">Upload #{{ $upload->id }}</p>
                <p class="text-sm text-gray-500">Generado el {{ now()->format('d/m/Y H:i') }}</p>
            </div>

            <!-- Resumen -->
            <div class="mb-8">
                <h2 class="text-xl font-semibold mb-4">Resumen General</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p><strong>Archivo:</strong> {{ $upload->original_filename }}</p>
                        <p><strong>Fecha de carga:</strong> {{ $upload->created_at->format('d/m/Y H:i') }}</p>
                        <p><strong>Estado:</strong> {{ ucfirst($upload->status) }}</p>
                        <p><strong>Shop Code:</strong> {{ $upload->shop_code ?? 'No especificado' }}</p>
                    </div>
                    <div>
                        <p><strong>Total productos:</strong> {{ number_format($upload->total_products ?? 0) }}</p>
                        <p><strong>Exitosos:</strong>
                            {{ number_format(($upload->created_variants ?? 0) + ($upload->updated_variants ?? 0)) }}</p>
                        <p><strong>Con errores:</strong> {{ number_format($upload->failed_variants ?? 0) }}</p>
                        <p><strong>Tiempo de procesamiento:</strong> 
                            @if($upload->created_at && $upload->updated_at)
                                {{ $upload->created_at->diffForHumans($upload->updated_at, true) }}
                            @else
                                No disponible
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            <!-- Detalle por tipo -->
            <div class="mb-8">
                <h2 class="text-xl font-semibold mb-4">Detalle por Acción</h2>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Acción</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Cantidad</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Porcentaje</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td class="px-6 py-4">Variantes Creadas</td>
                            <td class="px-6 py-4">{{ number_format($upload->created_variants ?? 0) }}</td>
                            <td class="px-6 py-4">
                                @php
                                    $total = $upload->total_products ?? 1;
                                    $created = $upload->created_variants ?? 0;
                                    $percentage = $total > 0 ? round(($created / $total) * 100, 1) : 0;
                                @endphp
                                {{ $percentage }}%
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4">Variantes Actualizadas</td>
                            <td class="px-6 py-4">{{ number_format($upload->updated_variants ?? 0) }}</td>
                            <td class="px-6 py-4">
                                @php
                                    $updated = $upload->updated_variants ?? 0;
                                    $percentage = $total > 0 ? round(($updated / $total) * 100, 1) : 0;
                                @endphp
                                {{ $percentage }}%
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4">Variantes con Error</td>
                            <td class="px-6 py-4">{{ number_format($upload->failed_variants ?? 0) }}</td>
                            <td class="px-6 py-4">
                                @php
                                    $failed = $upload->failed_variants ?? 0;
                                    $percentage = $total > 0 ? round(($failed / $total) * 100, 1) : 0;
                                @endphp
                                {{ $percentage }}%
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Productos con error -->
            @if ($logs->where('status', 'failed')->count() > 0)
                <div class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-red-600">Productos con Errores</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-red-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Variant ID
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Código Barras
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Código Interno
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Descripción
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Error
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($logs->where('status', 'failed') as $log)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap font-mono text-purple-600 font-bold">
                                            {{ $log->product_variant_id ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap font-mono text-gray-900">
                                            {{ $log->productVariant->cod_barras ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap font-mono text-blue-600 font-medium">
                                            {{ $log->productVariant->codigo_interno ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 text-gray-900 max-w-xs">
                                            <div class="truncate" title="{{ $log->productVariant->descripcion ?? 'N/A' }}">
                                                {{ $log->productVariant->descripcion ?? 'N/A' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-red-600 max-w-md">
                                            <div class="break-words">
                                                {{ $log->error_message ?? 'Error sin especificar' }}
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Productos omitidos -->
            @if ($logs->where('action', 'skipped')->count() > 0)
                <div class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-yellow-600">Productos Omitidos</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-yellow-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Variant ID
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Código Barras
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Código Interno
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Descripción
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Razón
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($logs->where('action', 'skipped') as $log)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap font-mono text-purple-600 font-bold">
                                            {{ $log->product_variant_id ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap font-mono text-gray-900">
                                            {{ $log->productVariant->cod_barras ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap font-mono text-blue-600 font-medium">
                                            {{ $log->productVariant->codigo_interno ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 text-gray-900 max-w-xs">
                                            <div class="truncate" title="{{ $log->productVariant->descripcion ?? 'N/A' }}">
                                                {{ $log->productVariant->descripcion ?? 'N/A' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-yellow-600">
                                            {{ $log->error_message ?? 'Producto omitido' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Productos exitosos (muestra) -->
            @if ($logs->where('status', 'success')->count() > 0)
                <div class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-green-600">
                        Productos Exitosos 
                        <span class="text-sm font-normal text-gray-600">
                            (mostrando primeros 20 de {{ $logs->where('status', 'success')->count() }})
                        </span>
                    </h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-green-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Variant ID (goodsCode)
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Código Barras
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Código Interno
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Descripción
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Precios
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acción
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($logs->where('status', 'success')->take(20) as $log)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap font-mono text-purple-600 font-bold">
                                            {{ $log->product_variant_id ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap font-mono text-gray-900">
                                            {{ $log->productVariant->cod_barras ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap font-mono text-blue-600 font-medium">
                                            {{ $log->productVariant->codigo_interno ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 text-gray-900 max-w-xs">
                                            <div class="truncate" title="{{ $log->productVariant->descripcion ?? 'N/A' }}">
                                                {{ $log->productVariant->descripcion ?? 'N/A' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if($log->productVariant)
                                                <div>
                                                    <span class="text-green-600 font-medium">
                                                        ${{ number_format($log->productVariant->precio_final, 2) }}
                                                    </span>
                                                    @if($log->productVariant->precio_calculado != $log->productVariant->precio_final)
                                                        <br>
                                                        <span class="text-blue-600 text-xs">
                                                            Promo: ${{ number_format($log->productVariant->precio_calculado, 2) }}
                                                        </span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-gray-400">N/A</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @switch($log->action)
                                                @case('created')
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                        Creado
                                                    </span>
                                                    @break
                                                @case('updated')
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                        Actualizado
                                                    </span>
                                                    @break
                                                @default
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                                        {{ ucfirst($log->action) }}
                                                    </span>
                                            @endswitch
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Información adicional -->
            @if(isset($detailedStats) && isset($uploadProgress))
                <div class="mb-8">
                    <h2 class="text-xl font-semibold mb-4">Información Técnica</h2>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <h3 class="font-medium text-gray-700">Estadísticas de Procesamiento</h3>
                                <ul class="mt-2 text-sm text-gray-600">
                                    <li>Progreso: {{ $uploadProgress['progress_percentage'] ?? 0 }}%</li>
                                    <li>Tasa de éxito: {{ $uploadProgress['success_rate'] ?? 0 }}%</li>
                                    <li>Estado del upload: {{ $uploadProgress['upload_status'] ?? 'desconocido' }}</li>
                                </ul>
                            </div>
                            <div>
                                <h3 class="font-medium text-gray-700">Arquitectura</h3>
                                <ul class="mt-2 text-sm text-gray-600">
                                    <li>✅ Nueva arquitectura con variantes</li>
                                    <li>✅ IDs estables para eRetail (ProductVariant.id)</li>
                                    <li>✅ Soporte para códigos duplicados</li>
                                    <li>✅ Múltiples variantes por producto</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Acciones -->
            <div class="flex justify-center space-x-4 mt-8">
                <a href="{{ route('uploads.show', $upload) }}"
                    class="bg-blue-500 text-white px-6 py-3 rounded hover:bg-blue-600">
                    Ver Detalle Completo
                </a>
                <a href="{{ route('uploads.download', $upload) }}"
                    class="bg-gray-500 text-white px-6 py-3 rounded hover:bg-gray-600">
                    Descargar Original
                </a>
                @if($upload->status === 'completed')
                    <form method="POST" action="{{ route('uploads.refresh-tags', $upload) }}" class="inline">
                        @csrf
                        <button type="submit" 
                            class="bg-purple-500 text-white px-6 py-3 rounded hover:bg-purple-600">
                            Actualizar Etiquetas ESL
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection