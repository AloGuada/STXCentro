<?php

namespace App\Models\Alm;

use App\Models\Concerns\LlenaLlavesDeItem;
use App\Models\Costos\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un renglón entregado.
 *
 * `pedido_detalle_id` amarra renglón con renglón: con el amarre sólo en la
 * cabecera habría que casar por `producto_id`, y eso falla en cuanto un pedido
 * pide el mismo artículo dos veces.
 */
class SalidaDetalle extends Model
{
    use LlenaLlavesDeItem;

    protected $table = 'alm_salida_detalle';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'salida_id',
        'item_id',
        'producto_id',
        'articulo_id',
        'pedido_detalle_id',
        'cantidad',
        'costo_unitario',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'costo_unitario' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Salida, $this>
     */
    public function salida(): BelongsTo
    {
        return $this->belongsTo(Salida::class);
    }

    /**
     * Con qué guarda Almacén este renglón.
     *
     * Es la relación buena: `articulo_id` es la columna que queda cuando se
     * cierre la mudanza del catálogo. `producto()` sigue aquí sólo mientras
     * conviven las dos columnas.
     *
     * @return BelongsTo<Articulo, $this>
     */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }

    /**
     * El producto de Compras. **Andamio**: se va con la columna en la fase B.
     * Lo que hoy se lea de aquí debe pasar a `articulo()`.
     *
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * @return BelongsTo<PedidoDetalle, $this>
     */
    public function pedidoDetalle(): BelongsTo
    {
        return $this->belongsTo(PedidoDetalle::class, 'pedido_detalle_id');
    }

    /** Lo que costó lo entregado, al costo con el que salió del almacén. */
    public function importe(): float
    {
        return (float) $this->cantidad * (float) ($this->costo_unitario ?? 0);
    }
}
