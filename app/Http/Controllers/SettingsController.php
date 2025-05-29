<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Upload;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Mostrar configuración
     */
    public function index()
    {
        // En lugar de all(), usamos get() que es más compatible
        $settings = AppSetting::query()->get()->keyBy('key');
        $uploads = Upload::orderBy('created_at', 'desc')
            ->get();
        return view('settings.index', compact('settings', 'uploads'));


        // return view('settings.index', compact('settings'));
    }

    /**
     * Actualizar configuración
     */
public function update(Request $request)
{
    $request->validate([
        'discount_percentage' => 'required|numeric|min:0|max:100',
        'update_mode' => 'required|in:check_date,force_all,manual',
        'default_shop_code' => 'required|string',
        'create_missing_products' => 'required|boolean',
        'excel_skip_rows' => 'required|integer|min:0|max:10' // ✅ Nueva validación
    ]);

    foreach ($request->except('_token', '_method') as $key => $value) {
        AppSetting::set($key, $value);
    }

    return redirect()
        ->route('settings.index')
        ->with('success', 'Configuración actualizada correctamente');
}
}