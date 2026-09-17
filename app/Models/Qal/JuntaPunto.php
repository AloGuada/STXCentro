<?php

namespace App\Models\Qal;

use App\Enums\Qal\ResultadoPunto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uno de los 18 puntos del mapeo, contestado para una junta.
 */
class JuntaPunto extends Model
{
    public $timestamps = false;

    protected $table = 'qal_junta_puntos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'junta_id',
        'punto_id',
        'resultado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resultado' => ResultadoPunto::class,
        ];
    }

    /**
     * @return BelongsTo<Junta, $this>
     */
    public function junta(): BelongsTo
    {
        return $this->belongsTo(Junta::class, 'junta_id');
    }

    /**
     * @return BelongsTo<PuntoInspeccion, $this>
     */
    public function punto(): BelongsTo
    {
        return $this->belongsTo(PuntoInspeccion::class, 'punto_id');
    }
}
