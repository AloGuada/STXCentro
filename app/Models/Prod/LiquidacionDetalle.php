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
        'pieza_id',
        'qr',
        'qs',
        'obra_id',
        'marca',
        'lote',
        'proceso_id',
        'proceso_nombre',
        'subproceso_id',
        'subproceso_nombre',
        'descripcion',
        'categoria_nombre',
        'peso_unitario',
        'longitud',
        'grupo_precio_id',
        'porcentaje',
        'kilos',
        'precio_kilo_aplicado',
        'precio_subproceso_aplicado',
        'total',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'peso_unitario' => 'decimal:3',
            'longitud' => 'integer',
            'porcentaje' => 'decimal:2',
            'kilos' => 'decimal:3',
            'precio_kilo_aplicado' => 'decimal:4',
            'precio_subproceso_aplicado' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class, 'liquidacion_id');
    }

    /**
     * Snapshot: un renglon es una pieza pagada en un proceso. Ni concepto_id ni
     * pieza_id tienen FK (el detalle sobrevive al borrado del catalogo), y el
     * renglon guarda su propia copia de qs, marca, lote, proceso, descripcion,
     * peso y longitud. Estas relaciones son solo un puente para navegar al
     * catalogo actual; nunca deben usarse para mostrar o calcular lo ya pagado.
     */
    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto::class, 'concepto_id');
    }

    public function pieza(): BelongsTo
    {
        return $this->belongsTo(Pieza::class, 'pieza_id');
    }
}
