<?php

namespace App\Models\Costos;

use App\Models\Concerns\LlenaLlavesDeItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<\Database\Factories\Costos\OrdenCompraDetalleFactory>
 */
class OrdenCompraDetalle extends Model
{
    use HasFactory, LlenaLlavesDeItem;

    /**
     * @return list<string>
     */
    protected static function llavesLegado(): array
    {
        return ['producto_id'];
    }

    protected $table = 'costos_ordenes_compra_detalle';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'orden_compra_id',
        'requisicion_detalle_id',
        'item_id',
        'producto_id',
        'obra_rubro_id',
        'uso_cfdi_id',
        'tipo_fiscal',
        'sin_impuestos',
        'descripcion',
        'codigo_producto',
        'unidad',
        'cantidad',
        'cantidad_cancelada',
        'precio_unitario',
        'subtotal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'cantidad_cancelada' => 'decimal:4',
            'precio_unitario' => 'decimal:4',
            'subtotal' => 'decimal:2',
            'sin_impuestos' => 'boolean',
            'tipo_fiscal' => \App\Enums\Costos\TipoFiscalPartida::class,
        ];
    }

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class, 'orden_compra_id');
    }

    public function obraRubro(): BelongsTo
    {
        return $this->belongsTo(ObraRubro::class, 'obra_rubro_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function usoCfdi(): BelongsTo
    {
        return $this->belongsTo(UsoCfdi::class, 'uso_cfdi_id');
    }

    public function requisicionDetalle(): BelongsTo
    {
        return $this->belongsTo(RequisicionDetalle::class, 'requisicion_detalle_id');
    }

    /**
     * Las cancelaciones de unidades de esta partida, autorizadas o no.
     *
     * @return HasMany<OrdenCompraDetalleCancelacion, $this>
     */
    public function cancelaciones(): HasMany
    {
        return $this->hasMany(OrdenCompraDetalleCancelacion::class, 'orden_compra_detalle_id');
    }

    /**
     * Lo que sigue vigente de la partida: lo pedido menos lo cancelado.
     */
    public function cantidadVigente(): float
    {
        return max(0.0, (float) $this->cantidad - (float) $this->cantidad_cancelada);
    }
}
