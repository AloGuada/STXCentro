<?php

namespace App\Models\Cob;

use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Periodo planeado de cobro de una estimación dentro del cronograma del proyecto.
 * Se genera desde el modal de plan de cobro (N estimaciones × días); las fechas se
 * calculan secuencialmente desde la fecha de inicio del plan del proyecto.
 */
class PlanCobro extends Model
{
    use HasFactory;

    protected $table = 'cob_plan_cobro';

    /** @var list<string> */
    protected $fillable = [
        'proyecto_id',
        'orden',
        'dias',
        'fecha_inicio_plan',
        'fecha_fin_plan',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_inicio_plan' => 'date',
            'fecha_fin_plan' => 'date',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }
}
