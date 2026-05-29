<?php

namespace App\Models\Costos;

use App\Models\Media;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @use HasFactory<\Database\Factories\Costos\RequisicionCotizacionPrecioFactory>
 */
class RequisicionCotizacionPrecio extends Model
{
    use HasFactory;

    protected $table = 'costos_requisicion_cotizacion_precio';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'requisicion_detalle_id',
        'proveedor_id',
        'precio_unitario',
        'moneda',
        'tiempo_entrega_dias',
        'observaciones',
        'media_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio_unitario' => 'decimal:2',
            'tiempo_entrega_dias' => 'integer',
        ];
    }

    public function detalle(): BelongsTo
    {
        return $this->belongsTo(RequisicionDetalle::class, 'requisicion_detalle_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
