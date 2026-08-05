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
        'obra_id',
        'marca',
        'etapa',
        'descripcion',
        'peso_unitario',
        'longitud',
        'grupo_precio_id',
        'cantidad',
        'porcentaje',
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
            'peso_unitario' => 'decimal:3',
            'longitud' => 'integer',
            'porcentaje' => 'decimal:2',
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
     * Snapshot: concepto_id no tiene FK (el detalle sobrevive al borrado del
     * concepto). El renglon guarda su propia copia de marca, etapa, descripcion,
     * peso y longitud, asi que esta relacion es solo un puente para navegar a la
     * pieza actual; nunca debe usarse para mostrar o calcular lo ya pagado.
     */
    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto::class, 'concepto_id');
    }
}
