<?php

namespace App\Models\Alm;

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
    protected $table = 'alm_salida_detalle';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'salida_id',
        'producto_id',
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
