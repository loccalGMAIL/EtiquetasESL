<?php

// use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\Api\UploadStatusController;
use App\Http\Controllers\SettingsController;

Route::get('/', function () {
    return redirect()->route('uploads.index');
});

// Rutas de uploads
Route::resource('uploads', UploadController::class)->only([
    'index', 'create', 'store', 'show'
]);
Route::get('uploads/{upload}/download', [UploadController::class, 'download'])->name('uploads.download');
Route::get('uploads/{upload}/report', [UploadController::class, 'report'])->name('uploads.report');
Route::get('uploads/{upload}/status', [UploadStatusController::class, 'show'])->name('api.uploads.status');
Route::post('uploads/{upload}/refresh-tags', [UploadController::class, 'refreshTags'])->name('uploads.refresh-tags');


// Rutas de configuración
Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');