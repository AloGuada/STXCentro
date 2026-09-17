<?php

namespace App\Models\Qal;

use App\Enums\Qal\NivelAql;
use App\Enums\Qal\VeredictoLote;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El muestreo AQL de un lote de piezas iguales en 1ª, con la aceptación y el
 * rechazo que regían al capturarlo.
 */
class Muestreo extends Model
{
    protected $table = 'qal_muestreos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'inspeccion_id',
        'tamano_lote',
        'nivel',
        'muestra',
        'aceptacion',
        'rechazo',
        'conformes',
        'rechazadas',
        'veredicto',
        'disposicion',
        'detalle_fallas',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nivel' => NivelAql::class,
            'veredicto' => VeredictoLote::class,
            'tamano_lote' => 'integer',
            'muestra' => 'integer',
            'aceptacion' => 'integer',
            'rechazo' => 'integer',
            'conformes' => 'integer',
            'rechazadas' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Inspeccion, $this>
     */
    public function inspeccion(): BelongsTo
    {
        return $this->belongsTo(Inspeccion::class, 'inspeccion_id');
    }
}
