<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Upload;

class UploadStatusController extends Controller
{
    /**
     * Obtener estado actual del upload
     */
    public function show($uploadId)
    {
        $upload = Upload::find($uploadId);
        
        if (!$upload) {
            return response()->json(['error' => 'Upload no encontrado'], 404);
        }
        
        return response()->json([
            'status' => $upload->status,
            'progress' => [
                'percentage' => $upload->progress_percentage,
                'total' => $upload->total_products,
                'processed' => $upload->processed_products,
                'created' => $upload->created_products,
                'updated' => $upload->updated_products,
                'skipped' => $upload->skipped_products,
                'failed' => $upload->failed_products
            ],
            'error_message' => $upload->error_message
        ]);
    }
}