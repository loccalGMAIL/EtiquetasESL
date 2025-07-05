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
                    </div>
                    <div>
                        <p><strong>Total productos:</strong> {{ number_format($upload->total_products) }}</p>
                        <p><strong>Exitosos:</strong>
                            {{ number_format($upload->created_products + $upload->updated_products) }}</p>
                        <p><strong>Con errores:</strong> {{ number_format($upload->failed_products) }}</p>
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
                            <td class="px-6 py-4">Productos Creados</td>
                            <td class="px-6 py-4">{{ number_format($upload->created_products) }}</td>
                            <td class="px-6 py-4">
                                {{ $upload->total_products > 0 ? round(($upload->created_products / $upload->total_products) * 100, 2) : 0 }}%
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4">Productos Actualizados</td>
                            <td class="px-6 py-4">{{ number_format($upload->updated_products) }}</td>
                            <td class="px-6 py-4">
                                {{ $upload->total_products > 0 ? round(($upload->updated_products / $upload->total_products) * 100, 2) : 0 }}%
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4">Productos Omitidos</td>
                            <td class="px-6 py-4">{{ number_format($upload->skipped_products) }}</td>
                            <td class="px-6 py-4">
                                {{ $upload->total_products > 0 ? round(($upload->skipped_products / $upload->total_products) * 100, 2) : 0 }}%
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4">Errores</td>
                            <td class="px-6 py-4">{{ number_format($upload->failed_products) }}</td>
                            <td class="px-6 py-4">
                                {{ $upload->total_products > 0 ? round(($upload->failed_products / $upload->total_products) * 100, 2) : 0 }}%
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- <!-- Productos con errores -->
            @if ($logs->where('status', 'failed')->count() > 0)
                <div class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-red-600">Productos con Errores</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Código</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Descripción</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Error</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($logs->where('status', 'failed')->take(20) as $log)
                                    <tr>
                                        <td class="px-6 py-4">{{ $log->cod_barras }}</td>
                                        <td class="px-6 py-4">{{ Str::limit($log->descripcion, 50) }}</td>
                                        <td class="px-6 py-4 text-red-600">{{ $log->error_message }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if ($logs->where('status', 'failed')->count() > 20)
                            <p class="text-sm text-gray-500 mt-2">
                                ... y {{ $logs->where('status', 'failed')->count() - 20 }} errores más
                            </p>
                        @endif
                    </div>
                </div>
            @endif --}}


            <!-- Productos con errores -->
            @if ($logs->where('status', 'failed')->count() > 0)
                <div class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-red-600">Productos con Errores</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Código Barras
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Código Interno
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Descripción
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Error
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($logs->where('status', 'failed') as $log)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap font-mono text-gray-900">
                                            {{ $log->cod_barras }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap font-mono text-blue-600 font-medium">
                                            {{ $log->codigo ?? '-' }}
                                        </td>
                                        <td class="px-6 py-4 text-gray-900 max-w-xs">
                                            <div class="truncate" title="{{ $log->descripcion }}">
                                                {{ $log->descripcion }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-red-600 max-w-md">
                                            <div class="break-words">
                                                {{ $log->error_message }}
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
            @if ($logs->where('status', 'skipped')->count() > 0)
                <div class="mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-yellow-600">Productos Omitidos</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Código Barras
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Código Interno
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Descripción
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Razón
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($logs->where('status', 'skipped') as $log)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap font-mono text-gray-900">
                                            {{ $log->cod_barras }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap font-mono text-blue-600 font-medium">
                                            {{ $log->codigo ?? '-' }}
                                        </td>
                                        <td class="px-6 py-4 text-gray-900 max-w-xs">
                                            <div class="truncate" title="{{ $log->descripcion }}">
                                                {{ $log->descripcion }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-yellow-600">
                                            {{ ucfirst(str_replace('_', ' ', $log->skip_reason ?? 'No especificada')) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif




            <div class="text-center mt-8 no-print">
                <button onclick="window.print()" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                    <i class="fas fa-print mr-2"></i>
                    Imprimir Reporte
                </button>
                <a href="{{ route('uploads.show', $upload) }}"
                    class="ml-2 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Volver a Detalles
                </a>
            </div>
        </div>
    </div>

    <style>
        @media print {
            .no-print {
                display: none;
            }

            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
@endsection
