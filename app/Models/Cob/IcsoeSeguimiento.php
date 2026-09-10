<?php

namespace App\Models\Cob;

use App\Enums\Cob\IcsoeEstatus;
use App\Enums\Cob\IcsoeMetodo;
use App\Models\Proyecto;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Seguimiento ICSOE de un proyecto (uno por proyecto, unique en BD).
 */
class IcsoeSeguimiento extends Model
{
    use HasFactory;

    protected $table = 'cob_icsoe_seguimientos';

    /** @var list<string> */
    protected $fillable = [
        'proyecto_id',
        'metodo',
        'estatus',
        'fecha_inicio',
        'fecha_fin',
        'superficie_m2',
        'costo_m2',
        'porcentaje_mo',
        'prima_riesgo',
        'monto_base',
        'monto_base_anterior',
        'mo_estimada_total',
        'mo_estimada_total_anterior',
        'mo_estimada_diaria',
        'total_dias',
        'mo_real_total',
        'diferencia_mo',
        'monto_riesgo',
        'motivo_cambio',
        'recalculado_at',
        'verificado_at',
        'verificado_por',
        'notas',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metodo' => IcsoeMetodo::class,
            'estatus' => IcsoeEstatus::class,
            'fecha_inicio' => 'date:Y-m-d',
            'fecha_fin' => 'date:Y-m-d',
            'superficie_m2' => 'decimal:2',
            'costo_m2' => 'decimal:2',
            'porcentaje_mo' => 'decimal:2',
            'prima_riesgo' => 'decimal:5',
            'monto_base' => 'decimal:2',
            'monto_base_anterior' => 'decimal:2',
            'mo_estimada_total' => 'decimal:2',
            'mo_estimada_total_anterior' => 'decimal:2',
            'mo_estimada_diaria' => 'decimal:4',
            'total_dias' => 'integer',
            'mo_real_total' => 'decimal:2',
            'diferencia_mo' => 'decimal:2',
            'monto_riesgo' => 'decimal:2',
            'recalculado_at' => 'datetime',
            'verificado_at' => 'datetime',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function meses(): HasMany
    {
        return $this->hasMany(IcsoeMes::class, 'seguimiento_id')
            ->orderBy('anio')
            ->orderBy('mes');
    }

    public function verificadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'verificado_por');
    }

    /** Los que esperan revisión salen primero, y entre ellos el más reciente. */
    public function scopePendientesPrimero(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN estatus = ? THEN 0 ELSE 1 END', [IcsoeEstatus::PendienteVerificacion->value])
            ->orderByDesc('recalculado_at')
            ->orderBy('id');
    }

    public function estaPendiente(): bool
    {
        return $this->estatus === IcsoeEstatus::PendienteVerificacion;
    }
}
