<?php

namespace App\Models\Costos;

use App\Models\Concerns\LlenaLlavesDeItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<\Database\Factories\Costos\RequisicionDetalleFactory>
 */
class RequisicionDetalle extends Model
{
    use HasFactory, LlenaLlavesDeItem;

    /**
     * @return list<string>
     */
    protected static function llavesLegado(): array
    {
        return ['producto_id'];
    }

    protected $table = 'costos_requisicion_detalle';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'requisicion_id',
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
        'solo_cotizacion',
        'notas',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'solo_cotizacion' => 'boolean',
            'sin_impuestos' => 'boolean',
            'tipo_fiscal' => \App\Enums\Costos\TipoFiscalPartida::class,
        ];
    }

    public function requisicion(): BelongsTo
    {
        return $this->belongsTo(Requisicion::class, 'requisicion_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function obraRubro(): BelongsTo
    {
        return $this->belongsTo(ObraRubro::class, 'obra_rubro_id');
    }

    public function usoCfdi(): BelongsTo
    {
        return $this->belongsTo(UsoCfdi::class, 'uso_cfdi_id');
    }

    public function cotizaciones(): HasMany
    {
        return $this->hasMany(RequisicionCotizacionPrecio::class, 'requisicion_detalle_id');
    }

    public function selecciones(): HasMany
    {
        return $this->hasMany(RequisicionSeleccion::class, 'requisicion_detalle_id');
    }

    /**
     * Los renglones de orden de compra que nacieron de esta partida. La liga ya
     * existía (`requisicion_detalle_id`): sirve para saber qué pasó con lo que
     * se pidió sin guardar nada en la requisición.
     *
     * @return HasMany<OrdenCompraDetalle, $this>
     */
    public function ordenCompraDetalles(): HasMany
    {
        return $this->hasMany(OrdenCompraDetalle::class, 'requisicion_detalle_id');
    }

    /**
     * Unidades de esta partida que compras dio por canceladas en la orden, ya
     * con la firma del jefe de compras. Derivado: no vive en la requisición.
     */
    public function cantidadCanceladaEnOc(): float
    {
        return (float) $this->ordenCompraDetalles->sum(fn (OrdenCompraDetalle $d) => (float) $d->cantidad_cancelada);
    }

    /**
     * Lo que queda vivo de lo que se pidió, una vez descontado lo cancelado en
     * la orden. Si la partida nunca llegó a una OC, es la cantidad pedida.
     */
    public function cantidadVigenteEnOc(): float
    {
        return max(0.0, (float) $this->cantidad - $this->cantidadCanceladaEnOc());
    }
}
