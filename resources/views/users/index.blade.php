@extends('layouts.app')

@section('title', 'Gestión de Usuarios')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Gestión de Usuarios</h1>
            <p class="text-gray-600">Administra los usuarios del sistema</p>
        </div>
        <button id="btnNuevoUsuario" 
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
            <i class="fas fa-plus mr-2"></i>
            Nuevo Usuario
        </button>
    </div>

    <!-- Tabla de usuarios -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Usuario
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Email
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Fecha Creación
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($users as $user)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                            <i class="fas fa-user text-gray-600"></i>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $user->name }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $user->email }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    {{ $user->created_at->format('d/m/Y H:i') }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button onclick="editarUsuario({{ $user->id }})" 
                                        class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-edit"></i>
                                    Editar
                                </button>
                                <button onclick="eliminarUsuario({{ $user->id }}, '{{ $user->name }}')" 
                                        class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                No hay usuarios registrados
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de Usuario -->
<div id="modalUsuario" class="hidden fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full z-50 backdrop-blur-sm">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg transform transition-all duration-300 scale-95 opacity-0" id="modalContent">
            <!-- Header del modal -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-8 py-6 rounded-t-2xl">
                <div class="flex justify-between items-center">
                    <div class="flex items-center">
                        <div class="bg-white bg-opacity-20 rounded-full p-2 mr-4">
                            <i id="modalIcon" class="fas fa-user-plus text-xl"></i>
                        </div>
                        <h3 id="modalTitulo" class="text-xl font-semibold">
                            Nuevo Usuario
                        </h3>
                    </div>
                    <button onclick="cerrarModal()" class="text-white hover:text-gray-200 transition-colors duration-200 p-2 rounded-full hover:bg-white hover:bg-opacity-20">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
            </div>

            <!-- Formulario -->
            <form id="formUsuario" method="POST" class="px-8 py-6">
                @csrf
                <div id="methodField"></div>

                <!-- Nombre -->
                <div class="mb-6">
                    <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-user text-blue-600 mr-2"></i>
                        Nombre Completo
                    </label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           required
                           placeholder="Ingresa el nombre completo"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-200">
                    <div id="errorName" class="hidden text-red-500 text-sm mt-2 flex items-center">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        <span></span>
                    </div>
                </div>

                <!-- Email -->
                <div class="mb-6">
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-envelope text-blue-600 mr-2"></i>
                        Correo Electrónico
                    </label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           required
                           placeholder="usuario@ejemplo.com"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-200">
                    <div id="errorEmail" class="hidden text-red-500 text-sm mt-2 flex items-center">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        <span></span>
                    </div>
                </div>

                <!-- Contraseña -->
                <div class="mb-6">
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-lock text-blue-600 mr-2"></i>
                        Contraseña
                    </label>
                    <div class="relative">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               placeholder="••••••••"
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-200 pr-12">
                        <button type="button" onclick="togglePassword('password')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                            <i id="passwordIcon" class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div id="errorPassword" class="hidden text-red-500 text-sm mt-2 flex items-center">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        <span></span>
                    </div>
                    <div id="passwordHelp" class="hidden text-sm text-amber-600 mt-2 bg-amber-50 p-3 rounded-lg border border-amber-200">
                        <i class="fas fa-info-circle mr-1"></i>
                        Dejar en blanco para mantener la contraseña actual
                    </div>
                </div>

                <!-- Confirmar Contraseña -->
                <div class="mb-8">
                    <label for="password_confirmation" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-lock text-blue-600 mr-2"></i>
                        Confirmar Contraseña
                    </label>
                    <div class="relative">
                        <input type="password" 
                               id="password_confirmation" 
                               name="password_confirmation" 
                               placeholder="••••••••"
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-200 pr-12">
                        <button type="button" onclick="togglePassword('password_confirmation')" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                            <i id="passwordConfirmationIcon" class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Botones -->
                <div class="flex justify-end space-x-4">
                    <button type="button" 
                            onclick="cerrarModal()" 
                            class="px-6 py-3 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-gray-300">
                        <i class="fas fa-times mr-2"></i>
                        Cancelar
                    </button>
                    <button type="submit" 
                            id="btnSubmit"
                            class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white rounded-lg font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-300 transform hover:scale-105">
                        <i id="btnIcon" class="fas fa-save mr-2"></i>
                        <span id="btnText">Crear Usuario</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Confirmación para Eliminar -->
<div id="modalEliminar" class="hidden fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full z-50 backdrop-blur-sm">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md transform transition-all duration-300 scale-95 opacity-0" id="modalEliminarContent">
            <!-- Header del modal -->
            <div class="bg-gradient-to-r from-red-500 to-red-600 text-white px-8 py-6 rounded-t-2xl">
                <div class="flex items-center justify-center">
                    <div class="bg-white bg-opacity-20 rounded-full p-3 mr-4">
                        <i class="fas fa-exclamation-triangle text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold">Confirmar Eliminación</h3>
                </div>
            </div>

            <!-- Contenido -->
            <div class="px-8 py-6 text-center">
                <div class="mb-6">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                        <i class="fas fa-user-times text-2xl text-red-600"></i>
                    </div>
                    <p class="text-gray-700 text-lg mb-2">
                        ¿Estás seguro de que deseas eliminar al usuario:
                    </p>
                    <p class="text-xl font-semibold text-gray-900" id="nombreUsuarioEliminar"></p>
                    <p class="text-sm text-gray-500 mt-4 bg-red-50 p-3 rounded-lg border border-red-200">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        Esta acción no se puede deshacer
                    </p>
                </div>
                
                <div class="flex justify-center space-x-4">
                    <button onclick="cerrarModalEliminar()" 
                            class="px-6 py-3 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-gray-300">
                        <i class="fas fa-times mr-2"></i>
                        Cancelar
                    </button>
                    <form id="formEliminar" method="POST" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" 
                                class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white rounded-lg font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-red-300 transform hover:scale-105">
                            <i class="fas fa-trash mr-2"></i>
                            Eliminar Usuario
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let usuarioEditando = null;

// Abrir modal para nuevo usuario
document.getElementById('btnNuevoUsuario').addEventListener('click', function() {
    usuarioEditando = null;
    document.getElementById('modalTitulo').textContent = 'Nuevo Usuario';
    document.getElementById('modalIcon').className = 'fas fa-user-plus text-xl';
    document.getElementById('btnText').textContent = 'Crear Usuario';
    document.getElementById('btnIcon').className = 'fas fa-save mr-2';
    document.getElementById('formUsuario').action = '{{ route("users.store") }}';
    document.getElementById('methodField').innerHTML = '';
    document.getElementById('passwordHelp').classList.add('hidden');
    document.getElementById('password').required = true;
    document.getElementById('password_confirmation').required = true;
    
    // Limpiar formulario
    document.getElementById('formUsuario').reset();
    limpiarErrores();
    
    mostrarModal('modalUsuario');
});

// Función para editar usuario
function editarUsuario(userId) {
    usuarioEditando = userId;
    
    // Obtener datos del usuario
    fetch(`/users/${userId}`)
        .then(response => response.json())
        .then(user => {
            document.getElementById('modalTitulo').textContent = 'Editar Usuario';
            document.getElementById('modalIcon').className = 'fas fa-user-edit text-xl';
            document.getElementById('btnText').textContent = 'Actualizar Usuario';
            document.getElementById('btnIcon').className = 'fas fa-save mr-2';
            document.getElementById('formUsuario').action = `/users/${userId}`;
            document.getElementById('methodField').innerHTML = '@method("PUT")';
            document.getElementById('passwordHelp').classList.remove('hidden');
            document.getElementById('password').required = false;
            document.getElementById('password_confirmation').required = false;
            
            // Llenar datos
            document.getElementById('name').value = user.name;
            document.getElementById('email').value = user.email;
            document.getElementById('password').value = '';
            document.getElementById('password_confirmation').value = '';
            
            limpiarErrores();
            mostrarModal('modalUsuario');
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error al obtener los datos del usuario', 'error');
        });
}

// Función para eliminar usuario
function eliminarUsuario(userId, userName) {
    document.getElementById('nombreUsuarioEliminar').textContent = userName;
    document.getElementById('formEliminar').action = `/users/${userId}`;
    mostrarModal('modalEliminar');
}

// Mostrar modal con animación
function mostrarModal(modalId) {
    const modal = document.getElementById(modalId);
    const content = document.getElementById(modalId === 'modalUsuario' ? 'modalContent' : 'modalEliminarContent');
    
    modal.classList.remove('hidden');
    
    // Animar entrada
    setTimeout(() => {
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
    }, 10);
}

// Cerrar modales con animación
function cerrarModal() {
    cerrarModalConAnimacion('modalUsuario');
}

function cerrarModalEliminar() {
    cerrarModalConAnimacion('modalEliminar');
}

function cerrarModalConAnimacion(modalId) {
    const modal = document.getElementById(modalId);
    const content = document.getElementById(modalId === 'modalUsuario' ? 'modalContent' : 'modalEliminarContent');
    
    // Animar salida
    content.classList.remove('scale-100', 'opacity-100');
    content.classList.add('scale-95', 'opacity-0');
    
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

// Toggle password visibility
function togglePassword(fieldId) {
    const passwordField = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + 'Icon');
    
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

// Limpiar errores
function limpiarErrores() {
    const errores = ['errorName', 'errorEmail', 'errorPassword'];
    errores.forEach(id => {
        const elemento = document.getElementById(id);
        elemento.classList.add('hidden');
        elemento.querySelector('span').textContent = '';
    });
    
    // Limpiar bordes rojos
    const campos = ['name', 'email', 'password', 'password_confirmation'];
    campos.forEach(campo => {
        const input = document.getElementById(campo);
        input.classList.remove('border-red-500', 'focus:border-red-500');
        input.classList.add('border-gray-200', 'focus:border-blue-500');
    });
}

// Manejar envío del formulario
document.getElementById('formUsuario').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('btnSubmit');
    const btnText = document.getElementById('btnText');
    const btnIcon = document.getElementById('btnIcon');
    
    // Deshabilitar botón y mostrar loading
    submitBtn.disabled = true;
    btnIcon.className = 'fas fa-spinner fa-spin mr-2';
    btnText.textContent = 'Procesando...';
    
    const formData = new FormData(this);
    const actionUrl = this.action;
    
    fetch(actionUrl, {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => {
        if (response.ok) {
            mostrarNotificacion(usuarioEditando ? 'Usuario actualizado correctamente' : 'Usuario creado correctamente', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            return response.text().then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.errors) {
                        mostrarErrores(data.errors);
                    }
                } catch (e) {
                    mostrarNotificacion('Error al procesar la solicitud', 'error');
                }
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('Error al enviar el formulario', 'error');
    })
    .finally(() => {
        // Rehabilitar botón
        submitBtn.disabled = false;
        btnIcon.className = 'fas fa-save mr-2';
        btnText.textContent = usuarioEditando ? 'Actualizar Usuario' : 'Crear Usuario';
    });
});

// Mostrar errores de validación
function mostrarErrores(errores) {
    limpiarErrores();
    
    Object.keys(errores).forEach(campo => {
        const errorElement = document.getElementById(`error${campo.charAt(0).toUpperCase() + campo.slice(1)}`);
        const inputElement = document.getElementById(campo);
        
        if (errorElement && inputElement) {
            errorElement.querySelector('span').textContent = errores[campo][0];
            errorElement.classList.remove('hidden');
            inputElement.classList.remove('border-gray-200', 'focus:border-blue-500');
            inputElement.classList.add('border-red-500', 'focus:border-red-500');
        }
    });
}

// Mostrar notificaciones
function mostrarNotificacion(mensaje, tipo) {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 transform transition-all duration-300 translate-x-full ${
        tipo === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
    }`;
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${tipo === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
            ${mensaje}
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animar entrada
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 10);
    
    // Remover después de 3 segundos
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// Cerrar modal al hacer clic fuera
window.addEventListener('click', function(e) {
    const modal = document.getElementById('modalUsuario');
    const modalEliminar = document.getElementById('modalEliminar');
    
    if (e.target === modal) {
        cerrarModal();
    }
    if (e.target === modalEliminar) {
        cerrarModalEliminar();
    }
});

// Cerrar modal con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (!document.getElementById('modalUsuario').classList.contains('hidden')) {
            cerrarModal();
        }
        if (!document.getElementById('modalEliminar').classList.contains('hidden')) {
            cerrarModalEliminar();
        }
    }
});
</script>
@endsection