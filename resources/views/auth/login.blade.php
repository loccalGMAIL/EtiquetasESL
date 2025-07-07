<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

    <title>Iniciar Sesión - ELS Retail Updater</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .login-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .floating-animation {
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-20px);
            }
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>

<body class="login-gradient min-h-screen">
    <!-- Alerts -->
    @if (session('success'))
        <div class="fixed top-4 right-4 z-50">
            <div class="bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center transform transition-all duration-300"
                id="successAlert">
                <i class="fas fa-check-circle mr-2"></i>
                {{ session('success') }}
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="fixed top-4 right-4 z-50">
            <div class="bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center transform transition-all duration-300"
                id="errorAlert">
                <i class="fas fa-exclamation-circle mr-2"></i>
                {{ session('error') }}
            </div>
        </div>
    @endif

    <div class="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8">
        <!-- Elementos decorativos flotantes -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="floating-animation absolute top-20 left-10 w-20 h-20 bg-white bg-opacity-10 rounded-full"></div>
            <div class="floating-animation absolute top-40 right-20 w-16 h-16 bg-white bg-opacity-5 rounded-full"
                style="animation-delay: -2s;"></div>
            <div class="floating-animation absolute bottom-20 left-1/4 w-12 h-12 bg-white bg-opacity-15 rounded-full"
                style="animation-delay: -4s;"></div>
            <div class="floating-animation absolute bottom-40 right-1/4 w-24 h-24 bg-white bg-opacity-5 rounded-full"
                style="animation-delay: -1s;"></div>
        </div>

        <div class="max-w-md w-full space-y-8">
            <!-- Header -->
            <div class="text-center">
                <div
                    class="mx-auto h-20 w-20 bg-white bg-opacity-20 rounded-full flex items-center justify-center mb-6 floating-animation">
                    <i class="fas fa-tags text-3xl text-white"></i>
                </div>
                <h2 class="text-4xl font-bold text-white mb-2">
                    ELS Retail Updater
                </h2>
                <p class="text-xl text-white text-opacity-90">
                    Inicia sesión en tu cuenta
                </p>
            </div>

            <!-- Formulario de Login -->
            <div class="glass-effect rounded-2xl shadow-2xl p-8">
                <form method="POST" action="{{ route('login.post') }}" id="loginForm">
                    @csrf

                    <!-- Email -->
                    <div class="mb-6">
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-envelope text-blue-600 mr-2"></i>
                            Correo Electrónico
                        </label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required
                            autofocus placeholder="usuario@ejemplo.com"
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-200 @error('email') border-red-500 focus:border-red-500 @enderror">
                        @error('email')
                            <div class="text-red-500 text-sm mt-2 flex items-center">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div class="mb-6">
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-lock text-blue-600 mr-2"></i>
                            Contraseña
                        </label>
                        <div class="relative">
                            <input type="password" id="password" name="password" required placeholder="••••••••"
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-200 pr-12 @error('password') border-red-500 focus:border-red-500 @enderror">
                            <button type="button" onclick="togglePassword()"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                                <i id="passwordIcon" class="fas fa-eye"></i>
                            </button>
                        </div>
                        @error('password')
                            <div class="text-red-500 text-sm mt-2 flex items-center">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center">
                            <input type="checkbox" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="remember" class="ml-2 block text-sm text-gray-700">
                                Recordarme
                            </label>
                        </div>
                        {{-- <div class="text-sm">
                            <a href="#" class="font-medium text-blue-600 hover:text-blue-500 transition-colors duration-200">
                                ¿Olvidaste tu contraseña?
                            </a>
                        </div> --}}
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" id="submitBtn"
                        class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-300 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i id="submitIcon" class="fas fa-sign-in-alt mr-2"></i>
                        <span id="submitText">Iniciar Sesión</span>
                    </button>
                </form>

                <!-- Footer del formulario -->
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        ¿No tienes una cuenta?
                        <a href="http://wa.me/+543541549674" target="_blank"
                            class="font-medium text-blue-600 hover:text-blue-500 transition-colors duration-200">
                            Contacta al administrador
                        </a>
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center">
                <p class="text-sm text-white text-opacity-75">
                    &copy; {{ date('Y') }}
                    Designed by <a target="_blank" href="https://pez.com.ar">Pez</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const icon = document.getElementById('passwordIcon');

            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Handle form submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const submitIcon = document.getElementById('submitIcon');
            const submitText = document.getElementById('submitText');

            // Disable button and show loading
            submitBtn.disabled = true;
            submitIcon.className = 'fas fa-spinner fa-spin mr-2';
            submitText.textContent = 'Iniciando sesión...';
        });

        // Auto-hide alerts
        function hideAlert(alertId) {
            const alert = document.getElementById(alertId);
            if (alert) {
                setTimeout(() => {
                    alert.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 4000);
            }
        }

        hideAlert('successAlert');
        hideAlert('errorAlert');

        // Focus animation
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('transform', 'scale-105');
            });

            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('transform', 'scale-105');
            });
        });
    </script>
</body>

</html>
