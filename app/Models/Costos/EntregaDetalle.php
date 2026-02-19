<?php

namespace App\Models\Costos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
