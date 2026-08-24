<?php

namespace App\Models\Costos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @use HasFactory<\Database\Factories\Costos\OrdenCompraDetalleFactory>
 */
class OrdenCompraDetalle extends Model
{
    use HasFactory;

    protected $table = 'costos_ordenes_compra_detalle';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'orden_compra_id',
        'requisicion_detalle_id',
        'producto_id',
        'obra_rubro_id',
        'uso_cfdi_id',
        'tipo_fiscal',
        'sin_impuestos',
        'descripcion',
        'codigo_producto',
        'unidad',
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
}
