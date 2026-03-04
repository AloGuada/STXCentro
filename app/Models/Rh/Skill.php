<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Skill extends Model
{
    /** @use HasFactory<\Database\Factories\Rh\SkillFactory> */
    use HasFactory;

    protected $table = 'rh_skills';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'tipo',
    ];

    public function puestos(): BelongsToMany
    {
        return $this->belongsToMany(Puesto::class, 'rh_puesto_skills', 'skill_id', 'puesto_id')
            ->withPivot('nivel_requerido')
            ->withTimestamps();
    }

    public function skillsDemostradas(): HasMany
    {
        return $this->hasMany(SkillDemostrada::class, 'skill_id');
    }
}
