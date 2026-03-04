<?php

namespace App\Models\Rh;

use App\Models\Departamento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Puesto extends Model
{
    /** @use HasFactory<\Database\Factories\Rh\PuestoFactory> */
    use HasFactory;

    protected $table = 'rh_puestos';

    /** @var list<string> */
    protected $fillable = [
        'departamento_id',
        'nombre',
        'descripcion',
        'codigo',
        'ubicacion',
        'hora_entrada',
        'hora_salida',
        'puesto_jefe_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hora_entrada' => 'datetime:H:i',
            'hora_salida' => 'datetime:H:i',
        ];
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }

    public function puestoJefe(): BelongsTo
    {
        return $this->belongsTo(self::class, 'puesto_jefe_id');
    }

    public function subordinados(): HasMany
    {
        return $this->hasMany(self::class, 'puesto_jefe_id');
    }

    public function actividades(): HasMany
    {
        return $this->hasMany(Actividad::class, 'puesto_id');
    }

    public function documentosPuesto(): HasMany
    {
        return $this->hasMany(DocumentoPuesto::class, 'puesto_id');
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'rh_puesto_skills', 'puesto_id', 'skill_id')
            ->withPivot('nivel_requerido')
            ->withTimestamps();
    }

    public function requerimientos(): BelongsToMany
    {
        return $this->belongsToMany(Requerimiento::class, 'rh_puesto_requerimientos', 'puesto_id', 'requerimiento_id')
            ->withTimestamps();
    }

    public function periodosLaborales(): HasMany
    {
        return $this->hasMany(PeriodoLaboral::class, 'puesto_id');
    }

    public function requisiciones(): HasMany
    {
        return $this->hasMany(Requisicion::class, 'puesto_id');
    }
}
