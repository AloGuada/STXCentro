<?php

namespace App\Models\Costos;

use App\Enums\Costos\DevolucionEstatus;
use App\Enums\Costos\FacturaEstatus;
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

    /**
     * Al crear una recepción por partida, revisar si alguna factura en
     * pendiente_entrega de esta OC ya quedó cubierta y promoverla.
     */
    protected static function booted(): void
    {
        static::created(function (self $ed) {
            $ocd = $ed->ordenCompraDetalle;
            $oc = $ocd?->ordenCompra;
            if (! $oc) {
                return;
            }

            $oc->facturas()
                ->where('estatus', FacturaEstatus::PendienteEntrega->value)
                ->with('detalles')
                ->get()
                ->each->recalcularEstatus();
        });
    }

    protected $table = 'costos_entrega_detalle';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'entrega_id',
        'orden_compra_detalle_id',
        'cantidad_recibida',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_recibida' => 'decimal:2',
        ];
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(Entrega::class, 'entrega_id');
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
}
