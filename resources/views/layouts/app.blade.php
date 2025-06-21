<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ELS Retail Updater')</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Navbar -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <!-- Logo -->
                    <div class="flex-shrink-0 flex items-center">
                        <h1 class="text-xl font-bold text-gray-800">
                            <i class="fas fa-tags text-blue-600 mr-2"></i>
                            ESL eRetail Updater
                        </h1>
                    </div>
                    
                    <!-- Navigation Links -->
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="{{ route('uploads.index') }}" 
                           class="@if(request()->routeIs('uploads.*')) border-blue-500 text-gray-900 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 @endif inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-file-upload mr-2"></i>
                            Uploads
                        </a>
                        <a href="{{ route('settings.index') }}" 
                           class="@if(request()->routeIs('settings.*')) border-blue-500 text-gray-900 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 @endif inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            <i class="fas fa-cog mr-2"></i>
                            Configuración
                        </a>
                    </div>
                </div>
                
                <!-- Right side -->
                <div class="flex items-center">
                    <a href="{{ route('uploads.create') }}" 
                       class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                        <i class="fas fa-plus mr-2"></i>
                        Nuevo Upload
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Alerts -->
    @if(session('success'))
        <div class="max-w-7xl mx-auto mt-4 px-4 sm:px-6 lg:px-8">
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="max-w-7xl mx-auto mt-4 px-4 sm:px-6 lg:px-8">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <!-- Page Content -->
    <main class="py-8">
        @yield('content')
    </main>

    @yield('scripts')
</body>
</html>