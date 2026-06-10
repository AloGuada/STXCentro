<?php

namespace App\Models\Cotiz;

use App\Enums\Cotiz\ResumenBloque;
use App\Enums\Cotiz\ResumenTipoFormula;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @use HasFactory<\Database\Factories\Cotiz\ResumenFilaFactory>
 */
class ResumenFila extends Model
{
    use HasFactory;

    protected $table = 'cotiz_resumen_filas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
        'bloque',
        'tipo_formula',
        'coef_default',
        'referencia_extra',
        'orden',
        'bloqueada',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bloque' => ResumenBloque::class,
            'tipo_formula' => ResumenTipoFormula::class,
            'coef_default' => 'decimal:6',
            'orden' => 'integer',
            'bloqueada' => 'boolean',
        ];
    }
}
