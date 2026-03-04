<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingTarea extends Model
{
    /** @use HasFactory<\Database\Factories\Rh\OnboardingTareaFactory> */
    use HasFactory;

    protected $table = 'rh_onboarding_tareas';

    /** @var list<string> */
    protected $fillable = [
        'onboarding_id',
        'responsable_periodo_id',
        'titulo',
        'descripcion',
        'completada',
        'fecha_vencimiento',
        'fecha_completada',
        'evidencia_ruta',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'completada' => 'boolean',
            'fecha_vencimiento' => 'date',
            'fecha_completada' => 'date',
        ];
    }

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(Onboarding::class, 'onboarding_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(PeriodoLaboral::class, 'responsable_periodo_id');
    }
}
