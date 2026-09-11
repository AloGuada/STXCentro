<?php

namespace App\Models\Qal;

use App\Models\Concepto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una marca del plan con cuántas piezas van de ella, o una baja con su motivo.
 *
 * Son filas y no el texto pegado (RN-25): así se puede preguntar en qué
 * semanas se programó una marca. `marca` va normalizada como la lee
 * `LectorDeProgramacion`; `concepto_id` la amarra al catálogo vigente cuando
 * es única en él.
 *
 * @use HasFactory<\Database\Factories\Qal\ProgramacionMarcaFactory>
 */
class ProgramacionMarca extends Model
{
    use HasFactory;

    protected $table = 'qal_programacion_marcas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'programacion_id',
        'concepto_id',
        'marca',
        'cantidad',
        'es_baja',
        'motivo_baja',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'es_baja' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Programacion, $this>
     */
    public function programacion(): BelongsTo
    {
        return $this->belongsTo(Programacion::class, 'programacion_id');
    }

    /**
     * @return BelongsTo<Concepto, $this>
     */
    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto::class, 'concepto_id');
    }
}
