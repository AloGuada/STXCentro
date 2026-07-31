<?php

namespace App\Models\Rh;

use App\Models\Prod\GrupoEmpleado;
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
        'imss',
        'curp',
        'rfc',
        'numero_ine',
        'estado_civil',
        'hijos',
        'domicilio',
        'cp',
        'localidad',
        'nombre_padre',
        'nombre_madre',
        'cuenta_banco',
        'banco_op',
        'c_infonavit',
        'c_fonacot',
        'tramite_banco',
        'texto_cv',
        'contacto_emergencia_1_nombre',
        'contacto_emergencia_1_telefono',
        'contacto_emergencia_2_nombre',
        'contacto_emergencia_2_telefono',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date:Y-m-d',
            'cv_procesado_at' => 'datetime',
            'reintentos' => 'integer',
            'hijos' => 'integer',
            'tramite_banco' => 'boolean',
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

    public function documentos(): HasMany
    {
        return $this->hasMany(PersonaDocumento::class, 'persona_id');
    }

    public function periodosLaborales(): HasMany
    {
        return $this->hasMany(PeriodoLaboral::class, 'persona_id');
    }

    /**
     * Contratación vigente. Una persona puede existir sin ninguna (prospecto,
     * eventual) y acumula un periodo por cada recontratación, así que se toma
     * el activo más reciente.
     */
    public function periodoVigente(): HasOne
    {
        return $this->hasOne(PeriodoLaboral::class, 'persona_id')
            ->where('estado', 'activo')
            ->latest('fecha_inicio')
            ->latest('id');
    }

    /** Grupo de destajo al que pertenece. Nadie puede estar en dos a la vez. */
    public function grupoDeProduccion(): HasOne
    {
        return $this->hasOne(GrupoEmpleado::class, 'persona_id');
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
}
