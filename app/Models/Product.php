<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProductVariant;
use App\Models\ProductPriceHistory;
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo_interno',
        'precio_final',
        'precio_calculado',
        'last_price_update'
    ];

    protected $casts = [
        'precio_final' => 'decimal:2',
        'precio_calculado' => 'decimal:2',
        'last_price_update' => 'datetime'
    ];
    /**
     * Relación con las variantes del producto
     * Un producto puede tener múltiples variantes (códigos de barras)
     */
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Buscar o crear producto por código interno
     */
    public static function findOrCreateByCodigoInterno($codigoInterno)
    {
        return self::firstOrCreate(
            ['codigo_interno' => $codigoInterno],
            [
                'precio_final' => 0.00,
                'precio_calculado' => 0.00,
                'last_price_update' => now()
            ]
        );
    }

    /**
     * Actualizar precio del producto
     * Esto afecta a todas las variantes del producto
     */
    public function updatePrice($precioFinal, $precioCalculado, $uploadId = null)
    {
        $precioAnterior = $this->precio_final;

        // Actualizar precios
        $this->update([
            'precio_final' => $precioFinal,
            'precio_calculado' => $precioCalculado,
            'last_price_update' => now()
        ]);
    }


    /**
     * Verificar si el precio cambió
     */
    public function hasPriceChanged($nuevoPrecio)
    {
        return $this->precio_final != $nuevoPrecio;
    }

    /**
     * Calcular porcentaje de cambio de precio
     */
    public function calculatePriceChangePercentage($nuevoPrecio)
    {
        if ($this->precio_final == 0) {
            return 0;
        }
        return round((($nuevoPrecio - $this->precio_final) / $this->precio_final) * 100, 2);
    }

    /**
     * Obtener variantes activas
     */
    public function activeVariants()
    {
        return $this->variants()->where('is_active', true);
    }

    /**
     * Obtener todas las variantes con sus códigos de barras
     */
    public function getAllBarcodes()
    {
        return $this->variants()->pluck('cod_barras')->toArray();
    }

    /**
     * Scope para productos con precio mayor a X
     */
    public function scopeWithPriceGreaterThan($query, $price)
    {
        return $query->where('precio_final', '>', $price);
    }

    /**
     * Scope para productos actualizados recientemente
     */
    public function scopeRecentlyUpdated($query, $days = 7)
    {
        return $query->where('last_price_update', '>=', now()->subDays($days));
    }

    /**
     * Scope para productos sin actualizar hace X días
     */
    public function scopeNotUpdatedSince($query, $days = 30)
    {
        return $query->where('last_price_update', '<', now()->subDays($days))
            ->orWhereNull('last_price_update');
    }

//     /**
//      * Obtener último cambio de precio
//      */
//     public function getLastPriceChange()
//     {
//         return $this->priceHistory()
//             ->orderBy('created_at', 'desc')
//             ->first();
//     }

//     /**
//      * Obtener histórico de precios ordenado
//      */
//     public function getPriceHistoryOrdered()
//     {
//         return $this->priceHistory()
//             ->orderBy('fec_ul_mo', 'desc')
//             ->get();
//     }
}