<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

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
        'etapa',
        'responsable_sugerido',
        'duracion_estimada',
        'completada',
        'fecha_vencimiento',
        'fecha_completada',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'completada' => 'boolean',
            'fecha_vencimiento' => 'date:Y-m-d',
            'fecha_completada' => 'date:Y-m-d',
        ];
    }

    public function media(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable');
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
