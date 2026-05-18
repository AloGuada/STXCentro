<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Candidatura extends Model
{
    /** @use HasFactory<\Database\Factories\Rh\CandidaturaFactory> */
    use HasFactory;

    protected $table = 'rh_candidaturas';

    /** @var list<string> */
    protected $fillable = [
        'requisicion_id',
        'persona_id',
        'fecha_aplicacion',
        'porcentaje_match',
        'porcentaje_skills',
        'porcentaje_requisitos',
        'notas',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_aplicacion' => 'date',
            'porcentaje_match' => 'decimal:2',
            'porcentaje_skills' => 'decimal:2',
            'porcentaje_requisitos' => 'decimal:2',
        ];
    }

    public function requisicion(): BelongsTo
    {
        return $this->belongsTo(Requisicion::class, 'requisicion_id');
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    public function calcularPorcentajes(): void
    {
        $this->loadMissing(['requisicion.puesto.skills', 'requisicion.puesto.requerimientos']);
        $puesto = $this->requisicion?->puesto;
        if ($puesto === null) {
            return;
        }

        $personaId = $this->persona_id;

        $skillIds = $puesto->skills->pluck('id')->all();
        $totalSkills = count($skillIds);
        $cumpleSkills = $totalSkills === 0 ? 0 : SkillDemostrada::query()
            ->where('persona_id', $personaId)
            ->whereIn('skill_id', $skillIds)
            ->where('cumple', true)
            ->count();

        $reqIds = $puesto->requerimientos->pluck('id')->all();
        $totalReqs = count($reqIds);
        $cumpleReqs = $totalReqs === 0 ? 0 : RequerimientoDemostrado::query()
            ->where('persona_id', $personaId)
            ->whereIn('requerimiento_id', $reqIds)
            ->where('cumple', true)
            ->count();

        $pctSkills = $totalSkills === 0 ? null : round(($cumpleSkills / $totalSkills) * 100, 2);
        $pctReqs = $totalReqs === 0 ? null : round(($cumpleReqs / $totalReqs) * 100, 2);

        $pctMatch = match (true) {
            $pctSkills !== null && $pctReqs !== null => round(($pctSkills + $pctReqs) / 2, 2),
            $pctSkills !== null => $pctSkills,
            $pctReqs !== null => $pctReqs,
            default => null,
        };

        $this->update([
            'porcentaje_skills' => $pctSkills,
            'porcentaje_requisitos' => $pctReqs,
            'porcentaje_match' => $pctMatch,
        ]);
    }
}
