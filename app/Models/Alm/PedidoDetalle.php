<?php

namespace App\Models\Alm;

use App\Models\Alm\Concerns\LlenaArticuloId;
use App\Models\Costos\Producto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un renglón de lo pedido.
 *
 * `cantidad_surtida` es caché: la escribe `App\Services\Alm\SurtidoPedido`
 * recalculándola desde los documentos vivos, nunca sumándole de a poco. Así,
 * cancelar una salida la deja correcta sin lógica de reverso.
 */
class PedidoDetalle extends Model
{
    use LlenaArticuloId;

    protected $table = 'alm_pedido_detalle';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pedido_id',
        'producto_id',
        'articulo_id',
        'cantidad_solicitada',
        'cantidad_surtida',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_solicitada' => 'decimal:4',
            'cantidad_surtida' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Pedido, $this>
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
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

    /** Lo que falta por entregar de este renglón. Nunca negativo. */
    /** Un activo, con o sin serie: no se gasta, se presta bajo resguardo. */
    public function esHerramienta(): bool
    {
        return $this->articulo?->tipo === \App\Enums\Alm\ProductoTipo::Activo;
    }

    public function pendiente(): float
    {
        return max(0, (float) $this->cantidad_solicitada - (float) $this->cantidad_surtida);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->whereColumn('cantidad_surtida', '<', 'cantidad_solicitada');
    }
}
