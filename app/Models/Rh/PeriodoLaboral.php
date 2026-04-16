<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
            'sueldo_mensual' => 'decimal:2',
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
}
