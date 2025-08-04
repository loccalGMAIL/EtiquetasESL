<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Product;
use App\Models\UploadProcessLog;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'codigo_interno',
        'cod_barras',
        'descripcion',
        'is_active'
    ];

    protected $casts = [
        'product_id' => 'integer',
        'is_active' => 'boolean'
    ];

    /**
     * Relación con el producto maestro
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relación con los logs de procesamiento
     */
    public function processLogs()
    {
        return $this->hasMany(UploadProcessLog::class);
    }

    /**
     * Buscar o crear variante
     * CLAVE: codigo_interno + descripcion
     */
    public static function findOrCreateVariant($codigoInterno, $descripcion, $codBarras, $productId)
    {
        return self::firstOrCreate(
            [
                'codigo_interno' => $codigoInterno,
                'cod_barras' => $codBarras  // ← CORRECTO
            ],
            [
                'product_id' => $productId,
                'descripcion' => $descripcion,  // ← Mover aquí
                'is_active' => true
            ]
        );
    }

    /**
     * Actualizar código de barras si cambió
     */
    public function updateBarcodeIfChanged($newBarcode)
    {
        if ($this->cod_barras !== $newBarcode) {
            $oldBarcode = $this->cod_barras;
            $this->update(['cod_barras' => $newBarcode]);

            return [
                'changed' => true,
                'old_barcode' => $oldBarcode,
                'new_barcode' => $newBarcode
            ];
        }

        return ['changed' => false];
    }

    /**
     * Obtener datos para enviar a eRetail
     * CRÍTICO: Este ID es el goodsCode que va a eRetail
     */
    public function getERetailData()
    {
        return [
            'goodsCode' => $this->id, // CRÍTICO: ID estable para eRetail
            'codigo_interno' => $this->codigo_interno,
            'cod_barras' => $this->cod_barras,
            'descripcion' => $this->descripcion,
            'precio' => $this->product->precio_calculado ?? 0.00,
            'is_active' => $this->is_active
        ];
    }

    /**
     * Buscar variante por cualquier código de barras
     */
    public static function findByBarcode($barcode)
    {
        return self::where('cod_barras', $barcode)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Buscar variantes por código interno
     */
    public static function findByCodigoInterno($codigoInterno)
    {
        return self::where('codigo_interno', $codigoInterno)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Buscar variante específica por código interno y descripción
     */
    public static function findByCodigoAndDescription($codigoInterno, $descripcion)
    {
        return self::where('codigo_interno', $codigoInterno)
            ->where('descripcion', $descripcion)
            ->first();
    }

    /**
     * Obtener todas las variantes para enviar a eRetail
     */
    public static function getVariantsForERetail($shopCode = null)
    {
        $query = self::with('product')
            ->where('is_active', true);

        if ($shopCode) {
            // Si necesitamos filtrar por tienda en el futuro
            // $query->whereHas('product', function($q) use ($shopCode) {
            //     $q->where('shop_code', $shopCode);
            // });
        }

        return $query->get()->map(function ($variant) {
            return $variant->getERetailData();
        });
    }

    /**
     * Desactivar variante
     */
    public function deactivate()
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Activar variante
     */
    public function activate()
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Scope para variantes activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para variantes inactivas
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope para buscar por descripción parcial
     */
    public function scopeWithDescriptionLike($query, $term)
    {
        return $query->where('descripcion', 'like', "%{$term}%");
    }

    /**
     * Verificar si el código de barras cambió
     */
    public function hasBarcodeChanged($newBarcode)
    {
        return $this->cod_barras !== $newBarcode;
    }

    /**
     * Obtener precio actual desde el producto
     */
    public function getCurrentPrice()
    {
        return $this->product->precio_calculado ?? 0.00;
    }

    /**
     * Generar descripción única para mostrar
     */
    public function getDisplayDescriptionAttribute()
    {
        return "{$this->codigo_interno} - {$this->descripcion}";
    }

    /**
     * Obtener información completa para debugging
     */
    public function getDebugInfo()
    {
        return [
            'variant_id' => $this->id,
            'product_id' => $this->product_id,
            'codigo_interno' => $this->codigo_interno,
            'cod_barras' => $this->cod_barras,
            'descripcion' => $this->descripcion,
            'precio_calculado' => $this->getCurrentPrice(),
            'is_active' => $this->is_active,
            'eretail_goodsCode' => $this->id // Para debugging
        ];
    }
}