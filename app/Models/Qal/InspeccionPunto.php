<?php

namespace App\Models\Qal;

use App\Enums\Qal\ResultadoPunto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La respuesta a un punto del catálogo en una inspección: el texto que eligió
 * el inspector y lo que significa.
 */
class InspeccionPunto extends Model
{
    public $timestamps = false;

    protected $table = 'qal_inspeccion_puntos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'inspeccion_id',
        'punto_id',
        'resultado',
        'valor_numerico',
        'valor_texto',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resultado' => ResultadoPunto::class,
            'valor_numerico' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Inspeccion, $this>
     */
    public function inspeccion(): BelongsTo
    {
        return $this->belongsTo(Inspeccion::class, 'inspeccion_id');
    }

    /**
     * @return BelongsTo<PuntoInspeccion, $this>
     */
    public function punto(): BelongsTo
    {
        return $this->belongsTo(PuntoInspeccion::class, 'punto_id');
    }
}
