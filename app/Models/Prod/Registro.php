<?php

namespace App\Models\Prod;

use App\Models\Concepto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Registro extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\RegistroFactory> */
    use HasFactory;

    protected $table = 'prod_registros';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'fecha',
        'concepto_id',
        'grupo_trabajo_id',
        'cantidad',
        'porcentaje',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'cantidad' => 'integer',
            'porcentaje' => 'decimal:2',
        ];
    }

    /**
     * Piezas equivalentes que este registro consume del catálogo: pagar 10
     * piezas al 60% gasta 6, y las 4 restantes quedan para liquidarse después.
     */
    public function piezasEquivalentes(): float
    {
        return round($this->cantidad * ((float) $this->porcentaje / 100), 4);
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto::class, 'concepto_id');
    }

    public function grupoTrabajo(): BelongsTo
    {
        return $this->belongsTo(GrupoTrabajo::class, 'grupo_trabajo_id');
    }
}
