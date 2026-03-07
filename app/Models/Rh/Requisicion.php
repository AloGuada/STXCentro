<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Requisicion extends Model
{
    /** @use HasFactory<\Database\Factories\Rh\RequisicionFactory> */
    use HasFactory;

    protected $table = 'rh_requisiciones';

    /** @var list<string> */
    protected $fillable = [
        'folio',
        'puesto_id',
        'cantidad',
        'estado',
        'tipo_requisicion',
        'tipo_contrato_generado',
        'procesar_ia',
        'justificacion',
        'nombre_solicitante',
        'puesto_solicitante',
        'responsable_entrevista',
        'salario',
        'fecha_creacion',
        'fecha_cierre',
        'solicitada_por_periodo_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'procesar_ia' => 'boolean',
            'salario' => 'decimal:2',
            'fecha_creacion' => 'date',
            'fecha_cierre' => 'date',
        ];
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class, 'puesto_id');
    }

    public function solicitadaPorPeriodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoLaboral::class, 'solicitada_por_periodo_id');
    }

    public function extra(): HasOne
    {
        return $this->hasOne(RequisicionExtra::class, 'requisicion_id');
    }

    public function candidaturas(): HasMany
    {
        return $this->hasMany(Candidatura::class, 'requisicion_id');
    }

    public function periodosLaborales(): HasMany
    {
        return $this->hasMany(PeriodoLaboral::class, 'requisicion_id');
    }
}
