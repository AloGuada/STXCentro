<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PuestoSkill extends Model
{
    protected $table = 'rh_puesto_skills';

    /** @var list<string> */
    protected $fillable = [
        'puesto_id',
        'skill_id',
        'nivel_requerido',
    ];

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class, 'puesto_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }
}
