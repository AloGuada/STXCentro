<?php

namespace App\Models\Costos;

use App\Enums\Costos\DevolucionEstatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<\Database\Factories\Costos\EntregaDetalleFactory>
 */
class EntregaDetalle extends Model
{
    use HasFactory;

    protected $table = 'costos_entrega_detalle';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'entrega_id',
        'orden_compra_detalle_id',
        'producto_id',
        'descripcion',
        'unidad',
        'cantidad_recibida',
        'precio_unitario',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_recibida' => 'decimal:4',
            'precio_unitario' => 'decimal:4',
        ];
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(Entrega::class, 'entrega_id');
    }

    /**
     * El artículo recibido. Se guarda aquí y no sólo en la partida de la orden:
     * las entradas sin orden no tienen partida, y si alguien re-apunta la de la
     * orden el movimiento ya sellado no debe cambiar de artículo.
     *
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function ordenCompraDetalle(): BelongsTo
    {
        return $this->belongsTo(OrdenCompraDetalle::class, 'orden_compra_detalle_id');
    }

    public function devoluciones(): HasMany
    {
        return $this->hasMany(Devolucion::class, 'entrega_detalle_id');
    }

    /**
     * Suma de devoluciones vigentes contra esta partida de recepcion.
     */
    public function getCantidadDevueltaAttribute(): float
    {
        return (float) $this->devoluciones()
            ->where('estatus', DevolucionEstatus::Vigente->value)
            ->sum('cantidad');
    }

    /**
     * Cantidad neta efectivamente recibida = recibida - devuelta vigente.
     */
    public function getCantidadNetaRecibidaAttribute(): float
    {
        return max(0.0, (float) $this->cantidad_recibida - $this->cantidad_devuelta);
    }

    /**
     * Precio unitario efectivo del renglón recibido: el capturado en la recepción
     * si existe, o el de la partida de la OC como respaldo (recepciones antiguas
     * o sin ajuste de precio).
     */
    public function getPrecioUnitarioEfectivoAttribute(): float
    {
        return $this->precio_unitario !== null
            ? (float) $this->precio_unitario
            : (float) ($this->ordenCompraDetalle?->precio_unitario ?? 0);
    }
}
