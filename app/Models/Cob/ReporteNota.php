<?php

namespace App\Models\Cob;

use Illuminate\Database\Eloquent\Model;

/**
 * Nota/comentario del reporte semanal de cobranza. Es lo único persistido del
 * reporte: los montos se calculan en vivo. Una fila por (año, semana).
 */
class ReporteNota extends Model
{
    protected $table = 'cob_reporte_notas';

    /** @var list<string> */
    protected $fillable = [
        'anio',
        'semana',
        'notas',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'semana' => 'integer',
        ];
    }
}
