<?php

namespace App\Models\Cob;

use App\Models\Costos\Presupuesto;
use App\Models\Obra;
use App\Services\Cob\IcsoeService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Partida extends Model
{
    use HasFactory;

    protected $table = 'cob_partidas';

    /**
     * Las partidas componen el valor a ejecutar de las obras que no son a
     * precio unitario, así que moverlas obliga a recalcular el ICSOE.
     */
    protected static function booted(): void
    {
        static::saved(function (self $partida) {
            if ($partida->wasRecentlyCreated || $partida->wasChanged(['monto', 'obra_id'])) {
                app(IcsoeService::class)->programarPorObra($partida->obra_id, 'Cambiaron las partidas de la obra');
            }
        });

        static::deleted(fn (self $partida) => app(IcsoeService::class)->programarPorObra(
            $partida->obra_id,
            'Se eliminó una partida de la obra',
        ));
    }

    /** @var list<string> */
    protected $fillable = [
        'obra_id',
        'tipo',
        'estatus',
        'descripcion',
        'monto',
        'moneda',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    /**
     * Presupuesto de costos ligado a esta partida, si existe.
     */
    public function presupuesto(): MorphOne
    {
        return $this->morphOne(Presupuesto::class, 'presupuestable');
    }

    /** Estimaciones (nivel partida) que cubren esta partida. */
    public function estimaciones(): BelongsToMany
    {
        return $this->belongsToMany(Estimacion::class, 'cob_estimacion_partida', 'partida_id', 'estimacion_id')
            ->withTimestamps();
    }
}
