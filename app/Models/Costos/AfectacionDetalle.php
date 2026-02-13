<?php

namespace App\Models\Costos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @use HasFactory<\Database\Factories\Costos\AfectacionDetalleFactory>
 */
class AfectacionDetalle extends Model
{
    use HasFactory;

    protected $table = 'costos_afectaciones_detalle';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'afectacion_id',
        'obra_rubro_id',
        'concepto',
        'cantidad',
        'precio_unitario',
        'monto',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'precio_unitario' => 'decimal:2',
            'monto' => 'decimal:2',
        ];
    }

    public function afectacion(): BelongsTo
    {
        return $this->belongsTo(AfectacionPresupuestal::class, 'afectacion_id');
    }

    public function obraRubro(): BelongsTo
    {
        return $this->belongsTo(ObraRubro::class, 'obra_rubro_id');
    }
}
