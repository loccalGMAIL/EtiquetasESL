@extends('layouts.app')

@section('title', 'Nuevo Upload')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
        <div class="p-6">
            <h2 class="text-2xl font-bold mb-6">Cargar Archivo de Productos</h2>
            
            <form action="{{ route('uploads.store') }}" 
                  method="POST" 
                  enctype="multipart/form-data"
                  x-data="uploadForm()">
                @csrf
                
<!-- Instrucciones -->
<div class="mb-6 bg-blue-50 border-l-4 border-blue-400 p-4">
    <div class="flex">
        <div class="flex-shrink-0">
            <i class="fas fa-info-circle text-blue-400"></i>
        </div>
        <div class="ml-3">
            <p class="text-sm text-blue-700">
                El archivo Excel debe contener las columnas:
                <strong>Cód.Barras</strong>, <strong>Código</strong>, <strong>Descripción</strong>, 
                <strong>Fina ($)</strong> y <strong>UltModif</strong>
            </p>
            <p class="text-sm text-blue-700 mt-1">
                Se aplicará un descuento del {{ \App\Models\AppSetting::get('discount_percentage', 12) }}% automáticamente
            </p>
            <div class="mt-2 text-xs text-blue-600">
                <p><strong>Estructura esperada:</strong></p>
                <ul class="list-disc list-inside ml-2 space-y-1">
                    <li><strong>Cód.Barras:</strong> Código de barras del producto</li>
                    <li><strong>Código:</strong> Código interno del sistema de facturación</li>
                    <li><strong>Descripción:</strong> Nombre del producto</li>
                    <li><strong>Fina ($):</strong> Precio final del producto</li>
                    <li><strong>UltModif:</strong> Fecha de última modificación</li>
                </ul>
            </div>
        </div>
    </div>
</div>
                
                <!-- Shop Code -->
                <div class="mb-6">
                    <label for="shop_code" class="block text-sm font-medium text-gray-700 mb-2">
                        Código de Tienda
                    </label>
                    <input type="text" 
                           name="shop_code" 
                           id="shop_code"
                           value="{{ config('eretail.default_shop_code') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-sm text-gray-500">
                        Dejar en blanco para usar el valor por defecto
                    </p>
                </div>
                
                <!-- File Upload -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Archivo Excel
                    </label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md"
                         @drop.prevent="handleDrop"
                         @dragover.prevent
                         @dragenter.prevent>
                        <div class="space-y-1 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="flex text-sm text-gray-600">
                                <label for="file" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                    <span>Seleccionar archivo</span>
                                    <input id="file" 
                                           name="file" 
                                           type="file" 
                                           class="sr-only"
                                           accept=".xlsx,.xls"
                                           @change="fileSelected"
                                           required>
                                </label>
                                <p class="pl-1">o arrastrar aquí</p>
                            </div>
                            <p class="text-xs text-gray-500">
                                Excel hasta 10MB
                            </p>
                        </div>
                    </div>
                    
                    <!-- Selected file info -->
                    <div x-show="selectedFile" class="mt-4 bg-gray-50 p-4 rounded-md">
                        <div class="flex items-center">
                            <i class="fas fa-file-excel text-green-600 text-2xl mr-3"></i>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900" x-text="selectedFile?.name"></p>
                                <p class="text-sm text-gray-500" x-text="formatFileSize(selectedFile?.size)"></p>
                            </div>
                            <button type="button" @click="removeFile()" class="text-red-500 hover:text-red-700">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    @error('file')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                
                <!-- Submit buttons -->
                <div class="flex items-center justify-end space-x-3">
                    <a href="{{ route('uploads.index') }}" 
                       class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </a>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-sm text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                            :disabled="!selectedFile">
                        <i class="fas fa-upload mr-2"></i>
                        Cargar y Procesar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function uploadForm() {
    return {
        selectedFile: null,
        
        handleDrop(e) {
            const file = e.dataTransfer.files[0];
            if (file && (file.name.endsWith('.xlsx') || file.name.endsWith('.xls'))) {
                this.selectedFile = file;
                // Actualizar el input file
                const input = document.getElementById('file');
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                input.files = dataTransfer.files;
            }
        },
        
        fileSelected(e) {
            this.selectedFile = e.target.files[0];
        },
        
        removeFile() {
            this.selectedFile = null;
            document.getElementById('file').value = '';
        },
        
        formatFileSize(bytes) {
            if (!bytes) return '';
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(1024));
            return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
        }
    }
}
</script>
@endsection