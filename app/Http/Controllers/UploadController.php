<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use App\Models\ProductUpdateLog;
use App\Services\ExcelProcessorService;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class UploadController extends Controller
{
    /**
     * Mostrar lista de uploads
     */
    public function index()
    {
        $uploads = Upload::orderBy('created_at', 'desc')
            ->paginate(10);
            
        return view('uploads.index', compact('uploads'));
    }
    
    /**
     * Mostrar formulario de carga
     */
    public function create()
    {
        return view('uploads.create');
    }
    
    /**
     * Almacenar archivo y procesar
     */
    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'file' => 'required|file|mimes:xlsx,xls|max:10240', // Max 10MB
    //         'shop_code' => 'nullable|string'
    //     ]);
        
    //     DB::beginTransaction();
        
    //     try {
    //         // Guardar archivo
    //         $file = $request->file('file');
    //         $filename = time() . '_' . $file->getClientOriginalName();
    //         $path = $file->storeAs('uploads', $filename);
            
    //         // Crear registro de upload
    //         $upload = Upload::create([
    //             'filename' => $path,
    //             'original_filename' => $file->getClientOriginalName(),
    //             'shop_code' => $request->shop_code ?? config('eretail.default_shop_code'),
    //             'user_id' => auth()->id() ?? null,
    //             'status' => 'pending'
    //         ]);
            
    //         DB::commit();
            
    //         // Despachar el procesamiento (lo haremos síncrono por ahora)
    //         // En producción, esto debería ser un Job en cola
    //         $this->processUpload($upload->id);
            
    //         return redirect()
    //             ->route('uploads.show', $upload)
    //             ->with('success', 'Archivo cargado correctamente. Procesando...');
                
    //     } catch (\Exception $e) {
    //         DB::rollBack();
            
    //         return redirect()
    //             ->back()
    //             ->with('error', 'Error al cargar archivo: ' . $e->getMessage())
    //             ->withInput();
    //     }
    // }

    /**
     * Almacenar archivo y procesar
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
            'shop_code' => 'nullable|string'
        ]);
        
        DB::beginTransaction();
        
        try {
            // Guardar archivo
            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('uploads', $filename);
            
            Log::info("Archivo guardado en: {$path}");
            
            // Crear registro de upload
            $upload = Upload::create([
                'filename' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'shop_code' => $request->shop_code ?? config('eretail.default_shop_code'),
                'user_id' => auth()->id() ?? null,
                'status' => 'pending'
            ]);
            
            Log::info("Upload creado con ID: {$upload->id}");
            
            DB::commit();
            
            // Procesar inmediatamente de forma síncrona
            try {
                Log::info("Iniciando procesamiento del upload {$upload->id}");
                
                $processor = app(ExcelProcessorService::class);
                $processor->processFile($path, $upload->id);
                
                Log::info("Procesamiento completado exitosamente");
                
                return redirect()
                    ->route('uploads.show', $upload)
                    ->with('success', 'Archivo procesado correctamente.');
                    
            } catch (\Exception $e) {
                Log::error("Error en procesamiento: " . $e->getMessage());
                
                // Actualizar el upload con el error
                $upload->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ]);
                
                return redirect()
                    ->route('uploads.show', $upload)
                    ->with('error', 'Error al procesar el archivo: ' . $e->getMessage());
            }
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al cargar archivo: " . $e->getMessage());
            
            return redirect()
                ->back()
                ->with('error', 'Error al cargar archivo: ' . $e->getMessage())
                ->withInput();
        }
    }
    
    /**
     * Mostrar detalles de un upload
     */
    public function show(Upload $upload)
    {
        $logs = ProductUpdateLog::where('upload_id', $upload->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        $statistics = [
            'total' => $upload->total_products,
            'procesados' => $upload->processed_products,
            'creados' => $upload->created_products,
            'actualizados' => $upload->updated_products,
            'omitidos' => $upload->skipped_products,
            'errores' => $upload->failed_products,
            'progreso' => $upload->progress_percentage
        ];
        
        return view('uploads.show', compact('upload', 'logs', 'statistics'));
    }
    
    /**
     * Descargar archivo original
     */
    public function download(Upload $upload)
    {
        if (!Storage::exists($upload->filename)) {
            abort(404, 'Archivo no encontrado');
        }
        
        return Storage::download($upload->filename, $upload->original_filename);
    }
    
    /**
     * Reporte de procesamiento
     */
    public function report(Upload $upload)
    {
        $logs = ProductUpdateLog::where('upload_id', $upload->id)
            ->get();
            
        return view('uploads.report', compact('upload', 'logs'));
    }
    
    /**
     * Procesar upload (método privado)
     */
    private function processUpload($uploadId)
    {
        try {
            $upload = Upload::find($uploadId);
            $processor = app(ExcelProcessorService::class);
            
            $processor->processFile(
                storage_path('app/private/' . $upload->filename),
                $uploadId
            );
            
        } catch (\Exception $e) {
            Log::error("Error procesando upload {$uploadId}: " . $e->getMessage());
        }
    }
}