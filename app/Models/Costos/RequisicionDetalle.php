<?php

namespace App\Models\Costos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<\Database\Factories\Costos\RequisicionDetalleFactory>
 */
class RequisicionDetalle extends Model
{
    use HasFactory;

    protected $table = 'costos_requisicion_detalle';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'requisicion_id',
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
}
