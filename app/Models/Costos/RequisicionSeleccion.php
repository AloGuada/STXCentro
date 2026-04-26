<?php

namespace App\Models\Costos;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @use HasFactory<\Database\Factories\Costos\RequisicionSeleccionFactory>
 */
class RequisicionSeleccion extends Model
{
    use HasFactory;

    protected $table = 'costos_requisicion_seleccion';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'requisicion_detalle_id',
        'cotizacion_precio_id',
        'proveedor_id',
        'cantidad',
        'obra_rubro_id',
        'orden_compra_detalle_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
        ];
    }

    public function detalle(): BelongsTo
    {
        return $this->belongsTo(RequisicionDetalle::class, 'requisicion_detalle_id');
    }

    public function cotizacionPrecio(): BelongsTo
    {
        return $this->belongsTo(RequisicionCotizacionPrecio::class, 'cotizacion_precio_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function obraRubro(): BelongsTo
    {
        return $this->belongsTo(ObraRubro::class);
    }

    public function ordenCompraDetalle(): BelongsTo
    {
        return $this->belongsTo(OrdenCompraDetalle::class);
    }
}
