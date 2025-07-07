<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\Api\UploadStatusController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Auth\AuthController; // ✅ Nueva importación

// ✅ Rutas de autenticación (sin middleware)
Route::get('login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('login', [AuthController::class, 'login'])->name('login.post');
Route::post('logout', [AuthController::class, 'logout'])->name('logout');
Route::get('auth/check', [AuthController::class, 'checkAuth'])->name('auth.check');

// ✅ Redirigir raíz al login si no está autenticado
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('uploads.index');
    }
    return redirect()->route('login');
});

// ✅ Rutas protegidas con middleware auth
Route::middleware(['auth'])->group(function () {
    
    // Rutas de uploads
    Route::resource('uploads', UploadController::class)->only([
        'index', 'create', 'store', 'show'
    ]);
    Route::get('uploads/{upload}/download', [UploadController::class, 'download'])->name('uploads.download');
    Route::get('uploads/{upload}/report', [UploadController::class, 'report'])->name('uploads.report');
    Route::get('uploads/{upload}/status', [UploadStatusController::class, 'show'])
        ->name('api.uploads.status');
        Route::post('uploads/{upload}/refresh-tags', [UploadController::class, 'refreshTags'])->name('uploads.refresh-tags');

    // Rutas de configuración
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

    // Rutas de usuarios
    Route::resource('users', UserController::class)->only([
        'index', 'store', 'show', 'update', 'destroy'
    ]);
    
});