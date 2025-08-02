<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Product;
use App\Models\Upload;

class ProductPriceHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'precio_original',
        'precio_promocional',
        'fec_ul_mo',
        'upload_id'
    ];

    protected $casts = [
        'product_id' => 'integer',
        'upload_id' => 'integer',
        'precio_original' => 'decimal:2',
        'precio_promocional' => 'decimal:2',
        'fec_ul_mo' => 'datetime'
    ];

    /**
     * Relación con el producto
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relación con el upload que generó este cambio
     */
    public function upload()
    {
        return $this->belongsTo(Upload::class);
    }

    /**
     * Registrar cambio de precio
     */
    public static function recordPriceChange($productId, $precioOriginal, $precioNuevo, $uploadId, $fechaModificacion = null)
    {
        return self::create([
            'product_id' => $productId,
            'precio_original' => $precioOriginal,
            'precio_promocional' => $precioNuevo,
            'fec_ul_mo' => $fechaModificacion ?? now(),
            'upload_id' => $uploadId
        ]);
    }

    /**
     * Verificar si hay cambio de precio para un producto
     */
    public static function hasPriceChanged($productId, $nuevoPrecio)
    {
        $lastPrice = self::where('product_id', $productId)
                          ->orderBy('fec_ul_mo', 'desc')
                          ->first();

        if (!$lastPrice) {
            return true; // Es el primer precio
        }

        return $lastPrice->precio_promocional != $nuevoPrecio;
    }

    /**
     * Obtener último precio registrado
     */
    public static function getLastPriceForProduct($productId)
    {
        return self::where('product_id', $productId)
                   ->orderBy('fec_ul_mo', 'desc')
                   ->first();
    }

    /**
     * Calcular porcentaje de cambio
     */
    public function getChangePercentageAttribute()
    {
        if ($this->precio_original == 0) {
            return 0;
        }

        return round((($this->precio_promocional - $this->precio_original) / $this->precio_original) * 100, 2);
    }

    /**
     * Calcular diferencia absoluta
     */
    public function getPriceDifferenceAttribute()
    {
        return $this->precio_promocional - $this->precio_original;
    }

    /**
     * Verificar si es un aumento de precio
     */
    public function isPriceIncrease()
    {
        return $this->precio_promocional > $this->precio_original;
    }

    /**
     * Verificar si es una disminución de precio
     */
    public function isPriceDecrease()
    {
        return $this->precio_promocional < $this->precio_original;
    }

    /**
     * Scope para cambios de precio por upload
     */
    public function scopeByUpload($query, $uploadId)
    {
        return $query->where('upload_id', $uploadId);
    }

    /**
     * Scope para cambios en un rango de fechas
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('fec_ul_mo', [$startDate, $endDate]);
    }

    /**
     * Scope para cambios de precio recientes
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('fec_ul_mo', '>=', now()->subDays($days));
    }

    /**
     * Scope para aumentos de precio
     */
    public function scopePriceIncreases($query)
    {
        return $query->whereRaw('precio_promocional > precio_original');
    }

    /**
     * Scope para disminuciones de precio
     */
    public function scopePriceDecreases($query)
    {
        return $query->whereRaw('precio_promocional < precio_original');
    }

    /**
     * Scope para cambios de precio mayores a un porcentaje
     */
    public function scopeWithChangeGreaterThan($query, $percentage)
    {
        return $query->whereRaw(
            'ABS((precio_promocional - precio_original) / precio_original * 100) > ?',
            [$percentage]
        );
    }

    /**
     * Obtener histórico de precios para un producto con estadísticas
     */
    public static function getProductPriceStatistics($productId)
    {
        $history = self::where('product_id', $productId)
                       ->orderBy('fec_ul_mo', 'desc')
                       ->get();

        if ($history->isEmpty()) {
            return null;
        }

        $increases = $history->filter(function ($item) {
            return $item->isPriceIncrease();
        });

        $decreases = $history->filter(function ($item) {
            return $item->isPriceDecrease();
        });

        return [
            'total_changes' => $history->count(),
            'price_increases' => $increases->count(),
            'price_decreases' => $decreases->count(),
            'avg_change_percentage' => $history->avg('change_percentage'),
            'max_price' => $history->max('precio_promocional'),
            'min_price' => $history->min('precio_promocional'),
            'current_price' => $history->first()->precio_promocional,
            'first_recorded_price' => $history->last()->precio_original,
            'last_change_date' => $history->first()->fec_ul_mo
        ];
    }

    /**
     * Obtener resumen de cambios por upload
     */
    public static function getUploadSummary($uploadId)
    {
        $changes = self::where('upload_id', $uploadId)->get();

        if ($changes->isEmpty()) {
            return null;
        }

        return [
            'total_price_changes' => $changes->count(),
            'price_increases' => $changes->filter(fn($c) => $c->isPriceIncrease())->count(),
            'price_decreases' => $changes->filter(fn($c) => $c->isPriceDecrease())->count(),
            'avg_change_percentage' => $changes->avg('change_percentage'),
            'total_price_difference' => $changes->sum('price_difference')
        ];
    }
}