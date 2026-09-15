<?php

namespace App\Models\Costos;

use App\Models\Concerns\LlenaLlavesDeItem;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Punto del histórico de precios de un producto: precio cotizado por un
 * proveedor en una fecha (normalmente alimentado desde las cotizaciones de
 * una requisición).
 *
 * @use HasFactory<\Database\Factories\Costos\ProductoPrecioFactory>
 */
class ProductoPrecio extends Model
{
    use HasFactory, LlenaLlavesDeItem;

    /**
     * @return list<string>
     */
    protected static function llavesLegado(): array
    {
        return ['producto_id'];
    }

    protected $table = 'costos_producto_precios';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'item_id',
        'producto_id',
        'proveedor_id',
        'precio',
        'moneda',
        'fecha',
        'requisicion_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio' => 'decimal:4',
            'fecha' => 'date',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }
}
