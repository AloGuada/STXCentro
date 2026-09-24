<?php

namespace App\Models\Qal;

use App\Models\Concepto;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Pieza;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una pieza del plan de la semana: el QR que se va a hacer, el grupo de
 * trabajo que lo hace y el módulo donde lo hace, escrito como lo nombra
 * planta («1.2»).
 *
 * La marca, el lote, el QR y el QS se congelan al agregarla: el catálogo se
 * versiona y el cruce con las inspecciones es por el QR escrito.
 *
 * @use HasFactory<\Database\Factories\Qal\ProgramacionPiezaFactory>
 */
class ProgramacionPieza extends Model
{
    use HasFactory;

    protected $table = 'qal_programacion_piezas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'programacion_id',
        'pieza_id',
        'concepto_id',
        'marca',
        'lote',
        'qr',
        'qs',
        'grupo_trabajo_id',
        'modulo',
    ];

    /**
     * @return BelongsTo<Programacion, $this>
     */
    public function programacion(): BelongsTo
    {
        return $this->belongsTo(Programacion::class, 'programacion_id');
    }

    /**
     * @return BelongsTo<Pieza, $this>
     */
    public function pieza(): BelongsTo
    {
        return $this->belongsTo(Pieza::class, 'pieza_id');
    }

    /**
     * @return BelongsTo<Concepto, $this>
     */
    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto::class, 'concepto_id');
    }

    /**
     * @return BelongsTo<GrupoTrabajo, $this>
     */
    public function grupoTrabajo(): BelongsTo
    {
        return $this->belongsTo(GrupoTrabajo::class, 'grupo_trabajo_id');
    }
}
