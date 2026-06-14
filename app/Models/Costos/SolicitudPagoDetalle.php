<?php

namespace App\Models\Costos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @use HasFactory<\Database\Factories\Costos\SolicitudPagoDetalleFactory>
 */
class SolicitudPagoDetalle extends Model
{
    use HasFactory;

    protected $table = 'costos_solicitudes_pago_detalle';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'solicitud_id',
        'obra_rubro_id',
        'sobre_obra_cerrada',
        'concepto',
        'cantidad',
        'precio_unitario',
        'subtotal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'precio_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'sobre_obra_cerrada' => 'boolean',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudPago::class, 'solicitud_id');
    }

    public function obraRubro(): BelongsTo
    {
        return $this->belongsTo(ObraRubro::class, 'obra_rubro_id');
    }
}
