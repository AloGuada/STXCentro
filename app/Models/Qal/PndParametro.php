<?php

namespace App\Models\Qal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un parámetro con que se corrió la prueba: frecuencia, palpador, penetrante…
 *
 * Va como clave/valor porque el juego de parámetros lo decide el método, no
 * nosotros. Lo que la pantalla sugiere por método es una ayuda de captura; el
 * laboratorio puede reportar otros y se guardan igual.
 *
 * @use HasFactory<\Database\Factories\Qal\PndParametroFactory>
 */
class PndParametro extends Model
{
    use HasFactory;

    protected $table = 'qal_pnd_parametros';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'qal_pnd_reporte_id',
        'clave',
        'valor',
    ];

    /**
     * @return BelongsTo<PndReporte, $this>
     */
    public function reporte(): BelongsTo
    {
        return $this->belongsTo(PndReporte::class, 'qal_pnd_reporte_id');
    }
}
