<?php

namespace App\Models\Costos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tipo de cambio de referencia cacheado por día y moneda.
 *
 * `tasa` = MXN por 1 unidad de la divisa. La fila `(fecha, moneda)` es única;
 * se puebla on-demand desde {@see \App\Services\Costos\TipoCambioService}
 * (USD → Banxico FIX, EUR → ECB XML).
 */
class TipoCambio extends Model
{
    /** @use HasFactory<\Database\Factories\Costos\TipoCambioFactory> */
    use HasFactory;

    protected $table = 'costos_tipos_cambio';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'fecha',
        'moneda',
        'fuente',
        'tasa',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'tasa' => 'decimal:6',
        ];
    }
}
