<?php

namespace App\Models\Cob;

use App\Models\Costos\Presupuesto;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Partida extends Model
{
    use HasFactory;

    protected $table = 'cob_partidas';

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
