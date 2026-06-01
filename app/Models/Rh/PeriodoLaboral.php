<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PeriodoLaboral extends Model
{
    /** @use HasFactory<\Database\Factories\Rh\PeriodoLaboralFactory> */
    use HasFactory;

    protected $table = 'rh_periodos_laborales';

    /** @var list<string> */
    protected $fillable = [
        'persona_id',
        'puesto_id',
        'requisicion_id',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'salario_diario',
        'sueldo_mensual',
        'sueldo_real',
        'periodicidad_pago',
        'tipo_salario',
        'tipo_contrato',
        'numero_empleado',
        'numero_locker',
        'tipo_empleado',
        'motivo_baja',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date:Y-m-d',
            'fecha_fin' => 'date:Y-m-d',
            'salario_diario' => 'decimal:2',
            'sueldo_real' => 'decimal:2',
        ];
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class, 'puesto_id');
    }

    public function requisicion(): BelongsTo
    {
        return $this->belongsTo(Requisicion::class, 'requisicion_id');
    }

    public function onboarding(): HasOne
    {
        return $this->hasOne(Onboarding::class, 'periodo_id');
    }

    /**
     * Crea el onboarding de este periodo copiando las tareas de la plantilla
     * de su puesto (si tiene plantillas). Devuelve el onboarding creado, o
     * null si el periodo ya tenía uno.
     */
    public function generarOnboardingDesdePlantilla(): ?Onboarding
    {
        if ($this->onboarding) {
            return null;
        }

        return DB::transaction(function (): Onboarding {
            $onboarding = $this->onboarding()->create([
                'fecha_inicio' => now(),
                'progreso' => 0,
            ]);

            $puesto = $this->puesto;

            if ($puesto !== null) {
                $fechaInicio = Carbon::parse($onboarding->fecha_inicio);

                foreach ($puesto->plantillasOnboarding()->orderBy('orden')->orderBy('id')->get() as $tpl) {
                    $onboarding->tareas()->create([
                        'titulo' => $tpl->titulo,
                        'descripcion' => $tpl->descripcion,
                        'etapa' => $tpl->etapa,
                        'responsable_sugerido' => $tpl->responsable,
                        'duracion_estimada' => $tpl->duracion_estimada,
                        'fecha_vencimiento' => $tpl->dias_desde_inicio !== null
                            ? $fechaInicio->copy()->addDays($tpl->dias_desde_inicio)
                            : null,
                    ]);
                }
            }

            return $onboarding;
        });
    }

    /** @param Builder<self> $query */
    public function scopeActivosConPersona(Builder $query): Builder
    {
        return $query->where('estado', 'activo')->with('persona');
    }
}
