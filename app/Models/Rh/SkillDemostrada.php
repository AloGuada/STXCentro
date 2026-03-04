<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillDemostrada extends Model
{
    protected $table = 'rh_skills_demostradas';

    /** @var list<string> */
    protected $fillable = [
        'persona_id',
        'skill_id',
        'onboarding',
        'cumple',
        'nivel_alcanzado',
        'evidencia',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'onboarding' => 'boolean',
            'cumple' => 'boolean',
        ];
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }
}
