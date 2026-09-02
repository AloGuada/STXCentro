<?php

namespace App\Models\Alm;

use App\Models\Alm\Concerns\LlenaArticuloId;
use App\Models\Costos\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que salió y lo que llegó de un artículo.
 *
 * `cantidad_recibida` en `null` es «todavía no se confirma», que no es lo mismo
 * que «llegaron cero»: sin esa distinción, un renglón sin revisar y uno que se
 * perdió entero se verían igual.
 */
class TransferenciaDetalle extends Model
{
    use LlenaArticuloId;

    protected $table = 'alm_transferencia_detalle';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'transferencia_id',
        'producto_id',
        'articulo_id',
        'pedido_detalle_id',
        'cantidad_enviada',
        'cantidad_recibida',
        'costo_unitario',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_enviada' => 'decimal:4',
            'cantidad_recibida' => 'decimal:4',
            'costo_unitario' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Transferencia, $this>
     */
    public function transferencia(): BelongsTo
    {
        return $this->belongsTo(Transferencia::class);
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

    /** Lo que no llegó. Cero mientras nadie haya confirmado nada. */
    public function faltante(): float
    {
        if ($this->cantidad_recibida === null) {
            return 0.0;
        }

        return max(0, (float) $this->cantidad_enviada - (float) $this->cantidad_recibida);
    }
}
