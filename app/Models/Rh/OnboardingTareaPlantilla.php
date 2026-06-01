<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingTareaPlantilla extends Model
{
    /** @use HasFactory<\Database\Factories\Rh\OnboardingTareaPlantillaFactory> */
    use HasFactory;

    protected $table = 'rh_onboarding_tareas_plantilla';

    /** @var list<string> */
    protected $fillable = [
        'puesto_id',
        'titulo',
        'descripcion',
        'etapa',
        'responsable',
        'duracion_estimada',
        'dias_desde_inicio',
        'orden',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'dias_desde_inicio' => 'integer',
            'orden' => 'integer',
        ];
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class, 'puesto_id');
    }
}
