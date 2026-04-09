<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Persona extends Model
{
    /** @use HasFactory<\Database\Factories\Rh\PersonaFactory> */
    use HasFactory;

    protected $table = 'rh_personas';

    /** @var list<string> */
    protected $fillable = [
        'nombre',
        'apellido',
        'email',
        'telefono',
        'fecha_nacimiento',
        'cv_estado',
        'cv_procesado_at',
        'error_procesamiento',
        'reintentos',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date:Y-m-d',
            'cv_procesado_at' => 'datetime',
            'reintentos' => 'integer',
        ];
    }

    public function media(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable')->where('descripcion', 'cv');
    }

    public function foto(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable')->where('descripcion', 'foto');
    }

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombre} {$this->apellido}";
    }

    public function datosExtra(): HasOne
    {
        return $this->hasOne(DatosExtra::class, 'persona_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(PersonaDocumento::class, 'persona_id');
    }

    public function periodosLaborales(): HasMany
    {
        return $this->hasMany(PeriodoLaboral::class, 'persona_id');
    }

    public function skillsDemostradas(): HasMany
    {
        return $this->hasMany(SkillDemostrada::class, 'persona_id');
    }

    public function requerimientosDemostrados(): HasMany
    {
        return $this->hasMany(RequerimientoDemostrado::class, 'persona_id');
    }

    public function candidaturas(): HasMany
    {
        return $this->hasMany(Candidatura::class, 'persona_id');
    }

    public function contactosEmergencia(): HasMany
    {
        return $this->hasMany(ContactoEmergencia::class, 'persona_id');
    }
}
