<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UploadStatusController;

// API para estado de uploads
Route::get('uploads/{upload}/status', [UploadStatusController::class, 'show'])
    ->name('api.uploads.status');