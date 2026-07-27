<?php

namespace App\Models\Prod;

use App\Models\Concepto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiquidacionDetalle extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\LiquidacionDetalleFactory> */
    use HasFactory;

    protected $table = 'prod_liquidacion_detalle';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'liquidacion_id',
        'concepto_id',
        'grupo_precio_id',
        'cantidad',
        'kilos',
        'precio_kilo_aplicado',
        'total',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'kilos' => 'decimal:3',
            'precio_kilo_aplicado' => 'decimal:4',
            'total' => 'decimal:2',
        ];
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class, 'liquidacion_id');
    }

    /**
     * Snapshot: concepto_id no tiene FK (el detalle sobrevive al borrado del concepto).
     * La relacion es solo para resolver marca/descripcion cuando el concepto aun existe.
     */
    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto::class, 'concepto_id');
    }
}
